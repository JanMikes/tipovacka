<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use App\Rule\ExactScoreRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * A step of the create wizard that cannot advance says why AT the input that
 * stops it — the input carries aria-invalid, so form-feedback can take the
 * organizer straight there — and never two steps later.
 */
final class CreateWizardFeedbackTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testTheWizardIsAFormWhoseNavButtonsSubmitIt(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)->render();

        self::assertMatchesRegularExpression('#<form[^>]+data-controller="form-feedback"#', $html);
        self::assertMatchesRegularExpression('#<button type="submit"[^>]+data-action="live\#action:prevent" data-live-action-param="next"#', $html);
    }

    public function testEveryStepOneProblemIsFlaggedAtItsOwnInputAtOnce(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)
            ->call('next')
            ->render();

        self::assertStringContainsString('Krok 1 ze 4', $html);
        self::assertMatchesRegularExpression('#id="wizard-name"[^>]+aria-invalid="true" aria-describedby="wizard-name-error"#', $html);
        self::assertMatchesRegularExpression('#id="wizard-source"[^>]+aria-invalid="true" aria-describedby="wizard-source-error"#', $html);
        self::assertStringContainsString('id="wizard-name-error"', $html);
        self::assertStringContainsString('Zadejte prosím název soutěže', $html);
        self::assertStringContainsString('nebo zaškrtněte „Vytvořit soutěž od začátku"', html_entity_decode($html, \ENT_QUOTES));
    }

    public function testAnEmptyTeamFilterIsExplainedNextToThePicker(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)
            ->set('name', 'Jen týmy')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'teams')
            ->call('next')
            ->render();

        self::assertStringContainsString('Krok 1 ze 4', $html);
        self::assertStringContainsString('id="wizard-teams-error"', $html);
        // tom-select hides the real <select>, so the message is the scroll target.
        self::assertMatchesRegularExpression('#data-form-feedback-target="summary"[^>]*>\s*<ul id="wizard-teams-error"#', $html);
    }

    public function testMalformedInviteAddressesKeepTheOrganizerOnTheInvitationsStepAndAreNamed(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)
            ->set('name', 'Špatné adresy')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->set('inviteEmailsRaw', "hrac-jedna@example.com\nhrac-bez-zavinace.example.com, hrac@@example.com")
            ->call('next')
            ->render();

        self::assertStringContainsString('Pozvěte hráče', $html);
        self::assertMatchesRegularExpression('#id="wizard-emails"[^>]+aria-invalid="true"#', $html);
        self::assertStringContainsString('hrac-bez-zavinace.example.com', $html);
        self::assertStringContainsString('hrac@@example.com', $html);
        self::assertStringNotContainsString('nejsou platné e-maily: hrac-jedna@example.com', $html);
        self::assertNull($this->competitionNamed($client, 'Špatné adresy'));
    }

    public function testSubmittingWithAStepOneProblemBringsStepOneBack(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)
            ->set('name', 'Zmizí')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->call('next')
            ->set('name', '   ')
            ->call('submit')
            ->render();

        self::assertStringContainsString('Krok 1 ze 4', $html);
        self::assertMatchesRegularExpression('#id="wizard-name"[^>]+aria-invalid="true"#', $html);
    }

    public function testOutOfRangeRulePointsAreRefusedAtTheirInput(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client));

        $html = (string) $this->createLiveComponent('Competition:CreateWizard', [], $client)
            ->set('name', 'Moc bodů')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->set('rulePoints', [ExactScoreRule::IDENTIFIER => 5000])
            ->call('next')
            ->render();

        self::assertStringContainsString('Krok 2 ze 4', $html);
        self::assertStringContainsString('Zadejte celé číslo od 0 do 1000.', $html);
        self::assertMatchesRegularExpression('#data-rule="'.ExactScoreRule::IDENTIFIER.'"[^>]+aria-invalid="true"#', $html);
    }

    private function user(KernelBrowser $client): User
    {
        $user = $this->em($client)->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        return $user;
    }

    private function competitionNamed(KernelBrowser $client, string $name): ?Competition
    {
        $this->em($client)->clear();

        /** @var Competition|null $competition */
        $competition = $this->em($client)->createQueryBuilder()
            ->select('c')->from(Competition::class, 'c')
            ->where('c.name = :name')->setParameter('name', $name)
            ->getQuery()->getOneOrNullResult();

        return $competition;
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
