<?php

declare(strict_types=1);

namespace App\Twig\Components\Competition;

use App\Command\UpdateCompetitionScope\UpdateCompetitionScopeCommand;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Service\Competition\MatchScopeCatalog;
use App\Service\Competition\OwnMatchesSource;
use App\Voter\CompetitionVoter;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * „Zápasy soutěže" for a soutěž that already exists — the create wizard's step 1,
 * available every day after. Same basket, same editor ({@see ComposesMatchScope},
 * same two templates); the difference is only what happens on save: instead of
 * composing a new aggregate it dispatches {@see UpdateCompetitionScopeCommand},
 * which reconciles the layers that are already there.
 *
 * Private competitions only — a global one's scope belongs to the admin area
 * ({@see CompetitionVoter::EDIT} plus the handler's own guard).
 *
 * Nothing here touches a zdroj's matches: a curated rozpis is shared by the whole
 * app and a private one may be shared with a second soutěž, so the competition
 * only ever changes ITS OWN layers, selections and team filters. Editing the
 * matches themselves is a separate affordance on the page, offered strictly for
 * the zdroj {@see OwnMatchesSource} certifies as this soutěž's own.
 */
#[AsLiveComponent(name: 'Competition:ScopeEditor')]
final class ScopeEditor extends AbstractController
{
    use ComposesMatchScope;
    use DefaultActionTrait;

    #[LiveProp]
    public Competition $competition;

    private ?MatchSource $ownMatchSourceMemo = null;

    public function __construct(
        private readonly Security $security,
        private readonly MatchScopeCatalog $catalog,
        private readonly OwnMatchesSource $ownMatchesSource,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Loads the persisted layers into the basket. The soutěž's own zdroj is
     * carried with the basket's empty-`sourceId` marker, exactly as the wizard
     * carries a zdroj that does not exist yet — so the card reads „Vlastní
     * zápasy" and the command resolves it back to the same zdroj.
     */
    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
        $this->sportId = $competition->headlineSource->sport->id->toRfc4122();

        $ownLayer = $this->ownMatchesSource->layerOf($competition);
        $this->ownMatchesSourceId = $ownLayer?->matchSource->id->toRfc4122();

        $selectionsByLayer = $this->selectionRepository->selectedMatchIdsByLayer($competition->id);
        $teamsByLayer = $this->teamFilterRepository->teamIdsByLayer($competition->id);

        foreach ($competition->sources as $layer) {
            $layerId = $layer->id->toRfc4122();
            $isOwn = null !== $ownLayer && $layer->id->equals($ownLayer->id);

            $this->layers[] = [
                'sourceId' => $isOwn ? '' : $layer->matchSource->id->toRfc4122(),
                'mode' => $layer->selectionMode->value,
                'includePlayoff' => $layer->includePlayoff,
                'matchIds' => array_keys($selectionsByLayer[$layerId] ?? []),
                'teamIds' => array_keys($teamsByLayer[$layerId] ?? []),
            ];
        }

        // An existing soutěž always has at least one zdroj, so the basket — not
        // the editor — is the screen. „Přidat zdroj zápasů" opens the editor.
        $this->draftOpen = [] === $this->layers;
    }

    /**
     * „Vlastní zápasy" is in the basket but has no zdroj behind it yet — the
     * organizer added the card and has not saved. There is nowhere to put a match
     * until they do, and the panel says exactly that instead of offering a button
     * that cannot work.
     */
    public bool $ownMatchesAwaitsSave {
        get => $this->hasOwnMatchesLayer && null === $this->ownMatchesSourceId;
    }

    /**
     * The zdroj behind „Vlastní zápasy", for the match panel — the ONE zdroj this
     * screen may hand out edit affordances for. Memoized: the panel reads it three
     * times per render and the lookup is a query, not an identity-map hit.
     */
    public ?MatchSource $ownMatchSource {
        get => $this->ownMatchSourceMemo ??= (null === $this->ownMatchesSourceId
            ? null
            : $this->catalog->matchSource(Uuid::fromString($this->ownMatchesSourceId)));
    }

    /**
     * Its rozpis, kickoff-ordered.
     *
     * @var list<SportMatch>
     */
    public array $ownMatches {
        get {
            $source = $this->ownMatchSource;

            return null === $source ? [] : $this->catalog->matchesIn($source);
        }
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $this->errorMessage = null;
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $this->competition);

        // An editor left open with a usable zdroj counts as „add it" — nobody has
        // to press „Přidat" before „Uložit".
        if (!$this->commitOpenDraft()) {
            return null;
        }

        if ([] === $this->layers) {
            $this->errorMessage = 'Soutěž musí mít aspoň jeden zdroj zápasů.';

            return null;
        }

        foreach ($this->layerSpecs as $spec) {
            // Only a zdroj JOINING the soutěž is authorised: one that is already
            // in it may have finished since, and „poslední zápas" must not lock
            // the organizer out of their own scope screen.
            if (null === $spec->matchSourceId || null !== $this->competition->sourceFor($spec->matchSourceId)) {
                continue;
            }

            $this->denyAccessUnlessGranted(
                MatchSourceVoter::CREATE_COMPETITION,
                $this->catalog->matchSource($spec->matchSourceId),
            );
        }

        try {
            $this->commandBus->dispatch(new UpdateCompetitionScopeCommand(
                editorId: $this->currentUser()->id,
                competitionId: $this->competition->id,
                layers: $this->layerSpecs,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return null;
        }

        $this->addFlash('success', 'Zápasy soutěže byly uloženy.');

        // A full round trip, not a re-render: the saved layers are what the
        // basket must now show (a freshly created „Vlastní zápasy" zdroj gains an
        // id, and its match panel appears with it).
        return $this->redirectToRoute('competition_scope', ['id' => $this->competition->id->toRfc4122()]);
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
            throw $this->createAccessDeniedException('Pro úpravu soutěže se musíte přihlásit.');
        }

        return $user;
    }
}
