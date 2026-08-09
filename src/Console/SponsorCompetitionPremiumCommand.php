<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SponsorCompetitionPremium\SponsorCompetitionPremiumCommand as SponsorPremiumMessage;
use App\Repository\UserRepository;
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
 * „Premium na nás" for one soutěž, from the box — the same command the admin
 * button dispatches, for when the decision is taken over a support channel
 * rather than in the UI.
 *
 * The granting admin is named explicitly (`--by`) rather than guessed: this
 * writes off real money, so the audit trail should say who authorised it.
 */
#[AsCommand(
    name: 'app:competition:sponsor-premium',
    description: 'Grant (or withdraw) admin-sponsored premium for a competition — billed to nobody.',
)]
final class SponsorCompetitionPremiumCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('competition', InputArgument::REQUIRED, 'Competition UUID')
            ->addOption('by', null, InputOption::VALUE_REQUIRED, 'E-mail of the admin granting it')
            ->addOption('withdraw', null, InputOption::VALUE_NONE, 'Stop sponsoring: premium stays, billing returns to the organizer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $competition = $input->getArgument('competition');
        $by = $input->getOption('by');

        if (!is_string($competition) || !Uuid::isValid($competition)) {
            $output->writeln('<error>competition must be a UUID.</error>');

            return Command::INVALID;
        }

        if (!is_string($by) || '' === trim($by)) {
            $output->writeln('<error>--by=<admin e-mail> is required.</error>');

            return Command::INVALID;
        }

        $admin = $this->users->findByEmail(trim($by));

        if (null === $admin) {
            $output->writeln(sprintf('<error>No user with e-mail "%s".</error>', trim($by)));

            return Command::FAILURE;
        }

        $sponsored = true !== $input->getOption('withdraw');

        $this->commandBus->dispatch(new SponsorPremiumMessage(
            competitionId: Uuid::fromString($competition),
            grantedById: $admin->id,
            sponsored: $sponsored,
        ));

        $output->writeln($sponsored
            ? '<info>Premium is now on us — nobody will be charged for this soutěž.</info>'
            : '<info>Sponsorship withdrawn — premium stays, billing returns to the organizer.</info>');

        return Command::SUCCESS;
    }
}
