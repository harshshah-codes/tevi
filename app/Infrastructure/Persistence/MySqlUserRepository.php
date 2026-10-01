<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\User;
use App\Domain\Repositories\UserRepositoryInterface;
use PDO;

final class MySqlUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(User $user): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, phone, is_active)
                 VALUES (:email, :password_hash, :first_name, :last_name, :phone, :is_active)'
            );
            $stmt->execute([
                ':email'         => $user->email(),
                ':password_hash' => $user->passwordHash(),
                ':first_name'    => $user->firstName(),
                ':last_name'     => $user->lastName(),
                ':phone'         => $user->phone(),
                ':is_active'     => $user->isActive(),
            ]);

            $userId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();

            return $userId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapUser($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->mapUser($row);
    }

    public function update(int $id, array $data): bool
    {
        // Build dynamic SET clause
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        if (empty($sets)) {
            return false;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $params[':id'] = $id;

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        return $result && $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapUser(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            $row['phone'] !== null ? (string) $row['phone'] : null,
            (int) $row['is_active'],
            (string) $row['created_at'],
            (string) $row['updated_at']
        );
    }
}