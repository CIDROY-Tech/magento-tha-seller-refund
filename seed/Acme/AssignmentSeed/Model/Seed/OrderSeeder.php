<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\AddressFactory as OrderAddressFactory;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\Order\PaymentFactory as OrderPaymentFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the six deterministic seller / mixed / core orders. Each seller line carries its
 * mp_seller_code and an mp_tax_class option-id snapshot; each order carries its seller order id
 * and a delivery timestamp offset from the seed run time so the refund window rules apply.
 */
class OrderSeeder
{
    private const CORE_TAX_RATE = '0.1000';
    private const SCALE = 4;

    public function __construct(
        private readonly OrderFactory $orderFactory,
        private readonly OrderItemFactory $orderItemFactory,
        private readonly OrderAddressFactory $orderAddressFactory,
        private readonly OrderPaymentFactory $orderPaymentFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly TaxAttributeSeeder $taxAttributeSeeder,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array<int, string> increment ids of orders created this run
     */
    public function seed(int $customerId): array
    {
        $optionMap = $this->taxAttributeSeeder->loadOptionMap();
        $store = $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore(1);
        $customer = $this->customerRepository->getById($customerId);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $created = [];
        foreach (SeedData::orders() as $definition) {
            $incrementId = (string) $definition['increment_id'];
            if ($this->loadByIncrementId($incrementId) !== null) {
                continue;
            }
            $this->buildOrder($definition, $customer, $customerId, $store, $optionMap, $now);
            $created[] = $incrementId;
        }

        return $created;
    }

    public function reset(): void
    {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $gridTable = $this->resource->getTableName('sales_order_grid');
        $ids = SeedData::orderIncrementIds();

        // Child sales tables carry ON DELETE CASCADE foreign keys to sales_order.
        $connection->delete($orderTable, ['increment_id IN (?)' => $ids]);
        $connection->delete($gridTable, ['increment_id IN (?)' => $ids]);
    }

    public function loadByIncrementId(string $incrementId): ?Order
    {
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, int> $optionMap
     */
    private function buildOrder(
        array $definition,
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        int $customerId,
        \Magento\Store\Api\Data\StoreInterface $store,
        array $optionMap,
        \DateTimeImmutable $now
    ): void {
        $delivered = $now->modify(sprintf('%+d days', (int) $definition['delivered_offset_days']));
        $createdAt = $delivered->modify('-2 days');

        /** @var Order $order */
        $order = $this->orderFactory->create();

        $subtotal = '0.0000';
        $taxAmount = '0.0000';
        $grandTotal = '0.0000';
        $totalQty = 0.0;

        foreach ($definition['lines'] as $line) {
            $product = $this->productRepository->get((string) $line['sku']);
            $isSeller = (bool) $line['seller'];
            $qty = (float) $line['qty'];
            $price = $this->num($product->getPrice());
            $rowTotal = bcmul($price, (string) $qty, self::SCALE);

            $code = null;
            if ($isSeller) {
                $sku = (string) $line['sku'];
                $code = $this->taxCodeForSku($sku);
            }
            $rate = $code !== null ? SeedData::TAX_RATES[$code] : self::CORE_TAX_RATE;
            $rowTax = bcmul($rowTotal, $rate, self::SCALE);
            $percent = bcmul($rate, '100', self::SCALE);
            $priceInclTax = bcadd($price, bcmul($price, $rate, self::SCALE), self::SCALE);
            $rowTotalInclTax = bcadd($rowTotal, $rowTax, self::SCALE);

            $item = $this->orderItemFactory->create();
            $item->setStoreId((int) $store->getId());
            $item->setProductId((int) $product->getId());
            $item->setProductType($product->getTypeId());
            $item->setSku((string) $product->getSku());
            $item->setName((string) $product->getName());
            $item->setQtyOrdered($qty);
            $item->setQtyInvoiced($qty);
            $item->setQtyShipped($qty);
            $item->setPrice((float) $price);
            $item->setBasePrice((float) $price);
            $item->setOriginalPrice((float) $price);
            $item->setBaseOriginalPrice((float) $price);
            $item->setPriceInclTax((float) $priceInclTax);
            $item->setBasePriceInclTax((float) $priceInclTax);
            $item->setRowTotal((float) $rowTotal);
            $item->setBaseRowTotal((float) $rowTotal);
            $item->setRowTotalInclTax((float) $rowTotalInclTax);
            $item->setBaseRowTotalInclTax((float) $rowTotalInclTax);
            $item->setTaxAmount((float) $rowTax);
            $item->setBaseTaxAmount((float) $rowTax);
            $item->setTaxPercent((float) $percent);
            $item->setProductOptions([]);

            // Custom seller-refund columns on sales_order_item.
            if ($isSeller) {
                $item->setData('mp_seller_code', SeedData::SELLER_CODE);
                if ($code !== null && isset($optionMap[$code])) {
                    $item->setData('mp_tax_class', $optionMap[$code]);
                }
            } else {
                $item->setData('mp_seller_code', null);
                $item->setData('mp_tax_class', null);
            }

            $order->addItem($item);

            $subtotal = bcadd($subtotal, $rowTotal, self::SCALE);
            $taxAmount = bcadd($taxAmount, $rowTax, self::SCALE);
            $grandTotal = bcadd($grandTotal, $rowTotalInclTax, self::SCALE);
            $totalQty += $qty;
        }

        $order->setStoreId((int) $store->getId());
        $order->setState((string) $definition['state']);
        $order->setStatus((string) $definition['state']);
        $order->setIncrementId((string) $definition['increment_id']);
        $order->setCustomerId($customerId);
        $order->setCustomerIsGuest(0);
        $order->setCustomerGroupId((int) $customer->getGroupId());
        $order->setCustomerEmail((string) $customer->getEmail());
        $order->setCustomerFirstname((string) $customer->getFirstname());
        $order->setCustomerLastname((string) $customer->getLastname());

        $currency = 'JPY';
        $order->setBaseCurrencyCode($currency);
        $order->setGlobalCurrencyCode($currency);
        $order->setStoreCurrencyCode($currency);
        $order->setOrderCurrencyCode($currency);
        $order->setBaseToGlobalRate(1.0);
        $order->setBaseToOrderRate(1.0);
        $order->setStoreToBaseRate(1.0);
        $order->setStoreToOrderRate(1.0);

        $order->setSubtotal((float) $subtotal);
        $order->setBaseSubtotal((float) $subtotal);
        $order->setSubtotalInclTax((float) $grandTotal);
        $order->setBaseSubtotalInclTax((float) $grandTotal);
        $order->setTaxAmount((float) $taxAmount);
        $order->setBaseTaxAmount((float) $taxAmount);
        $order->setShippingAmount(0.0);
        $order->setBaseShippingAmount(0.0);
        $order->setShippingInclTax(0.0);
        $order->setBaseShippingInclTax(0.0);
        $order->setDiscountAmount(0.0);
        $order->setBaseDiscountAmount(0.0);
        $order->setGrandTotal((float) $grandTotal);
        $order->setBaseGrandTotal((float) $grandTotal);
        $order->setTotalPaid((float) $grandTotal);
        $order->setBaseTotalPaid((float) $grandTotal);
        $order->setTotalInvoiced((float) $grandTotal);
        $order->setBaseTotalInvoiced((float) $grandTotal);
        $order->setTotalQtyOrdered($totalQty);
        $order->setTotalItemCount(count($definition['lines']));
        $order->setCreatedAt($createdAt->format('Y-m-d H:i:s'));

        // Custom seller-refund columns on sales_order.
        $order->setData('mp_seller_order_id', (string) $definition['seller_order_id']);
        $order->setData('mp_delivered_at', $delivered->format('Y-m-d H:i:s'));

        $order->setBillingAddress($this->buildAddress($customer, 'billing'));
        $order->setShippingAddress($this->buildAddress($customer, 'shipping'));
        $order->setPayment($this->buildPayment());

        $this->orderRepository->save($order);
    }

    private function buildAddress(
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        string $type
    ): \Magento\Sales\Model\Order\Address {
        $address = $this->orderAddressFactory->create();
        $address->setAddressType($type);
        $address->setFirstname((string) $customer->getFirstname());
        $address->setLastname((string) $customer->getLastname());
        $address->setEmail((string) $customer->getEmail());
        $address->setStreet(['1-1 Test Street']);
        $address->setCity('Tokyo');
        $address->setPostcode('100-0001');
        $address->setCountryId('JP');
        $address->setRegion('Tokyo');
        $address->setTelephone('03-0000-0000');

        return $address;
    }

    private function buildPayment(): \Magento\Sales\Model\Order\Payment
    {
        $payment = $this->orderPaymentFactory->create();
        $payment->setMethod('checkmo');

        return $payment;
    }

    private function taxCodeForSku(string $sku): ?string
    {
        foreach (SeedData::products() as $product) {
            if ((string) $product['sku'] === $sku) {
                return $product['tax_code'] !== null ? (string) $product['tax_code'] : null;
            }
        }

        return null;
    }

    private function num(mixed $value): string
    {
        return sprintf('%.4F', (float) $value);
    }
}
