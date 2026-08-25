<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Block\Adminhtml\Refund;

use Acme\SellerRefund\Model\ResourceModel\Refund\Collection;
use Acme\SellerRefund\Model\ResourceModel\Refund\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Worklist grid backing block. The collection is prepared with the order summary and the
 * grouped line summary joined in, so the template renders one row per refund from joined
 * columns alone and never loads an order model per row.
 */
class Worklist extends Template
{
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRefunds(): Collection
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->joinOrderSummary()
            ->addLineSummary()
            ->setPageSize(50);
        $collection->setOrder('main_table.entity_id', Collection::SORT_ORDER_DESC);

        return $collection;
    }

    public function getViewUrl(int $refundId): string
    {
        return $this->getUrl('acme_refund/refund/view', ['refund_id' => $refundId]);
    }

    public function formatMoney(?string $amount, string $currency = 'JPY'): string
    {
        return $currency . ' ' . number_format((float) $amount, 0, '.', ',');
    }
}
