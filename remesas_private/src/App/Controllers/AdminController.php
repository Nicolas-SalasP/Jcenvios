<?php
namespace App\Controllers;

use App\Services\TransactionService;
use App\Services\PricingService;
use App\Services\DashboardService;
use App\Services\UserService;
use App\Repositories\RolRepository;
use App\Repositories\CuentasAdminRepository;
use Exception;

class AdminController extends BaseController
{
    private TransactionService $txService;
    private PricingService $pricingService;
    private UserService $userService;
    private DashboardService $dashboardService;
    private RolRepository $rolRepo;
    private CuentasAdminRepository $cuentasAdminRepo;

    public function __construct(
        TransactionService $txService,
        PricingService $pricingService,
        UserService $userService,
        DashboardService $dashboardService,
        RolRepository $rolRepo,
        CuentasAdminRepository $cuentasAdminRepo
    ) {
        $this->txService = $txService;
        $this->pricingService = $pricingService;
        $this->userService = $userService;
        $this->dashboardService = $dashboardService;
        $this->rolRepo = $rolRepo;
        $this->cuentasAdminRepo = $cuentasAdminRepo;
    }

    // --- MÉTODOS PARA ADMIN Y OPERADORES (Gestión de Órdenes) ---

    public function rejectTransaction(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        $data = $this->getJsonInput();

        $txId = (int) ($data['transactionId'] ?? 0);
        $reason = $data['reason'] ?? '';
        $actionType = $data['actionType'] ?? 'cancel';

        if ($txId <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de transacción inválido.'], 400);
            return;
        }

        $this->txService->adminRejectPayment($adminId, $txId, $reason, $actionType === 'retry');
        $this->sendJsonResponse(['success' => true]);
    }

    public function processTransaction(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        $data = $this->getJsonInput();
        $this->txService->adminConfirmPayment($adminId, (int) ($data['transactionId'] ?? 0));
        $this->sendJsonResponse(['success' => true]);
    }

    public function adminUploadProof(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        $transactionId = (int) ($_POST['transactionId'] ?? 0);
        $fileData = $_FILES['receiptFile'] ?? null;
        $comisionDestino = isset($_POST['comisionDestino']) ? (float) $_POST['comisionDestino'] : 0.00;

        if ($transactionId <= 0 || $fileData === null) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de transacción inválido o archivo no recibido.'], 400);
            return;
        }

        try {
            $this->txService->handleAdminProofUpload($adminId, $transactionId, $fileData, $comisionDestino);
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }

    // --- MÉTODOS SOLO PARA ADMIN (Configuración y Usuarios) ---

    public function upsertRate(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();

        try {
            // Se invoca el servicio que ahora retorna los items de la ruta para el refresco dinámico
            $resultData = $this->pricingService->adminUpsertRate($adminId, $data);

            // Retornamos el objeto 'data' que el JS espera (items, routeKey, etc)
            $this->sendJsonResponse([
                'success' => true,
                'data' => $resultData
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() ?: 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    public function deleteRate(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $tasaId = (int) ($data['tasaId'] ?? 0);

        try {
            $this->pricingService->adminDeleteRate($adminId, $tasaId);
            $this->sendJsonResponse(['success' => true, 'message' => 'Tasa eliminada correctamente.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function addPais(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        try {
            $this->pricingService->adminAddCountry($adminId, $data['nombrePais'] ?? '', $data['codigoMoneda'] ?? '', $data['rol'] ?? '');
            $this->sendJsonResponse(['success' => true], 201);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function updatePais(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        try {
            $this->pricingService->adminUpdateCountry($adminId, (int) ($data['paisId'] ?? 0), $data['nombrePais'] ?? '', $data['codigoMoneda'] ?? '');
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function updatePaisRol(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $this->pricingService->adminUpdateCountryRole($adminId, (int) ($data['paisId'] ?? 0), $data['newRole'] ?? '');
        $this->sendJsonResponse(['success' => true]);
    }

    public function togglePaisStatus(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $newStatus = (bool) ($data['newStatus'] ?? false);
        $this->pricingService->adminToggleCountryStatus($adminId, (int) ($data['paisId'] ?? 0), $newStatus);
        $this->sendJsonResponse(['success' => true]);
    }

    public function updateVerificationStatus(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $targetUserId = (int) ($data['userId'] ?? 0);
        $newStatusName = (string) ($data['newStatus'] ?? '');

        if ($targetUserId <= 0 || empty($newStatusName)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Datos inválidos.'], 400);
            return;
        }
        $this->userService->updateVerificationStatus($adminId, $targetUserId, $newStatusName);
        $this->sendJsonResponse(['success' => true, 'message' => 'Estado actualizado.']);
    }

    public function getDashboardStats(): void
    {
        $this->ensureAdmin();
        $stats = $this->dashboardService->getAdminDashboardStats();
        $this->sendJsonResponse(['success' => true, 'stats' => $stats]);
    }

    public function updateUserRole(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $targetUserId = (int) ($data['userId'] ?? 0);
        $newRoleId = (int) ($data['newRoleId'] ?? 0);

        if ($targetUserId <= 0 || $newRoleId <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Datos inválidos.'], 400);
            return;
        }

        try {
            $this->userService->adminUpdateUserRole($adminId, $targetUserId, $newRoleId);
            $this->sendJsonResponse(['success' => true, 'message' => 'Rol actualizado.']);
        } catch (Exception $e) {
            $code = $e->getCode() ?: 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    public function deleteUser(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $targetUserId = (int) ($data['userId'] ?? 0);

        if ($targetUserId <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de usuario inválido.'], 400);
            return;
        }
        $this->userService->adminDeleteUser($adminId, $targetUserId);
        $this->sendJsonResponse(['success' => true, 'message' => 'Usuario eliminado.']);
    }

    public function getCuentasAdmin(): void
    {
        $this->ensureAdmin();
        $cuentas = $this->cuentasAdminRepo->findAll();
        $this->sendJsonResponse(['success' => true, 'cuentas' => $cuentas]);
    }

    public function saveCuentaAdmin(): void
    {
        $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();

        if (empty($data['banco']) || empty($data['titular']) || empty($data['numeroCuenta']) || empty($data['rut'])) {
            throw new Exception("Faltan campos obligatorios.", 400);
        }

        if (isset($data['id']) && $data['id'] > 0) {
            $this->cuentasAdminRepo->update((int) $data['id'], $data);
        } else {
            $this->cuentasAdminRepo->create($data);
        }
        $this->sendJsonResponse(['success' => true]);
    }

    public function deleteCuentaAdmin(): void
    {
        $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $this->cuentasAdminRepo->delete((int) $data['id']);
        $this->sendJsonResponse(['success' => true]);
    }

    public function toggleUserBlock(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $targetUserId = (int) ($data['userId'] ?? 0);
        $newStatus = $data['newStatus'] ?? '';

        if ($targetUserId <= 0 || !in_array($newStatus, ['active', 'blocked'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Datos inválidos.'], 400);
            return;
        }

        $this->userService->toggleUserBlock($adminId, $targetUserId, $newStatus);
        $this->sendJsonResponse(['success' => true]);
    }

    public function updateTxCommission(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        $data = $this->getJsonInput();

        $txId = (int) ($data['transactionId'] ?? 0);
        if ($txId <= 0 || !isset($data['newCommission'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Datos inválidos.'], 400);
            return;
        }

        $newCommission = (float) $data['newCommission'];
        if ($newCommission < 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'La comisión no puede ser negativa.'], 400);
            return;
        }

        try {
            $this->txService->adminUpdateCommission($adminId, $txId, $newCommission);
            $this->sendJsonResponse(['success' => true, 'message' => 'Comisión actualizada correctamente.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function adminUpdateUser(): void
    {
        $adminId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();

        try {
            $this->userService->adminUpdateUserData($adminId, $data);
            $this->sendJsonResponse(['success' => true, 'message' => 'Datos del usuario actualizados.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function updateBcvRate(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();

        $newValue = (float) ($data['rate'] ?? 0);

        try {
            $this->pricingService->updateBcvRate($adminId, $newValue);
            $this->sendJsonResponse(['success' => true, 'message' => 'Tasa BCV actualizada.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}