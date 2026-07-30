<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * „Tipování otevřeno od" as the player meets it: the match STAYS in the lists
 * (with a clock and the admin's note instead of inputs), and the endpoints
 * behind those lists refuse a tip even when the inputs are forged back in.
 *
 * MockClock now = 2025-06-15 12:00 UTC; the opening below is 2025-06-18 09:00
 * UTC (= 11:00 Prague, which is what the UI prints).
 */
final class TipOpeningSurfacesTest extends WebTestCase
{
    private const string OPENS_AT = '2025-06-18 09:00:00';
    private const string COMPETITION_ID = AppFixtures::PUBLIC_COMPETITION_ID;
    private const string MATCH_ID = AppFixtures::MATCH_SCHEDULED_ID;

    public function testMatchPageShowsTheWaitingCopyInsteadOfTheTipInputs(): void
    {
        $client = static::createClient();
        $this->openTippingAt($client, self::OPENS_AT);
        $this->loginAdmin($client);

        $crawler = $client->request(
            'GET',
            '/souteze/'.self::COMPETITION_ID.'/zapasy/'.self::MATCH_ID,
        );

        self::assertResponseIsSuccessful();

        $text = $crawler->filter('body')->text();
        self::assertStringContainsString('Tipování tohoto zápasu otevřeme', $text);
        self::assertStringContainsString('Tipy otevřeme po losu skupin.', $text);
        // The waiting copy replaces the closed-window copy, never joins it.
        self::assertStringNotContainsString('uzávěrka proběhla', $text);
        self::assertCount(0, $crawler->filter('input[data-model="homeScore"]'));
    }

    public function testBatchTipPageKeepsTheRowButDropsItsInputs(): void
    {
        $client = static::createClient();
        $this->openTippingAt($client, self::OPENS_AT);
        $this->loginAdmin($client);

        $crawler = $client->request('GET', '/souteze/'.self::COMPETITION_ID.'/moje-tipy');
        self::assertResponseIsSuccessful();

        // The match is still listed — hiding it would read as „not in this soutěž".
        self::assertStringContainsString('Tipování otevřeme', $crawler->filter('body')->text());
        self::assertStringContainsString('Tipování ještě není otevřené', $crawler->filter('body')->text());
        // …but it takes no input.
        self::assertCount(
            0,
            $crawler->filter('input[name="guesses['.self::MATCH_ID.'][homeScore]"]'),
        );
    }

    /**
     * The gate is server-side: forging the inputs the page refused to render
     * changes nothing. This is the „direct POST" case, not a UI check.
     */
    public function testForgedBatchPostSavesNothingAndExplainsWhy(): void
    {
        $client = static::createClient();
        $this->openTippingAt($client, self::OPENS_AT);
        $this->loginAdmin($client);

        // A real page visit only to obtain a valid CSRF token — everything else
        // about this request is hand-built.
        $crawler = $client->request('GET', '/souteze/'.self::COMPETITION_ID.'/moje-tipy');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        $client->request('POST', '/souteze/'.self::COMPETITION_ID.'/moje-tipy', [
            '_token' => $token,
            'guesses' => [
                self::MATCH_ID => ['homeScore' => '3', 'awayScore' => '0'],
            ],
        ]);

        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertNull($this->storedGuess($client));
        self::assertStringContainsString(
            'Tipování tohoto zápasu začíná až',
            $client->getCrawler()->filter('body')->text(),
        );
    }

    private function openTippingAt(KernelBrowser $client, string $opensAt): void
    {
        /** @var MessageBusInterface $commandBus */
        $commandBus = $client->getContainer()->get('command.bus');

        $commandBus->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(self::COMPETITION_ID),
            sportMatchId: Uuid::fromString(self::MATCH_ID),
            deadline: null,
            changeOpening: true,
            opensAt: new \DateTimeImmutable($opensAt),
            openingNote: 'Tipy otevřeme po losu skupin.',
        ));

        $this->entityManager($client)->clear();
    }

    private function loginAdmin(KernelBrowser $client): void
    {
        $admin = $this->entityManager($client)->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        $client->loginUser($admin);
    }

    private function storedGuess(KernelBrowser $client): ?Guess
    {
        $em = $this->entityManager($client);
        $em->clear();

        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :c')
            ->setParameter('u', Uuid::fromString(AppFixtures::ADMIN_ID))
            ->setParameter('m', Uuid::fromString(self::MATCH_ID))
            ->setParameter('c', Uuid::fromString(self::COMPETITION_ID))
            ->getQuery()->getOneOrNullResult();

        return $guess;
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
