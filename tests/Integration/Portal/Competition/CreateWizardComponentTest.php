<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Rule\ExactScoreRule;
use App\Rule\ScorerHitRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * S08 create-competition wizard: per-step validation, and the two full happy
 * paths (from-scratch hockey, curated subset + custom rules + premium intent).
 */
final class CreateWizardComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testEmptyNameBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component->call('next')->render();

        self::assertStringContainsString('Zadejte prosím název soutěže', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testNeitherSourceNorFromScratchBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component->set('name', 'Bez zdroje')->call('next')->render();

        self::assertStringContainsString('Vyberte zdroj zápasů', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testSubsetWithZeroMatchesBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Vybrané')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'subset')
            ->set('selectedMatchIds', [])
            ->call('next')
            ->render();

        self::assertStringContainsString('Vyberte prosím alespoň jeden zápas', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testValidBasicsAdvanceToRules(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Postup dál')
            ->set('fromScratch', true)
            ->set('sportId', Sport::HOCKEY_ID)
            ->call('next')
            ->render();

        self::assertStringContainsString('Krok 2 ze 4', $html);
        self::assertStringContainsString('Vyberte pravidla', $html);
    }

    public function testFromScratchHockeyHappyPath(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        // Walk the flow, then submit on the final step.
        $response = $component
            ->set('name', 'Hokej od začátku')
            ->set('fromScratch', true)
            ->set('sportId', Sport::HOCKEY_ID)
            ->call('next')   // → rules
            ->call('next')   // → invites
            ->call('next')   // → support
            ->set('monetization', 'boosts')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression('#^/portal/turnaje/[0-9a-f-]{36}$#', (string) $response->headers->get('Location'));

        $competition = $this->competitionByName($client, 'Hokej od začátku');
        self::assertSame(MatchSourceKind::Private, $competition->matchSource->kind);
        self::assertSame('hockey', $competition->matchSource->sport->code);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertCount(1, $this->memberships($client, $competition->id));

        // Redirect points at the freshly created hidden source (empty-state „Přidejte zápasy").
        self::assertSame('/portal/turnaje/'.$competition->matchSource->id->toRfc4122(), $response->headers->get('Location'));
    }

    public function testCuratedSubsetCustomRulesPremiumHappyPath(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $response = $component
            ->set('name', 'Kurátor prémium')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'subset')
            ->set('selectedMatchIds', [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID])
            ->set('enabledRuleIds', [
                'correct_home_goals',
                'correct_away_goals',
                'correct_outcome',
                ExactScoreRule::IDENTIFIER,
                ScorerHitRule::IDENTIFIER,
            ])
            ->set('rulePoints', [ExactScoreRule::IDENTIFIER => 8])
            ->set('withPin', true)
            ->set('inviteEmailsRaw', 'novy-hrac@example.com')
            ->set('monetization', 'premium')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Kurátor prémium');
        self::assertSame('/portal/souteze/'.$competition->id->toRfc4122(), $response->headers->get('Location'));
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNotNull($competition->pin);

        $em = $this->em($client);

        // Subset selection rows.
        $selections = $em->createQueryBuilder()
            ->select('s')->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :c')->setParameter('c', $competition->id)
            ->getQuery()->getResult();
        self::assertCount(2, $selections);

        // Rule rows: changed point value + an enabled optional rule.
        self::assertSame(8, $this->rule($client, $competition->id, ExactScoreRule::IDENTIFIER)->points);
        self::assertTrue($this->rule($client, $competition->id, ScorerHitRule::IDENTIFIER)->enabled);

        // Invitation + stub user created (invitations "sent" via post-commit event).
        $invitations = $em->createQueryBuilder()
            ->select('i')->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :c')->setParameter('c', $competition->id)
            ->andWhere('i.email = :e')->setParameter('e', 'novy-hrac@example.com')
            ->getQuery()->getResult();
        self::assertCount(1, $invitations);

        $stub = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'novy-hrac@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $stub);
    }

    // ---- helpers ----

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function user(KernelBrowser $client, string $id): User
    {
        $user = $this->em($client)->find(User::class, Uuid::fromString($id));
        self::assertNotNull($user);

        return $user;
    }

    private function competitionByName(KernelBrowser $client, string $name): Competition
    {
        $this->em($client)->clear();

        $competition = $this->em($client)->createQueryBuilder()
            ->select('c')->from(Competition::class, 'c')
            ->where('c.name = :name')->setParameter('name', $name)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function rule(KernelBrowser $client, Uuid $competitionId, string $identifier): CompetitionRuleConfiguration
    {
        $rule = $this->em($client)->createQueryBuilder()
            ->select('r')->from(CompetitionRuleConfiguration::class, 'r')
            ->where('r.competition = :c')->setParameter('c', $competitionId)
            ->andWhere('r.ruleIdentifier = :i')->setParameter('i', $identifier)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(CompetitionRuleConfiguration::class, $rule);

        return $rule;
    }

    /**
     * @return list<Membership>
     */
    private function memberships(KernelBrowser $client, Uuid $competitionId): array
    {
        return $this->em($client)->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.competition = :c')->setParameter('c', $competitionId)
            ->getQuery()->getResult();
    }
}
