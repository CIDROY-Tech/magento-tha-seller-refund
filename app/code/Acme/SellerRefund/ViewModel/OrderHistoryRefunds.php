<?php

declare(strict_types=1);

namespace Acme\SellerRefund\ViewModel;

use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Supplies refund markers for the customer order history. A refunded order stays in the
 * history list; this surface adds, per refunded order, which lines were refunded and the
 * refund total, derived from the stored snapshot through the calculator.
 */
class OrderHistoryRefunds implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundTotalCalculator $calculator
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEntries(): array
    {
        $customerId = $this->customerSession->getCustomerId();
        if ($customerId === null || $customerId === '') {
            return [];
        }

        $orders = $this->orderCollectionFactory->create()
            ->addFieldToFilter('customer_id', (int) $customerId);

        $entries = [];
        foreach ($orders as $order) {
            $orderId = (int) $order->getEntityId();
            $refunds = $this->refundRepository->getByOrderId($orderId);
            if ($refunds === []) {
                continue;
            }

            $fullOrder = $this->orderRepository->get($orderId);
            $refundViews = [];
            foreach ($refunds as $refund) {
                $items = $this->refundRepository->getItems((int) $refund->getEntityId());
                $skus = [];
                foreach ($items as $item) {
                    $skus[] = $item->getSku();
                }
                $refundViews[] = [
                    'refund' => $refund,
                    'figures' => $this->calculator->fromSnapshot($refund, $items, $fullOrder),
                    'skus' => $skus,
                ];
            }

            $entries[] = [
                'increment_id' => (string) $order->getIncrementId(),
                'refunds' => $refundViews,
            ];
        }

        return $entries;
    }

    public function formatMoney(?string $amount, string $currency): string
    {
        return $currency . ' ' . number_format((float) $amount, 0, '.', ',');
    }
}
