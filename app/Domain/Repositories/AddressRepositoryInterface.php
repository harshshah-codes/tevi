<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Address;

interface AddressRepositoryInterface
{
    public function create(Address $address): int;

    /**
     * @return Address[]
     */
    public function findByUser(int $userId): array;

    public function findByIdForUser(int $id, int $userId): ?Address;

    public function findDefaultForUser(int $userId): ?Address;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $userId, array $data): bool;

    public function delete(int $id, int $userId): bool;

    public function clearDefault(int $userId): void;

    public function setDefault(int $id, int $userId): bool;

    public function countForUser(int $userId): int;
}
