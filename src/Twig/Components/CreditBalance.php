<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The wallet balance chip in the top bar (item 17). It renders on every page of the
 * authenticated shell, so the wallet is read at most ONCE per render: Twig resolves a
 * hooked property twice per `{{ this.balance }}` and `expose_public_props` reads it once
 * more, which measured 3 wallet SELECTs per page BEFORE this item (one template access)
 * and would have been 5 with the chip's two. Memoizing is safe under FrankenPHP's worker
 * mode because Twig components are not shared services (`Shared: no`) — every render gets
 * a fresh instance, so the value can never leak from one request or user to the next.
 */
#[AsTwigComponent('CreditBalance')]
final class CreditBalance
{
    private ?int $resolvedBalance = null;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
    }

    public int $balance {
        get => $this->resolvedBalance ??= $this->resolveBalance();
    }

    private function resolveBalance(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return $this->queryBus->handle(new GetCreditWallet($user->id))->balance;
    }
}
