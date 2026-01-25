<?php
namespace App\Controllers;

use App\Services\DashboardService;
use App\Repositories\CountryRepository;
use Exception;

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;
    private CountryRepository $countryRepository;

    public function __construct(
        DashboardService $dashboardService,
        CountryRepository $countryRepository
    ) {
        $this->dashboardService = $dashboardService;
        $this->countryRepository = $countryRepository;
    }

    public function getStats(): void
    {
        try {
            $stats = $this->dashboardService->getAdminDashboardStats();
            $this->sendJsonResponse(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function getDolarBcvData(): void
    {
        try {
            $origenId = (int)($_GET['origenId'] ?? 0);
            $destinoId = (int)($_GET['destinoId'] ?? 0);

            if ($origenId === 0) {
                $origenId = $this->countryRepository->findIdByName('Chile');
                if (!$origenId) throw new Exception("País de Origen 'Chile' no encontrado.", 500);
            }

            if ($destinoId === 0) {
                $destinoId = $this->countryRepository->findIdByName('Venezuela');
                if (!$destinoId) throw new Exception("País de Destino 'Venezuela' no encontrado.", 500);
            }

            $data = $this->dashboardService->getDolarBcvData($origenId, $destinoId);
            
            $this->sendJsonResponse($data);

        } catch (Exception $e) {
             error_log("Error en DashboardController::getDolarBcvData: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }

    public function subirComprobanteExpress(): void
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("Sesión expirada o inválida.", 401);
            }

            $transactionId = $_POST['transaction_id'] ?? null;
            $rut = $_POST['rut_titular'] ?? '';
            $nombre = $_POST['nombre_titular'] ?? '';
            $file = $_FILES['comprobante'] ?? null;

            if (!$transactionId || !$rut || !$nombre || !$file) {
                throw new Exception("Faltan datos obligatorios (ID, RUT, Nombre o Archivo).", 400);
            }

            $resultado = $this->dashboardService->procesarComprobanteExpress(
                (int)$_SESSION['user_id'],
                (int)$transactionId,
                $rut,
                $nombre,
                $file
            );

            if ($resultado) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Comprobante subido correctamente.']);
            } else {
                throw new Exception("No se pudo actualizar la base de datos.", 500);
            }

        } catch (Exception $e) {
            error_log("Error en subirComprobanteExpress: " . $e->getMessage());
            $this->sendJsonResponse(
                ['success' => false, 'error' => $e->getMessage()], 
                $e->getCode() >= 400 ? $e->getCode() : 500
            );
        }
    }
}