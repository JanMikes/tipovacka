<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Competition\TipVisibilityGate;
use App\Service\EffectiveTipDeadlineResolver;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The ONE rule behind every tip-revealing surface, and its ONE knob.
 *
 * Wired behaviour (`$freeRevealRequiresResult: true`, config/services.php): other
 * players' tips — concrete and aggregated — are free to read once the match HAS A
 * FINAL RESULT. The tip deadline plays no part: a kickoff that passed without the
 * match being played (an organizer late to postpone) reveals nothing.
 *
 * The product owner recorded that as „decision for now, might change later", so the
 * alternative must stay provably alive: the second half of this test constructs the
 * gate with the knob flipped and pins the pre-2026-07-30 deadline reveal. If that
 * ever stops working, the seam has rotted and the decision is no longer revertible.
 */
final class TipVisibilityGateTest extends IntegrationTestCase
{
    public function testWiredRuleRevealsOnTheResultAndIgnoresThePassedDeadline(): void
    {
        self::bootKernel();
        $gate = self::getContainer()->get(TipVisibilityGate::class);

        $competition = $this->boostsCompetition();
        $live = $this->match(AppFixtures::MATCH_LIVE_ID);
        $finished = $this->match(AppFixtures::MATCH_FINISHED_ID);
        $plain = $this->user(AppFixtures::VERIFIED_USER_ID);
        $entitled = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

        self::assertTrue($this->isPastDeadline($competition, $live), 'Pre-condition: live match past its deadline.');

        // No entitlement: the result is the only free door.
        self::assertFalse($gate->canSeeOthersTips($competition, $plain, $live));
        self::assertFalse($gate->canSeeDistribution($competition, $plain, $live));
        self::assertTrue($gate->canSeeOthersTips($competition, $plain, $finished));
        self::assertTrue($gate->canSeeDistribution($competition, $plain, $finished));

        // The OthersTips boost opens both, whatever the match state.
        self::assertTrue($gate->canSeeOthersTips($competition, $entitled, $live));
        self::assertTrue($gate->canSeeDistribution($competition, $entitled, $live));

        // Anonymous callers get exactly the free half.
        self::assertFalse($gate->canSeeOthersTips($competition, null, $live));
        self::assertTrue($gate->canSeeOthersTips($competition, null, $finished));

        // The batch variants must agree with the single-match ones, or a page and a
        // detail view would tell the viewer two different things.
        $byMatch = $gate->othersTipsVisibleByMatch($competition, $plain, [$live, $finished]);
        self::assertFalse($byMatch[$live->id->toRfc4122()]);
        self::assertTrue($byMatch[$finished->id->toRfc4122()]);

        $distByMatch = $gate->distributionVisibleByMatch($competition, $plain, [$live, $finished]);
        self::assertFalse($distByMatch[$live->id->toRfc4122()]);
        self::assertTrue($distByMatch[$finished->id->toRfc4122()]);
    }

    /**
     * The knob flipped = the pre-2026-07-30 rule, kept alive so the decision stays
     * revertible in one line of config/services.php.
     */
    public function testFlippedKnobRestoresTheDeadlineReveal(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entitlements = $container->get(CompetitionEntitlements::class);
        $deadlineResolver = $container->get(EffectiveTipDeadlineResolver::class);
        $clock = $this->clock();

        $deadlineGate = new TipVisibilityGate(
            entitlements: $entitlements,
            deadlineResolver: $deadlineResolver,
            clock: $clock,
            freeRevealRequiresResult: false,
        );

        $competition = $this->boostsCompetition();
        $live = $this->match(AppFixtures::MATCH_LIVE_ID);
        $scheduled = $this->match(AppFixtures::MATCH_SCHEDULED_ID);
        $plain = $this->user(AppFixtures::VERIFIED_USER_ID);

        // Past its deadline, no result — readable again under the old rule.
        self::assertFalse($live->isFinished);
        self::assertTrue($deadlineGate->canSeeOthersTips($competition, $plain, $live));
        self::assertTrue($deadlineGate->canSeeDistribution($competition, $plain, $live));
        self::assertTrue($deadlineGate->othersTipsVisibleByMatch($competition, $plain, [$live])[$live->id->toRfc4122()]);
        self::assertTrue($deadlineGate->distributionVisibleByMatch($competition, $plain, [$live])[$live->id->toRfc4122()]);

        // …and a match whose deadline has NOT passed stays hidden either way.
        self::assertFalse($deadlineGate->canSeeOthersTips($competition, $plain, $scheduled));
    }

    private function isPastDeadline(Competition $competition, SportMatch $sportMatch): bool
    {
        $resolver = self::getContainer()->get(EffectiveTipDeadlineResolver::class);

        return \DateTimeImmutable::createFromInterface($this->clock()->now())
            >= $resolver->deadlineFor($competition, $sportMatch);
    }

    private function boostsCompetition(): Competition
    {
        $competition = $this->entityManager()->find(
            Competition::class,
            Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
        );
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function match(string $id): SportMatch
    {
        $sportMatch = $this->entityManager()->find(SportMatch::class, Uuid::fromString($id));
        self::assertInstanceOf(SportMatch::class, $sportMatch);

        return $sportMatch;
    }

    private function user(string $id): User
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString($id));
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
