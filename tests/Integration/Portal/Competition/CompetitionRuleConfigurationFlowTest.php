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

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

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
                'period_away_goals' => 1,
                'period_exact' => 5,
                'period_home_goals' => 1,
                'period_tendency' => 2,
                'scorer_hit' => 2,
            ],
            $decoded,
        );
    }

    /**
     * W1 — the per-rule copy is ONE shared map (RulePresetProvider::RULE_COPY),
     * so this screen names a rule exactly as the create-competition wizard does.
     * The two used to carry duplicated Twig maps that could silently drift.
     */
    public function testRulesScreenUsesTheSharedRenamedRuleCopy(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Tip hosté', $html);
        self::assertStringContainsString('Správný tip hostujícího týmu', $html);
        self::assertStringContainsString('Tip domácí', $html);
        self::assertStringContainsString('Správný tip domácího týmu', $html);
        self::assertStringContainsString('bonus za obě uhodnutá skóre', $html);
        self::assertStringContainsString('Vítěz po prodloužení / penaltách', $html);

        // The two new period rules render here too, and period_tendency is kept.
        self::assertStringContainsString('Tip domácí v části zápasu', $html);
        self::assertStringContainsString('Tip hosté v části zápasu', $html);
        self::assertStringContainsString('Tendence části zápasu', $html);

        self::assertStringNotContainsString('Dobrý tip skóre hostů', $html);
        self::assertStringNotContainsString('Dobrý tip skóre domácích', $html);
    }

    public function testNonOwnerIsForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $nonOwner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($nonOwner);
        $client->loginUser($nonOwner);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

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
        $client->request('GET', '/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/pravidla');

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

        $client->request('GET', '/souteze/'.$competitionId.'/pravidla');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit pravidla', [
            'competition_rule_configuration_form[rules][exact_score][enabled]' => '1',
            'competition_rule_configuration_form[rules][exact_score][points]' => '20',
            'competition_rule_configuration_form[rules][correct_outcome][points]' => '3',
            'competition_rule_configuration_form[rules][correct_home_goals][points]' => '1',
            'competition_rule_configuration_form[rules][correct_away_goals][points]' => '1',
        ]);

        self::assertResponseRedirects('/souteze/'.$competitionId.'/pravidla');

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

    /**
     * An organizer may enable the overtime rule (the „Maxi" preset does) over
     * zdroje that never play extra time, where it can never award a point. The
     * rule is NOT hidden or auto-disabled for that — a soutěž can span several
     * zdroje with different flags, and a flag can be switched on later — so the
     * screen says so instead. VERIFIED_COMPETITION draws only from
     * PRIVATE_SOURCE, whose hasOvertime is false.
     */
    public function testStrongOvertimeHintWhenNoZdrojPlaysExtraTime(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString(
            'Žádný ze zdrojů zápasů této soutěže nehraje prodloužení, takže by toto pravidlo nikdy nebodovalo. Prodloužení zapnete v nastavení zdroje zápasů.',
            $html,
        );
        // The rule itself stays on offer — the hint is a caveat, not a removal.
        self::assertStringContainsString('Vítěz po prodloužení / penaltách', $html);
    }

    /**
     * PUBLIC_COMPETITION draws from PUBLIC_SOURCE, a knockout zdroj that does
     * play extra time — there is nothing honest to warn about, so neither the
     * strong nor the softer hint is rendered.
     */
    public function testNoOvertimeHintWhenEveryZdrojPlaysExtraTime(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/pravidla');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Vítěz po prodloužení / penaltách', $html);
        self::assertStringNotContainsString('Žádný ze zdrojů zápasů této soutěže nehraje prodloužení', $html);
        self::assertStringNotContainsString('Vítěze tipují hráči jen u zápasů ze zdrojů, které prodloužení hrají.', $html);
    }
}
