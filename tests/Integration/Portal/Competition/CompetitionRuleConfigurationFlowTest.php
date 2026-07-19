<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionRuleConfigurationFlowTest extends WebTestCase
{
    public function testOwnerSeesConfigurationPage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pravidla');

        // Preset values come from PHP defaultPoints via data-* (no hardcoded JS map).
        $defaults = $client->getCrawler()
            ->filter('[data-controller="scoring-preset"]')
            ->attr('data-scoring-preset-defaults-value');
        self::assertNotNull($defaults);
        $decoded = json_decode($defaults, true);
        self::assertIsArray($decoded);
        ksort($decoded);
        self::assertSame(
            [
                'correct_away_goals' => 1,
                'correct_home_goals' => 1,
                'correct_outcome' => 3,
                'exact_score' => 5,
                'overtime_exact' => 3,
                'period_exact' => 5,
                'period_tendency' => 2,
                'scorer_hit' => 2,
            ],
            $decoded,
        );
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $nonOwner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($nonOwner);
        $client->loginUser($nonOwner);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFormCarriesConfirmRecalculationWithEvaluationCount(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        // PUBLIC_COMPETITION has exactly one fixture evaluation.
        $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[data-controller="confirm-recalculation"][data-confirm-recalculation-count-value="1"]');
    }

    public function testAdminCanUpdateRuleConfiguration(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $competitionId = AppFixtures::PUBLIC_COMPETITION_ID;

        $client->request('GET', '/portal/souteze/'.$competitionId.'/pravidla');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit pravidla', [
            'competition_rule_configuration_form[rules][exact_score][enabled]' => '1',
            'competition_rule_configuration_form[rules][exact_score][points]' => '20',
            'competition_rule_configuration_form[rules][correct_outcome][points]' => '3',
            'competition_rule_configuration_form[rules][correct_home_goals][points]' => '1',
            'competition_rule_configuration_form[rules][correct_away_goals][points]' => '1',
        ]);

        self::assertResponseRedirects('/portal/souteze/'.$competitionId.'/pravidla');

        $em->clear();

        /** @var list<CompetitionRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(CompetitionRuleConfiguration::class, 'c')
            ->where('c.competition = :competitionId')
            ->andWhere('c.ruleIdentifier = :ruleIdentifier')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('ruleIdentifier', 'exact_score')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $configurations);
        self::assertSame(20, $configurations[0]->points);
    }
}
