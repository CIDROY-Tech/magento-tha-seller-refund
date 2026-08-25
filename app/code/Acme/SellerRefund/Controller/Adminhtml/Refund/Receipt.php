<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Controller\Adminhtml\Refund;

use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Pdf\RefundReceipt;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

class Receipt extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::receipt';

    public function __construct(
        Context $context,
        private readonly RawFactory $rawFactory,
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly RefundReceipt $receipt
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $refund = $this->refundRepository->getById((int) $this->getRequest()->getParam('refund_id'));
        $content = $this->receipt->render($refund);

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/pdf', true);
        $result->setHeader('Content-Disposition', 'attachment; filename="' . $refund->getRefundNo() . '.pdf"', true);
        $result->setContents($content);

        return $result;
    }
}
