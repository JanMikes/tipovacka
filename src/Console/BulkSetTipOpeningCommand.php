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
 *
 * The deadline end has three mutually exclusive stances, all of them writing
 * row 1 of the resolver's decision table (which beats „tipy se zamykají startem
 * soutěže"):
 *
 * - default — untouched, a stored uzávěrka survives the pass;
 * - `--deadline-own-kickoff` — pin it to the match's own kickoff;
 * - `--deadline-before-kickoff=N` — pin it N minutes before the match's own
 *   kickoff, the „every match takes tips until N minutes before it starts" move.
 *
 * Add `--only-missing-deadline` to leave every match that ALREADY carries an
 * explicit uzávěrka exactly as it is — the one-time migration of soutěže still
 * running on the default rule, without touching what an organizer has decided:
 *
 *     bin/console app:tip-opening:bulk-set \
 *         --deadline-before-kickoff=300 --only-missing-deadline \
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
            ->addOption('opens-at', null, InputOption::VALUE_REQUIRED, 'When tipping opens, in Europe/Prague local time (e.g. "2026-07-31 20:00"). Omit to touch the deadline end only.')
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to these sport match UUIDs (repeatable). Default: every match.')
            ->addOption('editor', null, InputOption::VALUE_REQUIRED, 'UUID of the ADMIN user performing the change')
            ->addOption('note', null, InputOption::VALUE_REQUIRED, 'Optional Czech text shown while a match waits', '')
            ->addOption('except', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sport match UUID to leave tippable (repeatable)')
            ->addOption('competition', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to these competition UUIDs (repeatable). Default: every active competition — on production prefer an explicit list so user-created soutěže stay untouched.')
            ->addOption('deadline-own-kickoff', null, InputOption::VALUE_NONE, 'Also pin each match deadline to its OWN kickoff, lifting the competition-wide lock-at-start (OVERWRITES any stored per-match deadline)')
            ->addOption('deadline-before-kickoff', null, InputOption::VALUE_REQUIRED, 'Also pin each match deadline this many MINUTES before its own kickoff (e.g. 300 = 5 hours), lifting the competition-wide lock-at-start')
            ->addOption('only-missing-deadline', null, InputOption::VALUE_NONE, 'Leave matches that already carry a per-match deadline untouched — only matches still following the competition default are rewritten')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually write. Without it the command only reports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $opensAtRaw = $input->getOption('opens-at');
        $editorRaw = $input->getOption('editor');

        if (!is_string($editorRaw) || !Uuid::isValid($editorRaw)) {
            $io->error('--editor (admin user UUID) is required.');

            return Command::INVALID;
        }

        $ownKickoffDeadline = true === $input->getOption('deadline-own-kickoff');
        $onlyMissingDeadline = true === $input->getOption('only-missing-deadline');
        $beforeKickoffRaw = $input->getOption('deadline-before-kickoff');
        $beforeKickoffMinutes = null;

        if (is_string($beforeKickoffRaw) && '' !== $beforeKickoffRaw) {
            if (!ctype_digit($beforeKickoffRaw)) {
                $io->error('--deadline-before-kickoff takes a whole number of minutes (e.g. 300 for five hours).');

                return Command::INVALID;
            }

            $beforeKickoffMinutes = (int) $beforeKickoffRaw;
        }

        if ($ownKickoffDeadline && null !== $beforeKickoffMinutes) {
            $io->error('--deadline-own-kickoff and --deadline-before-kickoff say different things about the same end; pass only one.');

            return Command::INVALID;
        }

        $changesDeadline = $ownKickoffDeadline || null !== $beforeKickoffMinutes;

        if ($onlyMissingDeadline && !$changesDeadline) {
            $io->error('--only-missing-deadline only narrows a deadline pass; add --deadline-own-kickoff or --deadline-before-kickoff.');

            return Command::INVALID;
        }

        $opensAtUtc = null;

        // No --opens-at = touch the deadline end only, leaving any stored opening
        // alone (the command then makes sense only together with the deadline flag).
        if (is_string($opensAtRaw) && '' !== $opensAtRaw) {
            try {
                $opensAt = new \DateTimeImmutable($opensAtRaw, new \DateTimeZone(self::INPUT_TIMEZONE));
            } catch (\Exception) {
                $io->error(sprintf('Could not read "%s" as a date and time.', $opensAtRaw));

                return Command::INVALID;
            }

            $opensAtUtc = $opensAt->setTimezone(new \DateTimeZone('UTC'));
        } elseif (!$changesDeadline) {
            $io->error('Nothing to do: pass --opens-at, or one of the --deadline-* options, or both.');

            return Command::INVALID;
        }

        $editorId = Uuid::fromString($editorRaw);
        $note = is_string($input->getOption('note')) ? trim($input->getOption('note')) : '';
        $apply = true === $input->getOption('apply');

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

        /** @var list<string> $onlyRaw */
        $onlyRaw = $input->getOption('only');
        $only = [];

        foreach ($onlyRaw as $value) {
            if (!Uuid::isValid($value)) {
                $io->error(sprintf('--only "%s" is not a UUID.', $value));

                return Command::INVALID;
            }

            $only[Uuid::fromString($value)->toRfc4122()] = true;
        }

        /** @var list<string> $competitionRaw */
        $competitionRaw = $input->getOption('competition');
        $onlyCompetitions = [];

        foreach ($competitionRaw as $value) {
            if (!Uuid::isValid($value)) {
                $io->error(sprintf('--competition "%s" is not a UUID.', $value));

                return Command::INVALID;
            }

            $onlyCompetitions[Uuid::fromString($value)->toRfc4122()] = true;
        }

        $plan = new BulkTipWindowPlan(
            opensAtUtc: $opensAtUtc,
            note: $note,
            editorId: $editorId,
            except: $except,
            only: $only,
            apply: $apply,
            ownKickoffDeadline: $ownKickoffDeadline,
            beforeKickoffMinutes: $beforeKickoffMinutes,
            onlyMissingDeadline: $onlyMissingDeadline,
        );

        $io->title($apply ? 'Nastavuji tipovací okno' : 'Zkušební běh (nic se nezapisuje)');
        $io->definitionList(
            ['Otevřít tipování' => null === $opensAtUtc
                ? '(nemění se)'
                : sprintf('%s Prague = %s UTC', $opensAtUtc->setTimezone(new \DateTimeZone(self::INPUT_TIMEZONE))->format('j. n. Y H:i'), $opensAtUtc->format('Y-m-d H:i'))],
            ['Text během čekání' => '' === $note ? '(žádný)' : $note],
            ['Jen zápasy' => [] === $only ? '(všechny)' : implode(', ', array_keys($only))],
            ['Výjimky (zápasy)' => [] === $except ? '(žádné)' : implode(', ', array_keys($except))],
            ['Uzávěrka zápasu' => match (true) {
                $ownKickoffDeadline => 'nastaví se na VÝKOP daného zápasu (ruší uzamčení celé soutěže při startu)',
                null !== $beforeKickoffMinutes => sprintf('nastaví se %d minut před VÝKOP daného zápasu (ruší uzamčení celé soutěže při startu)', $beforeKickoffMinutes),
                default => 'beze změny (platí pravidlo soutěže)',
            }],
            ['Rozsah uzávěrek' => $onlyMissingDeadline
                ? 'jen zápasy BEZ vlastní uzávěrky (dosud podle pravidla soutěže)'
                : 'všechny zápasy (přepíše i uloženou uzávěrku)'],
        );

        $planned = 0;
        $exempted = 0;
        $skipped = [];
        $rows = [];

        foreach ($this->competitionRepository->findAllActive() as $competition) {
            if ([] !== $onlyCompetitions && !isset($onlyCompetitions[$competition->id->toRfc4122()])) {
                continue;
            }

            [$competitionPlanned, $competitionExempt, $competitionSkipped] = $this->walkCompetition(
                $competition,
                $plan,
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
     * @param list<string> $skipped
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function walkCompetition(
        Competition $competition,
        BulkTipWindowPlan $plan,
        array &$skipped,
    ): array {
        $planned = 0;
        $exempt = 0;
        $skippedHere = 0;

        foreach ($this->matchProvider->matchesFor($competition) as $sportMatch) {
            $key = $sportMatch->id->toRfc4122();

            if ([] !== $plan->only && !isset($plan->only[$key])) {
                continue;
            }

            if (isset($plan->except[$key])) {
                ++$exempt;

                continue;
            }

            $existing = $this->settingRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);

            // The deadline end travels back unchanged unless the caller asked for
            // one of the --deadline-* stances — a bulk opening must never erase an
            // organizer's per-match uzávěrka. See BulkTipWindowPlan::deadlineFor.
            $deadline = $plan->deadlineFor($sportMatch, $existing?->deadline);

            // --only-missing-deadline reaching a match that already has one: there
            // is nothing to write here, so report it as an exception rather than
            // dispatching a no-op rewrite.
            if ($plan->changesDeadline
                && null === $plan->opensAtUtc
                && $plan->leavesDeadlineUnchanged($sportMatch, $existing?->deadline)
            ) {
                ++$exempt;

                continue;
            }

            // A deadline landing at or before the opening leaves an EMPTY window,
            // which the handler rightly rejects. Judge against the opening this
            // write results in: the new one, or the stored one it leaves alone.
            $effectiveOpening = $plan->opensAtUtc ?? $existing?->opensAt;

            if (null !== $effectiveOpening
                && $this->deadlineResolver->deadlineWithOverride($competition, $sportMatch, $deadline) <= $effectiveOpening
            ) {
                ++$skippedHere;
                $skipped[] = sprintf('%s — %s', $competition->name, self::label($sportMatch));

                continue;
            }

            if ($plan->apply) {
                $this->commandBus->dispatch(new SetCompetitionMatchDeadlineCommand(
                    editorId: $plan->editorId,
                    competitionId: $competition->id,
                    sportMatchId: $sportMatch->id,
                    deadline: $deadline,
                    // Without --opens-at the opening end is not part of this write
                    // at all, so a stored opening survives a deadline-only pass.
                    changeOpening: null !== $plan->opensAtUtc,
                    opensAt: $plan->opensAtUtc,
                    openingNote: '' === $plan->note ? null : $plan->note,
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
