<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Plugin\Adminhtml;

use Acme\SellerRefund\Model\RefundEligibility;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderView;

/**
 * Adds a "Create Seller Refund" button to the admin sales order view when the operator holds
 * the create privilege and the order is eligible for a refund.
 */
class OrderViewRefundButton
{
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly RefundEligibility $eligibility,
        private readonly Registry $registry,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @param OrderView $subject
     * @param OrderView $result
     * @return OrderView
     */
    public function afterSetLayout(OrderView $subject, $result)
    {
        if (!$this->authorization->isAllowed('Acme_SellerRefund::create')) {
            return $result;
        }

        $order = $this->registry->registry('sales_order');
        if (!$order instanceof OrderInterface) {
            return $result;
        }

        if (!$this->eligibility->check($order, $this->timezone->date())->isEligible()) {
            return $result;
        }

        $url = $subject->getUrl('acme_refund/refund/edit', ['order_id' => $order->getEntityId()]);
        $subject->addButton(
            'acme_seller_refund',
            [
                'label' => __('Create Seller Refund'),
                'onclick' => "setLocation('" . $url . "')",
                'class' => 'action-secondary',
            ]
        );

        return $result;
    }
}
