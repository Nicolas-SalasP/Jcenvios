<?php
namespace App\Controllers;

use App\Services\TransactionService;
use App\Services\PricingService;
use App\Services\UserService;
use App\Services\DashboardService;
use App\Services\SystemSettingsService; 
use App\Repositories\RolRepository;
use App\Repositories\CuentasAdminRepository;
use App\Services\FileHandlerService;
use Exception;

class AdminController extends BaseController
{
    private TransactionService $txService;
    private PricingService $pricingService;
    private UserService $userService;
    private DashboardService $dashboardService;
    private RolRepository $rolRepo;
    private CuentasAdminRepository $cuentasAdminRepo;
    private SystemSettingsService $settingsService;
    private FileHandlerService $fileHandler;

    public function __construct(
        TransactionService $txService,
        PricingService $pricingService,
        UserService $userService,
        DashboardService $dashboardService,
        RolRepository $rolRepo,
        CuentasAdminRepository $cuentasAdminRepo,
        SystemSettingsService $settingsService,
        FileHandlerService $fileHandler
    ) {
        $this->txService = $txService;
        $this->pricingService = $pricingService;
        $this->userService = $userService;
        $this->dashboardService = $dashboardService;
        $this->rolRepo = $rolRepo;
        $this->cuentasAdminRepo = $cuentasAdminRepo;
        $this->settingsService = $settingsService;
        $this->fileHandler = $fileHandler;
    }

    // --- GESTIÓN DE VACACIONES ---

    public function getHolidays(): void
    {
        $this->ensureAdmin();
        $holidays = $this->settingsService->getHolidays();
        $this->sendJsonResponse(['success' => true, 'holidays' => $holidays]);
    }

    public function addHoliday(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        
        $data = $this->getJsonInput();
        
        try {
            $this->settingsService->addHoliday(
                $adminId, 
                $data['inicio'] ?? '', 
                $data['fin'] ?? '', 
                $data['motivo'] ?? ''
            );
            $this->sendJsonResponse(['success' => true, 'message' => 'Feriado programado correctamente.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function deleteHoliday(): void
    {
        $adminId = $this->ensureLoggedIn(); 
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $holidayId = (int)($data['id'] ?? 0);
        
        try {
            $this->settingsService->deleteHoliday($holidayId, $adminId);
            $this->sendJsonResponse(['success' => true, 'message' => 'Feriado eliminado.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // --- GESTIÓN DE TRANSACCIONES ---

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

        try {
            $this->txService->adminRejectPayment($adminId, $txId, $reason, $actionType === 'retry');
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function processTransaction(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        $data = $this->getJsonInput();
        try {
            $this->txService->adminConfirmPayment($adminId, (int) ($data['transactionId'] ?? 0));
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function adminUploadProof(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        if (!isset($_FILES['receiptFile']) || empty($_POST['transactionId'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Datos incompletos.'], 400);
            return;
        }

        $transactionId = (int) $_POST['transactionId'];
        $fileData = $_FILES['receiptFile'];
        $comisionDestino = isset($_POST['comisionDestino']) ? (float) $_POST['comisionDestino'] : 0.00;
        $cuentaSalidaId = !empty($_POST['cuentaSalidaID']) ? (int) $_POST['cuentaSalidaID'] : null;

        try {
            $success = $this->txService->handleAdminProofUpload(
                $adminId, 
                $transactionId, 
                $fileData, 
                $comisionDestino, 
                $cuentaSalidaId
            );
            
            if ($success) {
                $this->sendJsonResponse(['success' => true]);
            } else {
                $this->sendJsonResponse(['success' => false, 'error' => 'No se pudo actualizar la orden.'], 500);
            }
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }

    public function uploadProof(): void
    {
        $this->adminUploadProof();
    }

    public function pauseTransaction(): void
    {
        $this->ensureAdminOrOperator(); 
        
        try {
            $data = $this->getJsonInput();
            $txId = (int) ($data['txId'] ?? 0);
            $motivo = trim($data['motivo'] ?? '');

            if ($txId <= 0 || empty($motivo)) {
                throw new Exception("ID de transacción o motivo no válidos.");
            }

            $success = $this->txService->pause($txId, $motivo, 6);

            if (!$success) {
                throw new Exception("No se pudo pausar. Verifique que la orden esté 'En Proceso' (ID 3).");
            }

            $this->sendJsonResponse(['success' => true, 'message' => 'Orden pausada correctamente.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function resumeTransactionAdmin(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        
        try {
            $data = $this->getJsonInput();
            $txId = (int) ($data['txId'] ?? 0);
            $nota = trim($data['nota'] ?? '');

            if ($txId <= 0) {
                throw new Exception("ID inválido");
            }

            $success = $this->txService->adminResumeTransaction($txId, $adminId, $nota);
            
            if ($success) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Orden reanudada.']);
            } else {
                throw new Exception("No se pudo reanudar. Verifique que esté en estado 'Pausado' (ID 6).");
            }
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function resumeTransaction(): void
    {
        $this->resumeTransactionAdmin();
    }

    public function authorizeTransaction(): void
    {
        $adminId = $this->ensureAdminOrOperator();
        
        try {
            $data = $this->getJsonInput();
            $txId = (int) ($data['transactionId'] ?? 0);

            if ($txId <= 0) throw new Exception("ID inválido");

            $success = $this->txService->authorizeRiskyTransaction($txId, $adminId);

            if ($success) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Riesgo autorizado.']);
            } else {
                throw new Exception("No se pudo autorizar. Verifique el estado.");
            }
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
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

    public function updateCommission(): void
    {
        $this->updateTxCommission();
    }

    // --- CONFIGURACIÓN Y USUARIOS ---

    public function upsertRate(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();

        try {
            $resultData = $this->pricingService->adminUpsertRate($adminId, $data);
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

    public function adminUpdateUserDoc(): void
    {
        $this->ensureLoggedIn();
        $this->ensureAdmin();

        if (!isset($_FILES['newDocFile']) || $_FILES['newDocFile']['error'] !== UPLOAD_ERR_OK) {
            $msg = isset($_FILES['newDocFile']) ? 'Error subida PHP: ' . $_FILES['newDocFile']['error'] : 'No llegó el archivo (newDocFile)';
            $this->sendJsonResponse(['success' => false, 'error' => $msg], 400);
            return;
        }
        if (empty($_POST['userId'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Falta el ID del usuario (userId está vacío).'], 400);
            return;
        }
        if (empty($_POST['docType'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Falta el tipo de documento (docType).'], 400);
            return;
        }

        $userId = (int) $_POST['userId'];
        $docType = $_POST['docType'];
        $fileData = $_FILES['newDocFile'];

        try {
            $newPath = '';

            if ($docType === 'perfil') {
                $newPath = $this->fileHandler->saveUserProfileImage($fileData, $userId);
                $this->userService->updateProfilePicPath($userId, $newPath);
            } elseif ($docType === 'frente' || $docType === 'reverso') {
                $newPath = $this->fileHandler->saveVerificationDoc($fileData, $userId, $docType);
                $this->userService->updateVerificationDocPath($userId, $docType, $newPath);
            } else {
                throw new Exception("Tipo de documento no válido.");
            }

            $this->sendJsonResponse(['success' => true, 'newPath' => $newPath]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // --- GESTIÓN DE CUENTAS ADMIN ---

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
        $id = $data['CuentaAdminID'] ?? $data['id'] ?? null;
        $rolId = isset($data['RolCuentaID']) ? (int)$data['RolCuentaID'] : (isset($data['rolCuentaId']) ? (int)$data['rolCuentaId'] : 1);
        
        $repoData = [
            'id' => $id,
            'rolCuentaId' => $rolId,
            'paisId' => $data['PaisID'] ?? $data['paisId'] ?? 0,
            'banco' => $data['Banco'] ?? $data['banco'] ?? '',
            'titular' => $data['Titular'] ?? $data['titular'] ?? '',
            'tipoCuenta' => $data['TipoCuenta'] ?? $data['tipoCuenta'] ?? '',
            'numeroCuenta' => $data['NumeroCuenta'] ?? $data['numeroCuenta'] ?? '',
            'rut' => $data['RUT'] ?? $data['rut'] ?? '',
            'email' => $data['Email'] ?? $data['email'] ?? '',
            'colorHex' => $data['ColorHex'] ?? $data['colorHex'] ?? '#000000',
            'saldoInicial' => $data['saldoInicial'] ?? 0.00,
            'activo' => $data['Activo'] ?? $data['activo'] ?? 1,
            'formaPagoId' => null,
            'instrucciones' => '' 
        ];

        if (empty($repoData['banco']) || empty($repoData['titular']) || empty($repoData['numeroCuenta'])) {
            throw new Exception("Banco, Titular y Número de Cuenta son obligatorios.", 400);
        }
        if ($rolId === 2) {
            $repoData['formaPagoId'] = 1; 
            $repoData['instrucciones'] = null;
        } else {
            $fpId = $data['FormaPagoID'] ?? $data['formaPagoId'] ?? 0;
            if (empty($fpId)) {
                throw new Exception("Debes seleccionar una Forma de Pago para cuentas de Origen o Mixtas.", 400);
            }
            $repoData['formaPagoId'] = (int)$fpId;
            $repoData['instrucciones'] = $data['Instrucciones'] ?? $data['instrucciones'] ?? '';
        }
        if ($id && $id > 0) {
            $this->cuentasAdminRepo->update((int)$id, $repoData);
        } else {
            $this->cuentasAdminRepo->create($repoData);
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

    // --- BCV Y AJUSTE GLOBAL ---

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

    public function applyGlobalAdjustment(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $percent = (float)($data['percent'] ?? 0);

        try {
            $count = $this->pricingService->applyGlobalAdjustment($adminId, $percent);
            $this->sendJsonResponse([
                'success' => true, 
                'message' => "Ajuste del {$percent}% aplicado correctamente a {$count} rutas activas."
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveGlobalAdjustmentSettings(): void
    {
        $adminId = $this->ensureLoggedIn();
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $percent = (float)($data['percent'] ?? 0);
        $time = (string)($data['time'] ?? '20:30');

        try {
            $this->pricingService->saveGlobalAdjustmentSettings($adminId, $percent, $time);
            $this->sendJsonResponse(['success' => true, 'message' => 'Configuración de ajuste global guardada.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}