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

class Cancel extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::cancel';

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
            $actorId = $this->_auth->getUser() ? (int) $this->_auth->getUser()->getId() : null;
            $this->processor->cancel($refundId, $actorId);

            return $result->setData(['ok' => true, 'message' => (string) __('Refund cancelled.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => (string) __('The refund could not be cancelled.')]);
        }
    }
}
