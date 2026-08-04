<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\CreateTeam\CreateTeamCommand;
use App\Entity\Sport;
use App\Repository\TeamAliasRepository;
use App\Repository\TeamRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Ops utility: seed GLOBAL directory teams from a JSON file
 * (`[{ "name": "AC Sparta Praha", "shortName": "SPA", "country": "CZ", "brandColor": "#AA1E1E" }, …]`)
 * BEFORE a feed source is synced, so every fixture resolves to a groomed team
 * (short name + country flag) instead of tripping the pending-team gate.
 *
 * Idempotent: a name that already resolves in the sport's global scope —
 * as a team name or an alias — is skipped and reported, never touched.
 */
#[AsCommand(
    name: 'app:teams:seed',
    description: 'Create global directory teams from a JSON seed file.',
)]
final class SeedTeamsCommand extends Command
{
    public function __construct(
        private readonly TeamRepository $teams,
        private readonly TeamAliasRepository $aliases,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the JSON seed file (relative paths resolve against the project dir)')
            ->addOption('sport', null, InputOption::VALUE_REQUIRED, 'Sport UUID', Sport::FOOTBALL_ID);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $sportRaw = $input->getOption('sport');

        if (!is_string($file) || !is_string($sportRaw)) {
            $output->writeln('<error>file and --sport must be strings.</error>');

            return Command::INVALID;
        }

        $path = str_starts_with($file, '/') ? $file : $this->projectDir.'/'.$file;

        if (!is_file($path)) {
            $output->writeln(sprintf('<error>Seed file "%s" not found.</error>', $path));

            return Command::FAILURE;
        }

        try {
            $rows = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln(sprintf('<error>Invalid JSON: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        if (!is_array($rows) || !array_is_list($rows)) {
            $output->writeln('<error>Expected a top-level JSON array of team objects.</error>');

            return Command::FAILURE;
        }

        $sportId = Uuid::fromString($sportRaw);
        $created = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || '' === trim($row['name'])) {
                $output->writeln(sprintf('<error>Row #%d has no usable "name" — aborting (nothing before this row is rolled back).</error>', $index));

                return Command::FAILURE;
            }

            $name = trim($row['name']);

            $existing = $this->teams->findGlobalByName($sportId, $name)
                ?? $this->aliases->findGlobalTeamByAlias($sportId, $name);

            if (null !== $existing) {
                ++$skipped;
                $output->writeln(sprintf('  <comment>exists: %s (→ %s)</comment>', $name, $existing->name));

                continue;
            }

            $shortName = is_string($row['shortName'] ?? null) ? trim($row['shortName']) : null;
            $country = is_string($row['country'] ?? null) ? strtoupper(trim($row['country'])) : null;
            $brandColor = is_string($row['brandColor'] ?? null) ? trim($row['brandColor']) : null;

            $this->commandBus->dispatch(new CreateTeamCommand(
                sportId: $sportId,
                name: $name,
                shortName: '' !== $shortName ? $shortName : null,
                country: '' !== $country ? $country : null,
                brandColor: '' !== $brandColor ? $brandColor : null,
            ));

            ++$created;
            $output->writeln(sprintf('  <info>created: %s</info>', $name));
        }

        $output->writeln(sprintf('<info>Done: %d created, %d already existed.</info>', $created, $skipped));

        return Command::SUCCESS;
    }
}
