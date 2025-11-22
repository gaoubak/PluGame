<?php

namespace App\Command;

use App\Service\RatingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-ratings',
    description: 'Recalculate average ratings for all creators based on their reviews',
)]
class RecalculateRatingsCommand extends Command
{
    public function __construct(
        private readonly RatingService $ratingService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Recalculating Creator Ratings');
        $io->text('This will update avgRating and ratingsCount for all creators based on their reviews...');

        $stats = $this->ratingService->recalculateAllRatings();

        $io->success('Ratings recalculated successfully!');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Creators', $stats['total_creators']],
                ['With Ratings', $stats['updated']],
                ['Without Ratings', $stats['no_ratings']],
            ]
        );

        return Command::SUCCESS;
    }
}
