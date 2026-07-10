<?php

declare(strict_types=1);

namespace App\Controller\Admin\Credits;

use App\Query\ListCreditPurchases\ListCreditPurchases;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/kredity', name: 'admin_credit_purchases', methods: ['GET'])]
final class ListCreditPurchasesController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('admin/credits/purchases.html.twig', [
            'purchases' => $this->queryBus->handle(new ListCreditPurchases()),
        ]);
    }
}
