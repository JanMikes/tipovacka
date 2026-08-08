<?php

declare(strict_types=1);

namespace App\Command\SyncMatchSourceFeed;

use App\Exception\FeedSyncUnavailable;
use App\Repository\MatchSourceRepository;
use App\Service\Feed\FeedSynchronizer;
use App\Service\Feed\FeedSyncResult;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncMatchSourceFeedHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private FeedSynchronizer $synchronizer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SyncMatchSourceFeedCommand $command): FeedSyncResult
    {
        $source = $this->matchSourceRepository->get($command->matchSourceId);

        if (!$source->isCurated) {
            throw FeedSyncUnavailable::notCurated($source->id);
        }

        if (!$source->hasFeed) {
            throw FeedSyncUnavailable::notBound($source->id);
        }

        $result = $this->synchronizer->sync($source, $command->snapshots, apply: true);

        // Stamped only on the applying path and inside this transaction, so a
        // rolled-back sync is re-attempted on the next tick rather than being
        // treated as done. Dry runs never move the cadence.
        $source->markFeedPolled(\DateTimeImmutable::createFromInterface($this->clock->now()));

        return $result;
    }
}
