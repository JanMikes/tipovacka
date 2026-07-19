<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Guess;

use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * S06 live-form behavior: the visible tip parts follow the competition's rule
 * enablement, and the overtime inputs appear REACTIVELY once the main tip
 * becomes a draw (LiveProp updates re-render the component).
 */
final class GuessSubmitFormComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testFeatureOffCompetitionRendersNoExtraInputs(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::ADMIN_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::PUBLIC_COMPETITION_ID,
        ], $client);

        $html = (string) $component->render();

        self::assertStringNotContainsString('poločas', $html);
        self::assertStringNotContainsString('Po prodloužení', $html);
        self::assertStringNotContainsString('scorer-picker', $html);
    }

    public function testFeatureOnCompetitionRendersPeriodInputsAndScorerPicker(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        $html = (string) $component->render();

        // Period inputs labelled per sport (football → poločasy).
        self::assertStringContainsString('1. poločas', $html);
        self::assertStringContainsString('2. poločas', $html);
        self::assertStringContainsString('data-model="period1Home"', $html);

        // Scorer picker: tom-select island grouped by both team names.
        self::assertStringContainsString('data-controller="scorer-picker"', $html);
        self::assertStringContainsString('data-live-ignore', $html);
        self::assertStringContainsString('optgroup label="Sparta Praha"', $html);
        self::assertStringContainsString('optgroup label="Slavia Praha"', $html);

        // Overtime rule is DISABLED in fixtures — even a draw shows no OT inputs.
        $component->set('homeScore', 1)->set('awayScore', 1);
        self::assertStringNotContainsString('Po prodloužení', (string) $component->render());
    }

    public function testDrawTipRevealsOvertimeInputsWhenRuleEnabled(): void
    {
        $client = static::createClient();

        /** @var MessageBusInterface $commandBus */
        $commandBus = $client->getContainer()->get('command.bus');
        $commandBus->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            changes: [
                'overtime_exact' => ['enabled' => true, 'points' => 3],
            ],
        ));

        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        // No tip yet ⇒ hidden.
        self::assertStringNotContainsString('Po prodloužení', (string) $component->render());

        // Non-draw tip ⇒ still hidden.
        $component->set('homeScore', 2)->set('awayScore', 1);
        self::assertStringNotContainsString('Po prodloužení', (string) $component->render());

        // Draw tip ⇒ overtime inputs appear.
        $component->set('awayScore', 2)->set('homeScore', 2);
        $html = (string) $component->render();
        self::assertStringContainsString('Po prodloužení', $html);
        self::assertStringContainsString('data-model="overtimeHomeScore"', $html);
    }

    public function testSubmitWithPeriodsStoresGuess(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        $component
            ->set('homeScore', 2)
            ->set('awayScore', 1)
            ->set('period1Home', 1)
            ->set('period1Away', 0)
            ->set('period2Home', 1)
            ->set('period2Away', 1)
            ->call('submit');

        self::assertStringContainsString('Tip uložen.', (string) $component->render());

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        /** @var \App\Entity\Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(\App\Entity\Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :c')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('u', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID))
            ->setParameter('c', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($guess);
        self::assertSame([[1, 0], [1, 1]], $guess->periodScores?->toArray());
    }

    public function testPartialPeriodTipIsRejectedInline(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        $component
            ->set('homeScore', 2)
            ->set('awayScore', 1)
            ->set('period1Home', 1)
            ->call('submit');

        self::assertStringContainsString('Vyplňte prosím všechny poločasy', (string) $component->render());
    }

    public function testPeriodSumMismatchIsRejectedInline(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        // Periods sum to 1:1, main tip says 2:1.
        $component
            ->set('homeScore', 2)
            ->set('awayScore', 1)
            ->set('period1Home', 1)
            ->set('period1Away', 0)
            ->set('period2Home', 0)
            ->set('period2Away', 1)
            ->call('submit');

        self::assertStringContainsString(
            'Součet skóre za jednotlivé části musí odpovídat tipu na základní hrací dobu.',
            (string) $component->render(),
        );
    }

    public function testOverlongScorerNameIsRejectedInline(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($client, AppFixtures::SECOND_VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Guess:GuessSubmitForm', [
            'sportMatch' => $this->getMatch($client, AppFixtures::MATCH_SCHEDULED_ID),
            'competitionId' => AppFixtures::SUBSET_COMPETITION_ID,
        ], $client);

        $component
            ->set('homeScore', 2)
            ->set('awayScore', 1)
            ->set('scorersJson', json_encode([
                ['side' => 'home', 'name' => str_repeat('a', 121)],
            ], \JSON_THROW_ON_ERROR))
            ->call('submit');

        // Czech 422 copy, no database driver exception.
        self::assertStringContainsString(
            'Jméno hráče nesmí být delší než 120 znaků.',
            (string) $component->render(),
        );
    }

    private function getUser(KernelBrowser $client, string $id): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString($id));
        self::assertNotNull($user);

        return $user;
    }

    private function getMatch(KernelBrowser $client, string $id): SportMatch
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $match = $em->find(SportMatch::class, Uuid::fromString($id));
        self::assertNotNull($match);

        return $match;
    }
}
