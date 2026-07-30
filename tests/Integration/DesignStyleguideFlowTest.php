<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class DesignStyleguideFlowTest extends WebTestCase
{
    public function testAdminCanViewStyleguide(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/_design');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Design — reference');
        // A „Připravujeme" reference label renders on the page.
        self::assertSelectorTextContains('body', 'Připravujeme');
    }

    public function testStyleguideRendersBothSoutezSwitcherVariants(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/_design');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // The grouped picker: live group first, finished second, source name + Prague date range.
        self::assertStringContainsString('<optgroup label="Probíhající">', $body);
        self::assertStringContainsString('<optgroup label="Ukončené">', $body);
        self::assertLessThan(
            strpos($body, '<optgroup label="Ukončené">'),
            strpos($body, '<optgroup label="Probíhající">'),
        );
        self::assertStringContainsString('data-meta="2. 6. 2026 – 11. 6. 2026"', $body);
        self::assertStringContainsString('data-sub="MS ve fotbale 2026"', $body);

        // The no-JS affordance ships with the control.
        self::assertStringContainsString('<noscript>', $body);

        // Exactly one soutěž renders a static chip, not a second form.
        self::assertSame(1, substr_count($body, 'action="/_design"'));
        self::assertStringContainsString('Jediná soutěž', $body);
    }

    /**
     * Half A is the live gallery: every shared component renders through its REAL
     * tag, so a component that changes shape shows up here first.
     */
    public function testHalfARendersTheSharedComponents(): void
    {
        $body = $this->loadAsAdmin();

        // Two halves, „Sdílené komponenty" first.
        self::assertStringContainsString('Sdílené komponenty', $body);
        self::assertStringContainsString('Připravujeme / reference', $body);
        self::assertLessThan(
            strpos($body, 'Připravujeme / reference'),
            strpos($body, 'Sdílené komponenty'),
        );
        // …and half A is labelled „Hotovo", half B „Připravujeme".
        self::assertStringContainsString('pill pill-done', $body);

        // Competition:Card in BOTH contexts (organizer adds the progress bar +
        // „Spravovat", public offers the entry-fee join CTA). Money is a fee, never a pool.
        self::assertStringContainsString('id="soutez-01930000-0000-7000-8000-0000000000b1"', $body);
        self::assertStringContainsString('lb-acc-bar', $body);
        self::assertStringContainsString('Spravovat', $body);
        self::assertStringContainsString('Připojit se za', $body);
        self::assertStringContainsString('Vstupné', $body);

        // Competition:FilterBar (once) and Competition:PlayingCard.
        self::assertStringContainsString('Všechny sporty', $body);
        self::assertStringContainsString('Viditelnost', $body);
        self::assertStringContainsString('id="hraju-01930000-0000-7000-8000-0000000000c1"', $body);

        // Match:MatchRow in all six states (`finished` paints the `done` stripe).
        // Item 21 — ONE card design, so there is one gallery, not a variant per shape.
        // Item 25 — `missing` is the red „Chybí tip" state; `open` („Brzy") stays amber.
        foreach (['open', 'missing', 'tipped', 'live', 'locked', 'done'] as $state) {
            self::assertStringContainsString('class="tip-row is-dash '.$state.'"', $body);
        }
        self::assertStringNotContainsString('variant="dashboard"', $body);
        self::assertStringNotContainsString('variant="default"', $body);
        // A match can sit in several soutěže: then every strip renders under the name
        // of ITS soutěž (`.tip-row-boost-label` exists only for a list of 2+).
        self::assertSame(2, substr_count($body, 'tip-row-boost-label'));

        // Leaderboard:Podium + Leaderboard:Delta (both variants).
        self::assertStringContainsString('class="podium mb-8"', $body);
        self::assertStringContainsString('lb-delta-up', $body);
        self::assertStringContainsString('lb-delta-chip', $body);

        // Presentational vocabulary: Pill, Badge, Avatar, TeamFlag, StatCard, EmptyState, Breadcrumbs.
        self::assertStringContainsString('pill pill-live', $body);
        self::assertStringContainsString('badge badge-organizer', $body);
        self::assertStringContainsString('class="avatar rank-1"', $body);
        self::assertStringContainsString('flag-coin', $body);
        self::assertStringContainsString('aria-label="Breadcrumb"', $body);
    }

    /**
     * Match:TipStats renders as the strip INSIDE a match card (compact) and as the
     * full card, with the split visible and with BOTH paywalls. Premium and boosts
     * are separate sample competitions — one competition never has both.
     */
    public function testHalfARendersEveryTipStatsState(): void
    {
        $body = $this->loadAsAdmin();

        // Visible: the real bars (.dist-fill / .dist-bar), not the paywall decoration.
        self::assertStringContainsString('dist-fill', $body);
        self::assertStringContainsString('class="dist-bar"', $body);
        // Locked: the decorative ghost fill plus each paywall's own copy.
        self::assertStringContainsString('dist-ghost-fill', $body);
        self::assertStringContainsString('Odemknout za', $body);       // boosts, affordable
        self::assertStringContainsString('Chybí kredity', $body);      // boosts, broke
        self::assertStringContainsString('Zapíná organizátor', $body); // premium
        self::assertStringContainsString('Zobrazí se po odehrání', $body); // nothing to sell

        // One name for the feature — item 12 settled on „Rozložení tipů", item 23 then
        // renamed the surface after the booster that unlocks it. The gallery advertises
        // that ONE name; none of the three retired ones may survive anywhere on it.
        self::assertStringContainsString('Jak tipují ostatní?', $body);
        self::assertStringNotContainsString('Rozložení tipů', $body);
        self::assertStringNotContainsString('Lišta tipů', $body);
        self::assertStringNotContainsString('Distribuce tipů', $body);
    }

    /**
     * The inertness guarantee, asserted on the MARKUP: nothing on this page can act.
     * Half A renders production components full of real links, a GET form and the
     * boost-purchase POST form, so the template neutralises them (see the `inert()`
     * macro). The ONLY form left is the switcher's, and it targets /_design itself.
     */
    public function testNothingOnThePageCanAct(): void
    {
        $body = $this->loadAsAdmin();

        self::assertSame(1, substr_count($body, '<form'), 'Only the SoutezSwitcher may keep a form.');
        self::assertStringNotContainsString('method="post"', $body, 'No form on the page may POST — least of all a credit purchase.');
        self::assertSame(1, substr_count($body, 'type="submit"'), 'Only the switcher\'s <noscript> button may submit.');
        self::assertStringNotContainsString('wtips:open-premium', $body);

        // No anchor points at a sample UUID (those routes exist, the ids do not → 404).
        self::assertStringNotContainsString('href="/souteze/0193', $body);
        self::assertStringNotContainsString('href="/zapasy/0193', $body);
        self::assertStringContainsString('data-inert-link="', $body);

        // The gallery itself (everything before the switcher section) holds no live
        // link, no form and no Stimulus controller at all.
        $start = strpos($body, 'Sdílené komponenty');
        $end = strpos($body, 'Přepínač soutěže');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $gallery = substr($body, $start, $end - $start);
        self::assertStringNotContainsString('href="', $gallery);
        self::assertStringNotContainsString('<form', $gallery);
        self::assertStringNotContainsString('data-controller="', $gallery);
    }

    private function loadAsAdmin(): string
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/_design');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        return $body;
    }

    public function testVerifiedNonAdminUserReceivesForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);

        $client->request('GET', '/_design');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_design');

        self::assertResponseStatusCodeSame(302);
    }
}
