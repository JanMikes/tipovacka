<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Email;

use App\DataFixtures\AppFixtures;
use App\Entity\EmailLog;
use App\Enum\EmailLogStatus;
use App\Tests\Support\WebFlowHelpers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/** The admin „Odeslané e-maily" page over a handful of logged attempts. */
final class SentEmailLogFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string SENT_ID = '019eeeee-0000-7000-8000-000000000001';
    private const string FAILED_ID = '019eeeee-0000-7000-8000-000000000002';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new EmailLog(
            id: Uuid::fromString(self::SENT_ID),
            recipient: 'tipujici@example.com',
            subject: 'Chybí vám 3 tipy',
            template: 'notification',
            status: EmailLogStatus::Sent,
            createdAt: new \DateTimeImmutable('2025-06-15 10:00:00 UTC'),
            body: "Chybí vám 3 tipy\n\nNatipovat: https://wtips.cz/zapasy\nOdhlásit tento typ: https://wtips.cz/e-maily/…?…",
            messageId: 'abc123@smtp.example',
        ));
        $entityManager->persist(new EmailLog(
            id: Uuid::fromString(self::FAILED_ID),
            recipient: 'nedorucitelny@example.com',
            subject: 'Potvrďte svůj e-mail a můžete tipovat',
            template: 'verify_email',
            status: EmailLogStatus::Failed,
            createdAt: new \DateTimeImmutable('2025-06-15 11:00:00 UTC'),
            error: '550 Mailbox unavailable (vzdáno, další pokus nebude)',
        ));
        $entityManager->flush();
    }

    public function testListShowsEveryAttemptNewestFirst(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $crawler = $this->client->request('GET', '/admin/odeslane-e-maily');

        self::assertResponseIsSuccessful();

        $rows = $crawler->filter('tbody tr');
        self::assertCount(2, $rows);
        self::assertStringContainsString('nedorucitelny@example.com', $rows->eq(0)->text());
        self::assertStringContainsString('550 Mailbox unavailable', $rows->eq(0)->text());
        self::assertStringContainsString('Chyba', $rows->eq(0)->text());
        self::assertStringContainsString('tipujici@example.com', $rows->eq(1)->text());
        self::assertStringContainsString('Odesláno', $rows->eq(1)->text());
    }

    public function testListFiltersByAddressAndOutcome(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $crawler = $this->client->request('GET', '/admin/odeslane-e-maily?q=TIPUJICI');
        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertAnySelectorTextContains('tbody', 'tipujici@example.com');

        $crawler = $this->client->request('GET', '/admin/odeslane-e-maily?stav=failed');
        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertAnySelectorTextContains('tbody', 'nedorucitelny@example.com');

        $this->client->request('GET', '/admin/odeslane-e-maily?q=nikdo');
        self::assertAnySelectorTextContains('tbody', 'Tomuto filtru neodpovídá žádný e-mail.');
    }

    public function testDetailShowsTheMailTextAndTheRelaysAnswer(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('GET', '/admin/odeslane-e-maily/'.self::SENT_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Chybí vám 3 tipy');
        self::assertSelectorTextContains('pre', 'Natipovat: https://wtips.cz/zapasy');
        self::assertAnySelectorTextContains('body', 'abc123@smtp.example');

        $this->client->request('GET', '/admin/odeslane-e-maily/'.self::FAILED_ID);
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', '550 Mailbox unavailable');
        self::assertSelectorNotExists('pre');
    }

    public function testUnknownEntryIsNotFound(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/odeslane-e-maily/019eeeee-0000-7000-8000-0000000000ff');

        self::assertResponseStatusCodeSame(404);
    }

    /** The log holds other people's addresses — it is not for players. */
    public function testPlayersCannotSeeIt(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('GET', '/admin/odeslane-e-maily');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/odeslane-e-maily/'.self::SENT_ID);
        self::assertResponseStatusCodeSame(403);
    }
}
