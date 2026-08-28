<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Model\Email;

use Acme\SellerRefund\Api\Data\RefundInterface;
use Acme\SellerRefund\Api\RefundRepositoryInterface;
use Acme\SellerRefund\Model\Config;
use Acme\SellerRefund\Model\Total\RefundTotalCalculator;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\FactoryInterface as TemplateFactoryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;

/**
 * Renders and sends the refund confirmation email once the ERP has confirmed the credit
 * note. The frontend area is emulated in a try/finally around both operations so the store
 * template, translations and design apply and the original area is always restored.
 */
class RefundConfirmationSender
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundTotalCalculator $calculator,
        private readonly TransportBuilder $transportBuilder,
        private readonly TemplateFactoryInterface $templateFactory,
        private readonly Emulation $appEmulation,
        private readonly Config $config
    ) {
    }

    public function render(RefundInterface $refund): string
    {
        [, $vars, $storeId] = $this->prepare($refund);

        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $template = $this->templateFactory->get($this->config->emailTemplate($storeId))
                ->setVars($vars)
                ->setOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId]);

            return (string) $template->processTemplate();
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    public function send(RefundInterface $refund): void
    {
        [$order, $vars, $storeId] = $this->prepare($refund);
        $email = (string) $order->getCustomerEmail();
        if ($email === '') {
            return;
        }

        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $name = trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname());
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->config->emailTemplate($storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->emailIdentity($storeId), $storeId)
                ->addTo($email, $name !== '' ? $name : $email)
                ->getTransport();
            $transport->sendMessage();
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @return array{0: OrderInterface, 1: array<string, mixed>, 2: int}
     */
    private function prepare(RefundInterface $refund): array
    {
        $items = $this->refundRepository->getItems((int) $refund->getEntityId());
        $order = $this->orderRepository->get($refund->getOrderId());
        $figures = $this->calculator->fromSnapshot($refund, $items, $order);
        $storeId = (int) $order->getStoreId();

        $vars = [
            'refund' => $refund,
            'order' => $order,
            'refund_no' => $refund->getRefundNo(),
            'currency' => $figures->currency,
            'is_partial' => $figures->isPartial(),
            'pre_refund' => [
                'subtotal' => $figures->preRefundSubtotal,
                'shipping' => $figures->preRefundShipping,
                'tax' => $figures->preRefundTax,
                'grand_total' => $figures->preRefundGrandTotal,
            ],
            'refund_totals' => [
                'subtotal' => $figures->refundSubtotal,
                'shipping' => $figures->refundShipping,
                'tax' => $figures->refundTax,
                'grand_total' => $figures->refundGrandTotal,
            ],
            'lines' => array_map(
                static fn (\Acme\SellerRefund\Model\Total\RefundFigureLine $line): array => $line->toArray(),
                $figures->lines
            ),
        ];

        return [$order, $vars, $storeId];
    }
}
