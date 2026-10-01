<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\User;

interface UserRepositoryInterface
{
    public function create(User $user): int;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function update(int $id, array $data): bool;
}