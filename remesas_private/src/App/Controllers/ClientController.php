<?php
namespace App\Controllers;

use App\Services\TransactionService;
use App\Services\PricingService;
use App\Services\CuentasBeneficiariasService;
use App\Services\UserService;
use App\Services\SystemSettingsService;
use App\Repositories\FormaPagoRepository;
use App\Repositories\TipoBeneficiarioRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Repositories\RolRepository;
use App\Services\NotificationService;
use Exception;

class ClientController extends BaseController
{
    private TransactionService $txService;
    private PricingService $pricingService;
    private CuentasBeneficiariasService $cuentasBeneficiariasService;
    private UserService $userService;
    private FormaPagoRepository $formaPagoRepo;
    private TipoBeneficiarioRepository $tipoBeneficiarioRepo;
    private TipoDocumentoRepository $tipoDocumentoRepo;
    private RolRepository $rolRepo;
    private NotificationService $notificationService;
    private SystemSettingsService $settingsService;

    public function __construct(
        TransactionService $txService,
        PricingService $pricingService,
        CuentasBeneficiariasService $cuentasBeneficiariasService,
        UserService $userService,
        FormaPagoRepository $formaPagoRepo,
        TipoBeneficiarioRepository $tipoBeneficiarioRepo,
        TipoDocumentoRepository $tipoDocumentoRepo,
        RolRepository $rolRepo,
        NotificationService $notificationService,
        SystemSettingsService $settingsService
    ) {
        $this->txService = $txService;
        $this->pricingService = $pricingService;
        $this->cuentasBeneficiariasService = $cuentasBeneficiariasService;
        $this->userService = $userService;
        $this->formaPagoRepo = $formaPagoRepo;
        $this->tipoBeneficiarioRepo = $tipoBeneficiarioRepo;
        $this->tipoDocumentoRepo = $tipoDocumentoRepo;
        $this->rolRepo = $rolRepo;
        $this->notificationService = $notificationService;
        $this->settingsService = $settingsService;
    }

    // --- CHECKEO DE SISTEMA---

    public function checkSystemStatus(): void
    {
        $status = $this->settingsService->checkSystemAvailability();
        $this->sendJsonResponse(['success' => true, 'status' => $status]);
    }

    // --- MÉTODOS EXISTENTES ---

    public function getPaises(): void
    {
        $rol = $_GET['rol'] ?? 'Ambos';
        $paises = $this->pricingService->getCountriesByRole($rol);
        $this->sendJsonResponse($paises);
    }

    public function getTasa(): void
    {
        $origenID = (int) ($_GET['origenID'] ?? 0);
        $destinoID = (int) ($_GET['destinoID'] ?? 0);
        $montoOrigen = (float) ($_GET['montoOrigen'] ?? 0);
        $tasa = $this->pricingService->getCurrentRate($origenID, $destinoID, $montoOrigen);
        $this->sendJsonResponse($tasa);
    }

    public function getBcvRate(): void
    {
        try {
            $rate = $this->pricingService->getBcvRate();
            $this->sendJsonResponse(['success' => true, 'rate' => $rate]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getFormasDePago(): void
    {
        $paisOrigenId = (int) ($_GET['origenId'] ?? 0);

        if ($paisOrigenId > 0) {
            $formasPago = $this->formaPagoRepo->findAvailableByCountry($paisOrigenId);
        } else {
            $formasPago = [];
        }

        $nombres = array_column($formasPago, 'Nombre');
        $this->sendJsonResponse($nombres);
    }

    public function getTransactionsHistory(): void
    {
        $userId = $this->ensureLoggedIn();

        try {
            $transacciones = $this->txService->getTransactionsByUser($userId);

            $this->sendJsonResponse([
                'success' => true,
                'transacciones' => $transacciones
            ]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getBeneficiaryTypes(): void
    {
        $tipos = $this->tipoBeneficiarioRepo->findAllActive();
        $nombres = array_column($tipos, 'Nombre');
        $this->sendJsonResponse($nombres);
    }

    public function getDocumentTypes(): void
    {
        try {
            $tipos = $this->tipoDocumentoRepo->getAll();
            $this->sendJsonResponse($tipos);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getAssignableRoles(): void
    {
        $roles = $this->rolRepo->findAssignableUserRoles();
        $this->sendJsonResponse($roles);
    }

    public function getCuentas(): void
    {
        $userId = $this->ensureLoggedIn();
        $paisID = (int) ($_GET['paisID'] ?? 0);
        $cuentas = $this->cuentasBeneficiariasService->getAccountsByUser($userId, $paisID ?: null);
        $this->sendJsonResponse($cuentas);
    }

    public function getBeneficiaryDetails(): void
    {
        $userId = $this->ensureLoggedIn();
        $cuentaId = (int) ($_GET['id'] ?? 0);
        if ($cuentaId <= 0) {
            throw new Exception("ID de cuenta inválido", 400);
        }
        $details = $this->cuentasBeneficiariasService->getAccountDetails($userId, $cuentaId);
        $this->sendJsonResponse(['success' => true, 'details' => $details]);
    }

    public function addCuenta(): void
    {
        $userId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $newId = $this->cuentasBeneficiariasService->addAccount($userId, $data);
        $this->sendJsonResponse(['success' => true, 'id' => $newId], 201);
    }

    public function updateBeneficiary(): void
    {
        $userId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $cuentaId = (int) ($data['cuentaId'] ?? 0);
        if ($cuentaId <= 0) {
            throw new Exception("ID de cuenta inválido", 400);
        }
        $this->cuentasBeneficiariasService->updateAccount($userId, $cuentaId, $data);
        $this->sendJsonResponse(['success' => true, 'message' => 'Beneficiario actualizado con éxito']);
    }

    public function deleteBeneficiary(): void
    {
        $userId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $cuentaId = (int) ($data['id'] ?? 0);

        if ($cuentaId <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de cuenta inválido'], 400);
            return;
        }

        try {
            $this->cuentasBeneficiariasService->deleteAccount($userId, $cuentaId);
            $this->sendJsonResponse(['success' => true, 'message' => 'Beneficiario eliminado con éxito']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createTransaccion(): void
    {
        $userId = $this->ensureLoggedIn();
        $sysStatus = $this->settingsService->checkSystemAvailability();
        if (!$sysStatus['available']) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => "El sistema está cerrado por feriado: " . $sysStatus['message']
            ], 403);
            return;
        }

        $data = $this->getJsonInput();
        $data['userID'] = $userId;
        $transactionId = $this->txService->createTransaction($data);
        $this->sendJsonResponse(['success' => true, 'transaccionID' => $transactionId], 201);
    }

    public function cancelTransaction(): void
    {
        $userId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $this->txService->cancelTransaction($data['transactionId'] ?? 0, $userId);
        $this->sendJsonResponse(['success' => true]);
    }

    public function uploadReceipt(): void
    {
        $userId = $this->ensureLoggedIn();
        $transactionId = (int) ($_POST['transactionId'] ?? 0);
        $fileData = $_FILES['receiptFile'] ?? null;
        $rutTitular = trim($_POST['rutTitularOrigen'] ?? '');
        $nombreTitular = trim($_POST['nombreTitularOrigen'] ?? '');

        if ($transactionId <= 0 || $fileData === null) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de transacción inválido o archivo no recibido.'], 400);
            return;
        }

        try {
            $this->txService->handleUserReceiptUpload($transactionId, $userId, $fileData, $rutTitular, $nombreTitular);
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }

    public function getUserProfile(): void
    {
        $userId = $this->ensureLoggedIn();
        $profile = $this->userService->getUserProfile($userId);
        $this->sendJsonResponse(['success' => true, 'profile' => $profile]);
    }

    public function updateUserProfile(): void
    {
        $userId = $this->ensureLoggedIn();
        $postData = $_POST;
        $fileData = $_FILES['fotoPerfil'] ?? null;

        $result = $this->userService->updateUserProfile($userId, $postData, $fileData);

        $_SESSION['user_photo_url'] = $result['fotoPerfilUrl'];

        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Perfil actualizado con éxito.',
            'newPhotoUrl' => $result['fotoPerfilUrl']
        ]);
    }

    public function uploadVerificationDocs(): void
    {
        $userId = $this->ensureLoggedIn();
        $this->userService->uploadVerificationDocs($userId, $_FILES);
        $this->sendJsonResponse(['success' => true, 'message' => 'Documentos subidos correctamente. Serán revisados.']);
    }

    public function generate2FASecret(): void
    {
        $userId = $this->ensureLoggedIn();
        $user = $this->userService->getUserProfile($userId);
        $secretData = $this->userService->generateUser2FASecret($userId, $user['Email']);

        $this->sendJsonResponse([
            'success' => true,
            'secret' => $secretData['secret'],
            'qrCodeUrl' => $secretData['qrCodeUrl']
        ]);
    }

    public function enable2FA(): void
    {
        $userId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $code = $data['code'] ?? '';

        if (empty($code)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'El código de verificación es obligatorio.'], 400);
            return;
        }

        $isValid = $this->userService->verifyAndEnable2FA($userId, $code);

        if ($isValid) {
            $backupCodes = $_SESSION['show_backup_codes'] ?? [];
            unset($_SESSION['show_backup_codes']);
            $this->sendJsonResponse(['success' => true, 'backup_codes' => $backupCodes]);
        } else {
            $this->sendJsonResponse(['success' => false, 'error' => 'Código de verificación inválido.'], 400);
        }
    }

    public function disable2FA(): void
    {
        $userId = $this->ensureLoggedIn();
        $success = $this->userService->disable2FA($userId);
        if ($success) {
            $_SESSION['twofa_enabled'] = false;
            $this->sendJsonResponse(['success' => true]);
        } else {
            $this->sendJsonResponse(['success' => false, 'error' => 'No se pudo desactivar 2FA.'], 500);
        }
    }

    public function getActiveDestinationCountries(): void
    {
        $paises = $this->pricingService->getCountriesByRole('Destino');
        $this->sendJsonResponse($paises);
    }

    public function handleContactForm(): void
    {
        $data = $this->getJsonInput();

        $name = trim(htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $email = trim($data['email'] ?? '');
        $subject = trim(htmlspecialchars($data['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $message = trim(htmlspecialchars($data['message'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            throw new Exception("Todos los campos son obligatorios.", 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El correo electrónico no es válido.", 400);
        }

        if (strlen($name) > 100 || strlen($subject) > 200 || strlen($message) > 5000) {
            throw new Exception("Uno o más campos exceden el límite de longitud.", 400);
        }

        try {
            $success = $this->notificationService->sendContactFormEmail($name, $email, $subject, $message);
            if ($success) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Mensaje enviado con éxito.']);
            } else {
                throw new Exception("No se pudo enviar el correo. Intenta más tarde.", 500);
            }
        } catch (Exception $e) {
            throw new Exception("Error del servidor al enviar el correo: " . $e->getMessage(), 500);
        }
    }

    public function getCurrentRate(): void
    {
        try {
            $origenId = (int) ($_GET['origen'] ?? 0);
            $destinoId = (int) ($_GET['destino'] ?? 0);
            $monto = (float) ($_GET['monto'] ?? 0);

            if ($origenId <= 0 || $destinoId <= 0) {
                throw new Exception("IDs de países inválidos.");
            }
            $tasa = $this->pricingService->getCurrentRate($origenId, $destinoId, $monto);
            $this->sendJsonResponse(['success' => true, 'tasa' => $tasa]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function resumeOrder(): void
    {
        $userId = $this->ensureLoggedIn();
        try {
            $data = $this->getJsonInput();

            $txId = (int) ($data['txId'] ?? 0);
            $mensaje = trim($data['mensaje'] ?? '');
            $beneficiaryData = $data['beneficiaryData'] ?? null;

            if ($txId <= 0) {
                throw new Exception("Identificador de transacción inválido.");
            }

            if (empty($mensaje) && empty($beneficiaryData)) {
                throw new Exception("Debes ingresar un mensaje o corregir algún dato.");
            }

            $estadoEnProcesoID = $this->txService->getEstadoIdByName('En Proceso');
            $success = $this->txService->requestResume($txId, $userId, $mensaje, $estadoEnProcesoID, $beneficiaryData);

            if (!$success) {
                throw new Exception("No se pudo actualizar la orden. Intente nuevamente.");
            }

            $this->sendJsonResponse(['success' => true, 'message' => 'Corrección aplicada y orden enviada a revisión.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function checkSessionStatus()
    {
        if (ob_get_length())
            ob_clean();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'logged_in' => false]);
            return;
        }

        $userId = $_SESSION['user_id'];

        try {
            $db = \App\Database\Database::getInstance()->getConnection();
            $sql = "SELECT u.VerificacionEstadoID, ev.NombreEstado as EstadoVerificacion, 
                        u.RolID, r.NombreRol 
                    FROM usuarios u
                    JOIN estados_verificacion ev ON u.VerificacionEstadoID = ev.EstadoID
                    JOIN roles r ON u.RolID = r.RolID
                    WHERE u.UserID = ?";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error en preparación SQL: " . $db->error);
            }

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                echo json_encode(['success' => false, 'logged_in' => false]);
                return;
            }
            $currentVerif = $_SESSION['verification_status'] ?? '';
            $currentRol = $_SESSION['user_rol_name'] ?? '';

            $needsRefresh = false;
            if ($user['EstadoVerificacion'] !== $currentVerif) {
                $_SESSION['verification_status'] = $user['EstadoVerificacion'];
                $_SESSION['verification_status_id'] = $user['VerificacionEstadoID'];
                $needsRefresh = true;
            }
            if ($user['NombreRol'] !== $currentRol) {
                $_SESSION['user_rol_name'] = $user['NombreRol'];
                $_SESSION['user_rol_id'] = $user['RolID'];
                $needsRefresh = true;
            }
            session_write_close();

            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'needs_refresh' => $needsRefresh
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getResellerDashboard(): void
    {
        $userId = $this->ensureLoggedIn();
        $fechaInicio = $_GET['inicio'] ?? date('Y-m-01 00:00:00');
        $fechaFin = $_GET['fin'] ?? date('Y-m-t 23:59:59');
        $stats = $this->txService->getResellerStats($userId, $fechaInicio, $fechaFin);

        $this->sendJsonResponse(['success' => true, 'stats' => $stats]);
    }

    public function calculateConversion(): void
    {
        $userId = $this->ensureLoggedIn();

        $montoGanancia = (float) ($_GET['monto'] ?? 0);
        $paisOrigenID = (int) $_GET['paisOrigenID'];
        $paisDestinoID = (int) $_GET['paisDestinoID'];
        try {
            $tasaInfo = $this->pricingService->getCurrentRate($paisOrigenID, $paisDestinoID, 1000); // Monto referencial
            $valorTasa = (float) $tasaInfo['tasa'];

            if ($valorTasa <= 0)
                throw new Exception("Tasa no válida");
            $montoConvertido = $montoGanancia / $valorTasa;

            $this->sendJsonResponse([
                'success' => true,
                'montoOriginal' => $montoGanancia,
                'tasaUsada' => $valorTasa,
                'montoConvertido' => round($montoConvertido, 2),
                'monedaDestino' => 'CLP'
            ]);

        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}