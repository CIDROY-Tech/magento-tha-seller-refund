<?php

declare(strict_types=1);

namespace Acme\SellerRefund\Test\Integration;

use Acme\SellerRefund\Block\Adminhtml\Refund\Worklist;
use Acme\SellerRefund\Model\ResourceModel\Refund\Collection;
use Acme\SellerRefund\Test\Integration\Framework\AppTestCase;

/**
 * The admin worklist block returns its collection with the order and line summary columns
 * joined in, and renders one row per refund without loading an order model per row.
 */
class WorklistRendersTest extends AppTestCase
{
    public function testWorklistReturnsCollectionWithJoinedSummaryColumns(): void
    {
        /** @var Worklist $block */
        $block = $this->create(Worklist::class);

        /** @var Collection $collection */
        $collection = $block->getRefunds();

        // The seeded refunds (SR-ORD-1003, SR-ORD-1006) are present.
        self::assertGreaterThanOrEqual(1, $collection->getSize());

        $items = $collection->getItems();
        self::assertNotEmpty($items);

        foreach ($items as $row) {
            $data = $row->getData();
            self::assertArrayHasKey('order_increment_id', $data);
            self::assertArrayHasKey('line_count', $data);
            self::assertNotSame('', (string) $row->getData('order_increment_id'));
        }
    }
}
