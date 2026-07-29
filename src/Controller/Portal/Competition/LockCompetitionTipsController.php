<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\LockCompetitionTips\LockCompetitionTipsCommand;
use App\Entity\User;
use App\Exception\CompetitionTipsLockTimeInvalid;
use App\Repository\CompetitionRepository;
use App\Service\PragueCalendar;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * „Uzamknout tipy" — immediately (`lock_mode=now`, the default) or at a chosen
 * future moment (`lock_mode=at` + `lock_at`, B2). The chosen moment arrives as
 * Czech wall-clock time from the datepicker and is stored UTC.
 */
#[Route(
    '/souteze/{id}/uzamknout-tipy',
    name: 'competition_lock_tips',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
final class LockCompetitionTipsController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        $redirect = $this->redirectToRoute('competition_detail', ['id' => $competition->id->toRfc4122()]);

        if (!$this->isCsrfTokenValid('competition_lock_tips_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $redirect;
        }

        $lockAt = null;

        if ('at' === $request->request->get('lock_mode', 'now')) {
            $raw = trim((string) $request->request->get('lock_at', ''));

            if ('' === $raw) {
                $this->addFlash('error', 'Vyberte datum a čas uzamčení.');

                return $redirect;
            }

            $lockAt = $this->parseLockAt($raw);

            if (null === $lockAt) {
                $this->addFlash('error', 'Neplatný formát data a času uzamčení.');

                return $redirect;
            }
        }

        try {
            $this->commandBus->dispatch(new LockCompetitionTipsCommand(
                editorId: $user->id,
                competitionId: $competition->id,
                lockAt: $lockAt,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            // A moment that is no longer in the future, or one the competition
            // start has overtaken (stale page / race). Flash it like the sibling
            // unlock controller instead of rendering a 409 page.
            if ($previous instanceof CompetitionTipsLockTimeInvalid) {
                $this->addFlash('error', $previous->getMessage());

                return $redirect;
            }

            throw $e;
        }

        $this->addFlash('success', null === $lockAt
            ? 'Tipy byly uzamčeny.'
            : sprintf('Uzamčení tipů je naplánováno na %s.', $lockAt->setTimezone(PragueCalendar::timezone())->format('j. n. Y H:i')));

        return $redirect;
    }

    /**
     * The datepicker submits Czech wall-clock time („Y-m-d H:i"); everything is
     * persisted in UTC.
     */
    private function parseLockAt(string $raw): ?\DateTimeImmutable
    {
        $localTimezone = PragueCalendar::timezone();

        // The leading „!" zeroes the fields the format does not carry (seconds),
        // so the stored moment is exactly what the organizer picked.
        foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw, $localTimezone);

            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        return null;
    }
}
