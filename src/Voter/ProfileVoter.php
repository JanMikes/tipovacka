<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'PROFILE_EDIT'|'PROFILE_DELETE', User>
 */
final class ProfileVoter extends Voter
{
    public const string EDIT = 'PROFILE_EDIT';
    public const string DELETE = 'PROFILE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /* @var User $subject */
        return $currentUser->id->equals($subject->id);
    }
}
