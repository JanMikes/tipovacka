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
            throw new CustomUserMessageAuthenticationException('Tento účet byl smazán. Pokud ho chcete obnovit, napište nám na kontakt@wtips.cz.');
        }

        if (!$user->isActive) {
            throw new CustomUserMessageAuthenticationException('Tento účet byl zablokován. Pokud jde podle vás o omyl, napište nám na kontakt@wtips.cz.');
        }

        if (null === $user->email) {
            throw new CustomUserMessageAuthenticationException('K tomuto účtu nemáme uložený e-mail, proto se k němu přihlásit nejde. Napište nám na kontakt@wtips.cz a pomůžeme vám.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Post-auth checks not needed — pre-auth covers all rejection cases.
    }
}
