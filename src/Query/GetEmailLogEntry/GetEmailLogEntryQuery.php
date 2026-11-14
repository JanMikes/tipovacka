<?php

declare(strict_types=1);

namespace App\Query\GetEmailLogEntry;

use App\Query\ListEmailLog\EmailLogItem;
use App\Repository\EmailLogRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetEmailLogEntryQuery
{
    public function __construct(
        private EmailLogRepository $emailLogRepository,
    ) {
    }

    public function __invoke(GetEmailLogEntry $query): ?EmailLogItem
    {
        $entry = $this->emailLogRepository->find($query->id);

        return null !== $entry ? EmailLogItem::fromEntry($entry) : null;
    }
}
