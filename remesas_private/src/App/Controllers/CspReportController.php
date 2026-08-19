<?php
namespace App\Controllers;

use App\Services\LogService;

class CspReportController extends BaseController
{
    private LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Recibe reportes de violación CSP (Reporting API: report-to) enviados
     * por el navegador. Se loguean en la bitácora (tabla logs, UserID null)
     * para que admin/logs.php los muestre igual que cualquier otra acción.
     */
    public function receiveCspReport(): void
    {
        $reports = $this->getJsonInput();

        if (!empty($reports)) {
            $detalles = json_encode($reports, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($detalles !== false) {
                $detalles = substr($detalles, 0, 5000);
                $this->logService->logAction(null, 'Violación CSP', $detalles);
            }
        }

        // El navegador no espera contenido de vuelta.
        http_response_code(204);
    }
}
