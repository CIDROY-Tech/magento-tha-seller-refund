<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Block\Adminhtml\Refund;

use Acme\SellerRefund\Model\ResourceModel\PriorRefundQuantity;
use Acme\SellerRefund\Model\SellerLineResolver;
use Acme\SellerRefund\Model\Tax\TaxCodeResolver;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Refund entry screen backing block. Exposes the seller order lines with their remaining
 * refundable quantity and the JS configuration the entry widget needs.
 */
class Form extends Template
{
    private ?OrderInterface $order = null;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SellerLineResolver $sellerLineResolver,
        private readonly PriorRefundQuantity $priorRefundQuantity,
        private readonly TaxCodeResolver $taxCodeResolver,
        private readonly FormKey $formKeyProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrderId(): int
    {
        return (int) $this->getRequest()->getParam('order_id');
    }

    public function getOrder(): OrderInterface
    {
        if ($this->order === null) {
            $this->order = $this->orderRepository->get($this->getOrderId());
        }

        return $this->order;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function getSellerLines(): array
    {
        $order = $this->getOrder();
        $prior = $this->priorRefundQuantity->sumRefundedByOrderItem($this->getOrderId());

        $lines = [];
        foreach ($this->sellerLineResolver->sellerLines($order) as $orderItemId => $item) {
            $ordered = sprintf('%.4F', (float) $item->getQtyOrdered());
            $refundedBefore = sprintf('%.4F', (float) ($prior[$orderItemId] ?? '0'));
            $remaining = bcsub($ordered, $refundedBefore, 4);
            $code = $this->taxCodeResolver->toBusinessCode((int) $item->getData('mp_tax_class'));

            $lines[] = [
                'order_item_id' => $orderItemId,
                'sku' => (string) $item->getSku(),
                'product_name' => (string) $item->getName(),
                'qty_ordered' => $ordered,
                'qty_refunded_before' => $refundedBefore,
                'qty_remaining' => $remaining,
                'unit_price' => sprintf('%.4F', (float) $item->getPrice()),
                'tax_code' => $code,
                'tax_rate' => $this->taxCodeResolver->rateFor($code),
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    public function getJsConfig(): array
    {
        $order = $this->getOrder();
        $currency = (string) $order->getOrderCurrencyCode();

        return [
            'calculateUrl' => $this->getUrl('acme_refund/refund/calculate'),
            'saveUrl' => $this->getUrl('acme_refund/refund/save'),
            'formKey' => $this->formKeyProvider->getFormKey(),
            'orderId' => (string) $this->getOrderId(),
            'refundNoPlaceholder' => 'SR-' . date('Ymd') . '-______',
            'currency' => $currency !== '' ? $currency : 'JPY',
        ];
    }

    public function formatMoney(?string $amount, string $currency = 'JPY'): string
    {
        return $currency . ' ' . number_format((float) $amount, 0, '.', ',');
    }
}
