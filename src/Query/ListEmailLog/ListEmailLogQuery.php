<?php

declare(strict_types=1);

namespace App\Query\ListEmailLog;

use App\Entity\EmailLog;
use App\Repository\EmailLogRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListEmailLogQuery
{
    public function __construct(
        private EmailLogRepository $emailLogRepository,
    ) {
    }

    public function __invoke(ListEmailLog $query): ListEmailLogResult
    {
        $page = max(1, $query->page);

        return new ListEmailLogResult(
            items: array_map(
                static fn (EmailLog $entry): EmailLogItem => EmailLogItem::fromEntry($entry),
                $this->emailLogRepository->search($query->search, $query->status, ListEmailLog::PER_PAGE, ($page - 1) * ListEmailLog::PER_PAGE),
            ),
            total: $this->emailLogRepository->count($query->search, $query->status),
            page: $page,
            perPage: ListEmailLog::PER_PAGE,
        );
    }
}
