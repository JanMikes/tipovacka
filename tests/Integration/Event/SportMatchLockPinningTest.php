<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\PostponeSportMatch\PostponeSportMatchCommand;
use App\Command\SoftDeleteSportMatch\SoftDeleteSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Service\EffectiveTipDeadlineResolver;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S07 correctness pin: when the match that DEFINED a competition's automatic
 * lock moment is postponed later or soft-deleted after that moment already
 * passed, `tipsLockedAt` must be pinned so the still-scheduled siblings do not
 * silently reopen. Uses a competition + matches created BEFORE the lock moment
 * (so the siblings are pre-lock, not late-added) — the only shape where the
 * naive recomputation would reopen tips.
 */
final class SportMatchLockPinningTest extends IntegrationTestCase
{
    public function testPostponingLockDefiningOpenerAfterStartPinsLockAndKeepsSiblingLocked(): void
    {
        // Opener kicked off June 10 (past ⇒ competition started, tips locked).
        [$competition, $opener, $sibling] = $this->seedStartedCompetition('2025-06-10 18:00:00 UTC');

        $resolver = self::getContainer()->get(EffectiveTipDeadlineResolver::class);
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        // Baseline: the sibling is locked at the June-10 lock moment.
        self::assertTrue($resolver->isLocked($competition, $sibling, null, $now));

        // Postpone the opener to well after the sibling.
        $this->commandBus()->dispatch(new PostponeSportMatchCommand(
            sportMatchId: $opener->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            newKickoffAt: new \DateTimeImmutable('2025-06-28 18:00:00 UTC'),
        ));

        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(Competition::class, $competition->id);
        self::assertInstanceOf(Competition::class, $reloaded);
        // The reached lock moment is pinned — NOT reopened to the sibling's kickoff.
        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00:00 UTC'), $reloaded->tipsLockedAt);

        $resolver->reset();
        $reloadedSibling = $em->find(SportMatch::class, $sibling->id);
        self::assertInstanceOf(SportMatch::class, $reloadedSibling);
        self::assertTrue($resolver->isLocked($reloaded, $reloadedSibling, null, $now));
    }

    public function testDeletingLockDefiningOpenerAfterStartPinsLockAndKeepsSiblingLocked(): void
    {
        [$competition, $opener, $sibling] = $this->seedStartedCompetition('2025-06-10 18:00:00 UTC');

        $this->commandBus()->dispatch(new SoftDeleteSportMatchCommand(
            sportMatchId: $opener->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(Competition::class, $competition->id);
        self::assertInstanceOf(Competition::class, $reloaded);
        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00:00 UTC'), $reloaded->tipsLockedAt);

        $resolver = self::getContainer()->get(EffectiveTipDeadlineResolver::class);
        $resolver->reset();
        $reloadedSibling = $em->find(SportMatch::class, $sibling->id);
        self::assertInstanceOf(SportMatch::class, $reloadedSibling);
        self::assertTrue($resolver->isLocked($reloaded, $reloadedSibling, null, new \DateTimeImmutable('2025-06-15 12:00:00 UTC')));
    }

    public function testPostponingOpenerBeforeCompetitionStartDoesNotPin(): void
    {
        // Opener kickoff June 18 is still ahead (now June 15): the competition
        // has not started, so postponing must NOT pin anything.
        [$competition, $opener] = $this->seedStartedCompetition('2025-06-18 18:00:00 UTC');

        $this->commandBus()->dispatch(new PostponeSportMatchCommand(
            sportMatchId: $opener->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            newKickoffAt: new \DateTimeImmutable('2025-06-28 18:00:00 UTC'),
        ));

        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(Competition::class, $competition->id);
        self::assertInstanceOf(Competition::class, $reloaded);
        self::assertNull($reloaded->tipsLockedAt);
    }

    /**
     * Builds a private source + All-mode competition + two matches, all created
     * 2025-05-01 (BEFORE any lock moment), so the matches are pre-lock siblings.
     *
     * @return array{Competition, SportMatch, SportMatch} [competition, opener, sibling]
     */
    private function seedStartedCompetition(string $openerKickoff): array
    {
        $em = $this->entityManager();
        $createdAt = new \DateTimeImmutable('2025-05-01 09:00:00 UTC');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $owner);
        $sport = $em->find(Sport::class, Uuid::fromString(Sport::FOOTBALL_ID));
        self::assertInstanceOf(Sport::class, $sport);

        $source = new MatchSource(
            id: Uuid::v7(),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Pin test source',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $createdAt,
        );
        $source->popEvents();
        $em->persist($source);

        $competition = new Competition(
            id: Uuid::v7(),
            matchSource: $source,
            owner: $owner,
            name: 'Pin test competition',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $createdAt,
        );
        $competition->popEvents();
        $em->persist($competition);

        $opener = $this->makeMatch($source, 'Opener home', 'Opener away', $openerKickoff, $createdAt);
        $sibling = $this->makeMatch($source, 'Sibling home', 'Sibling away', '2025-06-20 18:00:00 UTC', $createdAt);

        $em->flush();

        return [$competition, $opener, $sibling];
    }

    private function makeMatch(MatchSource $source, string $home, string $away, string $kickoff, \DateTimeImmutable $createdAt): SportMatch
    {
        $match = new SportMatch(
            id: Uuid::v7(),
            matchSource: $source,
            homeTeam: $home,
            awayTeam: $away,
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
            createdAt: $createdAt,
        );
        $match->popEvents();
        $this->entityManager()->persist($match);

        return $match;
    }
}
