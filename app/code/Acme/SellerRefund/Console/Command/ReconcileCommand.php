<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Console\Command;

use Acme\SellerRefund\Model\Reconciler;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReconcileCommand extends Command
{
    private const OPT_BATCH = 'batch';

    public function __construct(
        private readonly State $state,
        private readonly Reconciler $reconciler,
        private readonly TimezoneInterface $timezone,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('acme:refund:reconcile')
            ->setDescription('Enqueue Read Refund Status for settled cash refunds.')
            ->addOption(self::OPT_BATCH, 'b', InputOption::VALUE_REQUIRED, 'Maximum refunds to sweep.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batch = (int) $input->getOption(self::OPT_BATCH);

        $enqueued = $this->state->emulateAreaCode(
            Area::AREA_CRONTAB,
            fn (): int => $this->reconciler->run($this->timezone->date(), $batch)
        );

        $output->writeln(sprintf('<info>Enqueued %d status check(s).</info>', $enqueued));

        return Command::SUCCESS;
    }
}
