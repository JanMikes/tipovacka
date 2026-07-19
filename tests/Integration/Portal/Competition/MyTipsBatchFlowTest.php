<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Value\PeriodScores;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class MyTipsBatchFlowTest extends WebTestCase
{
    public function testMemberCanSaveMultipleGuessesAtOnce(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy';
        $formNode = $crawler->filter(sprintf('form[action="%s"]', $batchAction));
        self::assertGreaterThan(0, $formNode->count(), 'Batch save form not found.');

        $form = $formNode->form();
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][homeScore]'] = '3';
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][awayScore]'] = '2';
        $client->submit($form);

        self::assertResponseRedirects();

        $em->clear();
        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :gr')
            ->setParameter('u', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->setParameter('gr', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(3, $guess->homeScore);
        self::assertSame(2, $guess->awayScore);
    }

    public function testNonMemberGets403(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $outsider = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($outsider);
        self::assertNotNull($competition);

        $client->loginUser($outsider);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFeatureOnCompetitionShowsPeriodInputsAndSavesThem(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        // Period inputs + the scorers/OT hint (features on in SUBSET fixtures).
        self::assertStringContainsString('1. poločas', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Střelce a prodloužení tipnete v detailu zápasu.', (string) $client->getResponse()->getContent());

        $batchAction = '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy';
        $formNode = $crawler->filter(sprintf('form[action="%s"]', $batchAction));
        self::assertGreaterThan(0, $formNode->count(), 'Batch save form not found.');

        $form = $formNode->form();
        $matchId = AppFixtures::MATCH_SCHEDULED_ID;
        $form['guesses['.$matchId.'][homeScore]'] = '2';
        $form['guesses['.$matchId.'][awayScore]'] = '1';
        $form['guesses['.$matchId.'][periods][1][home]'] = '1';
        $form['guesses['.$matchId.'][periods][1][away]'] = '0';
        $form['guesses['.$matchId.'][periods][2][home]'] = '1';
        $form['guesses['.$matchId.'][periods][2][away]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects();

        $em->clear();
        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :gr')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('u', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString($matchId))
            ->setParameter('gr', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(2, $guess->homeScore);
        self::assertSame([[1, 0], [1, 1]], $guess->periodScores?->toArray());
    }

    public function testNoOpSaveWithDisabledPeriodRulesPreservesStoredPeriods(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID));
        self::assertNotNull($user);
        self::assertNotNull($competition);
        self::assertNotNull($match);

        // Legacy guess with period tips from when the period rules were still
        // enabled — VERIFIED_COMPETITION has them OFF today.
        $legacyId = Uuid::v7();
        $legacy = new Guess(
            id: $legacyId,
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: 2,
            awayScore: 1,
            submittedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1]]),
        );
        $legacy->popEvents();
        $em->persist($legacy);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy';
        $form = $crawler->filter(sprintf('form[action="%s"]', $batchAction))->form();

        // Submit unchanged — the main inputs prefill 2:1 from the stored guess.
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertStringContainsString(
            'Nebyly provedeny žádné změny.',
            (string) $client->getResponse()->getContent(),
        );

        $em->clear();
        $reloaded = $em->find(Guess::class, $legacyId);
        self::assertInstanceOf(Guess::class, $reloaded);
        self::assertNull($reloaded->deletedAt);
        self::assertSame([[1, 0], [1, 1]], $reloaded->periodScores?->toArray());
    }

    public function testDrawChangeDropsStaleOvertimeTipSilently(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var MessageBusInterface $commandBus */
        $commandBus = $client->getContainer()->get('command.bus');

        // Enable the overtime rule for SUBSET, then store a 0:0 tip with OT 1:0.
        $commandBus->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            changes: [
                'overtime_exact' => ['enabled' => true, 'points' => 3],
            ],
        ));
        $commandBus->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 0,
            awayScore: 0,
            overtimeHomeScore: 1,
            overtimeAwayScore: 0,
        ));

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy';
        $form = $crawler->filter(sprintf('form[action="%s"]', $batchAction))->form();
        $matchId = AppFixtures::MATCH_SCHEDULED_ID;
        $form['guesses['.$matchId.'][homeScore]'] = '1';
        $form['guesses['.$matchId.'][awayScore]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        $content = (string) $client->getResponse()->getContent();

        // Saved without an error: the stale OT pair (1:0 < new 1:1 tip) is
        // dropped silently, consistent with the non-draw drop.
        self::assertStringContainsString('Uloženo tipů: 1.', $content);
        self::assertStringNotContainsString('Tip po prodloužení nemůže být nižší', $content);

        $em->clear();
        $guess = $this->findSubsetGuess($em, $matchId);
        self::assertSame(1, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
        self::assertNull($guess->overtimeHomeScore);
        self::assertNull($guess->overtimeAwayScore);
    }

    public function testPeriodsWithoutMainScoreShowsRowErrorInsteadOfDeleting(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var MessageBusInterface $commandBus */
        $commandBus = $client->getContainer()->get('command.bus');

        $commandBus->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy';
        $form = $crawler->filter(sprintf('form[action="%s"]', $batchAction))->form();
        $matchId = AppFixtures::MATCH_SCHEDULED_ID;
        $form['guesses['.$matchId.'][homeScore]'] = '';
        $form['guesses['.$matchId.'][awayScore]'] = '';
        $form['guesses['.$matchId.'][periods][1][home]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertStringContainsString(
            'Vyplňte i celkové skóre zápasu.',
            (string) $client->getResponse()->getContent(),
        );

        // The row neither deleted the guess nor silently dropped the periods.
        $em->clear();
        $guess = $this->findSubsetGuess($em, $matchId);
        self::assertNull($guess->deletedAt);
        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
    }

    public function testPeriodSumMismatchShowsRowError(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/moje-tipy';
        $form = $crawler->filter(sprintf('form[action="%s"]', $batchAction))->form();
        $matchId = AppFixtures::MATCH_SCHEDULED_ID;
        $form['guesses['.$matchId.'][homeScore]'] = '2';
        $form['guesses['.$matchId.'][awayScore]'] = '1';
        // Periods sum to 1:1 — must not silently store an inconsistent tip.
        $form['guesses['.$matchId.'][periods][1][home]'] = '1';
        $form['guesses['.$matchId.'][periods][1][away]'] = '0';
        $form['guesses['.$matchId.'][periods][2][home]'] = '0';
        $form['guesses['.$matchId.'][periods][2][away]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertStringContainsString(
            'Součet skóre za jednotlivé části musí odpovídat tipu na základní hrací dobu.',
            (string) $client->getResponse()->getContent(),
        );
    }

    private function findSubsetGuess(EntityManagerInterface $em, string $matchId): Guess
    {
        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :c')
            ->setParameter('u', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString($matchId))
            ->setParameter('c', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);

        return $guess;
    }

    public function testFeatureOffCompetitionShowsNoPeriodInputs(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('1. poločas', $content);
        self::assertStringNotContainsString('Střelce a prodloužení tipnete', $content);
    }
}
