<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Controller\Adminhtml\Refund;

use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Outbox\Outbox;
use Acme\SellerRefund\Model\Submission\RefundSubmission;
use Acme\SellerRefund\Model\Outbox\Dispatcher;
use Acme\SellerRefund\Service\RefundProcessor;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly RefundProcessor $processor,
        private readonly Dispatcher $dispatcher,
        private readonly RefundRepositoryInterface $refundRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $orderId = (int) $this->getRequest()->getParam('order_id');
            $data = (array) $this->getRequest()->getParams();
            $data['actor_id'] = (int) ($this->_auth->getUser() ? $this->_auth->getUser()->getId() : 0);

            $refund = $this->processor->submit($orderId, RefundSubmission::fromRequest($data));
            $created = $this->dispatcher->run(Outbox::OP_CREATE, 1);
            $fresh = $this->refundRepository->getById((int) $refund->getEntityId());

            return $result->setData([
                'ok' => true,
                'refund_id' => (int) $fresh->getEntityId(),
                'refund_no' => $fresh->getRefundNo(),
                'status' => $fresh->getStatus(),
                'create_status' => $fresh->getCreateStatus(),
                'dispatched' => $created,
                'message' => (string) __('Refund %1 saved.', $fresh->getRefundNo()),
                'redirect_url' => $this->getUrl('acme_refund/refund/view', ['refund_id' => $fresh->getEntityId()]),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => (string) __('The refund could not be saved.')]);
        }
    }
}
