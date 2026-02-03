<?php

namespace App\Services;

use Exception;
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

class PDFService
{
    // Función auxiliar para colores hexadecimales
    private function hex2rgb($hex)
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

    // Función auxiliar para limpiar y codificar texto para FPDF
    private function cleanText($text)
    {
        if ($text === null)
            return '';
        // Decodificamos entidades HTML por si acaso y luego convertimos a ISO-8859-1
        return mb_convert_encoding(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), 'ISO-8859-1', 'UTF-8');
    }
    
    private function formatDocumentNumber($doc)
    {
        if (empty($doc)) return 'N/A';

        $clean = trim($doc);
        if (preg_match('/^([VEJGP])\s*[-]?\s*(\d+)$/i', $clean, $matches)) {
            $prefix = strtoupper($matches[1]);
            $number = $matches[2];
            return $prefix . '- ' . number_format((float)$number, 0, '', '.');
        }
        if (preg_match('/^(\d+)-([\dkK])$/', $clean, $matches)) {
            $body = number_format((float)$matches[1], 0, '', '.');
            $dv = strtoupper($matches[2]);
            return $body . '-' . $dv;
        }
        $onlyDigits = preg_replace('/\D/', '', $clean);
        if (strlen($onlyDigits) > 4 && strlen($onlyDigits) == strlen(str_replace(['.', ','], '', $clean))) {
            return number_format((float)$onlyDigits, 0, '', '.');
        }

        return $clean;
    }

    public function generateOrder(array $tx): string
    {
        // Limpieza de buffer para evitar errores de PDF corrupto
        if (ob_get_length())
            ob_clean();

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // --- LOGO ---
        $logoPath = __DIR__ . '/../../../../public_html/assets/img/logo.jpeg';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 30);
        }

        $pdf->SetXY(15, 35);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, $this->cleanText('Multiservicios JyC SPA'), 0, 0, 'C');

        $pdf->SetY(15);

        // --- TÍTULO ---
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $this->cleanText('ORDEN DE ENVÍO DE DINERO'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $this->cleanText('Comprobante de Transacción'), 0, 1, 'C');
        $pdf->Ln(12);

        // --- INFO GENERAL ---
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(40, 7, $this->cleanText('Nro. Orden:'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, $tx['TransaccionID'], 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(40, 7, 'Fecha:', 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, date("d/m/Y H:i", strtotime($tx['FechaTransaccion'])), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(40, 7, 'Estado:', 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, $this->cleanText($tx['Estado'] ?? 'Desconocido'), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(40, 7, $this->cleanText('Método de Pago:'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, $this->cleanText($tx['FormaDePago'] ?? 'N/A'), 0, 1);
        $pdf->Ln(5);

        // --- DATOS REMITENTE Y BENEFICIARIO ---
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(90, 8, 'DATOS DEL REMITENTE', 1, 0, 'C', true);
        $pdf->Cell(90, 8, 'DATOS DEL BENEFICIARIO', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $fill = false;
        $border = 'LR'; // Bordes laterales

        // Función interna para filas de datos
        $printDataRow = function ($labelRem, $valueRem, $labelBen, $valueBen, $isLast = false) use ($pdf, $border, $fill) {
            $currentBorder = $border . ($isLast ? 'B' : '');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(25, 6, $this->cleanText($labelRem), $currentBorder, 0, 'L', $fill);
            $pdf->SetFont('Arial', '', 9);
            // IMPORTANTE: Aquí se limpia el valor del usuario para acentos y comas
            $pdf->Cell(65, 6, $this->cleanText($valueRem), $currentBorder, 0, 'L', $fill);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(25, 6, $this->cleanText($labelBen), $currentBorder, 0, 'L', $fill);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(65, 6, $this->cleanText($valueBen), $currentBorder, 1, 'L', $fill);
        };

        $lblDocRem = !empty($tx['UsuarioTipoDocumentoNombre']) 
            ? $tx['UsuarioTipoDocumentoNombre'] . ':' 
            : 'Documento:';
            
        $lblDocBen = !empty($tx['BeneficiarioTipoDocumentoNombre']) 
            ? $tx['BeneficiarioTipoDocumentoNombre'] . ':' 
            : 'Documento:';

        // Valores formateados con miles y prefijos
        $valDocRem = $this->formatDocumentNumber($tx['NumeroDocumento']);
        $valDocBen = $this->formatDocumentNumber($tx['BeneficiarioDocumento']);

        // --- IMPRESIÓN DE FILAS ---
        $printDataRow('Nombre:', $tx['PrimerNombre'] . ' ' . $tx['PrimerApellido'], 'Nombre:', $tx['BeneficiarioNombre']);
        $printDataRow($lblDocRem, $valDocRem, $lblDocBen, $valDocBen);
        $printDataRow('Email:', $tx['Email'], 'Banco:', $tx['BeneficiarioBanco']);

        $banco = strtoupper($tx['BeneficiarioBanco'] ?? '');
        $rawCuenta = $tx['BeneficiarioNumeroCuenta'] ?? '';
        $rawTelefono = $tx['BeneficiarioTelefono'] ?? '';
        $rawCCI = $tx['BeneficiarioCCI'] ?? '';
        $esYapePlin = ($banco === 'YAPE' || $banco === 'PLIN');
        $esPeru = (isset($tx['MonedaDestino']) && $tx['MonedaDestino'] === 'PEN') || (isset($tx['PaisDestinoID']) && $tx['PaisDestinoID'] == 4);
        $tieneCuenta = !empty($rawCuenta)
            && $rawCuenta !== 'PAGO MOVIL'
            && $rawCuenta != '00000000000000000000'
            && !$esYapePlin;
        $tieneCCI = $esPeru && !empty($rawCCI) && !$esYapePlin;
        $tieneTelefono = !empty($rawTelefono);
        $rowsToPrint = [];

        if ($tieneCuenta) {
            $rowsToPrint[] = ['label' => 'Cuenta:', 'val' => $rawCuenta];
        }
        if ($tieneCCI) {
            $rowsToPrint[] = ['label' => 'CCI:', 'val' => $rawCCI];
        }
        if ($tieneTelefono) {
            $rowsToPrint[] = ['label' => 'Teléfono:', 'val' => $rawTelefono];
        }
        if (empty($rowsToPrint)) {
            $rowsToPrint[] = ['label' => 'Cuenta:', 'val' => 'N/A'];
        }

        foreach ($rowsToPrint as $index => $row) {
            $isLast = ($index === count($rowsToPrint) - 1);
            $printDataRow('', '', $row['label'], $row['val'], $isLast);
        }

        $pdf->Ln(8);

        // --- RESUMEN MONETARIO ---
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 9, $this->cleanText('RESUMEN DE LA TRANSACCIÓN'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', 'B', 10);
        $cellWidths = [60, 60, 60];
        $pdf->Cell($cellWidths[0], 7, 'Monto Enviado', 1, 0, 'C');
        $pdf->Cell($cellWidths[1], 7, 'Tasa Aplicada', 1, 0, 'C');
        $pdf->Cell($cellWidths[2], 7, 'Monto a Recibir', 1, 1, 'C');

        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell($cellWidths[0], 10, number_format($tx['MontoOrigen'], 2, ',', '.') . ' ' . $this->cleanText($tx['MonedaOrigen']), 1, 0, 'C');
        $pdf->Cell($cellWidths[1], 10, number_format($tx['ValorTasa'], 5, ',', '.'), 1, 0, 'C');

        $pdf->Cell($cellWidths[2], 10, number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $this->cleanText($tx['MonedaDestino']), 1, 1, 'C');
        $pdf->Ln(10);

        // --- INSTRUCCIONES DE PAGO (Desde la Cuenta Admin) ---
        if (isset($tx['CuentaAdmin']) && !empty($tx['CuentaAdmin'])) {

            $cuentaAdmin = $tx['CuentaAdmin'];

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetFillColor(220, 230, 240);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->Cell(0, 9, $this->cleanText('INSTRUCCIONES DE PAGO'), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            $yStart = $pdf->GetY();
            $pdf->SetY($yStart + 5);
            $hasQR = !empty($cuentaAdmin['QrCodeURL']);
            $colorRGB = $this->hex2rgb($cuentaAdmin['ColorHex'] ?? '#000000');
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetTextColor($colorRGB[0], $colorRGB[1], $colorRGB[2]);
            if ($hasQR) {
                $pdf->SetX(20);
                $pdf->Cell(110, 8, $this->cleanText($cuentaAdmin['Banco']), 0, 1, 'L');
            } else {
                $pdf->Cell(0, 8, $this->cleanText($cuentaAdmin['Banco']), 0, 1, 'C');
            }

            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(3);

            $fields = [
                'Titular:' => $cuentaAdmin['Titular'],
                'Tipo de Cuenta:' => $cuentaAdmin['TipoCuenta'],
                'Nro. Cuenta:' => $cuentaAdmin['NumeroCuenta'],
                'RUT/ID:' => $cuentaAdmin['RUT'],
                'Email:' => $cuentaAdmin['Email']
            ];

            foreach ($fields as $label => $value) {
                if (!empty($value)) {
                    $pdf->SetFont('Arial', 'B', 11);
                    $pdf->Cell(45, 6, $this->cleanText($label), 0, 0, 'R');

                    if ($label === 'Nro. Cuenta:') {
                        $pdf->SetFont('Arial', 'B', 14);
                    } else {
                        $pdf->SetFont('Arial', '', 11);
                    }
                    $pdf->Cell(80, 6, ' ' . $this->cleanText($value), 0, 1, 'L');
                }
            }
            if ($hasQR) {
                $qrPath = __DIR__ . '/../../../../public_html/assets/img/qr/' . $cuentaAdmin['QrCodeURL'];

                if (file_exists($qrPath)) {
                    $pdf->Image($qrPath, 145, $yStart + 5, 35, 0);
                    $pdf->SetXY(145, $yStart + 42);
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->Cell(35, 4, 'Escanea para pagar', 0, 0, 'C');
                    $pdf->SetY($yStart + 48);
                }
            } else {
                $pdf->Ln(4);
            }
            $currentY = $pdf->GetY();
            if ($hasQR && $currentY < ($yStart + 50)) {
                $pdf->SetY($yStart + 50);
            }

            if (!empty($cuentaAdmin['Instrucciones'])) {
                $pdf->Ln(2);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(200, 0, 0); // Rojo
                $pdf->Cell(25, 5, 'IMPORTANTE:', 0, 0, 'L');
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(0, 0, 0);

                $instrucciones = str_replace(["\r\n", "\r", "\n"], "\n", $cuentaAdmin['Instrucciones']);
                $pdf->SetX(15);
                $pdf->MultiCell(180, 5, $this->cleanText($instrucciones));
            }
            $height = $pdf->GetY() - $yStart;
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect(15, $yStart, 180, $height + 2);
            $pdf->SetY($pdf->GetY() + 5);
        }

        // Pie de página
        $pdf->SetY(-30);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(128);
        $pdf->Cell(0, 10, $this->cleanText('Gracias por preferir JC Envíos.'), 0, 0, 'C');

        return $pdf->Output('S');
    }
}