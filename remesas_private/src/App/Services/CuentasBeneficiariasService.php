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
        if (!$cuenta)
            throw new Exception("Cuenta no encontrada.", 404);
        return $cuenta;
    }

    public function addAccount(int $userId, array $data): int
    {
        $this->validateCommonFields($data);
        
        $paisId = isset($data['paisID']) ? (int)$data['paisID'] : 0;
        $nombreBanco = trim($data['nombreBanco'] ?? '');
        $data['cci'] = isset($data['cci']) ? trim($data['cci']) : null;
        $numeroCuenta = isset($data['numeroCuenta']) ? trim($data['numeroCuenta']) : null;
        
        // 1. COLOMBIA (ID 2)
        if ($paisId === 2) {
            $bancoLower = strtolower($nombreBanco);
            if ($bancoLower === 'nequi' || $bancoLower === 'daviplata') {
                if (empty($data['phoneNumber'])) throw new Exception("Para $nombreBanco se requiere el número de celular.", 400);
                $data['numeroCuenta'] = $data['phoneNumber'];
                $data['tipoBeneficiario'] = 'Pago Móvil'; 
                $isMobile = true;
            } else {
                // Bancolombia, etc.
                if (empty($numeroCuenta)) throw new Exception("El número de cuenta es obligatorio para $nombreBanco.", 400);
                $data['tipoBeneficiario'] = 'Cuenta Bancaria';
                $isMobile = false;
            }
        }
        // 2. VENEZUELA (ID 3)
        elseif ($paisId === 3) {
            $incluirCuenta = !empty($data['incluirCuentaBancaria']) && filter_var($data['incluirCuentaBancaria'], FILTER_VALIDATE_BOOLEAN);
            $incluirMovil = !empty($data['incluirPagoMovil']) && filter_var($data['incluirPagoMovil'], FILTER_VALIDATE_BOOLEAN);

            if ($incluirMovil) {
                if (empty($data['phoneNumber'])) throw new Exception("El teléfono es obligatorio para Pago Móvil.", 400);
                $data['tipoBeneficiario'] = 'Pago Móvil'; // Prioridad visual
                $isMobile = true;
            } 
            if ($incluirCuenta) {
                if (empty($numeroCuenta)) throw new Exception("El número de cuenta es obligatorio.", 400);
                if (!$incluirMovil) { // Si solo es cuenta
                    $data['tipoBeneficiario'] = 'Cuenta Bancaria';
                    $isMobile = false;
                }
            }
            if (!$incluirCuenta && !$incluirMovil) throw new Exception("Debes registrar al menos una cuenta o pago móvil.", 400);
        }
        // 3. PERÚ (ID 4)
        elseif ($paisId === 4) {
            $esBilletera = in_array($nombreBanco, ['Yape', 'Plin']);
            $esInterbank = ($nombreBanco === 'Interbank');
            
            if ($esBilletera) {
                if (empty($data['phoneNumber'])) throw new Exception("Para $nombreBanco se requiere el número de celular.", 400);
                $data['numeroCuenta'] = $data['phoneNumber'];
                $data['tipoBeneficiario'] = 'Pago Móvil';
                $isMobile = true;
            } else {
                if (empty($numeroCuenta)) throw new Exception("El número de cuenta es obligatorio.", 400);
                
                // Si NO es Interbank (es decir, es "Otro Banco"), EXIGIR CCI
                if (!$esInterbank) {
                    if (empty($data['cci'])) {
                        throw new Exception("Para transferencias a '$nombreBanco' en Perú, el CCI es obligatorio.", 400);
                    }
                }
                $data['tipoBeneficiario'] = 'Cuenta Bancaria';
                $isMobile = false;
            }
        }
        // 4. RESTO (ID 1, 5, etc.)
        else {
            if (empty($numeroCuenta)) throw new Exception("El número de cuenta es obligatorio.", 400);
            $data['tipoBeneficiario'] = 'Cuenta Bancaria';
            $isMobile = false;
        }

        try {
            $data['UserID'] = $userId;
            $prepared = $this->prepareSingleRecord($data, $isMobile);
            
            if (!empty($data['cci'])) {
                $prepared['cci'] = $data['cci'];
            }

            $newId = $this->cuentasRepo->create(
                $prepared['UserID'],
                (int)$prepared['paisID'],
                $prepared['alias'],
                $prepared['primerNombre'],
                $prepared['segundoNombre'],
                $prepared['primerApellido'],
                $prepared['segundoApellido'],
                $prepared['titularTipoDocumentoID'],
                $prepared['numeroDocumento'],
                $prepared['nombreBanco'],
                $prepared['numeroCuenta'],
                $prepared['cci'] ?? null,
                $prepared['numeroTelefono'] ?? null,
                $prepared['tipoBeneficiarioID']
            );

            $this->notificationService->logAdminAction($userId, 'Usuario creó Cuenta', "ID: {$newId} - Banco: {$nombreBanco}");
            return $newId;

        } catch (Exception $e) {
            error_log("Error addAccount: " . $e->getMessage());
            throw new Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateAccount(int $userId, int $cuentaId, array $data): bool
    {
        $this->validateCommonFields($data);
        $cuenta = $this->cuentasRepo->findByIdAndUserId($cuentaId, $userId);
        if (!$cuenta)
            throw new Exception("Cuenta no encontrada.");

        $data['UserID'] = $userId;
        $isSoloPagoMovil = (strtoupper($data['numeroCuenta'] ?? '') === 'PAGO MOVIL') || (strtoupper($cuenta['NumeroCuenta']) === 'PAGO MOVIL');
        if (isset($data['phoneNumber']) && !empty($data['phoneNumber'])) {
            $isSoloPagoMovil = true; 
        }

        if (empty($data['tipoBeneficiario'])) {
            $data['tipoBeneficiario'] = $isSoloPagoMovil ? 'Pago Móvil' : 'Cuenta Bancaria';
        }

        $prepared = $this->prepareSingleRecord($data, $isSoloPagoMovil);
        if (isset($data['cci'])) {
            $prepared['cci'] = trim($data['cci']);
        }

        $hasHistory = $this->txRepo->isAccountUsedInCompletedOrders($cuentaId);

        try {
            if ($hasHistory) {
                $prepared['paisID'] = $cuenta['PaisID'];
                $newCuentaId = $this->cuentasRepo->create(
                    $prepared['UserID'],
                    (int) $prepared['paisID'],
                    $prepared['alias'],
                    $prepared['primerNombre'],
                    $prepared['segundoNombre'],
                    $prepared['primerApellido'],
                    $prepared['segundoApellido'],
                    $prepared['titularTipoDocumentoID'],
                    $prepared['numeroDocumento'],
                    $prepared['nombreBanco'],
                    $prepared['numeroCuenta'],
                    $prepared['cci'] ?? null,
                    $prepared['numeroTelefono'] ?? null,
                    $prepared['tipoBeneficiarioID']
                );

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
                if (strlen($clean) > 30)
                    throw new Exception("El número de cuenta es demasiado largo.", 400);
                $data['numeroCuenta'] = $clean;
            }
        } else {
            if (!empty($data['phoneNumber'])) {
                $data['numeroTelefono'] = trim($data['phoneNumber']);
                $data['numeroCuenta'] = $data['numeroTelefono'];
            } else {
                $data['numeroCuenta'] = 'PAGO MOVIL';
            }
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