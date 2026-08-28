<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Observer;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Model\Email\RefundConfirmationSender;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends the refund confirmation email when a refund reaches ERP-confirmed. A mail failure
 * is logged and swallowed so it never rolls back the confirmed lifecycle state.
 */
class SendConfirmationEmail implements ObserverInterface
{
    public function __construct(
        private readonly RefundConfirmationSender $sender,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $refund = $observer->getEvent()->getData('refund');
        if (!$refund instanceof RefundInterface) {
            return;
        }

        try {
            $this->sender->send($refund);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Refund confirmation email failed: ' . $e->getMessage(),
                ['refund_no' => $refund->getRefundNo()]
            );
        }
    }
}
