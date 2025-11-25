<?php
namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\EstadoTransaccionRepository;
use App\Repositories\FormaPagoRepository;
use App\Repositories\CuentasBeneficiariasRepository;
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

    private const ESTADO_PENDIENTE_PAGO = 'Pendiente de Pago';
    private const ESTADO_EN_VERIFICACION = 'En Verificación';
    private const ESTADO_EN_PROCESO = 'En Proceso';
    private const ESTADO_PAGADO = 'Pagado';
    private const ESTADO_CANCELADO = 'Cancelado';

    public function __construct(
        TransactionRepository $txRepository,
        UserRepository $userRepository,
        NotificationService $notificationService,
        PDFService $pdfService,
        FileHandlerService $fileHandler,
        EstadoTransaccionRepository $estadoTxRepo,
        FormaPagoRepository $formaPagoRepo,
        ContabilidadService $contabilidadService,
        CuentasBeneficiariasRepository $cuentasRepo
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
    }

    private function getEstadoId(string $nombreEstado): int
    {
        $id = $this->estadoTxRepo->findIdByName($nombreEstado);
        if ($id === null) {
            throw new Exception("Configuración interna: Estado de transacción '{$nombreEstado}' no encontrado.", 500);
        }
        return $id;
    }

    public function createTransaction(array $data): int
    {
        $client = $this->userRepository->findUserById($data['userID']);
        if (!$client)
            throw new Exception("Usuario no encontrado.", 404);
        if ($client['VerificacionEstado'] !== 'Verificado')
            throw new Exception("Tu cuenta debe estar verificada para realizar transacciones.", 403);
        if (empty($client['Telefono']))
            throw new Exception("Falta tu número de teléfono en el perfil.", 400);

        $requiredFields = ['userID', 'cuentaID', 'tasaID', 'montoOrigen', 'monedaOrigen', 'montoDestino', 'monedaDestino', 'formaDePago'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new Exception("Faltan datos para crear la transacción: $field", 400);
            }
        }

        if ($data['montoOrigen'] <= 0) {
            throw new Exception("El monto debe ser mayor a cero.", 400);
        }

        $beneficiario = $this->cuentasRepo->findByIdAndUserId((int) $data['cuentaID'], (int) $data['userID']);
        if (!$beneficiario) {
            throw new Exception("Beneficiario no encontrado o no te pertenece.", 404);
        }

        $data['beneficiarioNombre'] = trim(implode(' ', [
            $beneficiario['TitularPrimerNombre'],
            $beneficiario['TitularSegundoNombre'],
            $beneficiario['TitularPrimerApellido'],
            $beneficiario['TitularSegundoApellido']
        ]));

        $data['beneficiarioDocumento'] = $beneficiario['TitularNumeroDocumento'];
        $data['beneficiarioBanco'] = $beneficiario['NombreBanco'];
        $data['beneficiarioNumeroCuenta'] = $beneficiario['NumeroCuenta'];
        $data['beneficiarioTelefono'] = $beneficiario['NumeroTelefono'];


        $formaPagoID = $this->formaPagoRepo->findIdByName($data['formaDePago']);
        if (!$formaPagoID) {
            throw new Exception("Forma de pago '{$data['formaDePago']}' no válida.", 400);
        }
        $data['formaPagoID'] = $formaPagoID;
        $data['estadoID'] = $this->getEstadoId(self::ESTADO_PENDIENTE_PAGO);

        try {
            $transactionId = $this->txRepository->create($data);
            $txData = $this->txRepository->getFullTransactionDetails($transactionId);
            if (!$txData)
                throw new Exception("No se pudieron obtener los detalles de la transacción #{$transactionId}.", 500);

            $txData['TelefonoCliente'] = $client['Telefono'];

            $pdfContent = $this->pdfService->generateOrder($txData);
            $pdfUrl = $this->fileHandler->savePdfTemporarily($pdfContent, $transactionId);

            $whatsappSent = $this->notificationService->sendOrderToClientWhatsApp($txData, $pdfUrl);
            $this->notificationService->sendNewOrderEmail($txData, $pdfContent);

            $logDetail = "TX ID: $transactionId - Notificación WhatsApp: " . ($whatsappSent ? 'Éxito' : 'Fallo');
            $this->notificationService->logAdminAction($data['userID'], 'Creación de Transacción', $logDetail);

            return $transactionId;

        } catch (Exception $e) {
            $this->notificationService->logAdminAction($data['userID'], 'Error Creación Transacción', "Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function handleUserReceiptUpload(int $txId, int $userId, array $fileData): bool
    {
        if (empty($fileData) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("No se recibió ningún archivo.", 400);
        }

        $fileHash = hash_file('sha256', $fileData['tmp_name']);
        if ($fileHash === false) {
            throw new Exception("Error al analizar el archivo del comprobante.", 500);
        }

        $existingTx = $this->txRepository->findByHash($fileHash);
        if ($existingTx) {
            throw new Exception("Este comprobante ya fue subido para la transacción #" . $existingTx['TransaccionID'] . ".", 409);
        }

        $relativePath = "";
        try {
            $relativePath = $this->fileHandler->saveReceiptFile($fileData, $txId);
        } catch (Exception $e) {
            throw new Exception("Error al guardar el comprobante: " . $e->getMessage(), $e->getCode() ?: 500);
        }

        $estadoEnVerificacionID = $this->getEstadoId(self::ESTADO_EN_VERIFICACION);
        $estadoPendienteID = $this->getEstadoId(self::ESTADO_PENDIENTE_PAGO);

        try {
            $affectedRows = $this->txRepository->uploadUserReceipt($txId, $userId, $relativePath, $fileHash, $estadoEnVerificacionID, $estadoPendienteID);
        } catch (\mysqli_sql_exception $e) {
            @unlink($this->fileHandler->getAbsolutePath($relativePath));
            if ($e->getCode() == 1062) {
                $existingTx = $this->txRepository->findByHash($fileHash);
                $txMsg = $existingTx ? " la transacción #" . $existingTx['TransaccionID'] : " otra transacción";
                throw new Exception("Este comprobante ya fue subido para" . $txMsg . ".", 409);
            }
            throw new Exception("Error de base de datos al guardar el comprobante: " . $e->getMessage(), 500);
        }

        if ($affectedRows === 0) {
            @unlink($this->fileHandler->getAbsolutePath($relativePath));

            $txExists = $this->txRepository->getFullTransactionDetails($txId);
            if (!$txExists || $txExists['UserID'] != $userId)
                throw new Exception("La transacción no existe o no te pertenece.", 404);

            $allowedStates = [$estadoPendienteID, $estadoEnVerificacionID];
            if (!in_array($txExists['EstadoID'], $allowedStates)) {
                throw new Exception("No se puede subir o modificar el comprobante. El estado actual es '{$txExists['Estado']}'.", 409);
            }

            throw new Exception("No se pudo actualizar la transacción en la base de datos.", 500);
        }

        $this->notificationService->logAdminAction($userId, 'Subida/Modificación de Comprobante', "TX ID: $txId. Archivo: $relativePath. Estado cambiado a En Verificación.");
        return true;
    }

    public function cancelTransaction(int $txId, int $userId): bool
    {
        $estadoCanceladoID = $this->getEstadoId(self::ESTADO_CANCELADO);
        $estadoPendienteID = $this->getEstadoId(self::ESTADO_PENDIENTE_PAGO);
        $affectedRows = $this->txRepository->cancel($txId, $userId, $estadoCanceladoID, $estadoPendienteID);

        if ($affectedRows === 0) {
            $txExists = $this->txRepository->getFullTransactionDetails($txId);
            if (!$txExists || $txExists['UserID'] != $userId)
                throw new Exception("La transacción no existe o no te pertenece.", 404);
            if ($txExists['EstadoID'] !== $estadoPendienteID)
                throw new Exception("No se puede cancelar. El estado actual es '{$txExists['Estado']}'.", 409);
            throw new Exception("No se pudo cancelar la transacción.", 500);
        }

        $this->notificationService->logAdminAction($userId, 'Usuario canceló transacción', "TX ID: $txId");
        return true;
    }

    public function adminConfirmPayment(int $adminId, int $txId): bool
    {
        $estadoEnProcesoID = $this->getEstadoId(self::ESTADO_EN_PROCESO);
        $estadoEnVerificacionID = $this->getEstadoId(self::ESTADO_EN_VERIFICACION);
        $affectedRows = $this->txRepository->updateStatus($txId, $estadoEnProcesoID, $estadoEnVerificacionID);

        if ($affectedRows === 0) {
            $txExists = $this->txRepository->getFullTransactionDetails($txId);
            if (!$txExists)
                throw new Exception("La transacción no existe.", 404);
            if ($txExists['EstadoID'] !== $estadoEnVerificacionID)
                throw new Exception("El estado de la transacción es '{$txExists['Estado']}', no 'En Verificación'.", 409);
            throw new Exception("No se pudo confirmar el pago.", 500);
        }

        $this->notificationService->logAdminAction($adminId, 'Admin confirmó pago', "TX ID: $txId. Estado cambiado a 'En Proceso'.");
        return true;
    }

    public function adminRejectPayment(int $adminId, int $txId): bool
    {
        $estadoCanceladoID = $this->getEstadoId(self::ESTADO_CANCELADO);
        $estadoEnVerificacionID = $this->getEstadoId(self::ESTADO_EN_VERIFICACION);
        $affectedRows = $this->txRepository->updateStatus($txId, $estadoCanceladoID, $estadoEnVerificacionID);

        if ($affectedRows === 0) {
            $txExists = $this->txRepository->getFullTransactionDetails($txId);
            if (!$txExists)
                throw new Exception("La transacción no existe.", 404);
            if ($txExists['EstadoID'] !== $estadoEnVerificacionID)
                throw new Exception("El estado de la transacción es '{$txExists['Estado']}', no 'En Verificación'.", 409);
            throw new Exception("No se pudo rechazar el pago.", 500);
        }

        $this->notificationService->logAdminAction($adminId, 'Admin rechazó pago', "TX ID: $txId. Estado cambiado a 'Cancelado'.");
        return true;
    }

    public function handleAdminProofUpload(int $adminId, int $txId, array $fileData, float $comisionDestino): bool
    {
        if (empty($fileData) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("No se recibió ningún archivo.", 400);
        }

        $relativePath = "";
        try {
            $relativePath = $this->fileHandler->saveAdminProofFile($fileData, $txId);
        } catch (Exception $e) {
            throw new Exception("Error al guardar el comprobante de envío: " . $e->getMessage(), $e->getCode() ?: 500);
        }

        $estadoPagadoID = $this->getEstadoId(self::ESTADO_PAGADO);
        $estadoEnProcesoID = $this->getEstadoId(self::ESTADO_EN_PROCESO);

        $affectedRows = $this->txRepository->uploadAdminProof($txId, $relativePath, $estadoPagadoID, $estadoEnProcesoID, $comisionDestino);

        if ($affectedRows === 0) {
            @unlink($this->fileHandler->getAbsolutePath($relativePath));
            $txExists = $this->txRepository->getFullTransactionDetails($txId);
            if (!$txExists)
                throw new Exception("La transacción no existe.", 404);
            if ($txExists['EstadoID'] !== $estadoEnProcesoID)
                throw new Exception("El estado de la transacción es '{$txExists['Estado']}', no 'En Proceso'.", 409);
            throw new Exception("No se pudo actualizar la transacción como pagada.", 500);
        }

        $txData = $this->txRepository->getFullTransactionDetails($txId);

        if ($txData && !empty($txData['PaisDestinoID'])) {
            $this->contabilidadService->registrarGasto(
                (int) $txData['PaisDestinoID'],
                (float) $txData['MontoDestino'],
                (float) $txData['ComisionDestino'],
                $adminId,
                $txId
            );
        } else {
            $this->notificationService->logAdminAction($adminId, 'Error Contabilidad', "TX ID $txId: No se pudo registrar gasto (faltan datos de País Destino).");
        }

        if ($txData && !empty($txData['TelefonoCliente'])) {
            // $this->notificationService->sendPaymentConfirmationToClientWhatsApp($txData);
        } else {
            $this->notificationService->logAdminAction($adminId, 'Advertencia Notificación', "TX ID $txId: No se pudo notificar al cliente sobre el pago (faltan datos).");
        }

        $this->notificationService->logAdminAction($adminId, 'Admin completó transacción', "TX ID: $txId. Comprobante envío: $relativePath. Comisión: $comisionDestino. Estado: 'Pagado'.");
        return true;
    }

    public function adminUpdateCommission(int $adminId, int $txId, float $newCommission): void
    {
        $txData = $this->txRepository->getFullTransactionDetails($txId);
        if (!$txData) {
            throw new Exception("Transacción no encontrada.", 404);
        }
        if (!in_array($txData['EstadoID'], [3, 4])) {
            throw new Exception("Solo se puede editar la comisión en órdenes 'En Proceso' o 'Pagadas'.", 400);
        }

        $oldCommission = (float) $txData['ComisionDestino'];

        if ($oldCommission === $newCommission) {
            return;
        }

        if (!$this->txRepository->updateCommission($txId, $newCommission)) {
            throw new Exception("Error al actualizar la comisión en la base de datos.", 500);
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
}