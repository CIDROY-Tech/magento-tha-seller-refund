<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Console\Command;

use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Export\RefundSyncExporter;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncExportCommand extends Command
{
    private const ARG_REFUND_NO = 'refund_no';

    public function __construct(
        private readonly State $state,
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly RefundSyncExporter $exporter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('acme:refund:sync-export')
            ->setDescription('Print the downstream core-system sync export for one refund.')
            ->addArgument(self::ARG_REFUND_NO, InputArgument::REQUIRED, 'Refund number (SR-YYYYMMDD-NNNNNN).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $refundNo = (string) $input->getArgument(self::ARG_REFUND_NO);

        $payload = $this->state->emulateAreaCode(
            Area::AREA_ADMINHTML,
            function () use ($refundNo, $output): ?array {
                $refund = $this->refundRepository->getByRefundNo($refundNo);
                if ($refund === null) {
                    $output->writeln(sprintf('<error>No refund found for "%s".</error>', $refundNo));

                    return null;
                }

                return $this->exporter->export($refund);
            }
        );

        if ($payload === null) {
            return Command::FAILURE;
        }

        $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
