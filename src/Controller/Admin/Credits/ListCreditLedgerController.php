<?php

declare(strict_types=1);

namespace App\Controller\Admin\Credits;

use App\Enum\CreditTransactionType;
use App\Query\ListAllCreditTransactions\ListAllCreditTransactions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-wide credit ledger: every debit/refund/top-up across all wallets, with
 * the S03/S10 transaction types, filterable by type and by competition. Doubles
 * as premium/boost state visibility (filter a competition to see its charges).
 */
#[Route('/admin/kredity/transakce', name: 'admin_credit_ledger', methods: ['GET'])]
final class ListCreditLedgerController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $typeParam = $request->query->get('type');
        $type = is_string($typeParam) ? CreditTransactionType::tryFrom($typeParam) : null;

        $competitionParam = $request->query->get('competition');
        $competitionId = null;
        if (is_string($competitionParam) && '' !== $competitionParam && Uuid::isValid($competitionParam)) {
            $competitionId = Uuid::fromString($competitionParam);
        }

        $ledger = $this->queryBus->handle(new ListAllCreditTransactions(
            type: $type,
            competitionId: $competitionId,
        ));

        return $this->render('admin/credits/ledger.html.twig', [
            'ledger' => $ledger,
            'types' => CreditTransactionType::cases(),
            'selectedType' => $type,
            'selectedCompetitionId' => $competitionId,
        ]);
    }
}
