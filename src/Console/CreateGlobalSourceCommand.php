<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\CreateCuratedMatchSource\CreateCuratedMatchSourceCommand;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Enum\UserRole;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
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
 * Ops utility: stand up a curated source together with its FREE global
 * competition in one transaction (the same composition the admin wizard's
 * „Rovnou vytvořit globální soutěž" step uses). Prints both UUIDs — the source
 * id feeds app:matches:bind-feed, the competition id feeds
 * app:tip-opening:bulk-set --competition.
 */
#[AsCommand(
    name: 'app:sources:create-global',
    description: 'Create a curated match source with a free global competition on top.',
)]
final class CreateGlobalSourceCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CompetitionRepository $competitions,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Match source name (e.g. "Liga mistrů 2026/27")')
            ->addOption('admin', null, InputOption::VALUE_REQUIRED, 'E-mail of the admin account that will own the source and competition')
            ->addOption('competition-name', null, InputOption::VALUE_REQUIRED, 'Global competition name (defaults to the source name)')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Source description')
            ->addOption('entry-fee', null, InputOption::VALUE_REQUIRED, 'Entry fee in credits', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $adminEmail = $input->getOption('admin');
        $entryFeeRaw = $input->getOption('entry-fee');

        if (!is_string($name) || '' === trim($name) || !is_string($adminEmail) || '' === trim($adminEmail)) {
            $output->writeln('<error>name and --admin are required.</error>');

            return Command::INVALID;
        }

        if (!is_string($entryFeeRaw) || !ctype_digit($entryFeeRaw)) {
            $output->writeln('<error>--entry-fee must be a non-negative integer.</error>');

            return Command::INVALID;
        }

        $admin = $this->users->findByEmail(trim($adminEmail));

        if (null === $admin) {
            $output->writeln(sprintf('<error>No user with e-mail "%s".</error>', $adminEmail));

            return Command::FAILURE;
        }

        if (!in_array(UserRole::ADMIN->value, $admin->getRoles(), true)) {
            $output->writeln(sprintf('<error>"%s" is not an admin.</error>', $adminEmail));

            return Command::FAILURE;
        }

        $competitionName = $input->getOption('competition-name');
        $description = $input->getOption('description');

        $envelope = $this->commandBus->dispatch(new CreateCuratedMatchSourceCommand(
            adminId: $admin->id,
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            name: trim($name),
            description: is_string($description) && '' !== trim($description) ? trim($description) : null,
            startAt: null,
            endAt: null,
            createGlobalCompetition: true,
            globalCompetitionName: is_string($competitionName) && '' !== trim($competitionName) ? trim($competitionName) : trim($name),
            globalCompetitionEntryFee: (int) $entryFeeRaw,
        ));

        $source = $envelope->last(HandledStamp::class)?->getResult();

        if (!$source instanceof MatchSource) {
            $output->writeln('<error>Source creation produced no result.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Source: %s  %s</info>', $source->id->toRfc4122(), $source->name));

        foreach ($this->competitions->findByMatchSource($source->id) as $competition) {
            if ($competition->isGlobal) {
                $output->writeln(sprintf('<info>Global competition: %s  %s</info>', $competition->id->toRfc4122(), $competition->name));
            }
        }

        return Command::SUCCESS;
    }
}
