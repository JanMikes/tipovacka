<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Notifications;

use App\DataFixtures\AppFixtures;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class NotificationCenterFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCenterRequiresLogin(): void
    {
        $this->client->request('GET', '/portal/oznameni');

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testFeedRendersFixtureNotifications(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/portal/oznameni');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Oznámení');
        self::assertAnySelectorTextContains('body', 'Bayern');
        self::assertAnySelectorTextContains('body', 'Sparta 2:1 Slavia');
    }

    public function testSettingsTabRendersPreferenceMatrix(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/portal/oznameni?tab=nastaveni');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', 'Připomínka tipů');
        self::assertAnySelectorTextContains('body', 'Konec soutěže');
    }

    public function testReadThroughMarksSingleReadAndRedirects(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        self::assertSame(1, $this->notifications()->countUnreadForUser(Uuid::fromString(AppFixtures::VERIFIED_USER_ID)));

        $this->client->request('GET', '/portal/oznameni/'.AppFixtures::NOTIFICATION_UNREAD_ID.'/precteno');

        self::assertResponseRedirects();
        self::assertStringContainsString('zebricek', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame(0, $this->notifications()->countUnreadForUser(Uuid::fromString(AppFixtures::VERIFIED_USER_ID)));
    }

    public function testMarkAllReadClearsUnread(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $this->client->request('GET', '/portal/oznameni');
        $form = $crawler->filter('form[action="/portal/oznameni/precteno"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/oznameni');
        self::assertSame(0, $this->notifications()->countUnreadForUser(Uuid::fromString(AppFixtures::VERIFIED_USER_ID)));
    }

    public function testBellShowsUnreadBadgeAndDropdown(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $component = $this->createLiveComponent('Notification:Bell', [], $this->client);

        // Closed: unread badge visible, dropdown items hidden.
        $closed = (string) $component->render();
        self::assertStringContainsString('Oznámení', $closed);

        // Open the dropdown → latest notifications appear.
        $opened = (string) $component->call('toggle')->render();
        self::assertStringContainsString('Bayern', $opened);
        self::assertStringContainsString('Zobrazit vše', $opened);
    }

    public function testPreferenceTogglePersists(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $component = $this->createLiveComponent('Notification:Preferences', [], $this->client);
        // GuessReminder defaults to email ON — toggling turns it OFF and persists a row.
        $component->call('toggle', ['type' => 'guess_reminder', 'channel' => 'email']);

        $preference = $this->preferences()->findOne(
            Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            NotificationType::GuessReminder,
        );

        self::assertNotNull($preference);
        self::assertFalse($preference->email);
        self::assertTrue($preference->inApp);
    }

    private function notifications(): NotificationRepository
    {
        /* @var NotificationRepository */
        return self::getContainer()->get(NotificationRepository::class);
    }

    private function preferences(): NotificationPreferenceRepository
    {
        /* @var NotificationPreferenceRepository */
        return self::getContainer()->get(NotificationPreferenceRepository::class);
    }
}
