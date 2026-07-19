<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\InsufficientCredits;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Service\Competition\GlobalJoinReturnIntentSession;
use App\Service\Credits\CreditsWord;
use App\Voter\CompetitionVoter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/pripojit-se',
    name: 'portal_competition_join_global',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class JoinGlobalCompetitionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly QueryBus $queryBus,
        private readonly GlobalJoinReturnIntentSession $returnIntent,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::JOIN_GLOBAL, $competition);

        if (!$this->isCsrfTokenValid('competition_join_global_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        try {
            $this->commandBus->dispatch(new JoinGlobalCompetitionCommand(
                userId: $user->id,
                competitionId: $competition->id,
            ));
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof InsufficientCredits) {
                return $this->redirectToTopUp($competition->id, $competition->entryFeeCredits, $user->id);
            }

            if ($inner instanceof AlreadyMember || $inner instanceof UniqueConstraintViolationException) {
                return $this->alreadyMember($competition->id);
            }

            if ($inner instanceof CannotJoinFinishedMatchSource) {
                $this->addFlash('error', 'Tato soutěž je již ukončena, nelze se do ní přidat.');

                return $this->redirectToRoute('public_competitions_list');
            }

            throw $handlerFailed;
        } catch (UniqueConstraintViolationException) {
            // A racing duplicate membership trips the partial unique index at flush
            // time (after the handler returned, so it arrives UNWRAPPED, not inside
            // HandlerFailedException). Each transaction rolls back its own fee, so
            // this is money-safe — surface a friendly message instead of a 500.
            return $this->alreadyMember($competition->id);
        }

        $this->addFlash('success', 'Vítejte v soutěži! Nezapomeňte si zadat tipy.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }

    private function alreadyMember(Uuid $competitionId): RedirectResponse
    {
        $this->addFlash('info', 'Už jste členem této soutěže.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competitionId->toRfc4122()]);
    }

    private function redirectToTopUp(Uuid $competitionId, int $entryFeeCredits, Uuid $userId): RedirectResponse
    {
        $balance = $this->queryBus->handle(new GetCreditWallet($userId))->balance;
        $missing = max(0, $entryFeeCredits - $balance);

        // Remember the competition so the credits return page can send the user
        // straight back to it after the top-up (they click „Připojit se" again —
        // we never auto-join).
        $this->returnIntent->store($competitionId->toRfc4122());

        $this->addFlash('warning', sprintf('Na vstupné potřebujete ještě %s.', CreditsWord::format($missing)));

        return $this->redirectToRoute('portal_credits');
    }
}
