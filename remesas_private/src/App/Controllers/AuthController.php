<?php
namespace App\Controllers;

use App\Services\UserService;
use Exception;

class AuthController extends BaseController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function loginUser(): void
    {
        try {
            $data = $this->getJsonInput();
            $result = $this->userService->loginUser($data['email'] ?? '', $data['password'] ?? '');
            $isPrivileged = isset($result['Rol']) && in_array($result['Rol'], ['Admin', 'Operador']);

            if ($result['twofa_enabled'] && !$isPrivileged) {
                $_SESSION['2fa_user_id'] = $result['UserID'];
                unset($_SESSION['user_id']);
                unset($_SESSION['user_rol_name']);

                $this->sendJsonResponse([
                    'success' => true,
                    'twofa_required' => true,
                    'redirect' => BASE_URL . '/verify-2fa.php'
                ]);
                return;
            }

            $this->finalizeLogin($result['UserID']);

        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 ? $e->getCode() : 401;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $statusCode);
        }
    }

    public function send2FACode(): void
    {
        try {
            if (!isset($_SESSION['2fa_user_id'])) {
                throw new Exception("Sesión de verificación expirada.", 401);
            }

            $data = $this->getJsonInput();
            $method = $data['method'] ?? 'email';
            $res = $this->userService->generateAndSend2FACode($_SESSION['2fa_user_id'], $method);

            if ($res) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Código enviado con éxito.']);
            } else {
                throw new Exception("No se pudo enviar el código. Intenta con otro método.");
            }
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function verify2FACode(): void
    {
        try {
            if (!isset($_SESSION['2fa_user_id'])) {
                throw new Exception("No hay una autenticación pendiente.", 400);
            }

            $userId = $_SESSION['2fa_user_id'];
            $data = $this->getJsonInput();
            $code = $data['code'] ?? '';

            if (empty($code)) {
                throw new Exception("El código es obligatorio.");
            }

            $isValid = $this->userService->verifyTemp2FACode($userId, $code);

            if (!$isValid) {
                $isValid = $this->userService->verifyUser2FACode($userId, $code);
            }

            if (!$isValid) {
                $isValid = $this->userService->verifyBackupCode($userId, $code);
            }

            if ($isValid) {
                $this->finalizeLogin($userId);
            } else {
                throw new Exception("Código de seguridad inválido o expirado.");
            }

        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 401);
        }
    }

    public function registerUser(): void
    {
        $data = $_POST;
        try {
            $result = $this->userService->registerUser($data);
            $this->finalizeLogin($result['UserID']);
        } catch (Exception $e) {
            $statusCode = $e->getCode() >= 400 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $statusCode);
        }
    }

    public function requestPasswordReset(): void
    {
        try {
            $data = $this->getJsonInput();
            $this->userService->requestPasswordReset($data['email'] ?? '');
            $this->sendJsonResponse(['success' => true, 'message' => 'Si tu correo está en nuestro sistema, recibirás un enlace para restablecer tu contraseña.']);

        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function performPasswordReset(): void
    {
        try {
            $data = $this->getJsonInput();
            $this->userService->performPasswordReset($data['token'] ?? '', $data['newPassword'] ?? '');
            $this->sendJsonResponse(['success' => true, 'message' => '¡Contraseña actualizada con éxito! Ya puedes iniciar sesión.']);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function finalizeLogin(int $userId): void
    {
        unset($_SESSION['2fa_user_id']);
        session_regenerate_id(true);

        $user = $this->userService->getUserProfile($userId);

        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['user_name'] = $user['PrimerNombre'];
        $_SESSION['user_rol_name'] = $user['Rol'];
        $_SESSION['verification_status'] = $user['VerificacionEstado'];
        $_SESSION['twofa_enabled'] = $user['twofa_enabled'];
        $_SESSION['user_photo_url'] = $user['FotoPerfilURL'] ?? null;
        $_SESSION['ultima_actividad'] = time();

        $redirectUrl = BASE_URL . '/dashboard/';

        if ($user['Rol'] === 'Admin') {
            $redirectUrl = BASE_URL . '/admin/';
        } elseif ($user['Rol'] === 'Operador') {
            $redirectUrl = BASE_URL . '/operador/pendientes.php';
        } elseif ($user['VerificacionEstado'] !== 'Verificado') {
            $redirectUrl = BASE_URL . '/dashboard/verificar.php';
        }

        $this->sendJsonResponse([
            'success' => true,
            'redirect' => $redirectUrl,
            'twofa_required' => false
        ]);
    }
}