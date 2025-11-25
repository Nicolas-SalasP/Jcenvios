<?php
require_once __DIR__ . '/../../remesas_private/vendor/autoload.php';
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

use App\Database\Database;
use App\Repositories\TransactionRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

try {
    $db = Database::getInstance();
    $txRepository = new TransactionRepository($db);

    $data = $txRepository->getExportData();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transacciones');

    $headers = [
        'Nombre y apellido del cliente',
        'Cantidad enviada',
        'Tasa de envió',
        'Cantidad destino',
        'Comisión',
        'Fecha de envió de comprobante admin',
        'Hora de envió de comprobante admin',
        'Banco de origen',
        'Banco de destino',
        'Cuenta beneficiario'
    ];
    $sheet->fromArray($headers, NULL, 'A1');

    $rowNumber = 2;
    foreach ($data as $row) {
        // 1. Nombre y apellido del cliente
        $sheet->setCellValue('A' . $rowNumber, $row['ClienteNombre']);

        // 2. Cantidad enviada
        $sheet->setCellValue('B' . $rowNumber, (float) $row['MontoOrigen']);
        $sheet->getStyle('B' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');

        // 3. Tasa de envio
        $sheet->setCellValue('C' . $rowNumber, (float) $row['ValorTasa']);
        $sheet->getStyle('C' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.000000');

        // 4. Cantidad destino
        $sheet->setCellValue('D' . $rowNumber, (float) $row['MontoDestino']);
        $sheet->getStyle('D' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');

        // 5. Comisión
        $sheet->setCellValue('E' . $rowNumber, (float) $row['ComisionDestino']);
        $sheet->getStyle('E' . $rowNumber)->getNumberFormat()->setFormatCode('#,##0.00');

        // 6 y 7. Fecha y Hora
        if (!empty($row['FechaCompletado'])) {
            $timestamp = strtotime($row['FechaCompletado']);

            // Fecha
            $sheet->setCellValue('F' . $rowNumber, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($timestamp));
            $sheet->getStyle('F' . $rowNumber)->getNumberFormat()->setFormatCode('dd/mm/yyyy');

            // Hora
            $sheet->setCellValue('G' . $rowNumber, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($timestamp));
            $sheet->getStyle('G' . $rowNumber)->getNumberFormat()->setFormatCode('HH:mm:ss');
        } else {
            $sheet->setCellValue('F' . $rowNumber, '-');
            $sheet->setCellValue('G' . $rowNumber, '-');
        }

        // 8. Banco de origen (Nombre del Banco Real de la Cuenta Admin)
        $sheet->setCellValue('H' . $rowNumber, $row['BancoOrigenReal'] ?? 'N/A');

        // 9. Banco de destino
        $sheet->setCellValue('I' . $rowNumber, $row['BeneficiarioBanco']);

        // 10. Cuenta beneficiario
        $sheet->setCellValueExplicit('J' . $rowNumber, $row['BeneficiarioNumeroCuenta'], DataType::TYPE_STRING);

        $rowNumber++;
    }

    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = "Reporte_Transacciones_" . date('Y-m-d_His') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);

    ob_end_clean();
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    error_log("Error al exportar transacciones XLSX: " . $e->getMessage());
    die("Error interno al generar el reporte: " . $e->getMessage());
}
?>