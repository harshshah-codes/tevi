<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\Address;
use App\Domain\Repositories\AddressRepositoryInterface;
use PDO;

final class MySqlAddressRepository implements AddressRepositoryInterface
{
    /** Columns a caller is allowed to write. */
    private const WRITABLE = [
        'label',
        'recipient_name',
        'phone',
        'line1',
        'line2',
        'city',
        'state',
        'pincode',
    ];

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(Address $address): int
    {
        $this->pdo->beginTransaction();

        try {
            // The very first address a user saves becomes their default.
            if ($address->isDefault() || $this->countForUser($address->userId()) === 0) {
                $this->clearDefault($address->userId());
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO addresses (user_id, label, recipient_name, phone, line1, line2, city, state, pincode, is_default)
                 VALUES (:user_id, :label, :recipient_name, :phone, :line1, :line2, :city, :state, :pincode, :is_default)'
            );
            $stmt->execute([
                ':user_id'       => $address->userId(),
                ':label'         => $address->label(),
                ':recipient_name' => $address->recipientName(),
                ':phone'         => $address->phone(),
                ':line1'         => $address->line1(),
                ':line2'         => $address->line2(),
                ':city'          => $address->city(),
                ':state'         => $address->state(),
                ':pincode'       => $address->pincode(),
                ':is_default'    => $address->isDefault() ? 1 : 0,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return Address[]
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, id DESC'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $addresses = [];
        foreach ($stmt->fetchAll() as $row) {
            $addresses[] = $this->mapAddress($row);
        }

        return $addresses;
    }

    public function findByIdForUser(int $id, int $userId): ?Address
    {
        $stmt = $this->pdo->prepare('SELECT * FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->mapAddress($row) : null;
    }

    public function findDefaultForUser(int $userId): ?Address
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM addresses WHERE user_id = :user_id AND is_default = 1 LIMIT 1'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->mapAddress($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $userId, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, self::WRITABLE, true)) {
                continue;
            }
            $sets[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[':id'] = $id;
        $params[':user_id'] = $userId;

        $stmt = $this->pdo->prepare(
            'UPDATE addresses SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :user_id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return false;
        }

        // Never leave a user without a default if one was just removed.
        if ($this->findDefaultForUser($userId) === null) {
            $next = $this->pdo->prepare(
                'SELECT id FROM addresses WHERE user_id = :user_id ORDER BY id ASC LIMIT 1'
            );
            $next->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $next->execute();
            $row = $next->fetch();
            if ($row) {
                $this->setDefault((int) $row['id'], $userId);
            }
        }

        return true;
    }

    public function clearDefault(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setDefault(int $id, int $userId): bool
    {
        $this->clearDefault($userId);

        $stmt = $this->pdo->prepare(
            'UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :user_id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapAddress(array $row): Address
    {
        return new Address(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['label'],
            (string) $row['recipient_name'],
            (string) $row['phone'],
            (string) $row['line1'],
            $row['line2'] !== null ? (string) $row['line2'] : null,
            (string) $row['city'],
            (string) $row['state'],
            (string) $row['pincode'],
            (int) $row['is_default'] === 1,
            (string) $row['created_at'],
        );
    }
}
