<?php
namespace App\Controllers;

use App\Services\PricingService;
use App\Repositories\CuentasAdminRepository;
use App\Database\Database;

class BotController extends BaseController
{
    private PricingService $pricingService;
    private CuentasAdminRepository $cuentasAdminRepo;

    public function __construct(PricingService $pricingService, ?CuentasAdminRepository $cuentasAdminRepo = null)
    {
        $this->pricingService = $pricingService;

        if ($cuentasAdminRepo === null) {
            $db = Database::getInstance();
            $this->cuentasAdminRepo = new CuentasAdminRepository($db);
        } else {
            $this->cuentasAdminRepo = $cuentasAdminRepo;
        }
    }

    public function handleWebhook(): void
    {
        $body = $_POST['Body'] ?? '';

        $mensaje = strtolower(trim($body));
        $respuesta = "";

        if ($mensaje === 'tasa' || $mensaje === 'precio') {
            try {
                $tasa = $this->pricingService->getCurrentRate(1, 3, 0);
                $valor = number_format($tasa['ValorTasa'], 5, ',', '.');
                $respuesta = "La tasa del día es: *{$valor} VES/CLP* 📈";
            } catch (\Exception $e) {
                $respuesta = "Lo siento, no pude obtener la tasa en este momento.";
            }

        } elseif (preg_match('/^calcular (\d+)$/', $mensaje, $matches)) {
            $monto = (float) $matches[1];
            try {
                $tasaData = $this->pricingService->getCurrentRate(1, 3, $monto);

                $total = $monto * $tasaData['ValorTasa'];

                $totalFmt = number_format($total, 2, ',', '.');
                $montoFmt = number_format($monto, 0, ',', '.');
                $tasaFmt = number_format($tasaData['ValorTasa'], 5, ',', '.');

                $respuesta = "💰 *Cálculo:*\n" .
                    "Envías: *{$montoFmt} CLP*\n" .
                    "Tasa: {$tasaFmt}\n" .
                    "Reciben: *{$totalFmt} VES* aprox.";
            } catch (\Exception $e) {
                $respuesta = "Monto no válido o fuera de rango.";
            }

            // 3. Lógica de DATOS BANCARIOS (Dinámico desde BD)
        } elseif ($mensaje === 'transferir' || $mensaje === 'cuenta' || $mensaje === 'datos') {
            // Buscamos cuenta activa para Transferencia (ID 1) en Chile (ID 1)
            $cuenta = $this->cuentasAdminRepo->findActiveByFormaPagoAndPais(1, 1);

            if ($cuenta) {
                $respuesta = "🏦 *Datos para Transferir:*\n\n" .
                    "*Banco:* {$cuenta['Banco']}\n" .
                    "*Tipo:* {$cuenta['TipoCuenta']}\n" .
                    "*Nro:* `{$cuenta['NumeroCuenta']}`\n" .
                    "*RUT:* {$cuenta['RUT']}\n" .
                    "*Titular:* {$cuenta['Titular']}\n" .
                    "*Email:* {$cuenta['Email']}\n\n" .
                    "⚠️ *Importante:* Envía el comprobante por aquí o súbelo en la web.";
            } else {
                $respuesta = "Por el momento no tenemos cuentas automáticas activas. Por favor contacta a un operador.";
            }

            // 4. Menú por defecto
        } else {
            $respuesta = "Hola! Soy el Bot de JC Envíos 🤖.\n" .
                "Escribe una opción:\n" .
                "- *Tasa*: Ver precio del día\n" .
                "- *Calcular [monto]*: Ej: Calcular 10000\n" .
                "- *Transferir*: Ver datos bancarios";
        }

        header('Content-Type: text/xml');
        echo "<Response><Message>{$respuesta}</Message></Response>";
        exit;
    }
}