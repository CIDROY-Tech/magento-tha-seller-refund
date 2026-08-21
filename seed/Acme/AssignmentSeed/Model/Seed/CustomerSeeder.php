<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Creates the single synthetic storefront customer that owns the seeded orders, so the
 * order history and order-details surfaces are viewable.
 */
class CustomerSeeder
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function seed(): int
    {
        $existing = $this->find();
        if ($existing !== null) {
            return $existing;
        }

        $store = $this->resolveStore();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId((int) $store->getWebsiteId());
        $customer->setStoreId((int) $store->getId());
        $customer->setGroupId(1);
        $customer->setEmail(SeedData::CUSTOMER_EMAIL);
        $customer->setFirstname(SeedData::CUSTOMER_FIRSTNAME);
        $customer->setLastname(SeedData::CUSTOMER_LASTNAME);

        $saved = $this->customerRepository->save($customer, $this->hashPassword());

        return (int) $saved->getId();
    }

    public function reset(): void
    {
        $id = $this->find();
        if ($id === null) {
            return;
        }
        try {
            $this->customerRepository->deleteById($id);
        } catch (NoSuchEntityException) {
            // Already gone.
        }
    }

    public function find(): ?int
    {
        try {
            $store = $this->resolveStore();
            $customer = $this->customerRepository->get(
                SeedData::CUSTOMER_EMAIL,
                (int) $store->getWebsiteId()
            );

            return (int) $customer->getId();
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    private function resolveStore(): \Magento\Store\Api\Data\StoreInterface
    {
        return $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore(1);
    }

    private function hashPassword(): string
    {
        // The repository accepts a password hash as its second argument; the platform encryptor
        // produces the stored format so the documented plain password verifies at login.
        return $this->encryptor->getHash(SeedData::CUSTOMER_PASSWORD, true);
    }
}
