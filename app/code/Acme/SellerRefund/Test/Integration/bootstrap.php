<?php

declare(strict_types=1);

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap as AppBootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/*
 * Live-application bootstrap for the Acme_SellerRefund integration suite. It boots the real
 * store the same way bin/magento does, exposes the shared ObjectManager, and puts the process
 * into the adminhtml area so admin-scoped services resolve.
 *
 * This file lives at app/code/Acme/SellerRefund/Test/Integration/bootstrap.php, so the store
 * root (which holds app/bootstrap.php) is six directories up.
 */
require __DIR__ . '/../../../../../../app/bootstrap.php';

$appBootstrap = AppBootstrap::create(BP, $_SERVER);
$objectManager = $appBootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
try {
    $state->setAreaCode(Area::AREA_ADMINHTML);
} catch (LocalizedException $e) {
    // The area code is already set (for example on a warm process); nothing to do.
}

// Shared handle other bootstrap consumers (AppTestCase) read.
$GLOBALS['acmeSellerRefundObjectManager'] = $objectManager;
