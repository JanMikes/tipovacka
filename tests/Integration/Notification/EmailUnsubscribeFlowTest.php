<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\EmailFrequency;
use App\Enum\NotificationType;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationSettingsRepository;
use App\Service\Notification\EmailPreferenceLinks;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „Odhlásit" from a notification mail, as a person with NO session experiences
 * it: the signed link is the only credential. Nobody logs in anywhere in this
 * class — that is the point.
 */
final class EmailUnsubscribeFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string USER = AppFixtures::VERIFIED_USER_ID;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testOpeningTheLinkChangesNothingAndAsksOnce(): void
    {
        $this->client->request('GET', $this->page('typ-guess_reminder'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odhlásit oznámení „Připomínka tipů“?');
        // The address is masked — the page is reachable by anyone the mail was forwarded to.
        self::assertAnySelectorTextContains('body', 'Všechna nastavení oznámení');
        self::assertStringNotContainsString(AppFixtures::VERIFIED_USER_EMAIL, (string) $this->client->getResponse()->getContent());

        // A GET is what link scanners do: it must not unsubscribe anyone.
        self::assertNull($this->preferences()->findOne(Uuid::fromString(self::USER), NotificationType::GuessReminder));
    }

    public function testOneButtonSwitchesTheTypeOffAndItCanBeUndone(): void
    {
        $crawler = $this->client->request('GET', $this->page('typ-guess_reminder'));
        $this->client->submit($crawler->selectButton('Odhlásit')->form());

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Odhlášeno');
        self::assertStringContainsString('u•••@tipovacka.test', (string) $this->client->getResponse()->getContent());

        $preference = $this->preferences()->findOne(Uuid::fromString(self::USER), NotificationType::GuessReminder);
        self::assertNotNull($preference);
        self::assertFalse($preference->email);
        self::assertTrue($preference->inApp, 'only the e-mail half is switched off');

        $this->client->submit($crawler->selectButton('Vrátit zpět')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Zase zapnuto');

        self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        self::assertTrue($this->preferences()->findOne(Uuid::fromString(self::USER), NotificationType::GuessReminder)?->email);
    }

    public function testASoutezCanBeMutedFromTheMail(): void
    {
        $crawler = $this->client->request('GET', $this->page('soutez-'.AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertSelectorTextContains('h1', 'Odhlásit soutěž '.AppFixtures::VERIFIED_COMPETITION_NAME.'?');

        $this->client->submit($crawler->selectButton('Odhlásit')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'Odhlášeno');
        self::assertTrue(self::getContainer()->get(MembershipRepository::class)->hasMutedNotifications(
            Uuid::fromString(self::USER),
            Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));
    }

    public function testFewerMailsIsOfferedNextToNoMails(): void
    {
        // An „průběžně" player is offered the daily summary as the softer way out.
        $this->setFrequency(EmailFrequency::Immediate);

        $crawler = $this->client->request('GET', $this->page('typ-guess_reminder'));
        $this->client->submit($crawler->selectButton('Raději jen jeden souhrn denně')->form());
        $this->client->followRedirect();

        self::assertAnySelectorTextContains('body', 'odteď nejvýš jeden souhrn denně');
        self::assertSame(EmailFrequency::Daily, $this->frequency());

        // …and „všechny e-maily" stops them all.
        $crawler = $this->client->request('GET', $this->page('typ-guess_reminder'));
        $this->client->submit($crawler->selectButton('Odhlásit všechny e-maily s oznámeními')->form());
        $this->client->followRedirect();

        self::assertSame(EmailFrequency::Never, $this->frequency());
    }

    /** The token only ever turns e-mail DOWN — it cannot bring a silenced player back to a daily mail. */
    public function testTheSofterOptionCannotSwitchEmailBackOn(): void
    {
        $this->client->request('POST', $this->oneClick('vse'));
        self::assertSame(EmailFrequency::Never, $this->frequency());

        $this->client->request('POST', $this->page(), ['akce' => 'denne']);

        self::assertSame(EmailFrequency::Never, $this->frequency());
    }

    /**
     * RFC 8058: the mail client POSTs `List-Unsubscribe=One-Click` with no cookies
     * and expects it done — a 200, no redirect, no confirmation page.
     */
    public function testOneClickPostUnsubscribesWithoutAnyFurtherStep(): void
    {
        $this->client->request('POST', $this->oneClick('typ-guess_reminder'), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($this->preferences()->findOne(Uuid::fromString(self::USER), NotificationType::GuessReminder)?->email);
    }

    public function testOneClickForTheDailySummaryStopsAllNotificationMail(): void
    {
        $this->client->request('POST', $this->oneClick('vse'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(EmailFrequency::Never, $this->frequency());
    }

    /** Some clients open the header URL in a browser instead — that is a GET, and a GET only asks. */
    public function testOneClickUrlOpenedInABrowserLeadsToThePage(): void
    {
        $this->client->request('GET', $this->oneClick('typ-guess_reminder'));

        self::assertResponseRedirects($this->page('typ-guess_reminder'));
        self::assertNull($this->preferences()->findOne(Uuid::fromString(self::USER), NotificationType::GuessReminder));
    }

    public function testAForgedOrMangledTokenIsNotFound(): void
    {
        $token = $this->links()->tokenFor(Uuid::fromString(self::USER));
        // Same signature, another player's id: the signature no longer matches.
        $foreign = $this->links()->tokenFor(Uuid::fromString(AppFixtures::ADMIN_ID));
        $forged = explode('.', $foreign)[0].'.'.explode('.', $token)[1];

        foreach ([$forged, 'abc.def', $token.'x'] as $bad) {
            $this->client->request('GET', '/e-maily/'.$bad);
            self::assertResponseStatusCodeSame(404);

            $this->client->request('POST', '/e-maily/'.$bad.'/odhlasit/vse');
            self::assertResponseStatusCodeSame(404);
        }

        self::assertNull($this->settings()->findForUser(Uuid::fromString(AppFixtures::ADMIN_ID)));
    }

    public function testUnknownScopeFallsBackToTheGeneralPage(): void
    {
        $this->client->request('GET', $this->page('typ-no_such_type'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jak často vám máme psát?');
    }

    private function page(?string $scope = null): string
    {
        $path = '/e-maily/'.$this->links()->tokenFor(Uuid::fromString(self::USER));

        return null === $scope ? $path : $path.'?co='.$scope;
    }

    private function oneClick(string $scope): string
    {
        return sprintf('/e-maily/%s/odhlasit/%s', $this->links()->tokenFor(Uuid::fromString(self::USER)), $scope);
    }

    private function setFrequency(EmailFrequency $frequency): void
    {
        $this->testCommandBus()->dispatch(new ChangeEmailDeliveryCommand(Uuid::fromString(self::USER), $frequency));
    }

    /** The stored frequency, read fresh — every request ran in its own kernel. */
    private function frequency(): ?EmailFrequency
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        return $this->settings()->findForUser(Uuid::fromString(self::USER))?->emailFrequency;
    }

    private function links(): EmailPreferenceLinks
    {
        /* @var EmailPreferenceLinks */
        return self::getContainer()->get(EmailPreferenceLinks::class);
    }

    private function preferences(): NotificationPreferenceRepository
    {
        /* @var NotificationPreferenceRepository */
        return self::getContainer()->get(NotificationPreferenceRepository::class);
    }

    private function settings(): NotificationSettingsRepository
    {
        /* @var NotificationSettingsRepository */
        return self::getContainer()->get(NotificationSettingsRepository::class);
    }
}
