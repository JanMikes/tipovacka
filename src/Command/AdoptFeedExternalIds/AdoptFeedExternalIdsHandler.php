<?php

declare(strict_types=1);

namespace App\Command\AdoptFeedExternalIds;

use App\Exception\FeedSyncUnavailable;
use App\Repository\MatchSourceRepository;
use App\Service\Feed\ExternalIdAdopter;
use App\Service\Feed\ExternalIdAdoption;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AdoptFeedExternalIdsHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ExternalIdAdopter $adopter,
    ) {
    }

    public function __invoke(AdoptFeedExternalIdsCommand $command): ExternalIdAdoption
    {
        $source = $this->matchSourceRepository->get($command->matchSourceId);

        if (!$source->isCurated) {
            throw FeedSyncUnavailable::notCurated($source->id);
        }

        return $this->adopter->adopt($source, $command->snapshots, apply: true, kickoffToleranceHours: $command->kickoffToleranceHours);
    }
}
