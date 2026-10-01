<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\User;
use App\Domain\Repositories\UserRepositoryInterface;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Register a new user.
     * @param array{
     *     email: string,
     *     password: string,
     *     firstName: string,
     *     lastName: string,
     *     phone?: string
     * } $data
     * @return int user id
     * @throws \Exception if email already exists
     */
    public function register(array $data): int
    {
        $email = $data['email'];
        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null) {
            throw new \Exception('Email already registered');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $user = new User(
            0, // id will be set by DB
            $email,
            $passwordHash,
            $data['firstName'],
            $data['lastName'],
            $data['phone'] ?? null,
            1, // is_active
            '', // createdAt will be set by DB
            ''  // updatedAt will be set by DB
        );

        // The repository will set the timestamps via DB defaults; we ignore the passed empty strings.
        $id = $this->userRepository->create($user);

        return $id;
    }

    /**
     * Look a user up by id.
     */
    public function findById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Update the editable parts of a user's profile.
     *
     * @param array{firstName?: string, lastName?: string, phone?: string|null} $data
     * @return array{id: int, email: string, firstName: string, lastName: string, phone: string|null}
     * @throws \RuntimeException when the user no longer exists
     */
    public function updateProfile(int $userId, array $data): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        $fields = [];
        if (array_key_exists('firstName', $data)) {
            $fields['first_name'] = $data['firstName'];
        }
        if (array_key_exists('lastName', $data)) {
            $fields['last_name'] = $data['lastName'];
        }
        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'];
            $fields['phone'] = ($phone === null || trim((string) $phone) === '') ? null : trim((string) $phone);
        }

        if ($fields !== []) {
            $this->userRepository->update($userId, $fields);
        }

        return [
            'id' => $user->id(),
            'email' => $user->email(),
            'firstName' => $fields['first_name'] ?? $user->firstName(),
            'lastName' => $fields['last_name'] ?? $user->lastName(),
            'phone' => array_key_exists('phone', $fields) ? $fields['phone'] : $user->phone(),
        ];
    }

    /**
     * Attempt to log in a user.
     * @param string $email
     * @param string $password
     * @return User|null authenticated user or null if credentials invalid
     */
    public function login(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            // User not found
            return null;
        }

        if (!password_verify($password, $user->passwordHash())) {
            // Invalid password
            return null;
        }

        if ($user->isActive() !== 1) {
            // Account deactivated
            return null;
        }

        return $user;
    }
}