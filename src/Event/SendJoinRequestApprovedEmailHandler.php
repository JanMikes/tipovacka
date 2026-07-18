<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\CompetitionJoinRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendJoinRequestApprovedEmailHandler
{
    public function __construct(
        private CompetitionJoinRequestRepository $requestRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(JoinRequestApproved $event): void
    {
        $request = $this->requestRepository->get($event->requestId);

        if (null === $request->user->email) {
            return;
        }

        $competitionUrl = $this->urlGenerator->generate(
            'portal_competition_detail',
            ['id' => $request->competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->to(new Address($request->user->email, $request->user->displayName))
            ->subject('Byl(a) jsi přijat(a) do soutěže na Tipovačce')
            ->htmlTemplate('emails/join_request_approved.html.twig')
            ->context([
                'nickname' => $request->user->displayName,
                'competitionName' => $request->competition->name,
                'matchSourceName' => $request->competition->matchSource->name,
                'competitionUrl' => $competitionUrl,
            ]);

        $this->mailer->send($email);
    }
}
