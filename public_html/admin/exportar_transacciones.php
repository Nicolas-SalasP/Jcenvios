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

    if ($modo === 'dia') {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
    }

    $data = $txRepository->getExportData($startDate, $endDate);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transacciones');

    $headers = [
        'ID',
        'Fecha Solicitud',
        'Cliente',
        'Doc. Cliente',
        'Cantidad enviada',
        'Tasa',
        'Cantidad destino',
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
    $sheet->getStyle('A1:O1')->getFont()->setBold(true);

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
        $sheet->setCellValue('E' . $rowNumber, (float) $row['MontoOrigen']);
        $sheet->getStyle('E' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValue('F' . $rowNumber, (float) $row['ValorTasa']);
        $sheet->getStyle('F' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00000');
        $sheet->setCellValue('G' . $rowNumber, (float) $row['MontoDestino']);
        $sheet->getStyle('G' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValue('H' . $rowNumber, (float) $row['ComisionDestino']);
        $sheet->getStyle('H' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
        if (!empty($row['FechaCompletado'])) {
            $timestamp = strtotime($row['FechaCompletado']);

            $sheet->setCellValue('I' . $rowNumber, Date::PHPToExcel($timestamp));
            $sheet->getStyle('I' . $rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy');

            $sheet->setCellValue('J' . $rowNumber, Date::PHPToExcel($timestamp));
            $sheet->getStyle('J' . $rowNumber)->getNumberFormat()->setFormatCode('HH:mm:ss');
        } else {
            $sheet->setCellValue('I' . $rowNumber, '-');
            $sheet->setCellValue('J' . $rowNumber, '-');
        }

        $sheet->setCellValue('K' . $rowNumber, $row['BancoOrigenCliente'] ?? 'N/A');
        $sheet->setCellValue('L' . $rowNumber, $row['BancoSalidaAdmin'] ?? 'N/A');
        $sheet->setCellValue('M' . $rowNumber, $row['BeneficiarioBanco']);
        $sheet->setCellValueExplicit('N' . $rowNumber, $row['BeneficiarioNumeroCuenta'], DataType::TYPE_STRING);
        $sheet->setCellValue('O' . $rowNumber, $row['BeneficiarioNombre']);

        $rowNumber++;
    }

    foreach (range('A', 'O') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $prefix = ($modo === 'dia') ? "Reporte_Diario_" : "Reporte_General_";
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