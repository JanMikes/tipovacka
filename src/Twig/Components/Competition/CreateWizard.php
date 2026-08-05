<?php

declare(strict_types=1);

namespace App\Twig\Components\Competition;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\CreateGlobalCompetition\CreateGlobalCompetitionCommand;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Repository\SportRepository;
use App\Repository\TeamRepository;
use App\Service\Competition\PinGenerator;
use App\Service\Competition\ScopeDraftResolver;
use App\Service\Competition\ShareableLinkTokenGenerator;
use App\Service\Credits\PricingConfig;
use App\Service\Scoring\RulePresetProvider;
use App\Value\CompetitionSourceSpec;
use App\Value\ScopeDraft;
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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The single 4-step „Vytvořit soutěž" wizard (S08). Hand-rolled LiveProps +
 * LiveActions (next/back/submit) keep the flow client-smooth; each step
 * validates before advancing. Submit composes ONE CreateCompetitionCommand.
 *
 * Judgment calls (see .docs/features/create-wizard.md):
 * - Match checklist is LiveProp-driven, NOT a data-live-ignore island: it must
 *   re-render when the source changes. Selection travels through the writable
 *   array LiveProp `selectedMatchIds` (multi-checkbox, `norender` so ticking a
 *   box never round-trips); the live text filter is pure client-side and
 *   survives because ticking does not re-render.
 * - Rule state is two writable arrays (`enabledRuleIds`, `rulePoints`) instead
 *   of a Symfony sub-form, so preset tiles + steppers stay instant client-side
 *   and tests can set them directly.
 * - PIN + shareable-link token are generated at mount and passed to the command
 *   so the previews shown in step 3 are the real values (WYSIWYG).
 */
#[AsLiveComponent(name: 'Competition:CreateWizard')]
final class CreateWizard extends AbstractController
{
    use DefaultActionTrait;

    private const int FIRST_STEP = 1;

    #[LiveProp]
    public int $step = self::FIRST_STEP;

    #[LiveProp(writable: true)]
    public string $name = '';

    /** „Popis soutěže" (item 19) — optional, capped at {@see Competition::DESCRIPTION_MAX_LENGTH}. */
    #[LiveProp(writable: true)]
    public string $description = '';

    #[LiveProp(writable: true)]
    public bool $fromScratch = false;

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
    public string $sportId = Sport::FOOTBALL_ID;

    /**
     * Committed scope layers, in display order — the „košík" of zdroje the
     * soutěž draws from. Plain arrays so the whole basket survives the
     * LiveComponent round trip; {@see layerSpecs} turns them into
     * {@see CompetitionSourceSpec} at submit time.
     *
     * An empty `sourceId` means „Moje zápasy" — the competition's own private
     * zdroj, created on demand.
     *
     * @var list<array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>}>
     */
    #[LiveProp(writable: true)]
    public array $layers = [];

    /**
     * Whether the „add a zdroj" editor is open. It is the whole step while the
     * basket is empty, so a single-source soutěž — still the overwhelmingly
     * common one — is composed exactly as it was before the basket existed.
     */
    #[LiveProp(writable: true)]
    public bool $draftOpen = true;

    /** Index of the layer being re-edited, or null while adding a new one. */
    #[LiveProp]
    public ?int $editingIndex = null;

    #[LiveProp(writable: true)]
    public string $sourceId = '';

    #[LiveProp(writable: true)]
    public string $selectionMode = 'all';

    #[LiveProp(writable: true)]
    public bool $includePlayoff = true;

    /** @var list<string> selected sport-match UUIDs (subset mode) */
    #[LiveProp(writable: true)]
    public array $selectedMatchIds = [];

    /** Comma-joined filter team UUIDs (teams mode); synced from the tom-select island. */
    #[LiveProp(writable: true)]
    public string $selectedTeamIdsCsv = '';

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

    #[LiveProp]
    public ?string $errorMessage = null;

    public function __construct(
        private readonly Security $security,
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly SportRepository $sportRepository,
        private readonly TeamRepository $teamRepository,
        private readonly RulePresetProvider $rulePresetProvider,
        private readonly PinGenerator $pinGenerator,
        private readonly ShareableLinkTokenGenerator $linkTokenGenerator,
        private readonly ScopeDraftResolver $scopeDraftResolver,
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

    // ---- Read models for the template ------------------------------------

    /**
     * The basket rows: one card per committed layer, with the copy describing
     * what it contributes. „Moje zápasy" is a layer like any other — the user
     * never meets the word „zdroj" for it.
     *
     * @var list<array{index: int, name: string, sportName: string, scope: string, isOwnMatches: bool, matchCount: int}>
     */
    public array $layerCards {
        get {
            $counts = $this->layerMatchCounts;
            $cards = [];

            foreach ($this->layers as $index => $layer) {
                $source = '' === $layer['sourceId'] ? null : ($this->allSourcesById[$layer['sourceId']] ?? null);

                $cards[] = [
                    'index' => $index,
                    'name' => null === $source ? 'Moje zápasy' : $source->name,
                    'sportName' => null === $source ? $this->selectedSport->name : $source->sport->name,
                    'scope' => $this->scopeLabel($layer),
                    'isOwnMatches' => null === $source,
                    'matchCount' => $counts[$index] ?? 0,
                ];
            }

            return $cards;
        }
    }

    /**
     * How many matches each committed layer contributes on its own — resolved
     * one layer at a time so a card can say „306 zápasů" without the user
     * having to guess what „Všechny zápasy" means in practice.
     *
     * @var array<int, int>
     */
    public array $layerMatchCounts {
        get {
            $counts = [];

            foreach ($this->layers as $index => $layer) {
                $counts[$index] = $this->scopeDraftResolver->resolve([$this->specFor($layer)])->matchCount;
            }

            return $counts;
        }
    }

    /**
     * The whole basket resolved: the fixture list the soutěž would start with,
     * its span, and any fixture taken twice from different zdroje.
     */
    public ScopeDraft $scopeDraft {
        get => $this->scopeDraftResolver->resolve($this->layerSpecs);
    }

    /** @var list<CompetitionSourceSpec> */
    public array $layerSpecs {
        get {
            $specs = [];

            foreach ($this->layers as $layer) {
                $specs[] = $this->specFor($layer);
            }

            return $specs;
        }
    }

    public bool $hasLayers {
        get => [] !== $this->layers;
    }

    /**
     * The sport every further zdroj must match, once the first layer fixed it.
     * Null while the basket is empty. Rules are configured once per soutěž and
     * phrased in the sport's own words, so a mixed scope has no coherent
     * ruleset — see .docs/DOMAIN.md §Core model.
     */
    public ?Sport $lockedSport {
        get {
            $first = $this->layers[0] ?? null;

            if (null === $first) {
                return null;
            }

            if ('' === $first['sourceId']) {
                return $this->selectedSport;
            }

            return ($this->allSourcesById[$first['sourceId']] ?? null)?->sport;
        }
    }


    /**
     * Curated sources plus the user's own private sources — the set the wizard
     * offers as „Zdroj zápasů".
     *
     * @var list<MatchSource>
     */
    public array $availableSources {
        get {
            $sources = $this->matchSourceRepository->findActiveCurated();

            // A global competition must sit on a curated source — never a private
            // one — so in global mode the user's own private sources are dropped.
            if (!$this->isGlobalKind) {
                $user = $this->currentUser();

                foreach ($this->matchSourceRepository->findPrivateByOwner($user->id) as $private) {
                    if ($private->isActive) {
                        $sources[] = $private;
                    }
                }
            }

            $locked = $this->lockedSport;
            $taken = [];

            foreach ($this->layers as $index => $layer) {
                if ('' !== $layer['sourceId'] && $index !== $this->editingIndex) {
                    $taken[$layer['sourceId']] = true;
                }
            }

            $sources = array_filter(
                $sources,
                // One sport per soutěž, and a zdroj already in the basket is not
                // offered again — adding it twice would mean the same union.
                static fn (MatchSource $source): bool => (null === $locked || $source->sport->id->equals($locked->id))
                    && !isset($taken[$source->id->toRfc4122()]),
            );

            return array_values($sources);
        }
    }

    /**
     * Every zdroj the user may reference, INCLUDING those already basketed —
     * `availableSources` hides those, but the basket still has to render their
     * names. Keyed by UUID.
     *
     * @var array<string, MatchSource>
     */
    public array $allSourcesById {
        get {
            $sources = $this->matchSourceRepository->findActiveCurated();

            if (!$this->isGlobalKind) {
                foreach ($this->matchSourceRepository->findPrivateByOwner($this->currentUser()->id) as $private) {
                    if ($private->isActive) {
                        $sources[] = $private;
                    }
                }
            }

            $byId = [];

            foreach ($sources as $source) {
                $byId[$source->id->toRfc4122()] = $source;
            }

            return $byId;
        }
    }

    /** @var array<string, MatchSource> */
    public array $sourcesById {
        get {
            $byId = [];

            foreach ($this->availableSources as $source) {
                $byId[$source->id->toRfc4122()] = $source;
            }

            return $byId;
        }
    }

    public ?MatchSource $selectedSource {
        get => '' !== $this->sourceId ? ($this->sourcesById[$this->sourceId] ?? null) : null;
    }

    public bool $isSubset {
        get => 'subset' === $this->selectionMode;
    }

    public bool $isTeams {
        get => 'teams' === $this->selectionMode;
    }

    /**
     * Teams that play in the chosen source — the pool the team-filter picker
     * offers and the wizard validates the selection against.
     *
     * @var list<\App\Entity\Team>
     */
    public array $sourceTeams {
        get {
            $source = $this->selectedSource;

            return null === $source ? [] : $this->teamRepository->listTeamsInSource($source->id);
        }
    }

    /**
     * The currently-picked filter teams as {id, name}. Rendered as <option selected>
     * so the tom-select chips survive step navigation (they reappear from the DOM,
     * not from bare ids). Stale ids from a previously chosen source are dropped.
     *
     * @var list<array{id: string, name: string}>
     */
    public array $filterTeamOptions {
        get {
            if ('' === $this->selectedTeamIdsCsv) {
                return [];
            }

            $selected = array_flip($this->parseCsvIds($this->selectedTeamIdsCsv));
            $options = [];

            foreach ($this->sourceTeams as $team) {
                $id = $team->id->toRfc4122();

                if (isset($selected[$id])) {
                    $options[] = ['id' => $id, 'name' => $team->name];
                }
            }

            return $options;
        }
    }

    /**
     * Matches of the chosen source grouped by round (fallback: kickoff date,
     * Prague), groups in first-kickoff order, matches kickoff-ordered within.
     *
     * @var array<string, list<SportMatch>>
     */
    public array $groupedMatches {
        get {
            $source = $this->selectedSource;

            if (null === $source) {
                return [];
            }

            $selectable = array_values(array_filter(
                $this->sportMatchRepository->listByMatchSource($source->id),
                static fn (SportMatch $match): bool => !$match->isCancelled,
            ));

            $groups = [];

            foreach ($selectable as $match) {
                $group = $match->round ?? $match->kickoffAt
                    ->setTimezone(new \DateTimeZone('Europe/Prague'))
                    ->format('j. n. Y');
                $groups[$group][] = $match;
            }

            return $groups;
        }
    }

    /** @var list<Sport> */
    public array $availableSports {
        get => $this->sportRepository->listAll();
    }

    public Sport $selectedSport {
        get => $this->sportRepository->get(Uuid::fromString($this->sportId));
    }

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

    /**
     * Commits the draft editor into the basket. This is the ONE place a layer
     * is added, so the sport lock and the „already in the basket" rule are
     * enforced once rather than at every entry point.
     */
    #[LiveAction]
    public function addLayer(): void
    {
        $this->errorMessage = null;

        $layer = $this->draftLayer();

        if (null === $layer) {
            return;
        }

        if (null !== $this->editingIndex && isset($this->layers[$this->editingIndex])) {
            $this->layers[$this->editingIndex] = $layer;
        } else {
            $this->layers[] = $layer;
        }

        $this->resetDraft();
        // Composing more than one zdroj is the exception, so the editor closes
        // and the basket becomes the step. „Přidat zdroj" reopens it.
        $this->draftOpen = false;
    }

    #[LiveAction]
    public function editLayer(#[LiveArg] int $index): void
    {
        if (!isset($this->layers[$index])) {
            return;
        }

        $layer = $this->layers[$index];

        $this->errorMessage = null;
        $this->editingIndex = $index;
        $this->sourceId = $layer['sourceId'];
        $this->fromScratch = '' === $layer['sourceId'];
        $this->selectionMode = $layer['mode'];
        $this->includePlayoff = $layer['includePlayoff'];
        $this->selectedMatchIds = $layer['matchIds'];
        $this->selectedTeamIdsCsv = implode(',', $layer['teamIds']);
        $this->draftOpen = true;
    }

    #[LiveAction]
    public function removeLayer(#[LiveArg] int $index): void
    {
        if (!isset($this->layers[$index])) {
            return;
        }

        $remaining = $this->layers;
        unset($remaining[$index]);
        $this->layers = array_values($remaining);
        $this->errorMessage = null;

        if ([] === $this->layers) {
            $this->draftOpen = true;
        }
    }

    /** Opens the editor for a NEW zdroj (as opposed to re-editing one). */
    #[LiveAction]
    public function startLayer(): void
    {
        $this->errorMessage = null;
        $this->resetDraft();
        $this->draftOpen = true;
    }

    /**
     * Adds „Moje zápasy" — the competition's own private zdroj. The user enters
     * its matches after the soutěž exists, so it needs no editor.
     */
    #[LiveAction]
    public function addOwnMatchesLayer(): void
    {
        $this->errorMessage = null;

        foreach ($this->layers as $layer) {
            if ('' === $layer['sourceId']) {
                return;
            }
        }

        $this->layers[] = [
            'sourceId' => '',
            'mode' => 'all',
            'includePlayoff' => true,
            'matchIds' => [],
            'teamIds' => [],
        ];
        $this->resetDraft();
        $this->draftOpen = false;
    }

    #[LiveAction]
    public function cancelLayer(): void
    {
        $this->errorMessage = null;
        $this->resetDraft();
        $this->draftOpen = [] === $this->layers;
    }

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
            $basketed = $this->matchSourceRepository->get($spec->matchSourceId);
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

        $source = $this->matchSourceRepository->get($first->matchSourceId);

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

        if ($this->fromScratch && null === $this->sportRepository->find(Uuid::fromString($this->sportId))) {
            $this->errorMessage = 'Vyberte prosím sport.';

            return false;
        }

        // Leaving the step with the editor still open and usable commits it, so
        // the one-zdroj case never has to press „Přidat" before „Pokračovat".
        if ($this->draftOpen && ($this->fromScratch || null !== $this->selectedSource)) {
            $this->addLayer();

            if (null !== $this->errorMessage) {
                return false;
            }
        }

        if ([] === $this->layers) {
            $this->errorMessage = 'Vyberte zdroj zápasů, nebo zvolte „Vytvořit soutěž od začátku".';

            return false;
        }

        return true;
    }

    /**
     * Selected match UUIDs, intersected with the chosen source's matches so a
     * stale selection left over from a previously chosen source is dropped.
     *
     * @return list<Uuid>
     */
    private function selectedMatchUuids(): array
    {
        if ($this->fromScratch || !$this->isSubset) {
            return [];
        }

        $validIds = [];

        foreach ($this->groupedMatches as $matches) {
            foreach ($matches as $match) {
                $validIds[$match->id->toRfc4122()] = true;
            }
        }

        $result = [];

        foreach ($this->selectedMatchIds as $id) {
            if (isset($validIds[$id])) {
                $result[] = Uuid::fromString($id);
            }
        }

        return $result;
    }

    /**
     * Filter team UUIDs, intersected with the chosen source's teams so a stale
     * selection left over from a previously chosen source is dropped.
     *
     * @return list<Uuid>
     */
    private function filterTeamUuids(): array
    {
        if (!$this->isTeams) {
            return [];
        }

        $validIds = [];

        foreach ($this->sourceTeams as $team) {
            $validIds[$team->id->toRfc4122()] = true;
        }

        $result = [];

        foreach ($this->parseCsvIds($this->selectedTeamIdsCsv) as $id) {
            if (isset($validIds[$id])) {
                $result[] = Uuid::fromString($id);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function parseCsvIds(string $csv): array
    {
        $ids = [];

        foreach (explode(',', $csv) as $raw) {
            $raw = trim($raw);

            if ('' !== $raw && Uuid::isValid($raw)) {
                $ids[] = $raw;
            }
        }

        return $ids;
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

    // ---- Basket helpers ---------------------------------------------------

    /**
     * The draft editor's current state as a basket layer, or null (with
     * `$errorMessage` set) when it does not describe a usable one.
     *
     * @return array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>}|null
     */
    private function draftLayer(): ?array
    {
        if ($this->fromScratch) {
            return [
                'sourceId' => '',
                'mode' => 'all',
                'includePlayoff' => true,
                'matchIds' => [],
                'teamIds' => [],
            ];
        }

        $source = $this->selectedSource;

        if (null === $source) {
            $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

            return null;
        }

        $locked = $this->lockedSport;

        if (null !== $locked && !$source->sport->id->equals($locked->id) && 0 !== $this->editingIndex) {
            $this->errorMessage = sprintf(
                'Soutěž může kombinovat jen zdroje stejného sportu — už jste vybrali %s.',
                mb_strtolower($locked->name),
            );

            return null;
        }

        $matchIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $this->selectedMatchUuids());
        $teamIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $this->filterTeamUuids());

        if ($this->isSubset && [] === $matchIds) {
            $this->errorMessage = 'Vyberte prosím alespoň jeden zápas.';

            return null;
        }

        if ($this->isTeams && [] === $teamIds) {
            $this->errorMessage = 'Vyberte prosím alespoň jeden tým.';

            return null;
        }

        return [
            'sourceId' => $source->id->toRfc4122(),
            'mode' => $this->selectionMode,
            'includePlayoff' => $this->includePlayoff,
            'matchIds' => $matchIds,
            'teamIds' => $teamIds,
        ];
    }

    private function resetDraft(): void
    {
        $this->editingIndex = null;
        $this->sourceId = '';
        $this->fromScratch = false;
        $this->selectionMode = 'all';
        $this->includePlayoff = true;
        $this->selectedMatchIds = [];
        $this->selectedTeamIdsCsv = '';
    }

    /**
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function specFor(array $layer): CompetitionSourceSpec
    {
        return new CompetitionSourceSpec(
            matchSourceId: '' === $layer['sourceId'] ? null : Uuid::fromString($layer['sourceId']),
            selectionMode: CompetitionMatchSelectionMode::from($layer['mode']),
            includePlayoff: $layer['includePlayoff'],
            selectedMatchIds: array_map(static fn (string $id): Uuid => Uuid::fromString($id), $layer['matchIds']),
            filterTeamIds: array_map(static fn (string $id): Uuid => Uuid::fromString($id), $layer['teamIds']),
        );
    }

    /**
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function scopeLabel(array $layer): string
    {
        if ('' === $layer['sourceId']) {
            return 'Zápasy, které si sami zadáte';
        }

        return match ($layer['mode']) {
            'subset' => sprintf('Vybrané zápasy (%d)', count($layer['matchIds'])),
            'teams' => sprintf('Jen zápasy vybraných týmů (%d)', count($layer['teamIds'])),
            default => $layer['includePlayoff'] ? 'Všechny zápasy' : 'Všechny zápasy kromě playoff',
        };
    }
}
