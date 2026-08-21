<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Console\Command;

use Acme\AssignmentSeed\Model\Seeder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seeds the deterministic synthetic assignment data. Safe to run repeatedly; pass --reset to
 * clear the previously seeded records before re-seeding.
 */
class AssignmentSeedCommand extends Command
{
    private const OPTION_RESET = 'reset';

    public function __construct(
        private readonly Seeder $seeder,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('assignment:seed');
        $this->setDescription('Seed the deterministic synthetic assignment data (products, orders, users, refunds).');
        $this->addOption(
            self::OPTION_RESET,
            null,
            InputOption::VALUE_NONE,
            'Remove the previously seeded records, then seed again.'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = (bool) $input->getOption(self::OPTION_RESET);

        try {
            if ($reset) {
                $output->writeln('<info>Resetting previously seeded records...</info>');
            }
            $summary = $this->seeder->run($reset);
        } catch (\Throwable $e) {
            $output->writeln('<error>Assignment seed failed: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $this->printSummary($output, $summary);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function printSummary(OutputInterface $output, array $summary): void
    {
        $output->writeln('<info>Assignment seed complete.</info>');
        $output->writeln(sprintf('  Seed version:      %s', (string) $summary['version']));
        $output->writeln(sprintf(
            '  Products:          %d present (%d created this run)',
            (int) $summary['products_total'],
            (int) $summary['products_created']
        ));
        $output->writeln(sprintf('  Customer login:    %s', (string) $summary['customer_email']));
        $output->writeln('  Admin usernames:   ' . implode(', ', $summary['admin_usernames']));
        $output->writeln('  Orders present:    ' . implode(', ', $summary['orders_present']));
        $output->writeln(
            '  Orders created:    ' .
            ($summary['orders_created'] === [] ? '(none; already present)' : implode(', ', $summary['orders_created']))
        );
        $output->writeln(
            '  Refunds created:   ' .
            ($summary['refunds_created'] === [] ? '(none; already present)' : implode(', ', $summary['refunds_created']))
        );
        $output->writeln(sprintf(
            '  Frontend theme:    %s',
            $summary['theme'] === null ? '(Acme/blank not registered; skipped)' : (string) $summary['theme']
        ));
    }
}
