<?php

declare(strict_types=1);

namespace App\Example\Command;

use App\Example\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-example',
    description: 'Seed example items for demonstration',
)]
final class SeedExampleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();

        $items = [
            new Item('First Item', 'This is the first example item.', $now),
            new Item('Second Item', 'Another example with a description.', $now),
            new Item('Third Item', null, $now),
        ];

        foreach ($items as $item) {
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Seeded %d example items.', count($items)));

        return Command::SUCCESS;
    }
}
