<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCompetitionFlowTest extends WebTestCase
{
    public function testCreateFromCuratedSourceHonorsTipSettings(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/souteze/nova?zdroj='.AppFixtures::PUBLIC_SOURCE_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Zdroj zápasů');

        $client->submitForm('Vytvořit soutěž', [
            'competition_form[name]' => 'Další parta',
            'competition_form[matchSourceId]' => AppFixtures::PUBLIC_SOURCE_ID,
            'competition_form[selectionMode]' => 'all',
            'competition_form[hideOthersTipsBeforeDeadline]' => '1',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Další parta')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $competition->owner->id->toRfc4122());
        self::assertSame(AppFixtures::PUBLIC_SOURCE_ID, $competition->matchSource->id->toRfc4122());
        self::assertSame(CompetitionMatchSelectionMode::All, $competition->selectionMode);
        self::assertTrue($competition->includePlayoff);

        // Regression: the create form must persist tip settings (S01-documented bug).
        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        // S07: no group-wide deadline anymore — a new competition starts unlocked.
        self::assertNull($competition->tipsLockedAt);
    }

    public function testCreateFromOwnPrivateSourceViaPreselect(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/souteze/nova?zdroj='.AppFixtures::PRIVATE_SOURCE_ID);
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit soutěž', [
            'competition_form[name]' => 'Parta u piva 2',
            'competition_form[matchSourceId]' => AppFixtures::PRIVATE_SOURCE_ID,
            'competition_form[selectionMode]' => 'all',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Parta u piva 2')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(AppFixtures::PRIVATE_SOURCE_ID, $competition->matchSource->id->toRfc4122());
    }

    public function testCreateWithSubsetSelectionCreatesSelectionRows(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/souteze/nova?zdroj='.AppFixtures::PUBLIC_SOURCE_ID);
        self::assertResponseIsSuccessful();

        // Raw POST — checkbox arrays are simpler to submit directly
        // (form CSRF protection is disabled app-wide: config/packages/csrf.php).
        $client->request('POST', '/portal/souteze/nova?zdroj='.AppFixtures::PUBLIC_SOURCE_ID, [
            'competition_form' => [
                'name' => 'Jen vybrané zápasy',
                'description' => '',
                'matchSourceId' => AppFixtures::PUBLIC_SOURCE_ID,
                'selectionMode' => 'subset',
                'selectedMatchIds' => [AppFixtures::MATCH_SCHEDULED_ID],
            ],
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Jen vybrané zápasy')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(CompetitionMatchSelectionMode::Subset, $competition->selectionMode);

        $selections = $em->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $selections);
        self::assertSame(AppFixtures::MATCH_SCHEDULED_ID, $selections[0]->sportMatch->id->toRfc4122());
    }

    public function testSourceSwitchReloadPrefillsFormFromQueryParams(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        // The source-change reload (competition_matches_controller.js) carries
        // the user's typed inputs as query params — the form must restore them.
        $crawler = $client->request('GET', '/portal/souteze/nova?'.http_build_query([
            'zdroj' => AppFixtures::PUBLIC_SOURCE_ID,
            'name' => '  Test parta  ',
            'description' => 'Tipujeme spolu',
            'selectionMode' => 'subset',
            'includePlayoff' => '0',
            'hideOthersTipsBeforeDeadline' => '1',
            'withPin' => '1',
        ]));

        self::assertResponseIsSuccessful();

        self::assertSame('Test parta', $crawler->filter('input[name="competition_form[name]"]')->attr('value'));
        self::assertSame('Tipujeme spolu', $crawler->filter('textarea[name="competition_form[description]"]')->text());
        self::assertNotNull($crawler->filter('input[name="competition_form[selectionMode]"][value="subset"]')->attr('checked'));
        self::assertNull($crawler->filter('input[name="competition_form[includePlayoff]"]')->attr('checked'));
        self::assertNotNull($crawler->filter('input[name="competition_form[hideOthersTipsBeforeDeadline]"]')->attr('checked'));
        self::assertNotNull($crawler->filter('input[name="competition_form[withPin]"]')->attr('checked'));
    }

    public function testInvalidPrefillQueryParamsAreIgnored(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/portal/souteze/nova?'.http_build_query([
            'zdroj' => AppFixtures::PUBLIC_SOURCE_ID,
            'selectionMode' => 'garbage',
            'withPin' => 'yes',
        ]));

        self::assertResponseIsSuccessful();

        self::assertNotNull($crawler->filter('input[name="competition_form[selectionMode]"][value="all"]')->attr('checked'));
        self::assertNull($crawler->filter('input[name="competition_form[withPin]"]')->attr('checked'));
    }

    public function testSubsetWithoutSelectedMatchesIsRejected(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/souteze/nova?zdroj='.AppFixtures::PUBLIC_SOURCE_ID);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/portal/souteze/nova?zdroj='.AppFixtures::PUBLIC_SOURCE_ID, [
            'competition_form' => [
                'name' => 'Prázdný výběr',
                'description' => '',
                'matchSourceId' => AppFixtures::PUBLIC_SOURCE_ID,
                'selectionMode' => 'subset',
            ],
        ]);

        // Invalid form re-render responds 422 (AbstractController::render + invalid form).
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Vyberte prosím alespoň jeden zápas.');

        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Prázdný výběr')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNull($competition);
    }
}
