<?php

declare(strict_types=1);

namespace App\Twig\Components\Competition;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\CreateGlobalCompetition\CreateGlobalCompetitionCommand;
use App\Entity\Competition;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use App\Service\Competition\MatchScopeCatalog;
use App\Service\Competition\PinGenerator;
use App\Service\Competition\ShareableLinkTokenGenerator;
use App\Service\Credits\PricingConfig;
use App\Service\Scoring\RulePresetProvider;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The single 4-step „Vytvořit soutěž" wizard (S08). Hand-rolled LiveProps +
 * LiveActions (next/back/submit) keep the flow client-smooth; each step
 * validates before advancing. Submit composes ONE CreateCompetitionCommand.
 *
 * Step 1's „Zápasy soutěže" košík is {@see ComposesMatchScope} — the same basket
 * the organizer edits afterwards at „/souteze/{id}/zapasy" ({@see ScopeEditor}).
 *
 * Judgment calls (see .docs/features/create-wizard.md):
 * - Rule state is two writable arrays (`enabledRuleIds`, `rulePoints`) instead
 *   of a Symfony sub-form, so preset tiles + steppers stay instant client-side
 *   and tests can set them directly.
 * - PIN + shareable-link token are generated at mount and passed to the command
 *   so the previews shown in step 3 are the real values (WYSIWYG).
 */
#[AsLiveComponent(name: 'Competition:CreateWizard')]
final class CreateWizard extends AbstractController
{
    use ComposesMatchScope;
    use DefaultActionTrait;

    private const int FIRST_STEP = 1;

    #[LiveProp]
    public int $step = self::FIRST_STEP;

    #[LiveProp(writable: true)]
    public string $name = '';

    /** „Popis soutěže" (item 19) — optional, capped at {@see Competition::DESCRIPTION_MAX_LENGTH}. */
    #[LiveProp(writable: true)]
    public string $description = '';

    /**
     * Admin-only: build a GLOBAL (publicly discoverable) competition instead of a
     * private one. Flipped exclusively by the {@see useGlobalKind}/{@see usePrivateKind}
     * actions (both re-check ROLE_ADMIN) — never a writable prop — and {@see submit()}
     * re-denies non-admins as the final guard, so the mode is unforgeable client-side.
     */
    #[LiveProp]
    public bool $isGlobalKind = false;

    /** Global-only entry fee in credits (0 = free), burned once at join. */
    #[LiveProp(writable: true)]
    public int $entryFeeCredits = 0;

    #[LiveProp(writable: true)]
    public bool $withPin = false;

    #[LiveProp(writable: true)]
    public string $pin = '';

    #[LiveProp]
    public string $shareableLinkToken = '';

    #[LiveProp(writable: true)]
    public string $inviteEmailsRaw = '';

    /** @var list<string> enabled rule identifiers */
    #[LiveProp(writable: true)]
    public array $enabledRuleIds = [];

    /** @var array<string, int> rule identifier → points */
    #[LiveProp(writable: true)]
    public array $rulePoints = [];

    /**
     * Step 4 („Pozvete nás na pivo?"): Premium = „Férová soutěž", the recommended
     * and pre-selected default — the organizer decides for the whole group, so no
     * player can buy an individual edge. Boosts = „Volná volba Premium".
     * The global branch resets this to None in {@see useGlobalKind}.
     */
    #[LiveProp(writable: true)]
    public string $monetization = CompetitionMonetization::Premium->value;

    public function __construct(
        private readonly Security $security,
        private readonly MatchScopeCatalog $catalog,
        private readonly RulePresetProvider $rulePresetProvider,
        private readonly PinGenerator $pinGenerator,
        private readonly ShareableLinkTokenGenerator $linkTokenGenerator,
        private readonly QueryBus $queryBus,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function mount(?string $preselectedSourceId = null): void
    {
        $this->shareableLinkToken = $this->linkTokenGenerator->generate();
        $this->pin = $this->pinGenerator->generate();

        // Rule defaults — base rules enabled, optional rules off (per PHP rules).
        foreach ($this->rulePresetProvider->defaultPoints() as $identifier => $points) {
            $this->rulePoints[$identifier] = $points;
        }

        foreach ($this->rulePresetProvider->sections() as $section) {
            if ('base' === $section['category']) {
                foreach ($section['identifiers'] as $identifier) {
                    $this->enabledRuleIds[] = $identifier;
                }
            }
        }

        if (null !== $preselectedSourceId && isset($this->sourcesById[$preselectedSourceId])) {
            $this->sourceId = $preselectedSourceId;
        }
    }

    /** A global competition composes its scope from curated zdroje only. */
    public function isGlobalScope(): bool
    {
        return $this->isGlobalKind;
    }

    // ---- Read models for the template ------------------------------------

    /**
     * Sport driving period copy in step 2 — the from-scratch sport, or the
     * chosen source's sport.
     */
    public Sport $ruleSport {
        get => $this->fromScratch || null === $this->selectedSource
            ? $this->selectedSport
            : $this->selectedSource->sport;
    }

    /**
     * @var list<array{category: string, heading: string, identifiers: list<string>}>
     */
    public array $ruleSections {
        get => $this->rulePresetProvider->sections();
    }

    /** @var array<string, int> */
    public array $defaultPoints {
        get => $this->rulePresetProvider->defaultPoints();
    }

    /**
     * Per-rule DS copy — shared with {@see \App\Twig\Components\Scoring\RuleFields}
     * so the wizard and the post-creation rules screen never drift apart.
     *
     * @var array<string, array{label: string, sub: string}>
     */
    public array $ruleCopy {
        get => $this->rulePresetProvider->copy();
    }

    /** @var array<string, list<string>> */
    public array $rulePresets {
        get => $this->rulePresetProvider->presets();
    }

    public int $creditBalance {
        get => $this->queryBus->handle(new GetCreditWallet($this->currentUser()->id))->balance;
    }

    public int $premiumPerPlayer {
        get => PricingConfig::PREMIUM_PER_PLAYER;
    }

    /** @var list<array{label: string, price: int}> */
    public array $boostPrices {
        get => [
            ['label' => BoostType::TipDistribution->label(), 'price' => PricingConfig::BOOST_TIP_DISTRIBUTION],
            ['label' => BoostType::OthersTips->label(), 'price' => PricingConfig::BOOST_OTHERS_TIPS],
            ['label' => BoostType::TipChange->label(), 'price' => PricingConfig::BOOST_TIP_CHANGE],
        ];
    }

    /**
     * The ordered steps actually shown. A global competition skips „Pozvánky"
     * (step 3): PIN, shareable link and e-mail invites are all invalid for it
     * (joined only via the entry-fee flow). Numbering, progress and next/back
     * navigation all derive from this sequence.
     *
     * @var non-empty-list<int>
     */
    public array $stepSequence {
        get => $this->isGlobalKind ? [1, 2, 4] : [1, 2, 3, 4];
    }

    public int $stepCount {
        get => count($this->stepSequence);
    }

    /** 1-based position of the current step within {@see $stepSequence}. */
    public int $stepPosition {
        get {
            $index = array_search($this->step, $this->stepSequence, true);

            return false === $index ? 1 : $index + 1;
        }
    }

    public bool $isAdmin {
        get => $this->security->isGranted('ROLE_ADMIN');
    }

    public bool $isLastStep {
        get => $this->step === $this->stepSequence[array_key_last($this->stepSequence)];
    }

    // ---- Actions ---------------------------------------------------------

    #[LiveAction]
    public function next(): void
    {
        $this->errorMessage = null;

        if (!$this->validateStep($this->step)) {
            return;
        }

        $sequence = $this->stepSequence;
        $index = array_search($this->step, $sequence, true);

        if (false !== $index && $index < count($sequence) - 1) {
            $this->step = $sequence[$index + 1];
        }
    }

    #[LiveAction]
    public function back(): void
    {
        $this->errorMessage = null;

        $sequence = $this->stepSequence;
        $index = array_search($this->step, $sequence, true);

        if (false !== $index && $index > 0) {
            $this->step = $sequence[$index - 1];
        }
    }

    /**
     * Admin-only: switch the wizard into GLOBAL mode. Clears the choices a global
     * competition cannot carry (from-scratch source, subset selection) and resets
     * monetization to „none" — a public competition is free of paid features by
     * default; the admin may still pick premium/boosts. Re-checks ROLE_ADMIN so
     * the mode can never be entered by a non-admin.
     */
    #[LiveAction]
    public function useGlobalKind(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->isGlobalKind = true;
        $this->fromScratch = false;
        $this->selectionMode = 'all';
        $this->monetization = CompetitionMonetization::None->value;
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function usePrivateKind(): void
    {
        $this->isGlobalKind = false;
        $this->monetization = CompetitionMonetization::Premium->value;
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function regeneratePin(): void
    {
        $this->pin = $this->pinGenerator->generate();
    }

    #[LiveAction]
    public function submit(): ?Response
    {
        $this->errorMessage = null;

        // Defensive re-validation of every gated step actually shown.
        foreach ($this->stepSequence as $stepToValidate) {
            if (!$this->validateStep($stepToValidate)) {
                return null;
            }
        }

        $user = $this->currentUser();

        if ($this->isGlobalKind) {
            return $this->submitGlobal($user);
        }

        $specs = $this->layerSpecs;

        if ([] === $specs) {
            $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

            return null;
        }

        // The first layer travels in the command's own source fields, the rest
        // as specs — the command reads as „this soutěž, and also these zdroje".
        $first = $specs[0];
        $rest = array_slice($specs, 1);
        $ownMatchesFirst = null === $first->matchSourceId;
        $source = null;

        foreach ($specs as $spec) {
            if (null === $spec->matchSourceId) {
                continue;
            }

            // Every basketed zdroj is authorised, not just the headline one.
            $basketed = $this->catalog->matchSource($spec->matchSourceId);
            $this->denyAccessUnlessGranted(MatchSourceVoter::CREATE_COMPETITION, $basketed);

            if ($spec === $first) {
                $source = $basketed;
            }
        }

        try {
            $envelope = $this->commandBus->dispatch(new CreateCompetitionCommand(
                ownerId: $user->id,
                name: trim($this->name),
                description: $this->trimmedDescription(),
                matchSourceId: $source?->id,
                sportId: $ownMatchesFirst ? Uuid::fromString($this->sportId) : null,
                fromScratch: $ownMatchesFirst,
                withPin: $this->withPin,
                monetization: CompetitionMonetization::from($this->monetization),
                selectionMode: $first->selectionMode,
                includePlayoff: $first->includePlayoff,
                selectedMatchIds: $first->selectedMatchIds,
                filterTeamIds: $first->filterTeamIds,
                additionalSources: $rest,
                ruleChanges: $this->ruleChanges(),
                inviteEmails: '' === trim($this->inviteEmailsRaw) ? [] : [$this->inviteEmailsRaw],
                pin: $this->withPin ? $this->pin : null,
                shareableLinkToken: $this->shareableLinkToken,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return null;
        }

        $competition = $this->extractCompetition($envelope);

        if ($ownMatchesFirst) {
            $this->addFlash('success', 'Soutěž je připravená. Teď přidejte zápasy — ručně, nebo nahrajte celý rozpis.');

            return $this->redirectToRoute('match_source_detail', [
                'id' => $competition->headlineSource->id->toRfc4122(),
            ]);
        }

        $this->addFlash('success', 'Soutěž byla vytvořena. Pozvěte kamarády a můžete tipovat!');

        return $this->redirectToRoute('competition_detail', [
            'id' => $competition->id->toRfc4122(),
        ]);
    }

    // ---- Internals -------------------------------------------------------

    /**
     * Admin-only global-competition submit: reuses the battle-tested
     * {@see CreateGlobalCompetitionCommand} path (isGlobal, mode All over a curated
     * source, owner membership, rule config) rather than duplicating it into the
     * private handler. The wizard has already forced a curated source + „all"
     * selection and hidden the invites step, so only fee + monetization + rules
     * travel here.
     */
    private function submitGlobal(User $user): ?Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $first = $this->layerSpecs[0] ?? null;

        if (null === $first || null === $first->matchSourceId) {
            $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

            return null;
        }

        $source = $this->catalog->matchSource($first->matchSourceId);

        try {
            $envelope = $this->commandBus->dispatch(new CreateGlobalCompetitionCommand(
                adminId: $user->id,
                matchSourceId: $source->id,
                name: trim($this->name),
                entryFeeCredits: max(0, $this->entryFeeCredits),
                description: $this->trimmedDescription(),
                monetization: CompetitionMonetization::from($this->monetization),
                ruleChanges: $this->ruleChanges(),
                selectionMode: CompetitionMatchSelectionMode::Teams === $first->selectionMode
                    ? CompetitionMatchSelectionMode::Teams
                    : CompetitionMatchSelectionMode::All,
                filterTeamIds: $first->filterTeamIds,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return null;
        }

        $competition = $this->extractCompetition($envelope);

        $this->addFlash('success', 'Globální soutěž byla vytvořena. Je veřejně k nalezení a hráči se přidají za vstupné.');

        return $this->redirectToRoute('competition_detail', [
            'id' => $competition->id->toRfc4122(),
        ]);
    }

    /** Empty textarea ⇒ NULL in the database, never an empty string. */
    private function trimmedDescription(): ?string
    {
        $description = trim($this->description);

        return '' === $description ? null : $description;
    }

    private function validateStep(int $step): bool
    {
        return match ($step) {
            1 => $this->validateBasics(),
            default => true,
        };
    }

    private function validateBasics(): bool
    {
        if ('' === trim($this->name)) {
            $this->errorMessage = 'Zadejte prosím název soutěže.';

            return false;
        }

        if (mb_strlen(trim($this->description)) > Competition::DESCRIPTION_MAX_LENGTH) {
            $this->errorMessage = sprintf('Popis soutěže nesmí být delší než %d znaků.', Competition::DESCRIPTION_MAX_LENGTH);

            return false;
        }

        if ($this->isGlobalKind && $this->entryFeeCredits < 0) {
            $this->errorMessage = 'Vstupné nesmí být záporné.';

            return false;
        }

        if ($this->fromScratch && null === $this->catalog->findSport(Uuid::fromString($this->sportId))) {
            $this->errorMessage = 'Vyberte prosím sport.';

            return false;
        }

        // Leaving the step with the editor still open and usable commits it, so
        // the one-zdroj case never has to press „Přidat" before „Pokračovat".
        if (!$this->commitOpenDraft()) {
            return false;
        }

        if ([] === $this->layers) {
            $this->errorMessage = 'Vyberte zdroj zápasů, nebo zvolte „Vytvořit soutěž od začátku".';

            return false;
        }

        return true;
    }

    /**
     * @return array<string, array{enabled: bool, points: int}>
     */
    private function ruleChanges(): array
    {
        $changes = [];

        foreach ($this->defaultPoints as $identifier => $default) {
            $points = $this->rulePoints[$identifier] ?? $default;

            $changes[$identifier] = [
                'enabled' => in_array($identifier, $this->enabledRuleIds, true),
                'points' => max(0, (int) $points),
            ];
        }

        return $changes;
    }

    /** {@see ComposesMatchScope} dependency hook. */
    private function scopeCatalog(): MatchScopeCatalog
    {
        return $this->catalog;
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Pro vytvoření soutěže se musíte přihlásit.');
        }

        return $user;
    }

    private function extractCompetition(Envelope $envelope): Competition
    {
        $result = $envelope->last(HandledStamp::class)?->getResult();

        if (!$result instanceof Competition) {
            throw new \LogicException('Expected Competition to be returned by handler.');
        }

        return $result;
    }
}
