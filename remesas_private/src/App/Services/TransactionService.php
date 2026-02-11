<?php
namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\EstadoTransaccionRepository;
use App\Repositories\FormaPagoRepository;
use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\CuentasAdminRepository;
use App\Repositories\RateRepository;
use App\Services\NotificationService;
use App\Services\PDFService;
use App\Services\FileHandlerService;
use App\Services\ContabilidadService;
use Exception;

class TransactionService
{
    private TransactionRepository $txRepository;
    private UserRepository $userRepository;
    private CuentasBeneficiariasRepository $cuentasRepo;
    private NotificationService $notificationService;
    private PDFService $pdfService;
    private FileHandlerService $fileHandler;
    private EstadoTransaccionRepository $estadoTxRepo;
    private FormaPagoRepository $formaPagoRepo;
    private ContabilidadService $contabilidadService;
    private CuentasAdminRepository $cuentasAdminRepo;
    private RateRepository $rateRepository;

    private const ESTADO_PENDIENTE_PAGO = 'Pendiente de Pago';
    private const ESTADO_EN_VERIFICACION = 'En Verificación';
    private const ESTADO_EN_PROCESO = 'En Proceso';
    private const ESTADO_PAGADO = 'Exitoso';
    private const ESTADO_CANCELADO = 'Cancelado';
    private const ESTADO_PENDIENTE_APROBACION = 'Pendiente de Aprobación';
    private const ESTADO_PAUSADO = 'Pausado';

    public function __construct(
        TransactionRepository $txRepository,
        UserRepository $userRepository,
        NotificationService $notificationService,
        PDFService $pdfService,
        FileHandlerService $fileHandler,
        EstadoTransaccionRepository $estadoTxRepo,
        FormaPagoRepository $formaPagoRepo,
        ContabilidadService $contabilidadService,
        CuentasBeneficiariasRepository $cuentasRepo,
        CuentasAdminRepository $cuentasAdminRepo,
        RateRepository $rateRepository
    ) {
        $this->txRepository = $txRepository;
        $this->userRepository = $userRepository;
        $this->notificationService = $notificationService;
        $this->pdfService = $pdfService;
        $this->fileHandler = $fileHandler;
        $this->estadoTxRepo = $estadoTxRepo;
        $this->formaPagoRepo = $formaPagoRepo;
        $this->contabilidadService = $contabilidadService;
        $this->cuentasRepo = $cuentasRepo;
        $this->cuentasAdminRepo = $cuentasAdminRepo;
        $this->rateRepository = $rateRepository;
    }

    public function getEstadoIdByName(string $nombreEstado): int
    {
        $id = $this->estadoTxRepo->findIdByName($nombreEstado);
        if ($id === null) {
            if ($nombreEstado === self::ESTADO_PENDIENTE_APROBACION) {
                return 7;
            }
            throw new Exception("Configuración interna: Estado de transacción '{$nombreEstado}' no encontrado.", 500);
        }
        return (int) $id;
    }

    private function getEstadoId(string $nombreEstado): int
    {
        return $this->getEstadoIdByName($nombreEstado);
    }

    public function getTransactionsByUser(int $userId): array
    {
        return $this->txRepository->getAllByUser($userId);
    }

    public function pause(int $txId, string $motivo, int $estadoId = 6): bool
    {
        return $this->txRepository->pauseTransaction($txId, $motivo, $estadoId);
    }

    // --- REANUDAR CON CORRECCIÓN ---
    public function requestResume(int $txId, int $userId, string $mensaje, int $estadoId, ?array $beneficiaryData = null): bool
    {
        if ($beneficiaryData) {
            if (empty($beneficiaryData['nombre']) || empty($beneficiaryData['cuenta'])) {
                throw new Exception("Nombre y Cuenta son obligatorios para corregir.");
            }
            $this->txRepository->updateBeneficiarySnapshot($txId, $beneficiaryData);
        }
        $this->fileHandler->deleteOrderPdf($txId);
        return $this->txRepository->requestResume($txId, $userId, $mensaje, $estadoId);
    }

    public function cancelTransaction(int $txId, int $userId): bool
    {
        $estadoCanceladoID = $this->getEstadoId(self::ESTADO_CANCELADO);

        $nombresPermitidos = [
            self::ESTADO_PENDIENTE_PAGO,
            self::ESTADO_EN_VERIFICACION,
            self::ESTADO_EN_PROCESO,
            self::ESTADO_PAUSADO
        ];

        $allowedStatusIds = [];
        foreach ($nombresPermitidos as $nombre) {
            try {
                $id = $this->getEstadoId($nombre);
                if ($id)
                    $allowedStatusIds[] = $id;
            } catch (Exception $e) {
                continue;
            }
        }

        $affectedRows = $this->txRepository->cancel($txId, $userId, $estadoCanceladoID, $allowedStatusIds);

        if ($affectedRows === 0) {
            throw new Exception("No se puede cancelar la transacción en su estado actual.", 409);
        }

        $this->notificationService->logAdminAction($userId, 'Usuario canceló transacción', "TX ID: $txId");
        return true;
    }


    public function createTransaction(array $data): array
    {
        $client = $this->userRepository->findUserById($data['userID']);
        if (!$client) {
            throw new Exception("Usuario no encontrado.", 404);
        }
        if ($client['VerificacionEstado'] !== 'Verificado') {
            throw new Exception("Tu cuenta debe estar verificada para realizar transacciones.", 403);
        }
        if (empty($client['Telefono'])) {
            throw new Exception("Falta tu número de teléfono en el perfil.", 400);
        }

        $requiredFields = ['userID', 'cuentaID', 'tasaID', 'montoOrigen', 'monedaOrigen', 'montoDestino', 'monedaDestino', 'formaDePago'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new Exception("Faltan datos para crear la transacción: $field", 400);
            }
        }

        if ($data['montoOrigen'] <= 0) {
            throw new Exception("El monto debe ser mayor a cero.", 400);
        }

        $estadoInicialID = $this->getEstadoId(self::ESTADO_PENDIENTE_PAGO);
        $statusKey = 'created';
        $beneficiario = $this->cuentasRepo->findByIdAndUserId((int) $data['cuentaID'], (int) $data['userID']);
        if (!$beneficiario) {
            throw new Exception("Beneficiario no encontrado o no te pertenece.", 404);
        }

        $paisOrigenID = !empty($data['paisOrigenID']) ? (int) $data['paisOrigenID'] : ($client['PaisID'] ?? 1);
        $paisDestinoID = $beneficiario['PaisID'];

        $tasaInfo = $this->rateRepository->findCurrentRate($paisOrigenID, $paisDestinoID, (float) $data['montoOrigen']);

        if (!$tasaInfo) {
            throw new Exception("La tasa ha cambiado o ya no está disponible. Por favor recargue.", 400);
        }

        $inverseRoutes = [
            '2-3', // Col -> Ven
            '4-1', // Peru -> Chile
            '2-1', // Col -> Chile
            '3-1', // Ven -> Chile
            '3-4', // Ven -> Peru
        ];

        $routeKey = "{$paisOrigenID}-{$paisDestinoID}";
        $tasaValor = (float) $tasaInfo['ValorTasa'];
        $calculoBackend = 0;

        if (in_array($routeKey, $inverseRoutes)) {
            if ($tasaValor == 0)
                throw new Exception("Error crítico: Tasa 0 en ruta inversa.");
            $calculoBackend = (float) $data['montoOrigen'] / $tasaValor;
        } else {
            $calculoBackend = (float) $data['montoOrigen'] * $tasaValor;
        }

        $data['montoDestino'] = $calculoBackend;
        $data['tasaID'] = $tasaInfo['TasaID'];
        $data['tasaCapturada'] = $tasaValor; 

        if (isset($tasaInfo['EsRiesgoso']) && (int) $tasaInfo['EsRiesgoso'] === 1) {
            $estadoInicialID = 7;
            $statusKey = 'requires_approval';
        }

        $data['estadoID'] = $estadoInicialID;

        $ganancia = 0;
        if ((isset($client['Rol']) && $client['Rol'] === 'Revendedor') || (isset($client['RolID']) && $client['RolID'] == 4)) {
            $porcentaje = $client['PorcentajeComision'] ?? 0;
            if ($porcentaje > 0) {
                $ganancia = ($data['montoOrigen'] * $porcentaje) / 100;
                if (method_exists($this->userRepository, 'addGanancia')) {
                    $this->userRepository->addGanancia($client['UserID'], $ganancia);
                }
            }
        }

        $data['comisionRevendedor'] = $ganancia;
        $data['beneficiarioNombre'] = trim(implode(' ', array_filter([
            $beneficiario['TitularPrimerNombre'],
            $beneficiario['TitularSegundoNombre'],
            $beneficiario['TitularPrimerApellido'],
            $beneficiario['TitularSegundoApellido']
        ])));
        $data['beneficiarioDocumento'] = $beneficiario['TitularNumeroDocumento'];
        $data['beneficiarioBanco'] = $beneficiario['NombreBanco'];
        $data['beneficiarioNumeroCuenta'] = $beneficiario['NumeroCuenta'];
        $data['beneficiarioCCI'] = $beneficiario['CCI'] ?? null;
        $data['beneficiarioTelefono'] = $beneficiario['NumeroTelefono'];

        $formaPagoID = $this->formaPagoRepo->findIdByName($data['formaDePago']);
        if (!$formaPagoID) {
            throw new Exception("Forma de pago '{$data['formaDePago']}' no válida.", 400);
        }
        $data['formaPagoID'] = $formaPagoID;

        try {
            $transactionId = $this->txRepository->create($data);

            if ($statusKey === 'requires_approval') {
                $this->notificationService->logAdminAction($data['userID'], 'Orden Riesgosa Creada', "TX ID: $transactionId - Esperando aprobación.");
                return [
                    'id' => $transactionId,
                    'status' => 'requires_approval'
                ];
            }

            $txData = $this->txRepository->getFullTransactionDetails($transactionId);
            if (!$txData) {
                throw new Exception("No se pudieron obtener los detalles de la transacción.", 500);
            }

            $txData['TelefonoCliente'] = $client['Telefono'];

            // Obtener cuenta admin para el QR
            $cuentaAdminData = null;
            if (isset($txData['FormaPagoID']) && isset($txData['PaisOrigenID'])) {
                $cuentaAdmin = $this->cuentasAdminRepo->findActiveByFormaPagoAndPais(
                    (int) $txData['FormaPagoID'],
                    (int) $txData['PaisOrigenID']
                );
                if ($cuentaAdmin) {
                    $txData['CuentaAdmin'] = $cuentaAdmin;
                    $cuentaAdminData = [
                        'Banco' => $cuentaAdmin['Banco'],
                        'Titular' => $cuentaAdmin['Titular'],
                        'NumeroCuenta' => $cuentaAdmin['NumeroCuenta'],
                        'TipoCuenta' => $cuentaAdmin['TipoCuenta'],
                        'QrCodeURL' => $cuentaAdmin['QrCodeURL']
                    ];
                }
            }

            $pdfContent = $this->pdfService->generateOrder($txData);
            $pdfUrl = $this->fileHandler->savePdfTemporarily($pdfContent, $transactionId);

            $whatsappSent = $this->notificationService->sendOrderToClientWhatsApp($txData, $pdfUrl);
            $this->notificationService->sendNewOrderEmail($txData, $pdfContent);

            $logDetail = "TX ID: $transactionId - WhatsApp: " . ($whatsappSent ? 'Ok' : 'Falló');
            $this->notificationService->logAdminAction($data['userID'], 'Creación de Transacción', $logDetail);

            return [
                'id' => $transactionId,
                'status' => 'created',
                'cuentaAdmin' => $cuentaAdminData
            ];

        } catch (Exception $e) {
            $this->notificationService->logAdminAction($data['userID'], 'Error Creación', "Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function authorizeRiskyTransaction(int $txId, int $adminId): bool
    {
        $estadoRiesgo = 7;
        $estadoPendientePago = 1;
        $affectedRows = $this->txRepository->updateStatus($txId, $estadoPendientePago, $estadoRiesgo);

        if ($affectedRows > 0) {
            $txData = $this->txRepository->getFullTransactionDetails($txId);
            if ($txData) {
                $client = $this->userRepository->findUserById($txData['UserID']);
                $txData['TelefonoCliente'] = $client['Telefono'];

                if (isset($txData['FormaPagoID'], $txData['PaisOrigenID'])) {
                    $cuentaAdmin = $this->cuentasAdminRepo->findActiveByFormaPagoAndPais((int) $txData['FormaPagoID'], (int) $txData['PaisOrigenID']);
                    if ($cuentaAdmin)
                        $txData['CuentaAdmin'] = $cuentaAdmin;
                }

                $pdfContent = $this->pdfService->generateOrder($txData);
                $pdfUrl = $this->fileHandler->savePdfTemporarily($pdfContent, $txId);

                $this->notificationService->sendOrderToClientWhatsApp($txData, $pdfUrl);
                $this->notificationService->sendNewOrderEmail($txData, $pdfContent);
            }

            $this->notificationService->logAdminAction($adminId, 'Autorización Riesgo', "TX ID: $txId autorizada. Ahora puede pagar.");
            return true;
        }

        return false;
    }

    public function handleUserReceiptUpload(int $txId, int $userId, array $fileData, string $rutTitular, string $nombreTitular): bool
    {
        if (empty($fileData) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("No se recibió ningún archivo.", 400);
        }

        if (empty($rutTitular) || empty($nombreTitular)) {
            throw new Exception("El RUT y Nombre del titular de la cuenta origen son obligatorios.", 400);
        }

        $fileHash = hash_file('sha256', $fileData['tmp_name']);
        if ($fileHash === false) {
            throw new Exception("Error al analizar el archivo del comprobante.", 500);
        }

        $existingTx = $this->txRepository->findByHash($fileHash);
        if ($existingTx) {
            throw new Exception("Este comprobante ya fue subido para la transacción #" . $existingTx['TransaccionID'] . ".", 409);
        }

        $relativePath = $this->fileHandler->saveReceiptFile($fileData, $txId);

        $estadoEnVerificacionID = $this->getEstadoId(self::ESTADO_EN_VERIFICACION);
        $estadoPendienteID = $this->getEstadoId(self::ESTADO_PENDIENTE_PAGO);

        $affectedRows = $this->txRepository->uploadUserReceipt($txId, $userId, $relativePath, $fileHash, $estadoEnVerificacionID, $estadoPendienteID, $rutTitular, $nombreTitular);

        if ($affectedRows === 0) {
            @unlink($this->fileHandler->getAbsolutePath($relativePath));
            throw new Exception("No se pudo actualizar la transacción. Verifique que sea suya y esté en estado pendiente.", 409);
        }

        $this->notificationService->logAdminAction($userId, 'Subida de Comprobante', "TX ID: $txId. Archivo: $relativePath. Titular Origen: $nombreTitular ($rutTitular)");
        return true;
    }

    public function adminConfirmPayment(int $adminId, int $txId): bool
    {
        $estadoEnProcesoID = $this->getEstadoId(self::ESTADO_EN_PROCESO);
        $estadoEnVerificacionID = $this->getEstadoId(self::ESTADO_EN_VERIFICACION);
        $affectedRows = $this->txRepository->updateStatus($txId, $estadoEnProcesoID, $estadoEnVerificacionID);

        if ($affectedRows === 0) {
            throw new Exception("No se pudo confirmar el pago. Asegúrese de que la orden esté 'En Verificación'.", 409);
        }

        $txData = $this->txRepository->getFullTransactionDetails($txId);
        if (isset($txData['FormaPagoID'])) {
            $paisOrigenId = $txData['PaisOrigenID'] ?? 1;
            $cuentaAdmin = $this->cuentasAdminRepo->findActiveByFormaPagoAndPais((int) $txData['FormaPagoID'], (int) $paisOrigenId);

            if ($cuentaAdmin) {
                $this->contabilidadService->registrarIngresoVenta(
                    (int) $cuentaAdmin['CuentaAdminID'],
                    (float) $txData['MontoOrigen'],
                    $adminId,
                    $txId
                );
            }
        }

        $this->notificationService->logAdminAction($adminId, 'Admin confirmó pago', "TX ID: $txId. Estado: En Proceso.");
        return true;
    }

    public function adminRejectPayment(int $adminId, int $txId, string $reason = '', bool $isSoftReject = false): bool
    {
        $estadoCanceladoID = 5; // ID 5 = Cancelado Definitivo
        $estadoPendienteID = 1; // ID 1 = Pendiente de Pago 
        $estadosPermitidos = [
            2, // Verificación
            3, // En Proceso
            6, // Pausado
            7, // Riesgo (NUEVO)
            1  // Pendiente Pago (NUEVO)
        ];

        $nuevoEstadoID = $isSoftReject ? $estadoPendienteID : $estadoCanceladoID;

        $txData = $this->txRepository->getFullTransactionDetails($txId);
        if (!$txData)
            throw new Exception("Transacción no encontrada.", 404);

        $affectedRows = $this->txRepository->updateStatus($txId, $nuevoEstadoID, $estadosPermitidos);

        if ($affectedRows === 0) {
            throw new Exception("No se pudo rechazar. El estado actual no permite esta acción.", 409);
        }

        if ($isSoftReject) {
            $this->notificationService->sendCorrectionRequestEmail($txData['Email'], $txData['PrimerNombre'], $txId, $reason);
        } else {
            $this->notificationService->sendCancellationEmail($txData['Email'], $txData['PrimerNombre'], $txId, $reason);
        }

        $this->notificationService->logAdminAction($adminId, 'Admin rechazó pago', "TX ID: $txId. Motivo: $reason");
        return true;
    }

    public function handleAdminProofUpload(int $adminId, int $txId, array $fileData, float $comisionDestino, ?int $cuentaSalidaId = null): bool
    {
        if (empty($fileData) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("No se recibió ningún archivo.", 400);
        }
        $fileHash = hash_file('sha256', $fileData['tmp_name']);
        if ($fileHash === false) {
            throw new Exception("Error de seguridad: No se pudo verificar la integridad del archivo.", 500);
        }
        $existingTx = $this->txRepository->findByAdminProofHash($fileHash);
        if ($existingTx) {
            if ((int) $existingTx['TransaccionID'] !== $txId) {
                throw new Exception("Este comprobante ya fue utilizado en la transacción #" . $existingTx['TransaccionID'] . ". Por seguridad, no se permiten archivos duplicados.", 409);
            }
        }

        $txData = $this->txRepository->getFullTransactionDetails($txId);
        if (!$txData) {
            throw new Exception("Transacción no encontrada.", 404);
        }

        $cuentaAdmin = null;
        if ($cuentaSalidaId) {
            $cuentaAdmin = $this->cuentasAdminRepo->getById($cuentaSalidaId);
            if (!$cuentaAdmin) {
                throw new Exception("La cuenta bancaria de salida seleccionada no existe.", 404);
            }
            if ((float) $cuentaAdmin['SaldoActual'] < (float) $txData['MontoDestino']) {
                throw new Exception("Saldo insuficiente en la cuenta " . $cuentaAdmin['Banco'] . ". Saldo actual: " . $cuentaAdmin['SaldoActual'], 400);
            }
        }

        try {
            if (!empty($txData['ComprobanteEnvioURL'])) {
                $oldFilePath = $this->fileHandler->getAbsolutePath($txData['ComprobanteEnvioURL']);
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
        } catch (Exception $e) {
            error_log("Advertencia [handleAdminProofUpload]: No se pudo borrar el comprobante antiguo de TX $txId: " . $e->getMessage());
        }
        $relativePath = $this->fileHandler->saveAdminProofFile($fileData, $txId);
        $estadoPagadoID = $this->getEstadoId(self::ESTADO_PAGADO);
        $estadoEnProcesoID = $this->getEstadoId(self::ESTADO_EN_PROCESO);
        $affectedRows = $this->txRepository->uploadAdminProof(
            $txId,
            $relativePath,
            $fileHash,
            $estadoPagadoID,
            $estadoEnProcesoID,
            $comisionDestino
        );

        if ($affectedRows === 0) {
            @unlink($this->fileHandler->getAbsolutePath($relativePath));
            throw new Exception("No se pudo completar la transacción. Verifique que la orden esté en estado 'En Proceso'.", 409);
        }

        if ($cuentaSalidaId && $cuentaAdmin) {
            $nuevoSaldo = (float) $cuentaAdmin['SaldoActual'] - (float) $txData['MontoDestino'];
            $this->cuentasAdminRepo->updateSaldo($cuentaSalidaId, $nuevoSaldo);

            $this->txRepository->updateCuentaSalida($txId, $cuentaSalidaId);

            $this->contabilidadService->registrarEgresoPago(
                (int) $cuentaSalidaId,
                (float) $txData['MontoDestino'],
                $adminId,
                $txId
            );
        } else {
            if (!empty($txData['PaisDestinoID'])) {
                $this->contabilidadService->registrarGasto(
                    (int) $txData['PaisDestinoID'],
                    (float) $txData['MontoDestino'],
                    (float) $comisionDestino,
                    $adminId,
                    $txId
                );
            }
        }

        $this->notificationService->sendPaymentConfirmationToClientWhatsApp($txData);
        $this->notificationService->logAdminAction($adminId, 'Admin completó transacción', "TX ID: $txId. Estado: Exitoso. Comprobante actualizado.");

        return true;
    }

    public function adminUpdateCommission(int $adminId, int $txId, float $newCommission): void
    {
        $txData = $this->txRepository->getFullTransactionDetails($txId);
        if (!$txData)
            throw new Exception("Transacción no encontrada.", 404);

        $oldCommission = (float) $txData['ComisionDestino'];
        if ($oldCommission === $newCommission)
            return;

        if (!$this->txRepository->updateCommission($txId, $newCommission)) {
            throw new Exception("Error al actualizar la comisión.", 500);
        }

        if (!empty($txData['PaisDestinoID'])) {
            $this->contabilidadService->corregirGastoComision(
                (int) $txData['PaisDestinoID'],
                $oldCommission,
                $newCommission,
                $adminId,
                $txId
            );
        }

        $this->notificationService->logAdminAction($adminId, 'Admin editó comisión', "TX ID: $txId. De $oldCommission a $newCommission.");
    }

    public function adminResumeTransaction(int $txId, int $adminId, string $nota = ''): bool
    {
        $estadoEnProceso = 3;
        $estadoPausado = 6;

        $affected = $this->txRepository->updateStatus($txId, $estadoEnProceso, $estadoPausado);
        if ($affected > 0) {
            $txData = $this->txRepository->getFullTransactionDetails($txId);
            if ($txData) {
                $pdfContent = $this->pdfService->generateOrder($txData);
                $this->fileHandler->savePdfTemporarily($pdfContent, $txId);
            }
            $logMsg = "Admin reanudó orden.";
            if (!empty($nota)) {
                $logMsg .= " Nota: $nota";
            }
            $this->notificationService->logAdminAction($adminId, 'Orden Reanudada', "TX ID: $txId. $logMsg");
            return true;
        }
        return false;
    }

    public function getResellerStats(int $userId, string $fechaInicio, string $fechaFin): array
    {
        return $this->txRepository->getResellerStats($userId, $fechaInicio, $fechaFin);
    }

    public function forceUpdateState(int $txId, int $newState, string $note = ''): bool
    {
        $tx = $this->txRepository->getById($txId);
        if (!$tx) {
            throw new Exception("Transacción no encontrada.");
        }
        if ($tx['EstadoID'] == 7 && $newState == 1) {
            $adminId = $_SESSION['user_id'] ?? 0;
            return $this->authorizeRiskyTransaction($txId, $adminId);
        }
        $estadoActual = (int) $tx['EstadoID'];
        $affected = $this->txRepository->updateStatus($txId, $newState, $estadoActual);

        if ($affected > 0) {
            // $this->notificationService->logAdminAction(...)
            return true;
        }

        return false;
    }
}