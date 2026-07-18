<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionMatchSelection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionMatchSelectionFlowTest extends WebTestCase
{
    public function testOwnerSeesGroupedCheckboxListWithCurrentSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Výběr zápasů');
        self::assertSelectorTextContains('body', 'Sparta Praha');
        self::assertSelectorTextContains('body', 'Playoff');

        // Fixture selection: MATCH_SCHEDULED + MATCH_FINISHED are checked.
        $checked = $crawler->filter('input[name="matches[]"]:checked')->each(
            static fn ($node) => $node->attr('value'),
        );
        sort($checked);
        $expected = [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_FINISHED_ID];
        sort($expected);
        self::assertSame($expected, $checked);
    }

    public function testOwnerCanReplaceSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber', [
            '_token' => $token,
            'matches' => [AppFixtures::MATCH_PLAYOFF_ID],
        ]);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');

        $em->clear();

        $selectedIds = array_map(
            static fn (CompetitionMatchSelection $s): string => $s->sportMatch->id->toRfc4122(),
            $em->createQueryBuilder()
                ->select('s')
                ->from(CompetitionMatchSelection::class, 's')
                ->where('s.competition = :competitionId')
                ->setParameter('competitionId', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
                ->getQuery()
                ->getResult(),
        );

        self::assertSame([AppFixtures::MATCH_PLAYOFF_ID], $selectedIds);
    }

    public function testNonOwnerMemberCannotManageSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $stranger = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($stranger);
        $client->loginUser($stranger);

        $client->request('GET', '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAllModeCompetitionRedirectsToDetail(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID);
    }
}
