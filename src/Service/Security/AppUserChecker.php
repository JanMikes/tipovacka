<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isDeleted()) {
            throw new CustomUserMessageAuthenticationException('Tento účet byl smazán. Pro obnovení kontaktujte podporu.');
        }

        if (!$user->isActive) {
            throw new CustomUserMessageAuthenticationException('Váš účet byl zablokován. Pro více informací kontaktujte podporu.');
        }

        if (null === $user->email) {
            throw new CustomUserMessageAuthenticationException('Tento účet nemá přiřazený e-mail, přihlášení není možné.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Post-auth checks not needed — pre-auth covers all rejection cases.
    }
}
