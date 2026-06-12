<?php
namespace App\Controllers;

use Exception;

abstract class BaseController
{
    protected function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $jsonOutput = json_encode($data);
        if ($jsonOutput === false) {
            error_log("Fallo en sendJsonResponse: Error al codificar JSON: " . json_last_error_msg());
            http_response_code(500);
            echo '{"success":false,"error":"Error interno del servidor [JSON_ENCODE_FAIL]"}';
        } else {
            echo $jsonOutput;
        }
        
        exit();
    }

    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return [];
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error al decodificar JSON: " . json_last_error_msg() . " - Input: " . substr($input, 0, 500));
            $this->sendJsonResponse(['success' => false, 'error' => 'Los datos enviados no tienen un formato JSON válido.'], 400);
            exit();
        }
        return $data ?? [];
    }

    protected function ensureLoggedIn(): int
    {
        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Acceso denegado. Se requiere iniciar sesión.'], 401);
            exit();
        }
        return (int)$_SESSION['user_id'];
    }

    protected function ensureAdmin(): void
    {
        $userId = $this->ensureLoggedIn();
        if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
            $this->sendJsonResponse(['success' => false, 'error' => 'Acceso denegado. Se requiere rol de administrador.'], 403);
            exit();
        }
    }

    protected function ensureAdminOrOperator(): int
    {
        $userId = $this->ensureLoggedIn();
        if (!isset($_SESSION['user_rol_name']) || 
            ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')) {
            
            $this->sendJsonResponse(['success' => false, 'error' => 'Acceso denegado. Se requiere rol de Admin u Operador.'], 403);
            exit();
        }
        return $userId;
    }

    protected function ensureReseller(): int
    {
        $userId = $this->ensureLoggedIn();
        if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Revendedor') {
            $this->sendJsonResponse(['success' => false, 'error' => 'Acceso denegado. Se requiere rol de Revendedor.'], 403);
            exit();
        }
        return $userId;
    }
}