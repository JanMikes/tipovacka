<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class CompetitionMatchDeadlineAfterKickoff extends \DomainException
{
    public static function create(?\DateTimeImmutable $kickoffAt = null): self
    {
        $kickoff = null === $kickoffAt
            ? ''
            : ' ('.$kickoffAt->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i').')';

        return new self(sprintf(
            'Uzávěrka tipů může být nejpozději ve chvíli výkopu%s. Zvolte dřívější čas, nebo pole nechte prázdné — pak platí výchozí uzávěrka soutěže.',
            $kickoff,
        ));
    }
}
