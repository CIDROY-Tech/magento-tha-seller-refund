<?php

declare(strict_types=1);

namespace Acme\AssignmentSeed\Model\Seed;

use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;
use Magento\User\Model\UserFactory;

/**
 * Creates three admin roles (CS, Finance, Refund Administrator) and one admin user for each,
 * with graduated Acme_SellerRefund privileges. All credentials are synthetic and documented as
 * non-production.
 */
class AdminUserSeeder
{
    public function __construct(
        private readonly RoleFactory $roleFactory,
        private readonly RulesFactory $rulesFactory,
        private readonly UserFactory $userFactory,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly UserCollectionFactory $userCollectionFactory
    ) {
    }

    /**
     * @return array<int, string> the usernames created or already present
     */
    public function seed(): array
    {
        $usernames = [];
        foreach (SeedData::adminUsers() as $definition) {
            $roleId = $this->ensureRole(
                (string) $definition['role_name'],
                $definition['resources']
            );
            $this->ensureUser($definition, $roleId);
            $usernames[] = (string) $definition['username'];
        }

        return $usernames;
    }

    public function reset(): void
    {
        foreach (SeedData::adminUsers() as $definition) {
            $this->deleteUser((string) $definition['username']);
            $this->deleteRole((string) $definition['role_name']);
        }
    }

    /**
     * @param array<int, string> $resources
     */
    private function ensureRole(string $roleName, array $resources): int
    {
        $existingId = $this->roleIdByName($roleName);
        if ($existingId !== null) {
            return $existingId;
        }

        $role = $this->roleFactory->create();
        $role->setData('role_name', $roleName);
        $role->setData('parent_id', 0);
        $role->setData('tree_level', 1);
        $role->setData('sort_order', 0);
        $role->setData('role_type', RoleGroup::ROLE_TYPE);
        $role->setData('user_id', 0);
        $role->setData('user_type', (string) UserContextInterface::USER_TYPE_ADMIN);
        $role->save();

        $roleId = (int) $role->getId();

        $rules = $this->rulesFactory->create();
        $rules->setRoleId($roleId);
        $rules->setResources($resources);
        $rules->saveRel();

        return $roleId;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function ensureUser(array $definition, int $roleId): void
    {
        if ($this->userIdByUsername((string) $definition['username']) !== null) {
            return;
        }

        $user = $this->userFactory->create();
        $user->setData([
            'username' => (string) $definition['username'],
            'firstname' => (string) $definition['firstname'],
            'lastname' => (string) $definition['lastname'],
            'email' => (string) $definition['email'],
            'password' => (string) $definition['password'],
            'interface_locale' => 'en_US',
            'is_active' => 1,
        ]);
        $user->setRoleId($roleId);
        $user->save();
    }

    private function deleteUser(string $username): void
    {
        $id = $this->userIdByUsername($username);
        if ($id === null) {
            return;
        }
        $user = $this->userFactory->create();
        $user->load($id);
        if ($user->getId()) {
            $user->delete();
        }
    }

    private function deleteRole(string $roleName): void
    {
        $id = $this->roleIdByName($roleName);
        if ($id === null) {
            return;
        }
        $role = $this->roleFactory->create();
        $role->load($id);
        if ($role->getId()) {
            $role->delete();
        }
    }

    private function roleIdByName(string $roleName): ?int
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', $roleName);
        $collection->addFieldToFilter('role_type', RoleGroup::ROLE_TYPE);
        $collection->setPageSize(1);
        $role = $collection->getFirstItem();

        return $role->getId() ? (int) $role->getId() : null;
    }

    private function userIdByUsername(string $username): ?int
    {
        $collection = $this->userCollectionFactory->create();
        $collection->addFieldToFilter('username', $username);
        $collection->setPageSize(1);
        $user = $collection->getFirstItem();

        return $user->getId() ? (int) $user->getId() : null;
    }
}
