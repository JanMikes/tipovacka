<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Query\ListMyCompetitions\CompetitionListItem;
use App\Value\CompetitionSwitcherOption;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The soutěž (Competition) switcher — a grouped tom-select picker.
 *
 * Class-based because the control normalises its feed into one option shape, groups
 * it („Probíhající" first, „Ukončené" second) and formats the zdroj-zápasů date range
 * in Europe/Prague. See .docs/features/competition-switcher.md.
 *
 * Navigation is a plain GET form: `route` is submitted to and `param` is the QUERY
 * parameter carrying the chosen id — never a path placeholder, otherwise the no-JS
 * form could not build the URL. Targets that scope by path (the soutěž leaderboard)
 * are reached through a resolver route that redirects (`leaderboard` + `?soutez=`).
 */
#[AsTwigComponent(name: 'SoutezSwitcher')]
final class SoutezSwitcher
{
    public const string GROUP_LIVE = 'Probíhající';
    public const string GROUP_FINISHED = 'Ukončené';

    /**
     * The viewer's own soutěže straight from `ListMyCompetitions` — or, for any other
     * feed (the logged-out variant lists the public global competitions), the rows
     * already mapped to {@see CompetitionSwitcherOption} by the calling controller.
     * One component, two feeds; the second one only owes us five scalars.
     *
     * @var list<CompetitionListItem>|list<CompetitionSwitcherOption>
     */
    public array $competitions = [];

    /** RFC4122 id of the active soutěž; unknown/foreign ids fall back to the first option. */
    public ?string $currentId = null;

    /**
     * Route the GET form submits to. It must be reachable without a path parameter
     * carrying the COMPETITION — a plain GET form can only append a query string.
     */
    public string $route;

    /**
     * Path parameters the route needs that are NOT the competition — e.g. the match
     * id on `/zapasy/{id}`, which stays the same across every option. The competition
     * itself never goes here; it is always the query parameter below.
     *
     * @var array<string, string>
     */
    public array $routeParams = [];

    /** Query parameter carrying the chosen competition id. */
    public string $param = 'soutez';

    /** Eyebrow label above the control. */
    public string $label = 'Soutěž';

    /** DOM id of the <select> — override when a page renders more than one switcher. */
    public string $id = 'soutez-switcher';

    /** @var list<CompetitionSwitcherOption>|null */
    private ?array $normalized = null;

    /** @var list<CompetitionSwitcherOption> */
    public array $options {
        get => $this->normalized ??= $this->normalize();
    }

    /**
     * The selected option: the one matching `currentId`, else the first one. Falling
     * back (rather than 404-ing on a foreign id) is deliberate leak prevention — the
     * viewer only ever sees soutěže that are in their own list.
     */
    public ?CompetitionSwitcherOption $current {
        get => $this->findCurrent() ?? ($this->options[0] ?? null);
    }

    /**
     * Non-empty groups in render order — live first, finished second. The order is
     * locked in the dropdown too (`lockOptgroupOrder` in tom_select_controller.js).
     *
     * @var list<array{label: string, options: list<CompetitionSwitcherOption>}>
     */
    public array $groups {
        get {
            $live = [];
            $finished = [];

            foreach ($this->options as $option) {
                if ($option->isFinished) {
                    $finished[] = $option;
                } else {
                    $live[] = $option;
                }
            }

            $groups = [];

            if ([] !== $live) {
                $groups[] = ['label' => self::GROUP_LIVE, 'options' => $live];
            }

            if ([] !== $finished) {
                $groups[] = ['label' => self::GROUP_FINISHED, 'options' => $finished];
            }

            return $groups;
        }
    }

    /**
     * @return list<CompetitionSwitcherOption>
     */
    private function normalize(): array
    {
        $options = [];

        foreach ($this->competitions as $competition) {
            $options[] = $competition instanceof CompetitionListItem
                ? CompetitionSwitcherOption::fromDates(
                    id: $competition->competitionId->toRfc4122(),
                    name: $competition->competitionName,
                    subtitle: $competition->matchSourceName,
                    startAt: $competition->matchSourceStartAt,
                    endAt: $competition->matchSourceEndAt,
                    isFinished: $competition->matchSourceIsCompleted,
                )
                : $competition;
        }

        return $options;
    }

    private function findCurrent(): ?CompetitionSwitcherOption
    {
        foreach ($this->options as $option) {
            if ($option->id === $this->currentId) {
                return $option;
            }
        }

        return null;
    }
}
