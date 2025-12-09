<?php
namespace App\Controllers;

use App\Services\TransactionService;
use App\Services\PricingService;
use App\Services\CuentasBeneficiariasService;
use App\Services\UserService;
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

    public function __construct(
        TransactionService $txService,
        PricingService $pricingService,
        CuentasBeneficiariasService $cuentasBeneficiariasService,
        UserService $userService,
        FormaPagoRepository $formaPagoRepo,
        TipoBeneficiarioRepository $tipoBeneficiarioRepo,
        TipoDocumentoRepository $tipoDocumentoRepo,
        RolRepository $rolRepo,
        NotificationService $notificationService
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
    }

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

    // --- NUEVO MÉTODO AÑADIDO: OBTENER TASA BCV ---
    public function getBcvRate(): void
    {
        try {
            $rate = $this->pricingService->getBcvRate();
            $this->sendJsonResponse(['success' => true, 'rate' => $rate]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    // ----------------------------------------------

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

    public function getBeneficiaryTypes(): void
    {
        $tipos = $this->tipoBeneficiarioRepo->findAllActive();
        $nombres = array_column($tipos, 'Nombre');
        $this->sendJsonResponse($nombres);
    }

    public function getDocumentTypes(): void
    {
        $tipos = $this->tipoDocumentoRepo->findAllActive();
        $response = array_map(fn($tipo) => ['id' => $tipo['TipoDocumentoID'], 'nombre' => $tipo['NombreDocumento']], $tipos);
        $this->sendJsonResponse($response);
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

        if ($transactionId <= 0 || $fileData === null) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID de transacción inválido o archivo no recibido.'], 400);
            return;
        }

        try {
            $this->txService->handleUserReceiptUpload($transactionId, $userId, $fileData);
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
}