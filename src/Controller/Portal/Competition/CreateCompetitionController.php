<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Form\CompetitionFormData;
use App\Form\CompetitionFormType;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/souteze/nova', name: 'portal_competition_create')]
final class CreateCompetitionController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $availableSources = $this->availableSources($user);
        $sourcesById = [];

        foreach ($availableSources as $source) {
            $sourcesById[$source->id->toRfc4122()] = $source;
        }

        $preselectedSourceId = $request->query->get('zdroj');
        $initialSourceId = \is_string($preselectedSourceId) && isset($sourcesById[$preselectedSourceId])
            ? $preselectedSourceId
            : null;

        $formData = new CompetitionFormData();
        $formData->matchSourceId = $initialSourceId;

        if (!$request->isMethod('POST')) {
            // Source switch reloads the page (competition_matches_controller.js)
            // and carries the user's inputs as query params — restore them.
            $this->prefillFromQuery($formData, $request);
        }

        $form = $this->createForm(CompetitionFormType::class, $formData, [
            'with_source_selection' => true,
            'available_sources' => $availableSources,
            'initial_source_id' => $initialSourceId,
            'match_choices_provider' => fn (string $sourceId): array => isset($sourcesById[$sourceId])
                ? $this->buildMatchChoices($sourcesById[$sourceId])
                : [],
            'match_choice_attr_provider' => fn (string $sourceId): array => isset($sourcesById[$sourceId])
                ? $this->buildMatchChoiceAttr($sourcesById[$sourceId])
                : [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matchSource = null !== $formData->matchSourceId
                ? ($sourcesById[$formData->matchSourceId] ?? null)
                : null;

            if (null === $matchSource) {
                $form->get('matchSourceId')->addError(new FormError('Vyberte prosím zdroj zápasů.'));
            } elseif (CompetitionMatchSelectionMode::Subset === $formData->selectionMode && 0 === count($formData->selectedMatchIds)) {
                $form->get('selectedMatchIds')->addError(new FormError('Vyberte prosím alespoň jeden zápas.'));
            } else {
                $this->denyAccessUnlessGranted(MatchSourceVoter::CREATE_COMPETITION, $matchSource);

                $envelope = $this->commandBus->dispatch(new CreateCompetitionCommand(
                    ownerId: $user->id,
                    matchSourceId: $matchSource->id,
                    name: $formData->name,
                    description: $formData->description ?: null,
                    withPin: $formData->withPin,
                    hideOthersTipsBeforeDeadline: $formData->hideOthersTipsBeforeDeadline,
                    tipsDeadline: $formData->tipsDeadline,
                    selectionMode: $formData->selectionMode,
                    includePlayoff: CompetitionMatchSelectionMode::All === $formData->selectionMode
                        ? $formData->includePlayoff
                        : true,
                    selectedMatchIds: CompetitionMatchSelectionMode::Subset === $formData->selectionMode
                        ? array_map(static fn (string $id): Uuid => Uuid::fromString($id), $formData->selectedMatchIds)
                        : [],
                ));

                $competition = $this->extractCompetition($envelope);

                $this->addFlash('success', 'Soutěž byla vytvořena.');

                return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
            }
        }

        return $this->render('portal/competition/create.html.twig', [
            'form' => $form,
            'available_sources' => $availableSources,
        ]);
    }

    /**
     * Restores the user's typed-but-unsubmitted inputs from query params after
     * the source-change reload. Invalid values are silently ignored.
     */
    private function prefillFromQuery(CompetitionFormData $formData, Request $request): void
    {
        $name = $request->query->get('name');

        if (\is_string($name) && '' !== trim($name)) {
            $formData->name = trim($name);
        }

        $description = $request->query->get('description');

        if (\is_string($description) && '' !== trim($description)) {
            $formData->description = trim($description);
        }

        $selectionMode = $request->query->get('selectionMode');

        if (\is_string($selectionMode)) {
            $formData->selectionMode = CompetitionMatchSelectionMode::tryFrom($selectionMode) ?? $formData->selectionMode;
        }

        $formData->includePlayoff = $this->queryBool($request, 'includePlayoff', $formData->includePlayoff);
        $formData->hideOthersTipsBeforeDeadline = $this->queryBool($request, 'hideOthersTipsBeforeDeadline', $formData->hideOthersTipsBeforeDeadline);
        $formData->withPin = $this->queryBool($request, 'withPin', $formData->withPin);

        $tipsDeadline = $request->query->get('tipsDeadline');

        if (\is_string($tipsDeadline) && '' !== trim($tipsDeadline)) {
            // Same format + timezone the form field uses (view Europe/Prague, model UTC).
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($tipsDeadline), new \DateTimeZone('Europe/Prague'));

            if (false !== $parsed) {
                $formData->tipsDeadline = $parsed->setTimezone(new \DateTimeZone('UTC'));
            }
        }
    }

    private function queryBool(Request $request, string $key, bool $default): bool
    {
        return match ($request->query->get($key)) {
            '1' => true,
            '0' => false,
            default => $default,
        };
    }

    /**
     * Curated sources plus the user's own private sources — the same set the
     * S08 wizard will offer.
     *
     * @return list<MatchSource>
     */
    private function availableSources(User $user): array
    {
        $sources = $this->matchSourceRepository->findActiveCurated();

        foreach ($this->matchSourceRepository->findPrivateByOwner($user->id) as $private) {
            if ($private->isActive) {
                $sources[] = $private;
            }
        }

        return array_values($sources);
    }

    /**
     * @return array<string, string> label => sport match UUID (grouped order — group by group)
     */
    private function buildMatchChoices(MatchSource $source): array
    {
        $choices = [];

        foreach ($this->groupedSelectableMatches($source) as $matches) {
            foreach ($matches as $match) {
                $label = sprintf(
                    '%s – %s (%s)',
                    $match->homeTeam,
                    $match->awayTeam,
                    $match->kickoffAt->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i'),
                );

                while (isset($choices[$label])) {
                    $label .= ' ';
                }

                $choices[$label] = $match->id->toRfc4122();
            }
        }

        return $choices;
    }

    /**
     * @return array<string, array<string, string>> sport match UUID => checkbox attributes
     */
    private function buildMatchChoiceAttr(MatchSource $source): array
    {
        $attr = [];

        foreach ($this->groupedSelectableMatches($source) as $group => $matches) {
            foreach ($matches as $match) {
                $attr[$match->id->toRfc4122()] = [
                    'data-group' => (string) $group,
                    'data-playoff' => $match->isPlayoff ? '1' : '0',
                ];
            }
        }

        return $attr;
    }

    /**
     * Matches grouped by round (fallback: kickoff date), groups in first-kickoff
     * order, matches kickoff-ordered within each group.
     *
     * @return array<string, list<SportMatch>>
     */
    private function groupedSelectableMatches(MatchSource $source): array
    {
        $selectable = array_values(array_filter(
            $this->sportMatchRepository->listByMatchSource($source->id),
            static fn (SportMatch $match): bool => !$match->isCancelled,
        ));

        $groups = [];

        foreach ($selectable as $match) {
            $group = $match->round ?? $match->kickoffAt->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y');
            $groups[$group][] = $match;
        }

        return $groups;
    }

    private function extractCompetition(Envelope $envelope): Competition
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Competition) {
            throw new \LogicException('Expected Competition to be returned by handler.');
        }

        return $result;
    }
}
