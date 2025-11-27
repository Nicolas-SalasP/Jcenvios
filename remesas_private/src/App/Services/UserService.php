<?php
namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\EstadoVerificacionRepository;
use App\Repositories\RolRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Services\NotificationService;
use App\Services\FileHandlerService;
use Exception;
use PragmaRX\Google2FA\Google2FA;

class UserService
{
    private UserRepository $userRepository;
    private NotificationService $notificationService;
    private FileHandlerService $fileHandler;
    private EstadoVerificacionRepository $estadoVerificacionRepo;
    private RolRepository $rolRepo;
    private TipoDocumentoRepository $tipoDocumentoRepo;

    private string $encryptionKey;
    private const ENCRYPTION_CIPHER = 'aes-256-cbc';

    public function __construct(
        UserRepository $userRepository,
        NotificationService $notificationService,
        FileHandlerService $fileHandler,
        EstadoVerificacionRepository $estadoVerificacionRepo,
        RolRepository $rolRepo,
        TipoDocumentoRepository $tipoDocumentoRepo
    ) {
        $this->userRepository = $userRepository;
        $this->notificationService = $notificationService;
        $this->fileHandler = $fileHandler;
        $this->estadoVerificacionRepo = $estadoVerificacionRepo;
        $this->rolRepo = $rolRepo;
        $this->tipoDocumentoRepo = $tipoDocumentoRepo;

        if (!defined('APP_ENCRYPTION_KEY') || strlen(APP_ENCRYPTION_KEY) !== 32) {
            error_log("Error crítico: APP_ENCRYPTION_KEY no está definida o no tiene 32 caracteres en config.php");
            throw new Exception("Error de configuración interna del servidor.", 500);
        }
        $this->encryptionKey = APP_ENCRYPTION_KEY;
    }

    public function loginUser(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user['PasswordHash'])) {
            // Aquí podríamos incrementar intentos fallidos de un email "dummy" si no existe
            // para mitigar ataques de enumeración, pero por simplicidad solo lanzamos error.
            throw new Exception("Correo electrónico o contraseña no válidos.", 401);
        }
        if ($user['LockoutUntil'] && strtotime($user['LockoutUntil']) > time()) {
            throw new Exception("La cuenta está bloqueada temporalmente. Inténtalo más tarde.", 403);
        }

        $this->userRepository->updateLoginAttempts($user['UserID'], 0, null);
        $this->notificationService->logAdminAction($user['UserID'], 'Inicio de Sesión Exitoso', '');

        return $user;
    }

    public function registerUser(array $data): array
    {
        $requiredFields = ['primerNombre', 'primerApellido', 'email', 'password', 'tipoDocumento', 'numeroDocumento', 'phoneNumber', 'tipoPersona'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("El campo '$field' es obligatorio.", 400);
            }
        }

        $phoneCode = $data['phoneCode'] ?? '';
        $phoneNumber = preg_replace('/\D/', '', $data['phoneNumber'] ?? '');
        $data['telefono'] = $phoneCode . $phoneNumber;

        if (empty($data['telefono'])) {
            throw new Exception("El campo 'telefono' es obligatorio.", 400);
        }

        // --- Lógica de Roles y Revendedores ---
        // Aseguramos que solo se pueda registrar como Persona Natural o Empresa.
        // Los roles Admin o Revendedor se asignan manualmente por un Admin.
        if (!in_array($data['tipoPersona'], ['Persona Natural', 'Empresa'])) {
            throw new Exception("El tipo de cuenta '{$data['tipoPersona']}' no es válido.", 400);
        }

        $rolID = $this->rolRepo->findIdByName($data['tipoPersona']);
        if (!$rolID) {
            throw new Exception("Error interno: Rol no encontrado.", 500);
        }
        $data['rolID'] = $rolID;

        // --- Validaciones de Seguridad ---
        if (strlen($data['password']) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.", 400);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.", 400);
        }

        $data['passwordHash'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $tipoDocumentoID = $this->tipoDocumentoRepo->findIdByName($data['tipoDocumento']);
        if (!$tipoDocumentoID) {
            throw new Exception("Tipo de documento '{$data['tipoDocumento']}' no válido.", 400);
        }
        $data['tipoDocumentoID'] = $tipoDocumentoID;

        $estadoNoVerificadoID = $this->estadoVerificacionRepo->findIdByName('No Verificado');
        if (!$estadoNoVerificadoID)
            throw new Exception("Rol 'No Verificado' no encontrado.", 500);
        $data['verificacionEstadoID'] = $estadoNoVerificadoID;

        // --- REQUERIMIENTO 1: Limpieza de Nombres Opcionales ---
        // Si viene vacío o solo espacios, guardamos NULL en la base de datos
        $data['segundoNombre'] = !empty(trim($data['segundoNombre'] ?? '')) ? trim($data['segundoNombre']) : null;
        $data['segundoApellido'] = !empty(trim($data['segundoApellido'] ?? '')) ? trim($data['segundoApellido']) : null;

        try {
            $userId = $this->userRepository->create($data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode() ?: 500);
        }

        try {
            $this->notificationService->sendWelcomeEmail($data['email'], $data['primerNombre']);
        } catch (Exception $e) {
            error_log("Fallo al enviar email de bienvenida para UserID {$userId}: " . $e->getMessage());
            $this->notificationService->logAdminAction($userId, 'Error Email Bienvenida', "Fallo al enviar: " . $e->getMessage());
        }

        $this->notificationService->logAdminAction($userId, 'Registro de Usuario', "Email: " . $data['email']);

        $newUser = $this->userRepository->findByEmail($data['email']);
        if (!$newUser)
            throw new Exception("Error al obtener datos del usuario recién registrado.", 500);
        return $newUser;
    }

    public function requestPasswordReset(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            if ($this->userRepository->createResetToken($user['UserID'], $token, $expires)) {
                $this->notificationService->sendPasswordResetEmail($email, $token);
                $this->notificationService->logAdminAction($user['UserID'], 'Solicitud Recuperación Contraseña', "Token generado para {$email}");
            } else {
                $this->notificationService->logAdminAction($user['UserID'], 'Error Recuperación Contraseña', "Fallo al crear token para {$email}");
            }
        } else {
            $this->notificationService->logAdminAction(null, 'Intento Recuperación Contraseña Fallido', "Email no encontrado: {$email}");
        }
    }

    public function performPasswordReset(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.", 400);
        }

        $resetData = $this->userRepository->findValidResetToken($token);
        if (!$resetData) {
            throw new Exception("Token no válido o expirado. Solicita uno nuevo.", 400);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($this->userRepository->updatePassword($resetData['UserID'], $passwordHash)) {
            $this->userRepository->markTokenAsUsed($resetData['ResetID']);
            $this->notificationService->logAdminAction($resetData['UserID'], 'Contraseña Restablecida', "Contraseña actualizada mediante token.");
        } else {
            $this->notificationService->logAdminAction($resetData['UserID'], 'Error Restablecimiento Contraseña', "Fallo al actualizar contraseña con token.");
            throw new Exception("No se pudo actualizar la contraseña.", 500);
        }
    }

    public function getUserProfile(int $userId): array
    {
        $profile = $this->userRepository->findUserById($userId);
        if (!$profile) {
            throw new Exception("Perfil de usuario no encontrado.", 404);
        }

        unset($profile['PasswordHash']);
        unset($profile['twofa_secret']);
        unset($profile['twofa_backup_codes']);

        return $profile;
    }

    public function updateUserProfile(int $userId, array $postData, ?array $fileData): array
    {
        $user = $this->getUserProfile($userId);
        $fotoPerfilUrl = $user['FotoPerfilURL'] ?? null;
        $newPhotoPath = null;

        if (isset($fileData) && $fileData['error'] === UPLOAD_ERR_OK) {
            try {
                $newPhotoPath = $this->fileHandler->saveProfilePicture($fileData, $userId);
            } catch (Exception $e) {
                throw new Exception("Error al guardar la foto: " . $e->getMessage(), 500);
            }
        }

        if ($newPhotoPath) {
            $this->userRepository->updateProfileInfo($userId, $user['Telefono'], $newPhotoPath);
            $fotoPerfilUrl = $newPhotoPath;
            $this->notificationService->logAdminAction($userId, 'Perfil Actualizado', "Usuario cambió su foto de perfil.");
        }

        return ['fotoPerfilUrl' => $fotoPerfilUrl, 'telefono' => $user['Telefono']];
    }

    public function adminUpdateUserData(int $adminId, array $data): void
    {
        $targetUserId = (int) ($data['userId'] ?? 0);
        if ($targetUserId <= 0)
            throw new Exception("ID de usuario inválido.", 400);
        if (empty($data['primerNombre']) || empty($data['primerApellido']) || empty($data['telefono'])) {
            throw new Exception("Nombre, Apellido y Teléfono son obligatorios.", 400);
        }
        $success = $this->userRepository->updateGeneralData(
            $targetUserId,
            $data['primerNombre'],
            $data['segundoNombre'] ?? null,
            $data['primerApellido'],
            $data['segundoApellido'] ?? null,
            $data['telefono'],
            $data['numeroDocumento'] ?? null
        );

        if ($success) {
            $this->notificationService->logAdminAction($adminId, 'Admin editó usuario', "Editó datos personales del usuario ID: $targetUserId");
        } else {
            throw new Exception("No se pudieron actualizar los datos.", 500);
        }
    }

    public function uploadVerificationDocs(int $userId, array $files): void
    {
        if (!isset($files['docFrente']) || !isset($files['docReverso'])) {
            throw new Exception("Debes subir ambos lados del documento.", 400);
        }

        $estadoPendienteID = $this->estadoVerificacionRepo->findIdByName('Pendiente');
        if (!$estadoPendienteID)
            throw new Exception("Estado 'Pendiente' no encontrado en la base de datos.", 500);

        try {
            $pathFrente = $this->fileHandler->saveVerificationFile($files['docFrente'], $userId, 'frente');
            $pathReverso = $this->fileHandler->saveVerificationFile($files['docReverso'], $userId, 'reverso');
        } catch (Exception $e) {
            throw new Exception("Error al guardar archivos: " . $e->getMessage(), $e->getCode() ?: 500);
        }


        if ($this->userRepository->updateVerificationDocuments($userId, $pathFrente, $pathReverso, $estadoPendienteID)) {
            $this->notificationService->logAdminAction($userId, 'Subida Documentos Verificación', "Usuario ID: $userId. Estado cambiado a Pendiente.");
        } else {
            @unlink($this->fileHandler->getAbsolutePath($pathFrente));
            @unlink($this->fileHandler->getAbsolutePath($pathReverso));
            throw new Exception("No se pudieron actualizar los datos de verificación en la base de datos.", 500);
        }
    }

    public function updateVerificationStatus(int $adminId, int $userId, string $newStatusName): void
    {
        if (!in_array($newStatusName, ['Verificado', 'Rechazado'])) {
            throw new Exception("Estado de verificación no válido para esta acción: '{$newStatusName}'.", 400);
        }

        $newStatusID = $this->estadoVerificacionRepo->findIdByName($newStatusName);
        if (!$newStatusID) {
            throw new Exception("Estado '{$newStatusName}' no encontrado.", 500);
        }

        $estadoPendienteID = $this->estadoVerificacionRepo->findIdByName('Pendiente');
        if (!$estadoPendienteID)
            throw new Exception("Estado 'Pendiente' no encontrado.", 500);

        $user = $this->userRepository->findUserById($userId);
        if (!$user) {
            throw new Exception("Usuario no encontrado.", 404);
        }
        // Permitimos cambiar de Rechazado a Verificado si hubo error, o de Pendiente.
        // La restricción estricta podría bloquear correcciones manuales, así que la relajamos un poco para Admins.

        if ($this->userRepository->updateVerificationStatus($userId, $newStatusID)) {
            $this->notificationService->logAdminAction($adminId, 'Admin actualizó estado verificación', "Usuario ID: $userId, Nuevo Estado: $newStatusName (ID: $newStatusID)");
        } else {
            throw new Exception("No se pudo actualizar el estado de verificación.", 500);
        }
    }

    public function toggleUserBlock(int $adminId, int $userId, string $newStatus): void
    {
        if ($userId === $adminId) {
            throw new Exception("No puedes bloquearte a ti mismo.", 400);
        }
        if ($userId === 1) {
            throw new Exception("No se puede bloquear al administrador principal (ID 1).", 403);
        }

        if (!in_array($newStatus, ['blocked', 'active'])) {
            throw new Exception("Acción de bloqueo no válida: '{$newStatus}'.", 400);
        }

        $lockoutUntil = ($newStatus === 'blocked')
            ? date('Y-m-d H:i:s', strtotime('+10 years'))
            : null;

        if ($this->userRepository->updateLoginAttempts($userId, 0, $lockoutUntil)) {
            $actionText = $newStatus === 'blocked' ? 'Bloqueado' : 'Desbloqueado';
            $this->notificationService->logAdminAction($adminId, "Admin cambió estado de usuario", "Usuario ID: $userId, Nuevo Estado: $actionText");
        } else {
            throw new Exception("No se pudo actualizar el estado de bloqueo del usuario.", 500);
        }
    }

    public function adminUpdateUserRole(int $adminId, int $targetUserId, int $newRoleId): void
    {
        if ($targetUserId === $adminId) {
            throw new Exception("No puedes cambiar tu propio rol.", 400);
        }
        if ($targetUserId === 1) {
            throw new Exception("No se puede cambiar el rol del administrador principal (ID 1).", 403);
        }

        if ($newRoleId === 1) {
            $currentAdmins = $this->userRepository->countAdmins();
            if ($currentAdmins >= 3) {
                throw new Exception("Limite alcanzado. Maximo 3 Admins permitidos.", 403);
            }
        }

        if ($this->userRepository->updateRole($targetUserId, $newRoleId)) {
            $this->notificationService->logAdminAction($adminId, "Admin cambió rol de usuario", "Usuario ID: $targetUserId, Nuevo Rol ID: $newRoleId");
        } else {
            throw new Exception("No se pudo actualizar el rol del usuario.", 500);
        }
    }

    public function adminDeleteUser(int $adminId, int $targetUserId): void
    {
        if ($targetUserId === $adminId)
            throw new Exception("No puedes eliminarte a ti mismo.", 400);
        if ($targetUserId === 1)
            throw new Exception("No se puede eliminar al Super Administrador.", 403);
        if ($this->userRepository->delete($targetUserId)) {
            $this->notificationService->logAdminAction($adminId, "Admin eliminó usuario", "Usuario ID: $targetUserId (Soft Delete)");
        } else {
            throw new Exception("No se pudo eliminar al usuario.", 500);
        }
    }

    // --- MÉTODOS 2FA ---

    private function encryptData(string $data): string
    {
        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_CIPHER);
        if ($ivLength === false)
            throw new Exception("Cipher inválido para encriptación.");
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($data, self::ENCRYPTION_CIPHER, $this->encryptionKey, 0, $iv);
        if ($encrypted === false)
            throw new Exception("Fallo en encriptación.");

        return base64_encode($iv . $encrypted);
    }

    private function decryptData(string $data): ?string
    {
        $decoded = base64_decode($data);
        if ($decoded === false)
            return null;

        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_CIPHER);
        if ($ivLength === false)
            return null;
        if (strlen($decoded) < $ivLength)
            return null;

        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_CIPHER, $this->encryptionKey, 0, $iv);

        return $decrypted === false ? null : $decrypted;
    }

    private function generateBackupCodes(int $count = 10, int $length = 8): array
    {
        $codes = [];
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charLength = strlen($characters);
        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < $length; $j++) {
                $code .= $characters[random_int(0, $charLength - 1)];
            }
            $codes[] = $code;
        }
        return $codes;
    }

    public function generateUser2FASecret(int $userId, string $userEmail, string $appName = 'JC Envíos'): array
    {
        $google2fa = new Google2FA();
        $secretKey = $google2fa->generateSecretKey();
        $encryptedSecret = $this->encryptData($secretKey);

        $this->userRepository->update2FASecret($userId, $encryptedSecret);

        $qrCodeUrl = $google2fa->getQRCodeUrl($appName, $userEmail, $secretKey);

        return ['secret' => $secretKey, 'qrCodeUrl' => $qrCodeUrl];
    }

    public function verifyAndEnable2FA(int $userId, string $userProvidedCode): bool
    {
        $encryptedSecret = $this->userRepository->get2FASecret($userId);
        if (!$encryptedSecret) {
            throw new Exception("No se encontró un secreto 2FA. Vuelve a generarlo.", 400);
        }

        $secretKey = $this->decryptData($encryptedSecret);
        if (!$secretKey) {
            throw new Exception("Error interno al desencriptar secreto 2FA.", 500);
        }

        $google2fa = new Google2FA();
        $isValid = $google2fa->verifyKey($secretKey, $userProvidedCode);

        if ($isValid) {
            $backupCodes = $this->generateBackupCodes();
            $encryptedBackupCodes = $this->encryptData(json_encode($backupCodes));

            if ($this->userRepository->enable2FA($userId, $encryptedBackupCodes)) {
                $_SESSION['show_backup_codes'] = $backupCodes;
                $_SESSION['twofa_enabled'] = 1;

                $this->notificationService->logAdminAction($userId, '2FA Activado', "El usuario activó 2FA.");

                try {
                    $user = $this->getUserProfile($userId);
                    $this->notificationService->send2FABackupCodes($user['Email'], $secretKey, $backupCodes);
                } catch (Exception $e) {
                    error_log("Error al enviar email 2FA para UserID {$userId}: " . $e->getMessage());
                    $this->notificationService->logAdminAction($userId, 'Error Email 2FA', "Fallo al enviar códigos de respaldo: " . $e->getMessage());
                }

                return true;
            } else {
                throw new Exception("No se pudo activar 2FA en la base de datos.", 500);
            }
        }
        return false;
    }

    public function disable2FA(int $userId): bool
    {
        if ($this->userRepository->disable2FA($userId)) {
            $_SESSION['twofa_enabled'] = 0;
            $this->notificationService->logAdminAction($userId, '2FA Desactivado', "El usuario desactivó 2FA.");
            return true;
        }
        return false;
    }


    public function verifyUser2FACode(int $userId, string $code): bool
    {
        $encryptedSecret = $this->userRepository->get2FASecret($userId);
        if (!$encryptedSecret)
            return false;

        $secretKey = $this->decryptData($encryptedSecret);
        if (!$secretKey)
            return false;

        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secretKey, $code, 0);
    }

    public function verifyBackupCode(int $userId, string $code): bool
    {
        $encryptedBackupCodes = $this->userRepository->getBackupCodes($userId);
        if (!$encryptedBackupCodes)
            return false;

        $backupCodesJson = $this->decryptData($encryptedBackupCodes);
        if (!$backupCodesJson)
            return false;

        $backupCodes = json_decode($backupCodesJson, true);
        if (!is_array($backupCodes) || empty($backupCodes))
            return false;

        $key = array_search($code, $backupCodes);
        if ($key !== false) {
            unset($backupCodes[$key]);
            $newEncryptedBackupCodes = $this->encryptData(json_encode(array_values($backupCodes)));
            $this->userRepository->updateBackupCodes($userId, $newEncryptedBackupCodes);
            $this->notificationService->logAdminAction($userId, 'Código Respaldo 2FA Usado', "Se utilizó un código de respaldo.");
            return true;
        }
        return false;
    }
}