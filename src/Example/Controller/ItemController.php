<?php

declare(strict_types=1);

namespace App\Example\Controller;

use App\Example\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ItemController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/items', name: 'app_items')]
    public function __invoke(): Response
    {
        /** @var list<Item> $items */
        $items = $this->entityManager->getRepository(Item::class)->findAll();

        return $this->controller->render('example/index.html.twig', [
            'items' => $items,
        ]);
    }
}
