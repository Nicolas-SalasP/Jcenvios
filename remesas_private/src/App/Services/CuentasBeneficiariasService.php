<?php
namespace App\Services;

use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\TipoBeneficiarioRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Services\NotificationService;
use App\Services\PDFService;
use App\Services\FileHandlerService;
use Exception;

class CuentasBeneficiariasService
{
    private CuentasBeneficiariasRepository $cuentasRepo;
    private NotificationService $notificationService;
    private TransactionRepository $txRepo;
    private TipoBeneficiarioRepository $tipoBeneficiarioRepo;
    private TipoDocumentoRepository $tipoDocumentoRepo;
    private PDFService $pdfService;
    private FileHandlerService $fileHandler;

    public function __construct(
        CuentasBeneficiariasRepository $cuentasRepo,
        NotificationService $notificationService,
        TransactionRepository $txRepo,
        TipoBeneficiarioRepository $tipoBeneficiarioRepo,
        TipoDocumentoRepository $tipoDocumentoRepo,
        PDFService $pdfService,
        FileHandlerService $fileHandler
    ) {
        $this->cuentasRepo = $cuentasRepo;
        $this->notificationService = $notificationService;
        $this->txRepo = $txRepo;
        $this->tipoBeneficiarioRepo = $tipoBeneficiarioRepo;
        $this->tipoDocumentoRepo = $tipoDocumentoRepo;
        $this->pdfService = $pdfService;
        $this->fileHandler = $fileHandler;
    }

    public function getAccountsByUser(int $userId, ?int $paisId = null): array
    {
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
        if (!$cuenta) throw new Exception("Cuenta no encontrada.", 404);
        return $cuenta;
    }

    public function addAccount(int $userId, array $data): int
    {
        $this->validateCommonFields($data);
        $data['UserID'] = $userId;
        $incluirCuenta = !empty($data['incluirCuentaBancaria']) && filter_var($data['incluirCuentaBancaria'], FILTER_VALIDATE_BOOLEAN);
        $incluirMovil = !empty($data['incluirPagoMovil']) && filter_var($data['incluirPagoMovil'], FILTER_VALIDATE_BOOLEAN);

        if (!$incluirCuenta && !$incluirMovil) {
            throw new Exception("Debes seleccionar al menos una opción (Cuenta o Pago Móvil).", 400);
        }

        try {
            if ($incluirCuenta && $incluirMovil) {
                if (empty($data['numeroCuenta'])) throw new Exception("El número de cuenta es obligatorio.", 400);
                if (empty($data['numeroTelefono'])) throw new Exception("El teléfono es obligatorio para el Pago Móvil.", 400);
                $data['tipoBeneficiario'] = 'Cuenta Bancaria';
                $prepared = $this->prepareSingleRecord($data, false); 
                
                $newId = $this->cuentasRepo->create($prepared);
                $this->notificationService->logAdminAction($userId, 'Usuario creó Beneficiario Unificado (Cuenta+PM)', "ID: {$newId}");
                return $newId;
            }
            if ($incluirCuenta) {
                if (empty($data['numeroCuenta'])) throw new Exception("El número de cuenta es obligatorio.", 400);
                
                $data['numeroTelefono'] = null;
                $data['tipoBeneficiario'] = 'Cuenta Bancaria';

                $prepared = $this->prepareSingleRecord($data, false);
                $newId = $this->cuentasRepo->create($prepared);
                $this->notificationService->logAdminAction($userId, 'Usuario creó Cuenta Bancaria', "ID: {$newId}");
                return $newId;
            }

            if ($incluirMovil) {
                if (empty($data['numeroTelefono'])) throw new Exception("El teléfono es obligatorio.", 400);

                $data['tipoBeneficiario'] = 'Pago Móvil';
                $prepared = $this->prepareSingleRecord($data, true);
                
                $newId = $this->cuentasRepo->create($prepared);
                $this->notificationService->logAdminAction($userId, 'Usuario creó Pago Móvil', "ID: {$newId}");
                return $newId;
            }

            return 0;

        } catch (Exception $e) {
            error_log("Error addAccount: " . $e->getMessage());
            throw new Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateAccount(int $userId, int $cuentaId, array $data): bool
    {
        $this->validateCommonFields($data);
        $cuenta = $this->cuentasRepo->findByIdAndUserId($cuentaId, $userId);
        if (!$cuenta) throw new Exception("Cuenta no encontrada.");

        $data['UserID'] = $userId;
        $isSoloPagoMovil = (strtoupper($data['numeroCuenta'] ?? '') === 'PAGO MOVIL') || (strtoupper($cuenta['NumeroCuenta']) === 'PAGO MOVIL');
        if (empty($data['tipoBeneficiario'])) {
            $data['tipoBeneficiario'] = $isSoloPagoMovil ? 'Pago Móvil' : 'Cuenta Bancaria';
        }
        
        $prepared = $this->prepareSingleRecord($data, $isSoloPagoMovil);
        $hasHistory = $this->txRepo->isAccountUsedInCompletedOrders($cuentaId);

        try {
            if ($hasHistory) {
                $prepared['paisID'] = $cuenta['PaisID'];
                $newCuentaId = $this->cuentasRepo->create($prepared);

                if ($newCuentaId > 0) {
                    $this->txRepo->migratePendingOrdersToNewAccount($cuentaId, $newCuentaId);
                    $this->cuentasRepo->softDelete($cuentaId);
                    $this->regeneratePdfsForPendingOrders($newCuentaId);
                    return true;
                }
                throw new Exception("Error al versionar cuenta.");
            } else {
                $updated = $this->cuentasRepo->update($cuentaId, $prepared);
                if ($updated) {
                    $this->regeneratePdfsForPendingOrders($cuentaId);
                }
                return $updated;
            }
        } catch (Exception $e) {
            error_log("Error updateAccount: " . $e->getMessage());
            throw new Exception("Error al actualizar.", 500);
        }
    }

    public function deleteAccount(int $userId, int $cuentaId): bool
    {
        return $this->cuentasRepo->softDelete($cuentaId);
    }

    private function validateCommonFields(array $data): void
    {
        $requiredFields = ['alias', 'primerNombre', 'primerApellido', 'tipoDocumento', 'numeroDocumento', 'nombreBanco'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field]))
                throw new Exception("El campo '$field' es obligatorio.", 400);
        }
    }

    private function prepareSingleRecord(array $data, bool $isSoloPagoMovil): array
    {
        if (!$isSoloPagoMovil) {
            if (!empty($data['numeroCuenta'])) {
                $clean = preg_replace('/[^0-9]/', '', $data['numeroCuenta']);
                if (strlen($clean) > 20) throw new Exception("La cuenta excede 20 dígitos.", 400);
                $data['numeroCuenta'] = $clean;
            }
        } else {
            $data['numeroCuenta'] = 'PAGO MOVIL';
        }
        if (isset($data['tipoBeneficiario'])) {
            $tbID = $this->tipoBeneficiarioRepo->findIdByName($data['tipoBeneficiario']);
            $data['tipoBeneficiarioID'] = $tbID ?: 1;
        } else {
            $data['tipoBeneficiarioID'] = 1; 
        }

        if (isset($data['tipoDocumento']) && is_numeric($data['tipoDocumento'])) {
            $data['titularTipoDocumentoID'] = (int) $data['tipoDocumento'];
        } else {
            $tdID = $this->tipoDocumentoRepo->findIdByName($data['tipoDocumento'] ?? '');
            $data['titularTipoDocumentoID'] = $tdID ?: 0;
        }

        $data['segundoNombre'] = $data['segundoNombre'] ?? null;
        $data['segundoApellido'] = $data['segundoApellido'] ?? null;

        return $data;
    }

    private function regeneratePdfsForPendingOrders(int $cuentaBeneficiariaId): void
    {
        $pendingTransactions = $this->txRepo->getPendingTransactionsByAccountId($cuentaBeneficiariaId);

        foreach ($pendingTransactions as $tx) {
            try {
                $fullTxData = $this->txRepo->getFullTransactionDetails($tx['TransaccionID']);
                
                if ($fullTxData) {
                    $pdfContent = $this->pdfService->generateOrder($fullTxData);
                    $this->fileHandler->savePdfTemporarily($pdfContent, $tx['TransaccionID']);
                }
            } catch (Exception $e) {
                error_log("No se pudo regenerar PDF para TX #{$tx['TransaccionID']}: " . $e->getMessage());
            }
        }
    }
}