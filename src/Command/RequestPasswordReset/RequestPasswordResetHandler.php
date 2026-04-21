<?php

declare(strict_types=1);

namespace App\Command\RequestPasswordReset;

use App\Event\PasswordResetRequested;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
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
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        // Silent return for non-existent, deleted, or blocked users — no enumeration
        if (null === $user || $user->isDeleted() || !$user->isActive) {
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
