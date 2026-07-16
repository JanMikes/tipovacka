<?php

declare(strict_types=1);

namespace App\Event;

use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListCreditPurchases\ListCreditPurchases;
use App\Query\QueryBus;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendCreditPurchaseReceiptEmailHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private QueryBus $queryBus,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(CreditsPurchased $event): void
    {
        $user = $this->userRepository->get($event->userId);

        if (null === $user->email) {
            return;
        }

        $wallet = $this->queryBus->handle(new GetCreditWallet($event->userId));
        $purchases = $this->queryBus->handle(new ListCreditPurchases(userId: $event->userId));
        $invoiceUrl = null;

        foreach ($purchases as $purchase) {
            if ($purchase->id->equals($event->purchaseId)) {
                $invoiceUrl = $purchase->invoiceUrl;

                break;
            }
        }

        $creditsUrl = $this->urlGenerator->generate(
            'portal_credits',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->to(new Address($user->email, $user->displayName))
            ->subject(sprintf('Kredity připsány: +%d', $event->credits))
            ->htmlTemplate('emails/credit_purchase_receipt.html.twig')
            ->context([
                'nickname' => $user->displayName,
                'credits' => $event->credits,
                'balance' => $wallet->balance,
                'creditsUrl' => $creditsUrl,
                'invoiceUrl' => $invoiceUrl,
            ]);

        $this->mailer->send($email);
    }
}
