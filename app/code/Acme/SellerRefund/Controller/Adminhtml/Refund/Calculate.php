<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Controller\Adminhtml\Refund;

use Acme\SellerRefund\Model\ResourceModel\PriorRefundQuantity;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Calculate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PriorRefundQuantity $priorRefundQuantity,
        private readonly RefundTotalCalculator $calculator,
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $orderId = (int) $this->getRequest()->getParam('order_id');
            $qtyByItem = [];
            foreach ((array) $this->getRequest()->getParam('items', []) as $itemId => $qty) {
                if ($qty === '' || $qty === null) {
                    continue;
                }
                $qtyByItem[(int) $itemId] = (string) $qty;
            }
            $order = $this->orderRepository->get($orderId);
            $prior = $this->priorRefundQuantity->sumRefundedByOrderItem($orderId);
            $figures = $this->calculator->fromSelection($order, $qtyByItem, $prior, $this->timezone->date());

            return $result->setData(['ok' => true, 'figures' => $figures->toArray()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
