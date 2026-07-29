<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Query\ListMyCompetitions\CompetitionListItem;
use App\Twig\Components\SoutezSwitcher;
use App\Value\CompetitionSwitcherOption;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SoutezSwitcherTest extends TestCase
{
    private const string LIVE_ID = '01930000-0000-7000-8000-000000000001';
    private const string OTHER_LIVE_ID = '01930000-0000-7000-8000-000000000002';
    private const string FINISHED_ID = '01930000-0000-7000-8000-000000000003';

    public function testGroupsLiveFirstAndFinishedSecond(): void
    {
        $switcher = $this->switcher([
            $this->competition(self::FINISHED_ID, 'Ukončená', isCompleted: true),
            $this->competition(self::LIVE_ID, 'Živá'),
        ]);

        $groups = $switcher->groups;

        self::assertCount(2, $groups);
        self::assertSame(SoutezSwitcher::GROUP_LIVE, $groups[0]['label']);
        self::assertSame('Živá', $groups[0]['options'][0]->name);
        self::assertSame(SoutezSwitcher::GROUP_FINISHED, $groups[1]['label']);
        self::assertSame('Ukončená', $groups[1]['options'][0]->name);
    }

    public function testEmptyGroupIsNotRendered(): void
    {
        $switcher = $this->switcher([
            $this->competition(self::LIVE_ID, 'Živá'),
            $this->competition(self::OTHER_LIVE_ID, 'Taky živá'),
        ]);

        $groups = $switcher->groups;

        self::assertCount(1, $groups);
        self::assertSame(SoutezSwitcher::GROUP_LIVE, $groups[0]['label']);
    }

    public function testOptionCarriesSourceNameAndPragueDateRange(): void
    {
        // 23:30 UTC on 1. 6. is already 2. 6. in Prague — the range must never be
        // formatted straight from UTC.
        $switcher = $this->switcher([
            $this->competition(
                self::LIVE_ID,
                'Firemní MS 2026',
                startAt: new \DateTimeImmutable('2026-06-01 23:30:00', new \DateTimeZone('UTC')),
                endAt: new \DateTimeImmutable('2026-06-11 18:00:00', new \DateTimeZone('UTC')),
            ),
        ]);

        $option = $switcher->options[0];

        self::assertSame('MS ve fotbale 2026', $option->subtitle);
        self::assertSame('2. 6. 2026 – 11. 6. 2026', $option->dateRange);
    }

    public function testOpenEndedAndMissingDatesDegradeGracefully(): void
    {
        $day = new \DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('UTC'));

        self::assertSame('od 1. 3. 2026', $this->range($day, null));
        self::assertSame('do 1. 3. 2026', $this->range(null, $day));
        self::assertSame('1. 3. 2026', $this->range($day, $day));
        self::assertSame('', $this->range(null, null));
    }

    public function testUnknownCurrentIdFallsBackToTheFirstOption(): void
    {
        $switcher = $this->switcher([
            $this->competition(self::LIVE_ID, 'První'),
            $this->competition(self::OTHER_LIVE_ID, 'Druhá'),
        ]);
        $switcher->currentId = 'de305d54-75b4-431b-adb2-eb6b9e546014';

        self::assertNotNull($switcher->current);
        self::assertSame('První', $switcher->current->name);
    }

    public function testKnownCurrentIdIsSelected(): void
    {
        $switcher = $this->switcher([
            $this->competition(self::LIVE_ID, 'První'),
            $this->competition(self::OTHER_LIVE_ID, 'Druhá'),
        ]);
        $switcher->currentId = self::OTHER_LIVE_ID;

        self::assertNotNull($switcher->current);
        self::assertSame('Druhá', $switcher->current->name);
    }

    public function testNoCompetitionsMeansNothingToRender(): void
    {
        self::assertNull($this->switcher([])->current);
    }

    public function testPreNormalisedOptionsArePassedThrough(): void
    {
        // The logged-out variant maps its own read model to CompetitionSwitcherOption.
        $switcher = new SoutezSwitcher();
        $switcher->competitions = [
            new CompetitionSwitcherOption(
                id: self::LIVE_ID,
                name: 'Veřejná soutěž',
                subtitle: 'Fotbal',
                dateRange: '2. 6. 2026 – 11. 6. 2026',
                isFinished: false,
            ),
        ];

        self::assertSame('Veřejná soutěž', $switcher->options[0]->name);
        self::assertSame(SoutezSwitcher::GROUP_LIVE, $switcher->groups[0]['label']);
    }

    private function range(?\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): string
    {
        return CompetitionSwitcherOption::fromDates(
            id: self::LIVE_ID,
            name: 'X',
            subtitle: 'Y',
            startAt: $startAt,
            endAt: $endAt,
            isFinished: false,
        )->dateRange;
    }

    /**
     * @param list<CompetitionListItem>|list<CompetitionSwitcherOption> $competitions
     */
    private function switcher(array $competitions): SoutezSwitcher
    {
        $switcher = new SoutezSwitcher();
        $switcher->route = 'dashboard';
        $switcher->competitions = $competitions;

        return $switcher;
    }

    private function competition(
        string $id,
        string $name,
        bool $isCompleted = false,
        ?\DateTimeImmutable $startAt = null,
        ?\DateTimeImmutable $endAt = null,
    ): CompetitionListItem {
        return new CompetitionListItem(
            competitionId: Uuid::fromString($id),
            competitionName: $name,
            matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a1'),
            matchSourceName: 'MS ve fotbale 2026',
            matchSourceIsCompleted: $isCompleted,
            ownerNickname: 'admin',
            isOwner: true,
            joinedAt: new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('UTC')),
            matchSourceStartAt: $startAt,
            matchSourceEndAt: $endAt,
        );
    }
}
