<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\AdoptFeedExternalIds\AdoptFeedExternalIdsCommand as AdoptMessage;
use App\Repository\MatchSourceRepository;
use App\Service\Feed\ExternalIdAdopter;
use App\Service\Feed\ExternalIdAdoption;
use App\Service\Feed\MatchDataProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * ONE-TIME bridge run before binding a hand-maintained source to a real feed:
 * pairs the source's stored matches with the provider's fixtures and stamps
 * each with the provider's id, so the first sync updates those rows instead of
 * creating a duplicate season beside them.
 *
 * Always run `--dry-run` first — it prints exactly what would be paired and,
 * more importantly, what would not.
 */
#[AsCommand(
    name: 'app:matches:adopt-external-ids',
    description: 'Pair a source\'s existing matches with its feed provider\'s identifiers.',
)]
final class AdoptFeedExternalIdsCommand extends Command
{
    public function __construct(
        private readonly MatchSourceRepository $matchSources,
        private readonly MatchDataProviderRegistry $providers,
        private readonly ExternalIdAdopter $adopter,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'Match source UUID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the pairing without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->matchSources->get(Uuid::fromString((string) $input->getArgument('source')));
        $feedProvider = $source->feedProvider;

        if (null === $feedProvider) {
            $output->writeln(sprintf('<error>%s is not bound to a feed — run app:matches:bind-feed first.</error>', $source->name));

            return Command::FAILURE;
        }

        $provider = $this->providers->providerFor($feedProvider);

        if (null === $provider) {
            $output->writeln(sprintf('<error>No adapter for provider "%s".</error>', $feedProvider->value));

            return Command::FAILURE;
        }

        $snapshots = $provider->fetchMatches($source);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $adoption = $this->adopter->adopt($source, $snapshots, apply: false);
        } else {
            $envelope = $this->commandBus->dispatch(new AdoptMessage($source->id, $snapshots));
            $result = $envelope->last(HandledStamp::class)?->getResult();

            if (!$result instanceof ExternalIdAdoption) {
                $output->writeln('<error>Adoption produced no result.</error>');

                return Command::FAILURE;
            }

            $adoption = $result;
        }

        $this->render($output, $source->name, count($snapshots), $adoption);

        return Command::SUCCESS;
    }

    private function render(OutputInterface $output, string $sourceName, int $snapshotCount, ExternalIdAdoption $adoption): void
    {
        $output->writeln(sprintf(
            '<info>%s</info>%s: %d feed rows — %d adopted, %d already linked',
            $sourceName,
            $adoption->dryRun ? ' (dry run)' : '',
            $snapshotCount,
            count($adoption->adopted),
            $adoption->alreadyLinked,
        ));

        foreach ($adoption->conflicting as $label) {
            $output->writeln(sprintf('  <comment>replaced a foreign externalId: %s</comment>', $label));
        }

        foreach ($adoption->ambiguous as $label) {
            $output->writeln(sprintf('  <comment>ambiguous — several stored matches fit: %s</comment>', $label));
        }

        foreach ($adoption->unresolvedTeams as $teamName) {
            $output->writeln(sprintf('  <comment>unresolved team — add an alias: "%s"</comment>', $teamName));
        }

        // The two asymmetric leftovers are the ones worth eyeballing: a feed row
        // with no stored match is usually a round we never seeded, while a
        // stored match with no feed row may be a fixture that quietly moved.
        foreach ($adoption->unmatchedSnapshots as $label) {
            $output->writeln(sprintf('  <comment>feed row with no stored match: %s</comment>', $label));
        }

        foreach ($adoption->unmatchedMatches as $label) {
            $output->writeln(sprintf('  <comment>stored match with no feed row: %s</comment>', $label));
        }
    }
}
