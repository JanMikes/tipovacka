<?php

declare(strict_types=1);

namespace App\Command\InitiateCreditPurchase;

use App\Entity\CreditPurchase;
use App\Exception\CreditPurchaseRequiresEmail;
use App\Exception\InvalidCreditAmount;
use App\Repository\CreditPurchaseRepository;
use App\Repository\UserRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use App\Service\Payment\PaymentGateway;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class InitiateCreditPurchaseHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CreditPurchaseRepository $purchaseRepository,
        private CreditWalletProvider $walletProvider,
        private PaymentGateway $paymentGateway,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(InitiateCreditPurchaseCommand $command): InitiatedCreditCheckout
    {
        if ($command->credits < InitiateCreditPurchaseCommand::MINIMUM_CREDITS) {
            throw InvalidCreditAmount::belowMinimumPurchase($command->credits, InitiateCreditPurchaseCommand::MINIMUM_CREDITS);
        }

        if ($command->credits > InitiateCreditPurchaseCommand::MAXIMUM_CREDITS) {
            throw InvalidCreditAmount::aboveMaximumPurchase($command->credits, InitiateCreditPurchaseCommand::MAXIMUM_CREDITS);
        }

        $user = $this->userRepository->get($command->userId);

        if (null === $user->email) {
            throw CreditPurchaseRequiresEmail::forAnonymousAccount();
        }

        $now = $this->clock->now();
        $wallet = $this->walletProvider->getOrCreate($user, $now);

        if (null === $wallet->stripeCustomerId) {
            $customerId = $this->paymentGateway->createCustomer($user->email, $user->displayName, $user->id);
            $wallet->assignStripeCustomerId($customerId, $now);
        }

        $purchaseId = $this->identity->next();

        $session = $this->paymentGateway->createCreditCheckoutSession(
            customerId: $wallet->stripeCustomerId ?? throw new \LogicException('Stripe customer musí existovat.'),
            credits: $command->credits,
            purchaseId: $purchaseId,
            userId: $user->id,
            successUrl: $command->successUrl,
            cancelUrl: $command->cancelUrl,
        );

        $purchase = new CreditPurchase(
            id: $purchaseId,
            user: $user,
            credits: $command->credits,
            amountTotal: $command->credits * 100,
            currency: 'czk',
            stripeCheckoutSessionId: $session->id,
            createdAt: $now,
        );

        $this->purchaseRepository->save($purchase);

        return new InitiatedCreditCheckout($purchase, $session->url);
    }
}
