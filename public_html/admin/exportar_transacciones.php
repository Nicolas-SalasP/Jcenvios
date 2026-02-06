<?php
require_once __DIR__ . '/../../remesas_private/vendor/autoload.php';
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

use App\Database\Database;
use App\Repositories\TransactionRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if (!isset($_SESSION['user_rol_name']) || ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')) {
    die("Acceso denegado.");
}

try {
    $db = Database::getInstance();
    $txRepository = new TransactionRepository($db);

    $modo = $_GET['mode'] ?? 'historico';
    $startDate = $_GET['start'] ?? null;
    $endDate = $_GET['end'] ?? null;
    $originId = !empty($_GET['origin_id']) ? (int)$_GET['origin_id'] : null;
    $destId = !empty($_GET['dest_id']) ? (int)$_GET['dest_id'] : null;

    if ($modo === 'dia') {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
    }

    $data = $txRepository->getExportData($startDate, $endDate, $originId, $destId);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transacciones');

    $headers = [
        'ID',
        'Fecha Solicitud',
        'Cliente',
        'Doc. Cliente',
        'País Origen',
        'Cantidad enviada',
        'Tasa',
        'Cantidad destino',
        'País Destino',
        'Comisión',
        'Fecha Completado',
        'Hora Completado',
        'Banco Entrada (Cliente)',
        'Banco Salida (Admin)',
        'Banco Destino',
        'Cuenta Beneficiario',
        'Beneficiario'
    ];
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:Q1')->getFont()->setBold(true);

    $rowNumber = 2;
    foreach ($data as $row) {
        $sheet->setCellValue('A' . $rowNumber, $row['TransaccionID']);

        if (!empty($row['FechaTransaccion'])) {
            $tsSolicitud = strtotime($row['FechaTransaccion']);
            $sheet->setCellValue('B' . $rowNumber, Date::PHPToExcel($tsSolicitud));
            $sheet->getStyle('B' . $rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy HH:mm');
        } else {
            $sheet->setCellValue('B' . $rowNumber, '-');
        }

        $sheet->setCellValue('C' . $rowNumber, $row['ClienteNombre']);
        $sheet->setCellValueExplicit('D' . $rowNumber, $row['ClienteDocumento'], DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $rowNumber, $row['PaisOrigen']);
        $sheet->setCellValue('F' . $rowNumber, (float) $row['MontoOrigen']);
        $sheet->getStyle('F' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValue('G' . $rowNumber, (float) $row['ValorTasa']);
        $sheet->getStyle('G' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00000');
        $sheet->setCellValue('H' . $rowNumber, (float) $row['MontoDestino']);
        $sheet->getStyle('H' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValue('I' . $rowNumber, $row['PaisDestino']);
        $sheet->setCellValue('J' . $rowNumber, (float) $row['ComisionDestino']);
        $sheet->getStyle('J' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        
        if (!empty($row['FechaCompletado'])) {
            $timestamp = strtotime($row['FechaCompletado']);
            $sheet->setCellValue('K' . $rowNumber, Date::PHPToExcel($timestamp));
            $sheet->getStyle('K' . $rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy');

            $sheet->setCellValue('L' . $rowNumber, Date::PHPToExcel($timestamp));
            $sheet->getStyle('L' . $rowNumber)->getNumberFormat()->setFormatCode('HH:mm:ss');
        } else {
            $sheet->setCellValue('K' . $rowNumber, '-');
            $sheet->setCellValue('L' . $rowNumber, '-');
        }

        $sheet->setCellValue('M' . $rowNumber, $row['BancoOrigenCliente'] ?? 'N/A');
        $sheet->setCellValue('N' . $rowNumber, $row['BancoSalidaAdmin'] ?? 'N/A');
        $sheet->setCellValue('O' . $rowNumber, $row['BeneficiarioBanco']);
        $sheet->setCellValueExplicit('P' . $rowNumber, $row['BeneficiarioNumeroCuenta'], DataType::TYPE_STRING);
        $sheet->setCellValue('Q' . $rowNumber, $row['BeneficiarioNombre']);

        $rowNumber++;
    }

    foreach (range('A', 'Q') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $prefix = ($modo === 'dia') ? "Reporte_Diario_" : "Reporte_General_";
    if ($destId) { $prefix .= "Ruta_Filtrada_"; }
    
    $filename = $prefix . date('Y-m-d_His') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);

    if (ob_get_length())
        ob_end_clean();
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    error_log("Error al exportar transacciones XLSX: " . $e->getMessage());
    die("Error interno al generar el reporte: " . $e->getMessage());
}
?>