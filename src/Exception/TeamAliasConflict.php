<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class TeamAliasConflict extends \DomainException
{
    public static function aliasAlreadyResolves(string $alias, string $teamName): self
    {
        return new self(sprintf('Alias "%s" already resolves to team "%s" in this scope.', $alias, $teamName));
    }

    public static function aliasShadowsTeamName(string $alias, string $teamName): self
    {
        return new self(sprintf('Alias "%s" is already the name of team "%s" in this scope.', $alias, $teamName));
    }
}
