<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Email;

use App\DataFixtures\AppFixtures;
use App\Service\Email\EmailPreviews;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The admin „E-maily" page — and, through it, the guard that EVERY mail
 * template still renders: a mail is otherwise only exercised when the event
 * that sends it happens to be covered by a test.
 */
final class EmailPreviewFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListShowsEveryMailWithItsSubject(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/e-maily?mail=denni-souhrn');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('nav', 'Ověření e-mailu');
        self::assertAnySelectorTextContains('nav', 'Denní souhrn');
        self::assertSelectorExists('iframe[src="/admin/e-maily/denni-souhrn"]');
    }

    public function testEveryMailRendersAsACompleteBrandedDocument(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        /** @var EmailPreviews $previews */
        $previews = self::getContainer()->get(EmailPreviews::class);
        $slugs = array_map(strval(...), array_keys($previews->list()));

        self::assertGreaterThanOrEqual(10, count($slugs));

        foreach ($slugs as $slug) {
            $this->client->request('GET', '/admin/e-maily/'.$slug);
            $html = (string) $this->client->getResponse()->getContent();

            self::assertResponseIsSuccessful($slug);
            // The shared skeleton: declared colour schemes, the logo, the tagline.
            self::assertStringContainsString('<meta name="color-scheme" content="light dark">', $html, $slug);
            self::assertStringContainsString('/images/email/logo.png', $html, $slug);
            self::assertStringContainsString('Vše hraje, nic se nesází.', $html, $slug);
            // No style may depend on the <style> block alone (Gmail drops it for
            // non-Google accounts): the heading carries its colour inline.
            self::assertMatchesRegularExpression('/<h1 class="h1" style="[^"]*color:#0f1726/', $html, $slug);
        }
    }

    public function testUnknownMailIsNotFound(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/e-maily/neexistuje');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPlayersCannotSeeIt(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/admin/e-maily');

        self::assertResponseStatusCodeSame(403);
    }
}
