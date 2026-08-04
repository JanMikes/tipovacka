<?php

declare(strict_types=1);

namespace App\Console;

use App\Enum\FeedProvider;
use App\Exception\FeedSyncUnavailable;
use App\Repository\MatchSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Ops utility: bind (or, with provider "none", unbind) a curated source to an
 * external feed. Until the admin UI grows these two fields, this is the one
 * sanctioned way to switch a feed on — SQL by hand skips the curated guard.
 *
 * Writes directly through the EntityManager (no bus command): a config flip
 * with no side effects beyond the row itself.
 */
#[AsCommand(
    name: 'app:matches:bind-feed',
    description: 'Bind a curated match source to an external feed provider.',
)]
final class BindMatchSourceFeedCommand extends Command
{
    public function __construct(
        private readonly MatchSourceRepository $matchSources,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'Match source UUID')
            ->addArgument('provider', InputArgument::REQUIRED, sprintf(
                'Feed provider (%s) or "none" to unbind',
                implode('|', array_map(static fn (FeedProvider $p): string => $p->value, FeedProvider::cases())),
            ))
            ->addArgument('ref', InputArgument::OPTIONAL, 'Provider competition ref (soutěž code, league id, JSON path)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceId = $input->getArgument('source');
        $providerValue = $input->getArgument('provider');
        $ref = $input->getArgument('ref');

        if (!is_string($sourceId) || !is_string($providerValue)) {
            $output->writeln('<error>source and provider are required.</error>');

            return Command::INVALID;
        }

        $source = $this->matchSources->get(Uuid::fromString($sourceId));
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if ('none' === $providerValue) {
            $source->unbindFeed($now);
            $this->entityManager->flush();
            $output->writeln(sprintf('<info>%s: feed unbound.</info>', $source->name));

            return Command::SUCCESS;
        }

        $provider = FeedProvider::tryFrom($providerValue);
        if (null === $provider) {
            $output->writeln(sprintf('<error>Unknown provider "%s".</error>', $providerValue));

            return Command::INVALID;
        }

        if (!is_string($ref) || '' === trim($ref)) {
            $output->writeln('<error>ref is required when binding a provider.</error>');

            return Command::INVALID;
        }

        if (!$source->isCurated) {
            throw FeedSyncUnavailable::notCurated($source->id);
        }

        $source->bindFeed($provider, trim($ref), $now);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            '<info>%s: bound to %s (%s).</info>',
            $source->name,
            $provider->label(),
            trim($ref),
        ));

        return Command::SUCCESS;
    }
}
