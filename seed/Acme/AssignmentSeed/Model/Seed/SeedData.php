<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

/**
 * Canonical, deterministic description of everything the seeder creates.
 *
 * These constants are the source of truth consumed by every seeder helper. The JSON files
 * under seed/fixtures mirror this data verbatim so the README and tests can read it without
 * booting the application. All values are synthetic and non-production.
 */
class SeedData
{
    public const ATTRIBUTE_CODE = 'mp_tax_class';

    /**
     * Business tax codes = admin option values at store scope 0.
     * 999 = tax-exempt, 010 = 10 percent, 008 = 8 percent reduced rate.
     */
    public const TAX_CODES = ['999', '010', '008'];

    public const TAX_RATES = [
        '999' => '0.0000',
        '010' => '0.1000',
        '008' => '0.0800',
    ];

    public const SELLER_CODE = 'SLR-100';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            [
                'sku' => 'SELLER-RED-01',
                'name' => 'Seller Red Widget',
                'price' => '1200',
                'is_seller' => true,
                'tax_code' => '010',
            ],
            [
                'sku' => 'SELLER-BLU-02',
                'name' => 'Seller Blue Gadget',
                'price' => '800',
                'is_seller' => true,
                'tax_code' => '008',
            ],
            [
                'sku' => 'SELLER-GRN-03',
                'name' => 'Seller Green Accessory',
                'price' => '1500',
                'is_seller' => true,
                'tax_code' => '999',
            ],
            [
                'sku' => 'SELLER-YEL-04',
                'name' => 'Seller Yellow Kit',
                'price' => '3000',
                'is_seller' => true,
                'tax_code' => '010',
            ],
            [
                'sku' => 'CORE-STD-01',
                'name' => 'Core Standard Item',
                'price' => '2000',
                'is_seller' => false,
                'tax_code' => null,
            ],
        ];
    }

    public const CUSTOMER_EMAIL = 'refund.customer@example.com';
    public const CUSTOMER_PASSWORD = 'RefundCustomer#2026';
    public const CUSTOMER_FIRSTNAME = 'Rei';
    public const CUSTOMER_LASTNAME = 'Tanaka';

    /**
     * Admin roles and one user each. Passwords are synthetic and documented as
     * non-production credentials.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function adminUsers(): array
    {
        return [
            [
                'role_name' => 'Assignment CS',
                'username' => 'cs_agent',
                'password' => 'CsAgent#2026',
                'firstname' => 'Casey',
                'lastname' => 'Support',
                'email' => 'cs.agent@example.com',
                'resources' => [
                    'Magento_Backend::admin',
                    'Magento_Backend::dashboard',
                    'Acme_SellerRefund::refund',
                    'Acme_SellerRefund::view',
                    'Acme_SellerRefund::create',
                    'Acme_SellerRefund::cash_register',
                ],
            ],
            [
                'role_name' => 'Assignment Finance',
                'username' => 'finance_user',
                'password' => 'FinanceUser#2026',
                'firstname' => 'Fran',
                'lastname' => 'Ledger',
                'email' => 'finance.user@example.com',
                'resources' => [
                    'Magento_Backend::admin',
                    'Magento_Backend::dashboard',
                    'Acme_SellerRefund::refund',
                    'Acme_SellerRefund::view',
                    'Acme_SellerRefund::create',
                    'Acme_SellerRefund::cash_register',
                    'Acme_SellerRefund::retry',
                    'Acme_SellerRefund::audit',
                ],
            ],
            [
                'role_name' => 'Assignment Refund Administrator',
                'username' => 'refund_admin',
                'password' => 'RefundAdmin#2026',
                'firstname' => 'Robin',
                'lastname' => 'Admin',
                'email' => 'refund.admin@example.com',
                'resources' => [
                    'Magento_Backend::admin',
                    'Magento_Backend::dashboard',
                    'Acme_SellerRefund::refund',
                    'Acme_SellerRefund::view',
                    'Acme_SellerRefund::create',
                    'Acme_SellerRefund::cash_register',
                    'Acme_SellerRefund::retry',
                    'Acme_SellerRefund::cancel',
                    'Acme_SellerRefund::audit',
                    'Acme_SellerRefund::receipt',
                ],
            ],
        ];
    }

    /**
     * The six seeded orders. delivered_offset_days is relative to the seed run time
     * (negative = in the past). A line with a null seller_code is a first-party line.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function orders(): array
    {
        return [
            [
                'increment_id' => 'SR-ORD-1001',
                'seller_order_id' => 'SO-1001',
                'delivered_offset_days' => -5,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'SELLER-RED-01', 'qty' => 2, 'seller' => true],
                ],
                'refund' => null,
            ],
            [
                'increment_id' => 'SR-ORD-1002',
                'seller_order_id' => 'SO-1002',
                'delivered_offset_days' => -5,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'SELLER-RED-01', 'qty' => 1, 'seller' => true],
                    ['sku' => 'SELLER-BLU-02', 'qty' => 2, 'seller' => true],
                    ['sku' => 'CORE-STD-01', 'qty' => 1, 'seller' => false],
                ],
                'refund' => null,
            ],
            [
                'increment_id' => 'SR-ORD-1003',
                'seller_order_id' => 'SO-1003',
                'delivered_offset_days' => -6,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'SELLER-YEL-04', 'qty' => 3, 'seller' => true],
                    ['sku' => 'SELLER-GRN-03', 'qty' => 1, 'seller' => true],
                ],
                // Prior completed refund: 2 of the 3 SELLER-YEL-04 units already refunded.
                'refund' => [
                    'seq' => 900001,
                    'status' => 'erp_confirmed',
                    'type' => 'partial',
                    'reason_code' => 'defect',
                    'erp_refund_id' => 'ERP-8100103',
                    'version' => 6,
                    'create_status' => 'succeeded',
                    'status_check_status' => 'succeeded',
                    'confirm_status' => 'succeeded',
                    'cash_refund_status' => 'succeeded',
                    'transaction_number' => 'TXN-SEED-1003',
                    'items' => [
                        [
                            'sku' => 'SELLER-YEL-04',
                            'qty_refund' => 2,
                            'qty_refunded_before' => 0,
                        ],
                    ],
                ],
            ],
            [
                'increment_id' => 'SR-ORD-1004',
                'seller_order_id' => 'SO-1004',
                'delivered_offset_days' => -20,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'SELLER-RED-01', 'qty' => 1, 'seller' => true],
                ],
                'refund' => null,
            ],
            [
                'increment_id' => 'SR-ORD-1005',
                'seller_order_id' => 'SO-1005',
                'delivered_offset_days' => -5,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'CORE-STD-01', 'qty' => 1, 'seller' => false],
                ],
                'refund' => null,
            ],
            [
                'increment_id' => 'SR-ORD-1006',
                'seller_order_id' => 'SO-1006',
                'delivered_offset_days' => -4,
                'state' => 'complete',
                'lines' => [
                    ['sku' => 'SELLER-BLU-02', 'qty' => 1, 'seller' => true],
                ],
                // Refund pre-advanced to cash_refunded so the Confirm path is reachable.
                'refund' => [
                    'seq' => 900002,
                    'status' => 'cash_refunded',
                    'type' => 'full',
                    'reason_code' => 'customer_return',
                    'erp_refund_id' => 'ERP-8100106',
                    'version' => 4,
                    'create_status' => 'succeeded',
                    'status_check_status' => 'not_started',
                    'confirm_status' => 'not_started',
                    'cash_refund_status' => 'succeeded',
                    'transaction_number' => 'TXN-SEED-1006',
                    'items' => [
                        [
                            'sku' => 'SELLER-BLU-02',
                            'qty_refund' => 1,
                            'qty_refunded_before' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    public const THEME_PATH = 'frontend/Acme/blank';
    public const THEME_AREA = 'frontend';
    public const THEME_CODE = 'Acme/blank';

    public const CONFIG_MARKER_PATH = 'acme_assignment/seed/version';

    /**
     * @return array<int, string> the increment ids of every seeded order
     */
    public static function orderIncrementIds(): array
    {
        return array_map(
            static fn (array $order): string => (string) $order['increment_id'],
            self::orders()
        );
    }
}
