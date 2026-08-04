<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\AddTeamAlias\AddTeamAliasCommand as AddTeamAliasMessage;
use App\Entity\Sport;
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
 * Ops utility to seed feed-name → directory-team mappings before a feed source
 * is switched on (e.g. `app:team-alias:add "FC Viktoria Plzeň" "Viktoria Plzen"`).
 * One alias per invocation; conflicts (alias shadows a team name, alias already
 * mapped) are rejected by the handler.
 */
#[AsCommand(
    name: 'app:team-alias:add',
    description: 'Map an alternative team spelling onto a global directory team.',
)]
final class AddTeamAliasCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('team', InputArgument::REQUIRED, 'Existing directory team name')
            ->addArgument('alias', InputArgument::REQUIRED, 'The alternative spelling to map')
            ->addOption('sport', null, InputOption::VALUE_REQUIRED, 'Sport UUID', Sport::FOOTBALL_ID);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $team = $input->getArgument('team');
        $alias = $input->getArgument('alias');
        $sport = $input->getOption('sport');

        if (!is_string($team) || !is_string($alias) || !is_string($sport)) {
            $output->writeln('<error>team, alias and --sport must be strings.</error>');

            return Command::INVALID;
        }

        $this->commandBus->dispatch(new AddTeamAliasMessage(
            sportId: Uuid::fromString($sport),
            teamName: $team,
            alias: $alias,
        ));

        $output->writeln(sprintf('<info>Alias "%s" → "%s" saved.</info>', $alias, $team));

        return Command::SUCCESS;
    }
}
