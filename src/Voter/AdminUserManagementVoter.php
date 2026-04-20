<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'USER_BLOCK'|'USER_UNBLOCK', User>
 */
final class AdminUserManagementVoter extends Voter
{
    public const string BLOCK = 'USER_BLOCK';
    public const string UNBLOCK = 'USER_UNBLOCK';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::BLOCK, self::UNBLOCK], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
    }
}
