<?php

declare(strict_types=1);

namespace App\Service\Competition;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Remembers which global competition a user was trying to join when they were
 * bounced to the credit top-up page for insufficient credits. After a top-up we
 * redirect them BACK to that competition (they click „Připojit se" again — we
 * never auto-join). Mirrors the invitation-intent session pattern.
 */
final class GlobalJoinReturnIntentSession
{
    private const string KEY = 'global_join_return_competition_id';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function store(string $competitionId): void
    {
        $this->requestStack->getSession()->set(self::KEY, $competitionId);
    }

    public function consume(): ?string
    {
        $session = $this->requestStack->getSession();
        $competitionId = $session->get(self::KEY);
        $session->remove(self::KEY);

        return is_string($competitionId) ? $competitionId : null;
    }
}
