<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Controller\Adminhtml\Refund;

use Acme\SellerRefund\Service\RefundProcessor;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Complete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::cash_register';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly RefundProcessor $processor
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $refundId = (int) $this->getRequest()->getParam('refund_id');
            $txnNumber = (string) $this->getRequest()->getParam('transaction_number');
            $txnDate = (string) $this->getRequest()->getParam('transaction_date');
            $actorId = $this->_auth->getUser() ? (int) $this->_auth->getUser()->getId() : null;
            $this->processor->registerCashRefund($refundId, $txnNumber, $txnDate, $actorId);

            return $result->setData(['ok' => true, 'message' => (string) __('Cash refund registered.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => (string) __('The cash refund could not be registered.')]);
        }
    }
}
