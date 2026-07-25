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
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Repository\SportRepository;
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

    #[LiveProp(writable: true)]
    public string $sourceId = '';

    #[LiveProp(writable: true)]
    public string $selectionMode = 'all';

    #[LiveProp(writable: true)]
    public bool $includePlayoff = true;

    /** @var list<string> selected sport-match UUIDs (subset mode) */
    #[LiveProp(writable: true)]
    public array $selectedMatchIds = [];

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

    #[LiveProp(writable: true)]
    public string $monetization = CompetitionMonetization::Boosts->value;

    #[LiveProp]
    public ?string $errorMessage = null;

    public function __construct(
        private readonly Security $security,
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly SportRepository $sportRepository,
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

    // ---- Read models for the template ------------------------------------

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

            return array_values($sources);
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
            ['label' => 'Lišta tipů ostatních', 'price' => PricingConfig::BOOST_TIP_DISTRIBUTION],
            ['label' => 'Konkrétní tipy kolegů', 'price' => PricingConfig::BOOST_OTHERS_TIPS],
            ['label' => 'Měnit tip během turnaje', 'price' => PricingConfig::BOOST_TIP_CHANGE],
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
        $this->monetization = CompetitionMonetization::Boosts->value;
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

        $source = $this->fromScratch ? null : $this->selectedSource;

        if (!$this->fromScratch) {
            if (null === $source) {
                $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

                return null;
            }

            $this->denyAccessUnlessGranted(MatchSourceVoter::CREATE_COMPETITION, $source);
        }

        try {
            $envelope = $this->commandBus->dispatch(new CreateCompetitionCommand(
                ownerId: $user->id,
                name: trim($this->name),
                matchSourceId: $source?->id,
                sportId: $this->fromScratch ? Uuid::fromString($this->sportId) : null,
                fromScratch: $this->fromScratch,
                withPin: $this->withPin,
                monetization: CompetitionMonetization::from($this->monetization),
                selectionMode: $this->isSubset ? CompetitionMatchSelectionMode::Subset : CompetitionMatchSelectionMode::All,
                includePlayoff: $this->includePlayoff,
                selectedMatchIds: $this->selectedMatchUuids(),
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

        if ($this->fromScratch) {
            $this->addFlash('success', 'Soutěž je připravená. Teď přidejte zápasy — ručně, nebo nahrajte celý rozpis.');

            return $this->redirectToRoute('portal_match_source_detail', [
                'id' => $competition->matchSource->id->toRfc4122(),
            ]);
        }

        $this->addFlash('success', 'Soutěž byla vytvořena. Pozvěte kamarády a můžete tipovat!');

        return $this->redirectToRoute('portal_competition_detail', [
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

        $source = $this->selectedSource;

        if (null === $source) {
            $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

            return null;
        }

        try {
            $envelope = $this->commandBus->dispatch(new CreateGlobalCompetitionCommand(
                adminId: $user->id,
                matchSourceId: $source->id,
                name: trim($this->name),
                entryFeeCredits: max(0, $this->entryFeeCredits),
                monetization: CompetitionMonetization::from($this->monetization),
                ruleChanges: $this->ruleChanges(),
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return null;
        }

        $competition = $this->extractCompetition($envelope);

        $this->addFlash('success', 'Globální soutěž byla vytvořena. Je veřejně k nalezení a hráči se přidají za vstupné.');

        return $this->redirectToRoute('portal_competition_detail', [
            'id' => $competition->id->toRfc4122(),
        ]);
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

        if ($this->isGlobalKind && $this->entryFeeCredits < 0) {
            $this->errorMessage = 'Vstupné nesmí být záporné.';

            return false;
        }

        if ($this->fromScratch) {
            if (null === $this->sportRepository->find(Uuid::fromString($this->sportId))) {
                $this->errorMessage = 'Vyberte prosím sport.';

                return false;
            }

            return true;
        }

        if (null === $this->selectedSource) {
            $this->errorMessage = 'Vyberte zdroj zápasů, nebo zvolte „Vytvořit soutěž od začátku".';

            return false;
        }

        if ($this->isSubset && [] === $this->selectedMatchUuids()) {
            $this->errorMessage = 'Vyberte prosím alespoň jeden zápas.';

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
}
