<?php

declare(strict_types=1);

namespace App\Command\RequestPasswordReset;

use App\Event\PasswordResetRequested;
use App\Event\PasswordResetRequestedForUnregisteredEmail;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsMessageHandler]
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
        private RateLimiterFactoryInterface $signUpInvitationLimiter,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        // Unknown or deleted account — invite to sign up instead of staying silent.
        // The web response is identical either way, so emails still can't be enumerated;
        // only the mailbox owner learns whether the account exists.
        if (null === $user || $user->isDeleted()) {
            // The reset-password bundle throttles registered users; this guards the
            // unregistered path so the form can't be used to spam arbitrary addresses.
            if (!$this->signUpInvitationLimiter->create(mb_strtolower($command->email))->consume()->isAccepted()) {
                return;
            }

            $this->eventBus->dispatch(new PasswordResetRequestedForUnregisteredEmail(
                email: $command->email,
                occurredOn: \DateTimeImmutable::createFromInterface($this->clock->now()),
            ));

            return;
        }

        // Blocked users get nothing — no enumeration
        if (!$user->isActive) {
            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            // Rate-limited or other bundle restriction — stay silent
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // User was found via findByEmail, so email is guaranteed non-null.
        assert(null !== $user->email);

        $this->eventBus->dispatch(new PasswordResetRequested(
            userId: $user->id,
            email: $user->email,
            resetToken: $resetToken->getToken(),
            occurredOn: $now,
        ));
    }
}
