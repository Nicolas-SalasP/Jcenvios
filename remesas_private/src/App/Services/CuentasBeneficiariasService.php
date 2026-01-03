<?php
namespace App\Services;

use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\TipoBeneficiarioRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Services\NotificationService;
use Exception;

class CuentasBeneficiariasService
{
    private CuentasBeneficiariasRepository $cuentasRepo;
    private NotificationService $notificationService;
    private TipoBeneficiarioRepository $tipoBeneficiarioRepo;
    private TransactionRepository $txRepo;
    private TipoDocumentoRepository $tipoDocumentoRepo;

    public function __construct(
        CuentasBeneficiariasRepository $cuentasRepo,
        NotificationService $notificationService,
        TransactionRepository $txRepo,
        TipoBeneficiarioRepository $tipoBeneficiarioRepo,
        TipoDocumentoRepository $tipoDocumentoRepo
    ) {
        $this->cuentasRepo = $cuentasRepo;
        $this->notificationService = $notificationService;
        $this->txRepo = $txRepo;
        $this->tipoBeneficiarioRepo = $tipoBeneficiarioRepo;
        $this->tipoDocumentoRepo = $tipoDocumentoRepo;
    }

    public function getAccountsByUser(int $userId, ?int $paisId = null): array
    {
        // El repositorio ya filtra por Activo = 1
        $cuentas = $this->cuentasRepo->findByUserId($userId);

        if ($paisId !== null) {
            $cuentas = array_filter($cuentas, fn($cuenta) => isset($cuenta['PaisID']) && $cuenta['PaisID'] == $paisId);
            $cuentas = array_values($cuentas);
        }

        return $cuentas;
    }

    public function getAccountDetails(int $userId, int $cuentaId): ?array
    {
        $cuenta = $this->cuentasRepo->findByIdAndUserId($cuentaId, $userId);
        if (!$cuenta) {
            throw new Exception("Cuenta no encontrada o no te pertenece.", 404);
        }
        return $cuenta;
    }

    // Método unificado para Crear
    public function addAccount(int $userId, array $data): int
    {
        $data = $this->validateAndPrepareBeneficiaryData($data);
        $data['UserID'] = $userId;

        if (!isset($data['paisID']) || empty($data['paisID'])) {
            throw new Exception("El campo 'paisID' es obligatorio.", 400);
        }

        try {
            // Usamos createAndReturnId para consistencia
            $newId = $this->cuentasRepo->createAndReturnId($data);
            $this->notificationService->logAdminAction($userId, 'Usuario añadió cuenta', "Alias: {$data['alias']} - ID: {$newId}");
            return $newId;
        } catch (Exception $e) {
            error_log("Error al crear cuenta: " . $e->getMessage());
            throw new Exception("Error al guardar el beneficiario.", 500);
        }
    }

    // MÉTODO CLAVE: Actualización Inteligente (Versionado)
    public function updateAccount(int $userId, int $cuentaId, array $data): bool
    {
        // 1. Validar datos primero
        $data = $this->validateAndPrepareBeneficiaryData($data);

        // 2. Verificar propiedad
        $cuenta = $this->cuentasRepo->findByIdAndUserId($cuentaId, $userId);
        if (!$cuenta) {
            throw new Exception("Cuenta no encontrada o acceso denegado.");
        }

        // 3. Verificar si tiene historial cerrado
        $hasHistory = $this->txRepo->isAccountUsedInCompletedOrders($cuentaId);

        try {
            if ($hasHistory) {
                // === CASO A: TIENE HISTORIAL (VERSIONAR) ===

                // Aseguramos datos críticos para la nueva cuenta
                $data['UserID'] = $userId;
                $data['paisID'] = $cuenta['PaisID']; // Mantener país original

                // Crear NUEVA cuenta
                $newCuentaId = $this->cuentasRepo->createAndReturnId($data);

                if ($newCuentaId > 0) {
                    // Migrar órdenes activas (Pendientes/Pausadas) a la nueva
                    $this->txRepo->migratePendingOrdersToNewAccount($cuentaId, $newCuentaId);

                    // Ocultar la vieja (Soft Delete)
                    $this->cuentasRepo->softDelete($cuentaId);

                    $this->notificationService->logAdminAction($userId, 'Usuario actualizó cuenta (Versionado)', "Old ID: $cuentaId -> New ID: $newCuentaId");
                    return true;
                } else {
                    throw new Exception("Error al crear la versión corregida.");
                }

            } else {
                // === CASO B: SIN HISTORIAL (UPDATE SIMPLE) ===
                $success = $this->cuentasRepo->update($cuentaId, $userId, $data);
                if ($success) {
                    $this->notificationService->logAdminAction($userId, 'Usuario actualizó cuenta (Directo)', "ID: $cuentaId");
                }
                return $success;
            }
        } catch (Exception $e) {
            error_log("Error updateAccount: " . $e->getMessage());
            throw new Exception("Error al actualizar beneficiario.", 500);
        }
    }

    // Soft Delete para el usuario
    public function deleteAccount(int $userId, int $cuentaId): bool
    {
        try {
            // Verificar si tiene historial
            $hasHistory = $this->txRepo->isAccountUsedInCompletedOrders($cuentaId);

            if ($hasHistory) {
                // Si tiene historial, solo Soft Delete (Activo=0)
                return $this->cuentasRepo->softDelete($cuentaId);
            } else {
                // Si está limpia, podemos borrar físico o soft delete (preferimos soft por seguridad)
                return $this->cuentasRepo->softDelete($cuentaId);
            }
        } catch (Exception $e) {
            error_log("Error deleteAccount: " . $e->getMessage());
            throw new Exception("Error al eliminar cuenta.", 500);
        }
    }

    private function validateAndPrepareBeneficiaryData(array $data): array
    {
        // Mapeo de campos frontend -> backend si es necesario
        // Asegúrate que tu JS envíe estos nombres o ajusta aquí
        $requiredFields = ['alias', 'tipoBeneficiario', 'primerNombre', 'primerApellido', 'tipoDocumento', 'numeroDocumento', 'nombreBanco'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field]))
                throw new Exception("El campo '$field' es obligatorio.", 400);
        }

        // Validación Pago Móvil vs Cuenta
        if (strtoupper($data['numeroCuenta'] ?? '') === 'PAGO MOVIL') {
            if (empty($data['numeroTelefono']))
                throw new Exception("El teléfono es obligatorio para Pago Móvil.", 400);
        } else {
            if (empty($data['numeroCuenta']))
                throw new Exception("El número de cuenta es obligatorio.", 400);
            $clean = preg_replace('/[^0-9]/', '', $data['numeroCuenta']);
            if (strlen($clean) > 20)
                throw new Exception("Cuenta excede 20 dígitos.", 400);
        }

        // IDs Relacionales (TipoBeneficiario y TipoDocumento)
        $tbID = $this->tipoBeneficiarioRepo->findIdByName($data['tipoBeneficiario']);
        $data['tipoBeneficiarioID'] = $tbID ?: (int) $data['tipoBeneficiario'];

        $tdID = $this->tipoDocumentoRepo->findIdByName($data['tipoDocumento']);
        $data['titularTipoDocumentoID'] = $tdID ?: (int) $data['tipoDocumento'];

        // Limpieza final
        $data['segundoNombre'] = $data['segundoNombre'] ?? null;
        $data['segundoApellido'] = $data['segundoApellido'] ?? null;

        return $data;
    }
}