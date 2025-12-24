<?php
namespace App\Controllers;

use App\Services\PricingService;
use App\Services\NotificationService;
use App\Repositories\CuentasAdminRepository;
use App\Database\Database;

class BotController extends BaseController
{
    private PricingService $pricingService;
    private CuentasAdminRepository $cuentasAdminRepo;
    private NotificationService $notifService;
    private $db;
    private string $adminPhone;

    public function __construct(
        PricingService $pricingService,
        CuentasAdminRepository $cuentasAdminRepo,
        NotificationService $notifService
    ) {
        $this->pricingService = $pricingService;
        $this->cuentasAdminRepo = $cuentasAdminRepo;
        $this->notifService = $notifService;
        $this->db = Database::getInstance();
        $this->adminPhone = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : '';
    }

    public function handleWebhook(): void
    {
        $from = trim($_POST['From'] ?? '');
        $body = trim($_POST['Body'] ?? '');
        $msg = strtolower($body);

        if (empty($from))
            exit;

        $session = $this->getOrCreateSession($from);
        $estado = $session['estado'];
        $respuesta = "";

        if ($estado === 'CON_EJECUTIVO' && !in_array($msg, ['0', 'menu', 'finalizar'])) {
            exit;
        }

        if (in_array($msg, ['0', 'menu', 'inicio', 'hola', 'finalizar'])) {
            $this->updateSession($from, 'MENU_PRINCIPAL');
            $this->mostrarMenuPrincipal();
            exit;
        }

        switch ($estado) {
            case 'MENU_PRINCIPAL':
                if ($msg === '1') {
                    $respuesta = "📍 *Elije el país al que quieres transferir:* \n\n1- Venezuela 🇻🇪\n2- Volver al menú principal";
                    $this->updateSession($from, 'TASA_DESTINO');
                } elseif ($msg === '2') {
                    $respuesta = "🧮 *Calculadora de Conversión* \n\n👉 *Ingrese el monto que desea convertir:*";
                    $this->updateSession($from, 'CALC_MONTO');
                } elseif ($msg === '3') {
                    $respuesta = "🚀 *Opciones:* \n\n1- Registro Web 🌐\n2- Datos bancarios 🏦\n3- Volver";
                    $this->updateSession($from, 'SUBMENU_ENVIO');
                } elseif ($msg === '4') {
                    $this->alertarEjecutivo($from, "Solicitud Directa");
                    $this->updateSession($from, 'CON_EJECUTIVO');
                    $respuesta = "📞 *Solicitud enviada.* \nUn ejecutivo te escribirá pronto. El bot se ha desactivado. \n\n_Escribe *0* para reactivarlo._";
                } else {
                    $this->mostrarMenuPrincipal();
                    exit;
                }
                break;

            case 'TASA_DESTINO':
                if ($msg === '1') {
                    $respuesta = "🌍 *¿Desde qué país transfieres?* \n\n1- Chile 🇨🇱\n2- Colombia 🇨🇴\n3- Perú 🇵🇪";
                    $this->updateSession($from, 'TASA_ORIGEN');
                } else {
                    $this->resetToMenu($from);
                }
                break;

            case 'TASA_ORIGEN':
                $map = ['1' => 'Chile', '2' => 'Colombia', '3' => 'Perú'];
                if (isset($map[$msg])) {
                    $tasaVal = $this->obtenerTasaBot($map[$msg], 'Venezuela');
                    $respuesta = "📈 *Tasa referencial {$map[$msg]} -> Venezuela:* \n*{$tasaVal}* \n\n1- Ver datos para transferir\n2- Menú principal";
                    $this->updateSession($from, 'POST_TASA');
                } else {
                    $respuesta = "Seleccione 1, 2 o 3.";
                }
                break;

            case 'POST_TASA':
                if ($msg === '1') {
                    $respuesta = "💳 *Método de pago:* \n1- Caja vecina\n2- Transferencia bancaria";
                    $this->updateSession($from, 'DATOS_METODO');
                } else {
                    $this->resetToMenu($from);
                }
                break;

            case 'CALC_MONTO':
                $monto = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $body)));
                if ($monto > 0) {
                    $calc = $this->calcularFull($monto);
                    $respuesta = "📊 *Conversión:* \nEnvías: *{$monto} CLP* \nRecibes: *{$calc['ves']} VES* \nRef: *{$calc['usd']} USD* \n\n1- Ver datos para transferir\n2- Hablar con Ejecutivo\n3- Volver";
                    $this->updateSession($from, 'POST_CALC');
                } else {
                    $respuesta = "Monto inválido.";
                }
                break;

            case 'POST_CALC':
                if ($msg === '1') {
                    $respuesta = "💳 *Método de pago:* \n1- Caja vecina\n2- Transferencia bancaria";
                    $this->updateSession($from, 'DATOS_METODO');
                } elseif ($msg === '2') {
                    $this->alertarEjecutivo($from, "Consulta calculadora");
                    $this->updateSession($from, 'CON_EJECUTIVO');
                    $respuesta = "📞 Notificando a un ejecutivo...";
                } else {
                    $this->resetToMenu($from);
                }
                break;

            case 'SUBMENU_ENVIO':
                if ($msg === '1') {
                    $respuesta = "📝 *Registro JC Envíos* \n\nRegístrate en: https://jcenvios.cl/register.php \n\nEscribe *0* para volver.";
                } elseif ($msg === '2') {
                    $respuesta = "💳 *Métodos de pago:* \n1- Caja vecina\n2- Transferencia bancaria";
                    $this->updateSession($from, 'DATOS_METODO');
                } else {
                    $this->resetToMenu($from);
                }
                break;

            case 'DATOS_METODO':
                $metodoId = ($msg === '1') ? 2 : 1;
                $cuenta = $this->cuentasAdminRepo->findActiveByFormaPagoAndPais($metodoId, 1);
                if ($cuenta) {
                    $respuesta = "🏦 *Datos:* {$cuenta['Banco']} \n*Nro:* `{$cuenta['NumeroCuenta']}` \n*Titular:* {$cuenta['Titular']} \n*RUT:* {$cuenta['RUT']} \n\n*Nota:* {$cuenta['Instrucciones']} \n\n1- Hablar con Ejecutivo\n2- Volver al Inicio";
                    $this->updateSession($from, 'POST_DATOS_FINAL');
                } else {
                    $respuesta = "Sin cuentas activas.";
                }
                break;

            case 'POST_DATOS_FINAL':
                if ($msg === '1') {
                    $this->alertarEjecutivo($from, "Dudas sobre cuentas");
                    $this->updateSession($from, 'CON_EJECUTIVO');
                    $respuesta = "📞 Ejecutivo notificado.";
                } else {
                    $this->resetToMenu($from);
                }
                break;

            default:
                $this->resetToMenu($from);
                exit;
        }

        if (!empty($respuesta))
            $this->enviarRespuesta($respuesta);
    }

    private function alertarEjecutivo($cliente, $motivo)
    {
        if (empty($this->adminPhone))
            return;
        $texto = "⚠️ *ALERTA BOT:* El cliente *$cliente* solicita atención humana. \nMotivo: $motivo.";
        $this->notifService->sendWhatsApp($this->adminPhone, $texto);
    }

    private function mostrarMenuPrincipal()
    {
        $menu = "Hola, gracias por contactarte con *JC Envíos* 🤖 \n\n1- Ver las tasas del día\n2- Calculadora de conversión\n3- Transferir / Registrarme\n4- Hablar con un ejecutivo";
        $this->enviarRespuesta($menu);
    }

    private function resetToMenu($tel)
    {
        $this->updateSession($tel, 'MENU_PRINCIPAL');
        $this->mostrarMenuPrincipal();
    }

    private function obtenerTasaBot($orig, $dest)
    {
        $p = ['Chile' => 1, 'Colombia' => 2, 'Perú' => 5, 'Venezuela' => 3];
        $t = $this->pricingService->getCurrentRate($p[$orig], $p[$dest], 0);
        return number_format($t['ValorTasa'], ($orig === 'Colombia' ? 2 : 5), ',', '.') . " VES";
    }

    private function calcularFull($monto)
    {
        $tasa = $this->pricingService->getCurrentRate(1, 3, $monto);
        $totalVES = $monto * $tasa['ValorTasa'];
        $stmt = $this->db->prepare("SELECT valor FROM tasas_bcv ORDER BY fecha DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $bcv = $row['valor'] ?? 1;
        return [
            'ves' => number_format($totalVES, 2, ',', '.'),
            'usd' => number_format($totalVES / $bcv, 2, ',', '.')
        ];
    }

    private function getOrCreateSession($tel)
    {
        $stmt = $this->db->prepare("SELECT estado FROM bot_sessions WHERE telefono = ?");
        $stmt->bind_param("s", $tel);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res)
            return $res;
        $stmtI = $this->db->prepare("INSERT IGNORE INTO bot_sessions (telefono, estado) VALUES (?, 'MENU_PRINCIPAL')");
        $stmtI->bind_param("s", $tel);
        $stmtI->execute();
        $stmtI->close();
        return ['estado' => 'MENU_PRINCIPAL'];
    }

    private function updateSession($tel, $estado)
    {
        $stmt = $this->db->prepare("UPDATE bot_sessions SET estado = ? WHERE telefono = ?");
        $stmt->bind_param("ss", $estado, $tel);
        $stmt->execute();
        $stmt->close();
    }

    private function enviarRespuesta($texto)
    {
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response><Message>{$texto}</Message></Response>";
        exit;
    }
}