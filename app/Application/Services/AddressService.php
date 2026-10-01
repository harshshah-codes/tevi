<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\Address;
use App\Domain\Repositories\AddressRepositoryInterface;

final class AddressService
{
    public function __construct(
        private readonly AddressRepositoryInterface $repository,
    ) {
    }

    /**
     * @return Address[]
     */
    public function listForUser(int $userId): array
    {
        return $this->repository->findByUser($userId);
    }

    public function findForUser(int $id, int $userId): ?Address
    {
        return $this->repository->findByIdForUser($id, $userId);
    }

    public function defaultForUser(int $userId): ?Address
    {
        return $this->repository->findDefaultForUser($userId);
    }

    /**
     * @param array{
     *     label?: string,
     *     recipientName: string,
     *     phone: string,
     *     line1: string,
     *     line2?: string|null,
     *     city: string,
     *     state: string,
     *     pincode: string,
     *     isDefault?: bool
     * } $data
     * @throws \InvalidArgumentException when required fields are missing
     */
    public function createForUser(int $userId, array $data): int
    {
        $address = new Address(
            0,
            $userId,
            $this->label($data['label'] ?? ''),
            trim((string) $data['recipientName']),
            trim((string) $data['phone']),
            trim((string) $data['line1']),
            $this->optional($data['line2'] ?? null),
            trim((string) $data['city']),
            trim((string) $data['state']),
            trim((string) $data['pincode']),
            (bool) ($data['isDefault'] ?? false),
            '',
        );

        return $this->repository->create($address);
    }

    /**
     * @param array<string, mixed> $data
     * @return bool false when the address does not belong to this user
     */
    public function updateForUser(int $id, int $userId, array $data): bool
    {
        $fields = [];

        if (array_key_exists('label', $data)) {
            $fields['label'] = $this->label((string) $data['label']);
        }
        if (array_key_exists('recipientName', $data)) {
            $fields['recipient_name'] = trim((string) $data['recipientName']);
        }
        if (array_key_exists('phone', $data)) {
            $fields['phone'] = trim((string) $data['phone']);
        }
        if (array_key_exists('line1', $data)) {
            $fields['line1'] = trim((string) $data['line1']);
        }
        if (array_key_exists('line2', $data)) {
            $fields['line2'] = $this->optional($data['line2']);
        }
        if (array_key_exists('city', $data)) {
            $fields['city'] = trim((string) $data['city']);
        }
        if (array_key_exists('state', $data)) {
            $fields['state'] = trim((string) $data['state']);
        }
        if (array_key_exists('pincode', $data)) {
            $fields['pincode'] = trim((string) $data['pincode']);
        }

        foreach (['recipient_name', 'phone', 'line1', 'city', 'state', 'pincode'] as $required) {
            if (array_key_exists($required, $fields) && $fields[$required] === '') {
                throw new \InvalidArgumentException('Field cannot be empty: ' . $required);
            }
        }

        $updated = $fields !== [] ? $this->repository->update($id, $userId, $fields) : true;

        if (!empty($data['isDefault'])) {
            $this->repository->setDefault($id, $userId);
        }

        return $updated;
    }

    public function setDefaultForUser(int $id, int $userId): bool
    {
        return $this->repository->setDefault($id, $userId);
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        return $this->repository->delete($id, $userId);
    }

    private function label(string $label): string
    {
        $label = trim($label);

        return $label === '' ? 'Home' : mb_substr($label, 0, 50);
    }

    private function optional(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
