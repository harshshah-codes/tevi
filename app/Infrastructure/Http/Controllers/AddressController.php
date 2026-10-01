<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AddressService;
use InvalidArgumentException;

final class AddressController
{
    private const REQUIRED = ['recipientName', 'phone', 'line1', 'city', 'state', 'pincode'];

    public function __construct(
        private readonly AddressService $addressService,
    ) {
    }

    /**
     * GET /api/addresses
     */
    public function index(): void
    {
        $this->header();
        $userId = $this->requireUser();

        $addresses = $this->addressService->listForUser($userId);
        $default = $this->addressService->defaultForUser($userId);

        $this->respond([
            'success'   => true,
            'addresses' => array_map(static fn($a) => $a->toArray(), $addresses),
            'defaultId' => $default?->id(),
        ]);
    }

    /**
     * POST /api/addresses
     */
    public function store(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $data = $this->body();
        $missing = $this->missingFields($data);
        if ($missing !== []) {
            $this->respond(['success' => false, 'error' => 'Missing field: ' . $missing[0]], 422);
        }

        try {
            $id = $this->addressService->createForUser($userId, $data);
        } catch (InvalidArgumentException $e) {
            $this->respond(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->respond(['success' => false, 'error' => 'Could not save address: ' . $e->getMessage()], 500);
        }

        $address = $this->addressService->findForUser($id, $userId);

        $this->respond(['success' => true, 'address' => $address?->toArray()]);
    }

    /**
     * PUT /api/addresses/{id}
     */
    public function update(int $id): void
    {
        $this->header();

        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $data = $this->body();

        if ($this->addressService->findForUser($id, $userId) === null) {
            $this->respond(['success' => false, 'error' => 'Address not found'], 404);
        }

        try {
            $this->addressService->updateForUser($id, $userId, $data);
        } catch (InvalidArgumentException $e) {
            $this->respond(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->respond(['success' => false, 'error' => 'Could not update address: ' . $e->getMessage()], 500);
        }

        $address = $this->addressService->findForUser($id, $userId);

        $this->respond(['success' => true, 'address' => $address?->toArray()]);
    }

    /**
     * POST /api/addresses/{id}/default
     */
    public function makeDefault(int $id): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();

        if ($this->addressService->findForUser($id, $userId) === null) {
            $this->respond(['success' => false, 'error' => 'Address not found'], 404);
        }

        $this->addressService->setDefaultForUser($id, $userId);

        $this->respond(['success' => true, 'address' => $this->addressService->findForUser($id, $userId)?->toArray()]);
    }

    /**
     * DELETE /api/addresses/{id}
     */
    public function destroy(int $id): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();

        if (!$this->addressService->deleteForUser($id, $userId)) {
            $this->respond(['success' => false, 'error' => 'Address not found'], 404);
        }

        $default = $this->addressService->defaultForUser($userId);

        $this->respond(['success' => true, 'defaultId' => $default?->id()]);
    }

    /**
     * GET /api/addresses/default
     */
    public function showDefault(): void
    {
        $this->header();
        $userId = $this->requireUser();

        $default = $this->addressService->defaultForUser($userId);

        $this->respond(['success' => true, 'address' => $default?->toArray()]);
    }

    private function header(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    private function respond(array $payload, int $status = 200): void
    {
        ob_end_clean();
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private function requireUser(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->respond(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        return (int) $_SESSION['user_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($data)) {
            $this->respond(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function missingFields(array $data): array
    {
        $missing = [];
        foreach (self::REQUIRED as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
