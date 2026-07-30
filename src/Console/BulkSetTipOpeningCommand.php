<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Ops tool: set „tipování otevřeno od" on EVERY match of EVERY competition at
 * once, minus an explicit exception list — the „nothing is tippable until the
 * first round has been played" move an organizer makes once per season.
 *
 * It writes through the ordinary {@see SetCompetitionMatchDeadlineCommand}, so
 * every rule applies (admin-only editor, opening strictly before the effective
 * deadline, note requires a time). Nothing here talks to the database directly.
 *
 * Two safeguards worth knowing:
 *
 * - **Dry run by default.** Without `--apply` it only reports what it would do.
 * - **The deadline end is preserved.** A per-match deadline override already
 *   stored is read and passed back unchanged, so a bulk opening can never wipe
 *   an organizer's uzávěrka.
 *
 * Matches whose window would be empty (deadline at or before the opening —
 * finished matches, matches kicking off earlier, a locked competition) are
 * SKIPPED and reported rather than pushed at the handler, which would rightly
 * reject them one by one.
 *
 * Idempotent: re-running with the same arguments rewrites the same rows.
 *
 *     bin/console app:tip-opening:bulk-set \
 *         --opens-at="2026-07-31 20:00" \
 *         --note="Další zápasy půjdou tipovat po odehrání prvního kola" \
 *         --except=019fa008-7232-7284-aa49-b7e50684c0bc \
 *         --except=019fa008-7233-7603-b414-e0fb581541ef \
 *         --editor=<admin-user-uuid> [--apply]
 */
#[AsCommand(
    name: 'app:tip-opening:bulk-set',
    description: 'Set „tipování otevřeno od" on every competition match, minus an exception list.',
)]
final class BulkSetTipOpeningCommand extends Command
{
    private const string INPUT_TIMEZONE = 'Europe/Prague';

    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly CompetitionMatchSettingRepository $settingRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('opens-at', null, InputOption::VALUE_REQUIRED, 'When tipping opens, in Europe/Prague local time (e.g. "2026-07-31 20:00")')
            ->addOption('editor', null, InputOption::VALUE_REQUIRED, 'UUID of the ADMIN user performing the change')
            ->addOption('note', null, InputOption::VALUE_REQUIRED, 'Optional Czech text shown while a match waits', '')
            ->addOption('except', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sport match UUID to leave tippable (repeatable)')
            ->addOption('deadline-own-kickoff', null, InputOption::VALUE_NONE, 'Also pin each match deadline to its OWN kickoff, lifting the competition-wide lock-at-start (OVERWRITES any stored per-match deadline)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually write. Without it the command only reports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $opensAtRaw = $input->getOption('opens-at');
        $editorRaw = $input->getOption('editor');

        if (!is_string($opensAtRaw) || '' === $opensAtRaw || !is_string($editorRaw) || !Uuid::isValid($editorRaw)) {
            $io->error('--opens-at (Prague local time) and --editor (admin user UUID) are both required.');

            return Command::INVALID;
        }

        try {
            $opensAt = new \DateTimeImmutable($opensAtRaw, new \DateTimeZone(self::INPUT_TIMEZONE));
        } catch (\Exception) {
            $io->error(sprintf('Could not read "%s" as a date and time.', $opensAtRaw));

            return Command::INVALID;
        }

        $opensAtUtc = $opensAt->setTimezone(new \DateTimeZone('UTC'));
        $editorId = Uuid::fromString($editorRaw);
        $note = is_string($input->getOption('note')) ? trim($input->getOption('note')) : '';
        $apply = true === $input->getOption('apply');
        $ownKickoffDeadline = true === $input->getOption('deadline-own-kickoff');

        /** @var list<string> $exceptRaw */
        $exceptRaw = $input->getOption('except');
        $except = [];

        foreach ($exceptRaw as $value) {
            if (!Uuid::isValid($value)) {
                $io->error(sprintf('--except "%s" is not a UUID.', $value));

                return Command::INVALID;
            }

            $except[Uuid::fromString($value)->toRfc4122()] = true;
        }

        $io->title($apply ? 'Nastavuji otevření tipování' : 'Zkušební běh (nic se nezapisuje)');
        $io->definitionList(
            ['Otevřít tipování' => sprintf('%s Prague = %s UTC', $opensAt->format('j. n. Y H:i'), $opensAtUtc->format('Y-m-d H:i'))],
            ['Text během čekání' => '' === $note ? '(žádný)' : $note],
            ['Výjimky (zápasy)' => [] === $except ? '(žádné)' : implode(', ', array_keys($except))],
            ['Uzávěrka zápasu' => $ownKickoffDeadline
                ? 'nastaví se na VÝKOP daného zápasu (ruší uzamčení celé soutěže při startu)'
                : 'beze změny (platí pravidlo soutěže)'],
        );

        $planned = 0;
        $exempted = 0;
        $skipped = [];
        $rows = [];

        foreach ($this->competitionRepository->findAllActive() as $competition) {
            [$competitionPlanned, $competitionExempt, $competitionSkipped] = $this->walkCompetition(
                $competition,
                $opensAtUtc,
                $note,
                $editorId,
                $except,
                $apply,
                $ownKickoffDeadline,
                $skipped,
            );

            $planned += $competitionPlanned;
            $exempted += $competitionExempt;

            $rows[] = [
                $competition->name,
                $competitionPlanned,
                $competitionExempt,
                $competitionSkipped,
            ];
        }

        $io->table(['Soutěž', $apply ? 'nastaveno' : 'nastavilo by se', 'výjimka', 'přeskočeno'], $rows);

        if ([] !== $skipped) {
            $io->section('Přeskočené zápasy (uzávěrka je v ten čas už pryč — otevření by je uzavřelo navždy)');
            $io->listing(array_slice($skipped, 0, 30));

            if (count($skipped) > 30) {
                $io->writeln(sprintf('… a dalších %d.', count($skipped) - 30));
            }
        }

        $io->success(sprintf(
            '%s: %d zápasů v soutěžích, výjimky %d, přeskočeno %d.',
            $apply ? 'Zapsáno' : 'Zapsalo by se',
            $planned,
            $exempted,
            count($skipped),
        ));

        if (!$apply) {
            $io->note('Zkušební běh — pro skutečný zápis přidejte --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, true> $except
     * @param list<string>        $skipped
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function walkCompetition(
        Competition $competition,
        \DateTimeImmutable $opensAtUtc,
        string $note,
        Uuid $editorId,
        array $except,
        bool $apply,
        bool $ownKickoffDeadline,
        array &$skipped,
    ): array {
        $planned = 0;
        $exempt = 0;
        $skippedHere = 0;

        foreach ($this->matchProvider->matchesFor($competition) as $sportMatch) {
            $key = $sportMatch->id->toRfc4122();

            if (isset($except[$key])) {
                ++$exempt;

                continue;
            }

            $existing = $this->settingRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);
            // The deadline end travels back unchanged — a bulk opening must never
            // erase an organizer's per-match uzávěrka…
            $deadline = $existing?->deadline;

            // …unless the caller explicitly asked to pin every deadline to the
            // match's own kickoff. That is row 1 of the resolver's decision table,
            // which beats the competition-wide „tipy se zamykají startem soutěže":
            // the way to run a season round by round instead of freezing every tip
            // at the first kickoff.
            if ($ownKickoffDeadline) {
                $deadline = $sportMatch->kickoffAt;
            }

            if ($this->deadlineResolver->deadlineWithOverride($competition, $sportMatch, $deadline) <= $opensAtUtc) {
                ++$skippedHere;
                $skipped[] = sprintf('%s — %s', $competition->name, self::label($sportMatch));

                continue;
            }

            if ($apply) {
                $this->commandBus->dispatch(new SetCompetitionMatchDeadlineCommand(
                    editorId: $editorId,
                    competitionId: $competition->id,
                    sportMatchId: $sportMatch->id,
                    deadline: $deadline,
                    changeOpening: true,
                    opensAt: $opensAtUtc,
                    openingNote: '' === $note ? null : $note,
                ));
            }

            ++$planned;
        }

        return [$planned, $exempt, $skippedHere];
    }

    private static function label(SportMatch $sportMatch): string
    {
        return sprintf(
            '%s vs %s (%s)',
            $sportMatch->homeTeam->name,
            $sportMatch->awayTeam->name,
            $sportMatch->kickoffAt->format('j. n. Y H:i'),
        );
    }
}
