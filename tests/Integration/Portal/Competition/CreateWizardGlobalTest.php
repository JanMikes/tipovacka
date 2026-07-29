<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The create-competition wizard's admin-only GLOBAL branch: an admin flips the
 * „Typ soutěže" toggle and the same wizard stands up a global (publicly
 * discoverable, entry-fee) competition over a curated source — while a non-admin
 * never sees the option and cannot reach the mode.
 */
final class CreateWizardGlobalTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testAdminCreatesGlobalCompetitionThroughTheWizard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::ADMIN_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $response = $component
            ->call('useGlobalKind')
            ->set('name', 'Globální liga')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('entryFeeCredits', 50)
            ->set('monetization', 'boosts')
            ->call('next')   // základy → pravidla
            ->call('next')   // pravidla → podpora (skips „Pozvánky")
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Globální liga');
        self::assertSame('/souteze/'.$competition->id->toRfc4122(), $response->headers->get('Location'));

        self::assertTrue($competition->isGlobal);
        self::assertSame(50, $competition->entryFeeCredits);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertSame(CompetitionMatchSelectionMode::All, $competition->selectionMode);
        self::assertSame(MatchSourceKind::Curated, $competition->matchSource->kind);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_ID, $competition->matchSource->id->toRfc4122());
        self::assertSame(AppFixtures::ADMIN_ID, $competition->owner->id->toRfc4122());

        // No fee-free back door: global competitions carry no PIN / shareable link.
        self::assertNull($competition->pin);
        self::assertNull($competition->shareableLinkToken);

        // Only the admin's owner membership exists at creation.
        self::assertCount(1, $this->memberships($client, $competition->id));
    }

    public function testGlobalModeDefaultsMonetizationToNone(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::ADMIN_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        // Free public competition: never touch the monetization step — the toggle
        // must have defaulted it to „none".
        $response = $component
            ->call('useGlobalKind')
            ->set('name', 'Zdarma veřejná')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Zdarma veřejná');
        self::assertTrue($competition->isGlobal);
        self::assertSame(0, $competition->entryFeeCredits);
        self::assertSame(CompetitionMonetization::None, $competition->monetization);
    }

    public function testGlobalWizardShowsThreeStepsAndSkipsInvites(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::ADMIN_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        // Step 1 in global mode → „Krok 1 ze 3".
        $step1 = (string) $component->call('useGlobalKind')->render();
        self::assertStringContainsString('Krok 1 ze 3', $step1);
        self::assertStringContainsString('Vstupné (kredity)', $step1);

        // Advance past rules — the next step must be „Podpora" (monetization), not
        // „Pozvánky", and it is the LAST step (3 of 3).
        $step3 = (string) $component
            ->set('name', 'Kroky')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')   // → pravidla
            ->call('next')   // → podpora (skips invites)
            ->render();

        self::assertStringContainsString('Krok 3 ze 3', $step3);
        self::assertStringContainsString('Monetizace soutěže', $step3);
        self::assertStringNotContainsString('Pozvěte hráče', $step3);
    }

    public function testNonAdminNeverSeesTheGlobalOption(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)->render();

        self::assertStringContainsString('Krok 1 ze 4', $html);
        self::assertStringNotContainsString('Typ soutěže', $html);
        self::assertStringNotContainsString('Globální soutěž', $html);
    }

    public function testNonAdminCannotSwitchToGlobalMode(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent('Competition:CreateWizard', [], $client)->call('useGlobalKind');
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
