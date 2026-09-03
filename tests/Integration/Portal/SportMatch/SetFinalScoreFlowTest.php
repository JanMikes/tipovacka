<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchEvent;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\SportMatchState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SetFinalScoreFlowTest extends WebTestCase
{
    private function loginAdmin(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        return $em;
    }

    public function testScoreEntryPageShowsCzechLabelsAndSections(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Zapsat výsledek');
        self::assertAnySelectorTextContains('label span', 'Probíhá');
        self::assertAnySelectorTextContains('label span', 'Ukončený');
        self::assertAnySelectorTextContains('legend', 'Poločasy');
        self::assertAnySelectorTextContains('legend', 'Střelci');
        self::assertAnySelectorTextContains('button', 'Gól Sparta Praha');
        self::assertAnySelectorTextContains('button', 'Gól Slavia Praha');
        self::assertAnySelectorTextContains('button', 'Karta');
        self::assertAnySelectorTextContains('label', 'Toto byl poslední zápas zdroje');
        // The overtime question is ONE choice (no second score), draw-stands by default.
        self::assertAnySelectorTextContains('label', 'Remíza po základní hrací době je konečný výsledek');
        self::assertAnySelectorTextContains('label', 'Vyhrál Sparta Praha v prodloužení nebo na penalty');
        self::assertAnySelectorTextContains('label', 'Vyhrál Slavia Praha v prodloužení nebo na penalty');
        self::assertSelectorExists('input[name="set_final_score_form[overtimeWinner]"][value="draw"][checked]');
        self::assertSelectorNotExists('input[name="set_final_score_form[overtimeHomeScore]"]');
    }

    public function testLiveSaveThenFinishedSaveWithScorers(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore';

        // 1) „Probíhá" save — live score without finishing.
        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'live',
                'homeScore' => '1',
                'awayScore' => '0',
                'periods' => [
                    ['homeScore' => '1', 'awayScore' => '0'],
                    ['homeScore' => '', 'awayScore' => ''],
                ],
            ],
        ]);

        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Live, $match->state);
        self::assertSame(1, $match->homeScore);
        self::assertSame(0, $match->awayScore);
        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 0]], $match->periodScores->toArray());

        // 2) „Ukončený" save with periods and scorer rows.
        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '2',
                'awayScore' => '1',
                'periods' => [
                    ['homeScore' => '1', 'awayScore' => '0'],
                    ['homeScore' => '1', 'awayScore' => '1'],
                ],
                'events' => [
                    ['type' => 'goal', 'side' => 'home', 'minute' => '15', 'playerName' => 'Jan Kuchta'],
                    ['type' => 'goal', 'side' => 'home', 'minute' => '77', 'playerName' => 'Veljko Birmančević'],
                    ['type' => 'goal', 'side' => 'away', 'minute' => '54', 'playerName' => 'Mojmír Chytil'],
                    ['type' => 'yellow_card', 'side' => 'away', 'minute' => '89', 'playerName' => 'Mojmír Chytil'],
                ],
            ],
        ]);

        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);
        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 0], [1, 1]], $match->periodScores->toArray());

        /** @var list<MatchEvent> $events */
        $events = $em->createQueryBuilder()
            ->select('e', 'p')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(4, $events);
        $goals = array_values(array_filter($events, static fn (MatchEvent $e): bool => MatchEventType::Goal === $e->type));
        self::assertCount(3, $goals);
        $awayCards = array_values(array_filter(
            $events,
            static fn (MatchEvent $e): bool => MatchEventType::YellowCard === $e->type && MatchSide::Away === $e->side,
        ));
        self::assertCount(1, $awayCards);
        self::assertSame('Mojmír Chytil', $awayCards[0]->player->name);
    }

    public function testFinishedSaveWithOvertimeOnDraw(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore';

        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '2',
                'awayScore' => '2',
                'periods' => [
                    ['homeScore' => '1', 'awayScore' => '1'],
                    ['homeScore' => '1', 'awayScore' => '1'],
                ],
                'overtimeWinner' => 'home',
            ],
        ]);

        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        // „Vyhrál Sparta" is stored as the draw plus one goal for the winner.
        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(3, $match->overtimeHomeScore);
        self::assertSame(2, $match->overtimeAwayScore);

        // Correcting a match that already has a winner pre-selects it.
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="set_final_score_form[overtimeWinner]"][value="home"][checked]');
    }

    /**
     * The production case of 2026-09-03: a Conference League qualifier first
     * leg ended 2:2 and the organizer only needs the draw to stand. With the
     * default answer the match is saved as a plain draw — no second score
     * exists to get wrong.
     */
    public function testDrawWithTheDefaultAnswerSavesThePlainDraw(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $client->request('POST', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore', [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '2',
                'awayScore' => '2',
                'overtimeWinner' => 'draw',
            ],
        ]);

        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(2, $match->awayScore);
        self::assertNull($match->overtimeHomeScore);
        self::assertNull($match->overtimeAwayScore);
        self::assertNull($match->overtimeWinner);
    }

    public function testDrawWithNoOvertimeAnswerAtAllSavesThePlainDraw(): void
    {
        // The radios are disabled (not submitted) while JS hides the question.
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $client->request('POST', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore', [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '1',
                'awayScore' => '1',
            ],
        ]);

        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertNull($match->overtimeWinner);
    }

    public function testZdrojWithoutOvertimeAsksNoOvertimeQuestionAndRefusesOne(): void
    {
        // PRIVATE_SOURCE plays no extra time: a draw is final, the question is
        // not even built, and a forged answer is an extra field (422).
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'/skore';

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="set_final_score_form[overtimeWinner]"]');

        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '1',
                'awayScore' => '1',
                'overtimeWinner' => 'home',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '1',
                'awayScore' => '1',
            ],
        ]);
        self::assertResponseRedirects('/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertNull($match->overtimeWinner);
    }

    public function testForgedOvertimeAnswerIsRejectedWith422(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('POST', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore', [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '2',
                'awayScore' => '2',
                'overtimeWinner' => 'both',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('body', 'Zvolený výsledek po prodloužení není platný.');
    }

    public function testWinnerPickOnNonDrawIsRejectedWith422(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore';

        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '2',
                'awayScore' => '1',
                'overtimeWinner' => 'home',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('body', 'Vítěze po prodloužení lze zvolit jen při remíze v základní hrací době.');
    }

    public function testEmptyScorerNameIsRejectedWith422NotACrash(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore';

        // Organizer clicked „add goal" but left the player name blank (empty
        // minute too) — the exact production payload. This must fail validation
        // with a friendly message, not a 500 from a null → string type error.
        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '1',
                'awayScore' => '1',
                'events' => [
                    ['type' => 'goal', 'side' => 'home', 'minute' => '', 'playerName' => ''],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('body', 'Zadejte prosím jméno hráče.');

        // Nothing was saved — the match stayed scheduled.
        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Scheduled, $match->state);
    }

    public function testFinishedMatchPageHidesStateToggleAndShowsStaticPill(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_FINISHED_ID.'/skore');

        self::assertResponseIsSuccessful();
        // No „Probíhá" radio — a finished match cannot go back to live.
        self::assertSelectorNotExists('input[type="radio"][value="live"]');
        self::assertSelectorNotExists('input[type="radio"][value="finished"]');
        self::assertAnySelectorTextContains('body', 'Stav zápasu');
        self::assertAnySelectorTextContains('.pill', 'Ukončený');
    }

    public function testLiveStateOnFinishedMatchIsRejectedWith422(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        // Raw POST bypassing the UI (the toggle is not rendered at all).
        $client->request('POST', '/zapasy/'.AppFixtures::MATCH_FINISHED_ID.'/skore', [
            'set_final_score_form' => [
                'state' => 'live',
                'homeScore' => '2',
                'awayScore' => '2',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('body', 'Zvolený stav zápasu není platný.');

        // The match stayed finished with its original score.
        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);
    }

    public function testDomainRejectionOnCancelledMatchRendersFormError(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        $match->cancel(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $match->popEvents();
        $em->flush();
        $em->clear();

        // The form itself is valid — only the domain layer rejects the save.
        $client->request('POST', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore', [
            'set_final_score_form' => [
                'homeScore' => '1',
                'awayScore' => '0',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('body', 'Tento zápas nelze upravit');
    }

    public function testLastMatchCheckboxCompletesSource(): void
    {
        $client = static::createClient();
        $em = $this->loginAdmin($client);

        $url = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore';

        $client->request('POST', $url, [
            'set_final_score_form' => [
                'state' => 'finished',
                'homeScore' => '1',
                'awayScore' => '0',
                'isLastMatch' => '1',
            ],
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isCompleted);
    }
}
