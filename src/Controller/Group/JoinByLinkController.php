<?php

declare(strict_types=1);

namespace App\Controller\Group;

use App\Command\JoinGroupByLink\JoinGroupByLinkCommand;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Repository\GroupRepository;
use App\Service\Group\GroupJoinIntentSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/skupiny/pozvanka/{token}', name: 'group_join_by_link', requirements: ['token' => '[a-f0-9]{48}'])]
final class JoinByLinkController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupJoinIntentSession $intent,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $token): Response
    {
        $group = $this->groupRepository->getByShareableLinkToken($token);

        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->intent->store($token);

            $this->addFlash('info', 'Pro připojení se ke skupině se prosím přihlaste.');

            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified) {
            $this->intent->store($token);

            $this->addFlash('warning', 'Nejprve si ověř svou e-mailovou adresu.');

            return $this->redirectToRoute('app_verify_email_pending');
        }

        try {
            $this->commandBus->dispatch(new JoinGroupByLinkCommand(
                userId: $user->id,
                token: $token,
            ));

            $this->addFlash('success', 'Byl(a) jsi přidán(a) do skupiny.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof AlreadyMember) {
                $this->addFlash('info', 'Ve skupině již jsi.');
            } elseif ($inner instanceof CannotJoinFinishedTournament) {
                $this->addFlash('warning', 'Turnaj této skupiny je již ukončen.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
    }
}
