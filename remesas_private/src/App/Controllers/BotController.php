<?php
namespace App\Controllers;

use App\Services\PricingService;

class BotController extends BaseController
{
    private PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function handleWebhook(): void
    {
        $body = $_POST['Body'] ?? '';
        $from = $_POST['From'] ?? '';

        $mensaje = strtolower(trim($body));
        $respuesta = "";

        if ($mensaje === 'tasa' || $mensaje === 'precio') {
            try {
                $tasa = $this->pricingService->getCurrentRate(1, 2, 0);
                $valor = number_format($tasa['ValorTasa'], 2, ',', '.');
                $respuesta = "La tasa del día es: *{$valor} VES/CLP* 📈";
            } catch (\Exception $e) {
                $respuesta = "Lo siento, no pude obtener la tasa en este momento.";
            }

        } elseif (preg_match('/^calcular (\d+)$/', $mensaje, $matches)) {
            $monto = (float) $matches[1];
            try {
                $tasaData = $this->pricingService->getCurrentRate(1, 2, $monto);
                $total = $monto * $tasaData['ValorTasa'];
                $totalFmt = number_format($total, 2, ',', '.');
                $montoFmt = number_format($monto, 0, ',', '.');
                $respuesta = "Si envías *{$montoFmt} CLP*, se reciben *{$totalFmt} VES* aprox.";
            } catch (\Exception $e) {
                $respuesta = "Monto no válido o fuera de rango.";
            }

        } elseif ($mensaje === 'transferir') {
            $respuesta = "Para transferir, puedes hacerlo:\n\n" .
                "1. *Web:* Regístrate en https://jcenvios.cl\n" .
                "2. *Operador:* Escribe 'Hablar con humano' para que te atienda alguien.";
        } else {
            $respuesta = "Hola! Soy el Bot de JC Envíos 🤖.\n" .
                "Escribe una opción:\n" .
                "- *Tasa*: Ver precio del día\n" .
                "- *Calcular [monto]*: Ej: Calcular 10000\n" .
                "- *Transferir*: Ver opciones de envío";
        }

        header('Content-Type: text/xml');
        echo "<Response><Message>{$respuesta}</Message></Response>";
        exit;
    }
}