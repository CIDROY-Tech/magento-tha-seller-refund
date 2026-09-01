<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Block\Order\RefundDetails;
use Acme\SellerRefund\Model\Email\RefundConfirmationSender;
use Acme\SellerRefund\Model\Export\RefundSyncExporter;
use Acme\SellerRefund\Model\Pdf\RefundReceipt;
use Acme\SellerRefund\Model\Total\RefundFigures;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Acme\SellerRefund\Service\RefundProcessor;
use Acme\SellerRefund\Test\Integration\Framework\DbTransactionTestCase;
use Acme\SellerRefund\Test\Integration\Framework\SeedOrders;
use Acme\SellerRefund\ViewModel\OrderHistoryRefunds;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * On a pure-seller order every presentation and export surface derives its figures from the
 * calculator, so the receipt, the storefront order-details block, the order-history view
 * model, the downstream sync export and the confirmation email must agree to the last unit.
 */
class SurfacesAgreeOnPureSellerOrderTest extends DbTransactionTestCase
{
    public function testAllSurfacesAgreeOnRefundFigures(): void
    {
        $seed = $this->get(SeedOrders::class);
        $order = $seed->loadByIncrementId('SR-ORD-1001');
        $orderId = (int) $order->getEntityId();

        $refund = $this->get(RefundProcessor::class)->submit($orderId, $seed->fullSubmission($order));
        $refundId = (int) $refund->getEntityId();

        $repository = $this->get(RefundRepositoryInterface::class);
        $stored = $repository->getById($refundId);
        $fullOrder = $this->get(OrderRepositoryInterface::class)->get($orderId);

        // Baseline: the calculator's own figures for this snapshot.
        $expected = $this->get(RefundTotalCalculator::class)
            ->fromSnapshot($stored, $repository->getItems($refundId), $fullOrder)
            ->toArray()['refund'];

        // Surface 1: the reissued receipt.
        $receipt = $this->get(RefundReceipt::class)->buildData($stored);
        self::assertSame($expected, $receipt['refund'], 'Receipt figures diverge.');

        // Surface 2: the downstream sync export.
        $sync = $this->get(RefundSyncExporter::class)->export($stored);
        self::assertSame($expected, $sync['refund'], 'Sync export figures diverge.');

        // Surface 3: the storefront order-details block.
        $registry = $this->get(Registry::class);
        $registry->unregister('current_order');
        $registry->register('current_order', $fullOrder);
        $details = $this->create(RefundDetails::class);
        self::assertSame($expected, $this->figuresForRefund($details->getRefundViews(), $refundId));

        // Surface 4: the customer order-history view model.
        $this->get(CustomerSession::class)->setCustomerId((int) $order->getCustomerId());
        $historyFigures = $this->historyFiguresForRefund(
            $this->get(OrderHistoryRefunds::class)->getEntries(),
            (string) $order->getIncrementId(),
            $refundId
        );
        self::assertSame($expected, $historyFigures, 'Order-history figures diverge.');

        // Surface 5: the confirmation email's data (prepared vars, before rendering).
        self::assertSame($expected, $this->emailRefundTotals($stored), 'Email figures diverge.');
    }

    /**
     * @param array<int, array{refund: RefundInterface, figures: RefundFigures}> $views
     *
     * @return array<string, string>
     */
    private function figuresForRefund(array $views, int $refundId): array
    {
        foreach ($views as $view) {
            if ((int) $view['refund']->getEntityId() === $refundId) {
                return $view['figures']->toArray()['refund'];
            }
        }

        self::fail('The refund was not present among the order-details views.');
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     *
     * @return array<string, string>
     */
    private function historyFiguresForRefund(array $entries, string $incrementId, int $refundId): array
    {
        foreach ($entries as $entry) {
            if ((string) $entry['increment_id'] !== $incrementId) {
                continue;
            }
            foreach ($entry['refunds'] as $refundView) {
                if ((int) $refundView['refund']->getEntityId() === $refundId) {
                    /** @var RefundFigures $figures */
                    $figures = $refundView['figures'];

                    return $figures->toArray()['refund'];
                }
            }
        }

        self::fail('The refund was not present among the order-history entries.');
    }

    /**
     * @return array<string, string>
     */
    private function emailRefundTotals(RefundInterface $refund): array
    {
        $sender = $this->get(RefundConfirmationSender::class);
        $method = new \ReflectionMethod($sender, 'prepare');
        $method->setAccessible(true);
        /** @var array{0: OrderInterface, 1: array<string, mixed>, 2: int} $prepared */
        $prepared = $method->invoke($sender, $refund);

        return $prepared[1]['refund_totals'];
    }
}
