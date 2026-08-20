<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Console\Command;

use Acme\SellerRefund\Model\Outbox\Dispatcher;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OutboxWorkCommand extends Command
{
    private const OPT_OPERATION = 'operation';
    private const OPT_LIMIT = 'limit';

    public function __construct(
        private readonly State $state,
        private readonly Dispatcher $dispatcher,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('acme:refund:outbox:work')
            ->setDescription('Process pending seller-refund outbox jobs.')
            ->addOption(self::OPT_OPERATION, 'o', InputOption::VALUE_REQUIRED, 'Limit to one operation: create, status_check, confirm.')
            ->addOption(self::OPT_LIMIT, 'l', InputOption::VALUE_REQUIRED, 'Maximum jobs per operation.', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $operation = $input->getOption(self::OPT_OPERATION);
        $limit = (int) $input->getOption(self::OPT_LIMIT);

        $processed = $this->state->emulateAreaCode(
            Area::AREA_CRONTAB,
            fn (): int => $this->dispatcher->run($operation !== null && $operation !== '' ? (string) $operation : null, $limit)
        );

        $output->writeln(sprintf('<info>Processed %d refund outbox job(s).</info>', $processed));

        return Command::SUCCESS;
    }
}
