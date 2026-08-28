<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Block\Order;

use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Storefront order-details refund section. Shows the original pre-refund total and a distinct
 * refund section beneath it, all derived from the stored refund snapshot through the
 * calculator so the figures match every other surface.
 */
class RefundDetails extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundTotalCalculator $calculator,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof OrderInterface ? $order : null;
    }

    /**
     * @return array<int, array{refund: \Acme\SellerRefund\Api\Data\RefundInterface, figures: \Acme\SellerRefund\Model\Total\RefundFigures}>
     */
    public function getRefundViews(): array
    {
        $order = $this->getOrder();
        if ($order === null) {
            return [];
        }

        $views = [];
        foreach ($this->refundRepository->getByOrderId((int) $order->getEntityId()) as $refund) {
            $items = $this->refundRepository->getItems((int) $refund->getEntityId());
            $views[] = [
                'refund' => $refund,
                'figures' => $this->calculator->fromSnapshot($refund, $items, $order),
            ];
        }

        return $views;
    }

    public function formatMoney(?string $amount, string $currency): string
    {
        return $currency . ' ' . number_format((float) $amount, 0, '.', ',');
    }

    public function renderRefundDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->_localeDate->formatDate($value, \IntlDateFormatter::MEDIUM, false);
    }
}
