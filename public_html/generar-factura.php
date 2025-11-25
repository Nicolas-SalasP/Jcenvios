<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';
require_once __DIR__ . '/../remesas_private/src/lib/fpdf/fpdf.php';

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

global $conexion;
if (!$conexion) {
    http_response_code(500);
    die("Error interno del servidor al conectar con la base de datos.");
}

$sql = "SELECT
            T.TransaccionID, T.FechaTransaccion, T.MontoOrigen, T.MonedaOrigen, T.MontoDestino, T.MonedaDestino,
            U.PrimerNombre, U.PrimerApellido, U.Email, U.NumeroDocumento,
            T.BeneficiarioNombre, T.BeneficiarioDocumento, T.BeneficiarioBanco, T.BeneficiarioNumeroCuenta,
            TS.ValorTasa,
            ET.NombreEstado AS Estado,
            FP.Nombre AS FormaPago,
            T.FormaPagoID,
            TS.PaisOrigenID
        FROM transacciones AS T
        JOIN usuarios AS U ON T.UserID = U.UserID
        LEFT JOIN tasas AS TS ON T.TasaID_Al_Momento = TS.TasaID
        LEFT JOIN estados_transaccion AS ET ON T.EstadoID = ET.EstadoID
        LEFT JOIN formas_pago AS FP ON T.FormaPagoID = FP.FormaPagoID
        WHERE T.TransaccionID = ?";

$params = [$transactionId];
$types = "i";

if ($userRol !== 'Admin' && $userRol !== 'Operador') {
    $sql .= " AND T.UserID = ?";
    $params[] = $userId;
    $types .= "i";
}

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    error_log("Error al preparar la consulta para generar factura: " . $conexion->error);
    http_response_code(500);
    die("Error interno al preparar la consulta.");
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();
$tx = $resultado->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    die("Transacción no encontrada o no tienes permiso para verla.");
}

$cuentaAdmin = null;
if (!empty($tx['FormaPagoID']) && !empty($tx['PaisOrigenID'])) {
    $sqlCuenta = "SELECT * FROM cuentas_bancarias_admin WHERE FormaPagoID = ? AND PaisID = ? AND Activo = 1 LIMIT 1";
    $stmtC = $conexion->prepare($sqlCuenta);
    if ($stmtC) {
        $stmtC->bind_param("ii", $tx['FormaPagoID'], $tx['PaisOrigenID']);
        $stmtC->execute();
        $cuentaAdmin = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();
    }
}

function hex2rgb($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return array($r, $g, $b);
}


try {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // --- LOGO ---
    $logoPath = __DIR__ . '/assets/img/logo.jpeg';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 10, 30);
    }

    $pdf->SetXY(15, 35);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(30, 5, mb_convert_encoding('Multiservicios JyC SPA', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $pdf->SetY(15);

    // --- ENCABEZADO ---
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, mb_convert_encoding('ORDEN DE ENVÍO DE DINERO', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, mb_convert_encoding('Comprobante de Transacción', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Ln(12);

    // --- INFO GENERAL ---
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, mb_convert_encoding('Nro. Orden:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, htmlspecialchars($tx['TransaccionID']), 0, 1);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Fecha:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, date("d/m/Y H:i", strtotime($tx['FechaTransaccion'])), 0, 1);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, 'Estado:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, mb_convert_encoding(htmlspecialchars($tx['Estado'] ?? 'Desconocido'), 'ISO-8859-1', 'UTF-8'), 0, 1);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(40, 7, mb_convert_encoding('Método de Pago:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, mb_convert_encoding(htmlspecialchars($tx['FormaPago'] ?? 'N/A'), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Ln(5);

    // --- TABLAS DE DATOS ---
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(90, 8, 'DATOS DEL REMITENTE', 1, 0, 'C', true);
    $pdf->Cell(90, 8, 'DATOS DEL BENEFICIARIO', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $fill = false;
    $border = 'LR';

    $printDataRow = function ($labelRem, $valueRem, $labelBen, $valueBen, $isLast = false) use ($pdf, $border, $fill) {
        $currentBorder = $border . ($isLast ? 'B' : '');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 6, mb_convert_encoding($labelRem, 'ISO-8859-1', 'UTF-8'), $currentBorder, 0, 'L', $fill);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(65, 6, mb_convert_encoding(htmlspecialchars($valueRem), 'ISO-8859-1', 'UTF-8'), $currentBorder, 0, 'L', $fill);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 6, mb_convert_encoding($labelBen, 'ISO-8859-1', 'UTF-8'), $currentBorder, 0, 'L', $fill);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(65, 6, mb_convert_encoding(htmlspecialchars($valueBen), 'ISO-8859-1', 'UTF-8'), $currentBorder, 1, 'L', $fill);
    };

    $printDataRow('Nombre:', $tx['PrimerNombre'] . ' ' . $tx['PrimerApellido'], 'Nombre:', $tx['BeneficiarioNombre']);
    $printDataRow('Documento:', $tx['NumeroDocumento'], 'Documento:', $tx['BeneficiarioDocumento']);
    $printDataRow('Email:', $tx['Email'], 'Banco:', $tx['BeneficiarioBanco']);
    $printDataRow('', '', 'Cuenta:', $tx['BeneficiarioNumeroCuenta'], true);
    $pdf->Ln(8);

    // --- RESUMEN ---
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 9, mb_convert_encoding('RESUMEN DE LA TRANSACCIÓN', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 10);
    $cellWidths = [60, 60, 60];
    $pdf->Cell($cellWidths[0], 7, 'Monto Enviado', 1, 0, 'C');
    $pdf->Cell($cellWidths[1], 7, 'Tasa Aplicada', 1, 0, 'C');
    $pdf->Cell($cellWidths[2], 7, 'Monto a Recibir', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell($cellWidths[0], 10, number_format($tx['MontoOrigen'], 2, ',', '.') . ' ' . htmlspecialchars($tx['MonedaOrigen']), 1, 0, 'C');

    // CAMBIO: 5 Decimales
    $tasaMostrada = number_format($tx['ValorTasa'] ?? 0, 5, ',', '.');

    $pdf->Cell($cellWidths[1], 10, $tasaMostrada . ' ' . htmlspecialchars($tx['MonedaDestino']) . '/' . htmlspecialchars($tx['MonedaOrigen']), 1, 0, 'C');
    $pdf->Cell($cellWidths[2], 10, number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . htmlspecialchars($tx['MonedaDestino']), 1, 1, 'C');
    $pdf->Ln(10);

    if ($cuentaAdmin) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(220, 230, 240);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 9, mb_convert_encoding('INSTRUCCIONES DE PAGO', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        $yStart = $pdf->GetY();
        $pdf->SetY($yStart + 5);

        $colorRGB = hex2rgb($cuentaAdmin['ColorHex'] ?? '#000000');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor($colorRGB[0], $colorRGB[1], $colorRGB[2]);
        $pdf->Cell(0, 8, mb_convert_encoding($cuentaAdmin['Banco'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);

        $fields = [
            'Titular:' => $cuentaAdmin['Titular'],
            'Tipo de Cuenta:' => $cuentaAdmin['TipoCuenta'],
            'Nro. Cuenta:' => $cuentaAdmin['NumeroCuenta'],
            'RUT:' => $cuentaAdmin['RUT'],
            'Email:' => $cuentaAdmin['Email']
        ];

        foreach ($fields as $label => $value) {
            if (!empty($value)) {
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(85, 6, mb_convert_encoding($label, 'ISO-8859-1', 'UTF-8'), 0, 0, 'R');
                if ($label === 'Nro. Cuenta:') {
                    $pdf->SetFont('Arial', 'B', 14);
                } else {
                    $pdf->SetFont('Arial', '', 11);
                }

                $pdf->Cell(90, 6, mb_convert_encoding(' ' . $value, 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
            }
        }
        $pdf->Ln(4);

        if (!empty($cuentaAdmin['Instrucciones'])) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(200, 0, 0);
            $pdf->Cell(25, 5, 'IMPORTANTE:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $instrucciones = str_replace(["\r\n", "\r", "\n"], "\n", $cuentaAdmin['Instrucciones']);
            $pdf->MultiCell(0, 5, mb_convert_encoding($instrucciones, 'ISO-8859-1', 'UTF-8'));
        }

        $height = $pdf->GetY() - $yStart;
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(15, $yStart, 180, $height + 2);
        $pdf->SetY($pdf->GetY() + 5);
    }

    $pdf->SetY(-30);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(128);
    $pdf->Cell(0, 10, mb_convert_encoding('Gracias por preferir JC Envíos. Su confianza es nuestra prioridad.', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    if (ob_get_level()) {
        ob_end_clean();
    }

    $pdf->Output('I', 'orden-' . $tx['TransaccionID'] . '.pdf');
    exit;

} catch (Exception $e) {
    error_log("Error al generar PDF para TX ID {$transactionId}: " . $e->getMessage());
    http_response_code(500);
    die("Error interno al generar el comprobante PDF.");
}
?>