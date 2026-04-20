<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\GroupJoinRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendJoinRequestApprovedEmailHandler
{
    public function __construct(
        private GroupJoinRequestRepository $requestRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(JoinRequestApproved $event): void
    {
        $request = $this->requestRepository->get($event->requestId);

        $groupUrl = $this->urlGenerator->generate(
            'portal_group_detail',
            ['id' => $request->group->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@tipovacka.cz', 'Tipovačka'))
            ->to(new Address($request->user->email, $request->user->nickname))
            ->subject('Byl(a) jsi přijat(a) do skupiny na Tipovačce')
            ->htmlTemplate('emails/join_request_approved.html.twig')
            ->context([
                'nickname' => $request->user->nickname,
                'groupName' => $request->group->name,
                'tournamentName' => $request->group->tournament->name,
                'groupUrl' => $groupUrl,
            ]);

        $this->mailer->send($email);
    }
}
