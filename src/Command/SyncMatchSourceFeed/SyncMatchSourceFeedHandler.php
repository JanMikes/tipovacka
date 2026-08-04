<?php

declare(strict_types=1);

namespace App\Command\SyncMatchSourceFeed;

use App\Exception\FeedSyncUnavailable;
use App\Repository\MatchSourceRepository;
use App\Service\Feed\FeedSynchronizer;
use App\Service\Feed\FeedSyncResult;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncMatchSourceFeedHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private FeedSynchronizer $synchronizer,
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

        return $this->synchronizer->sync($source, $command->snapshots, apply: true);
    }
}
