<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('CreditBalance')]
final class CreditBalance
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
    }

    public int $balance {
        get {
            $user = $this->security->getUser();

            if (!$user instanceof User) {
                return 0;
            }

            return $this->queryBus->handle(new GetCreditWallet($user->id))->balance;
        }
    }
}
