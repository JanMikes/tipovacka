<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\TournamentRuleConfiguration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class TournamentRuleConfigurationFlowTest extends WebTestCase
{
    public function testOwnerSeesConfigurationPage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pravidla');
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $nonOwner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($nonOwner);
        $client->loginUser($nonOwner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/pravidla');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanUpdateRuleConfiguration(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $tournamentId = AppFixtures::PUBLIC_TOURNAMENT_ID;

        $client->request('GET', '/portal/turnaje/'.$tournamentId.'/pravidla');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit pravidla', [
            'tournament_rule_configuration_form[rules][exact_score][enabled]' => '1',
            'tournament_rule_configuration_form[rules][exact_score][points]' => '20',
            'tournament_rule_configuration_form[rules][correct_outcome][points]' => '3',
            'tournament_rule_configuration_form[rules][correct_home_goals][points]' => '1',
            'tournament_rule_configuration_form[rules][correct_away_goals][points]' => '1',
        ]);

        self::assertResponseRedirects('/portal/turnaje/'.$tournamentId.'/pravidla');

        $em->clear();

        /** @var list<TournamentRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->andWhere('c.ruleIdentifier = :ruleIdentifier')
            ->setParameter('tournamentId', Uuid::fromString($tournamentId))
            ->setParameter('ruleIdentifier', 'exact_score')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $configurations);
        self::assertSame(20, $configurations[0]->points);
    }
}
