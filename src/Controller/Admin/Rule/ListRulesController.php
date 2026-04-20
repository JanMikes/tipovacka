<?php

declare(strict_types=1);

namespace App\Controller\Admin\Rule;

use App\Query\ListRegisteredRules\ListRegisteredRules;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pravidla', name: 'admin_rule_list', methods: ['GET'])]
final class ListRulesController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $rules = $this->queryBus->handle(new ListRegisteredRules());

        return $this->render('admin/rule/list.html.twig', [
            'rules' => $rules,
        ]);
    }
}
