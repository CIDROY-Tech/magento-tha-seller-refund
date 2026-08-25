<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Controller\Adminhtml\Refund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Acme_SellerRefund::create';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Acme_SellerRefund::refund');
        $page->getConfig()->getTitle()->prepend(__('Create Seller Refund'));

        return $page;
    }
}
