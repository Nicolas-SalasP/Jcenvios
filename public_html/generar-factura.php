<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;
use App\Repositories\TransactionRepository;
use App\Repositories\CuentasAdminRepository;
use App\Services\PDFService;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Acceso denegado. Debes iniciar sesión.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    http_response_code(400);
    die("ID de transacción no válido.");
}

$transactionId = (int) $_GET['id'];
$userId = (int) $_SESSION['user_id'];
$userRol = $_SESSION['user_rol_name'] ?? 'Persona Natural';

try {
    $db = Database::getInstance();
    $txRepo = new TransactionRepository($db);
    $cuentasAdminRepo = new CuentasAdminRepository($db);
    $pdfService = new PDFService();

    $txData = $txRepo->getFullTransactionDetails($transactionId);

    if (!$txData) {
        throw new Exception("Transacción no encontrada.");
    }

    if ($userRol !== 'Admin' && $userRol !== 'Operador' && $txData['UserID'] != $userId) {
        http_response_code(403);
        die("No tienes permiso para ver esta factura.");
    }

    if (!empty($txData['FormaPagoID']) && !empty($txData['PaisOrigenID'])) {
        $cuentaAdmin = $cuentasAdminRepo->findActiveByFormaPagoAndPais(
            (int) $txData['FormaPagoID'],
            (int) $txData['PaisOrigenID']
        );

        if ($cuentaAdmin) {
            $txData['CuentaAdmin'] = $cuentaAdmin;
        }
    }
    $pdfContent = $pdfService->generateOrder($txData);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="orden-' . $transactionId . '.pdf"');
    echo $pdfContent;
    exit;

} catch (Exception $e) {
    error_log("Error al generar factura TX $transactionId: " . $e->getMessage());
    http_response_code(500);
    die("Error interno al generar el documento PDF.");
}
?>