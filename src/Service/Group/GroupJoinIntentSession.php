<?php

declare(strict_types=1);

namespace App\Service\Group;

use Symfony\Component\HttpFoundation\RequestStack;

final class GroupJoinIntentSession
{
    private const string KEY = 'group_join_intent_token';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function store(string $token): void
    {
        $this->requestStack->getSession()->set(self::KEY, $token);
    }

    public function consume(): ?string
    {
        $session = $this->requestStack->getSession();
        $token = $session->get(self::KEY);
        $session->remove(self::KEY);

        return is_string($token) ? $token : null;
    }
}
