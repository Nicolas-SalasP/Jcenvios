<?php
namespace App\Controllers;

use App\Services\ContabilidadService;
use Exception;

class ContabilidadController extends BaseController
{
    private ContabilidadService $contabilidadService;

    public function __construct(ContabilidadService $contabilidadService)
    {
        $this->contabilidadService = $contabilidadService;
        $this->ensureAdmin();
    }

    public function getSaldos(): void
    {
        $saldosPaises = $this->contabilidadService->getSaldosDashboard();
        $saldosBancos = $this->contabilidadService->getSaldosBancosDashboard();

        $this->sendJsonResponse([
            'success' => true,
            'saldos' => $saldosPaises,
            'bancos' => $saldosBancos
        ]);
    }

    public function agregarFondos(): void
    {
        $adminId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();

        $monto = (float) ($data['monto'] ?? 0);
        $descripcion = trim($data['descripcion'] ?? '');

        if ($monto <= 0)
            throw new Exception("El monto debe ser positivo.", 400);
        if (empty($descripcion))
            throw new Exception("La descripción es obligatoria.", 400);

        if (!empty($data['bancoId'])) {
            $this->contabilidadService->agregarFondosBanco((int) $data['bancoId'], $monto, $adminId, $descripcion);
        } elseif (!empty($data['paisId'])) {
            $this->contabilidadService->agregarFondosBanco((int) $data['paisId'], $monto, $adminId, $descripcion);
        } else {
            throw new Exception("Datos incompletos: Se requiere ID de cuenta.", 400);
        }

        $this->sendJsonResponse(['success' => true, 'message' => 'Fondos agregados con éxito.']);
    }

    public function retirarFondos(): void
    {
        $adminId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $monto = (float) ($data['monto'] ?? 0);
        $descripcion = trim($data['descripcion'] ?? $data['motivo'] ?? '');

        if ($monto <= 0)
            throw new Exception("El monto debe ser positivo.", 400);
        if (empty($descripcion))
            throw new Exception("El motivo o descripción es obligatorio.", 400);

        if (!empty($data['bancoId'])) {
            $this->contabilidadService->registrarRetiroBanco((int) $data['bancoId'], $monto, $descripcion, $adminId);
        } elseif (!empty($data['paisId'])) {
            $this->contabilidadService->registrarRetiroBanco((int) $data['paisId'], $monto, $descripcion, $adminId);
        } else {
            throw new Exception("Debe especificar una cuenta para el retiro.", 400);
        }

        $this->sendJsonResponse(['success' => true, 'message' => 'Retiro registrado correctamente.']);
    }

    public function registrarGastoVario(): void
    {
        $this->retirarFondos();
    }

    public function compraDivisas(): void
    {
        $adminId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();

        $bancoOrigenId = (int) ($data['bancoOrigenId'] ?? 0);
        $paisDestinoId = (int) ($data['paisDestinoId'] ?? 0);
        $montoSalida = (float) ($data['montoSalida'] ?? 0);
        $montoEntrada = (float) ($data['montoEntrada'] ?? 0);

        if ($bancoOrigenId <= 0 || $paisDestinoId <= 0 || $montoSalida <= 0 || $montoEntrada <= 0) {
            throw new Exception("Todos los campos son obligatorios y deben ser positivos.", 400);
        }

        $this->contabilidadService->procesarCompraDivisas($bancoOrigenId, $paisDestinoId, $montoSalida, $montoEntrada, $adminId);
        $this->sendJsonResponse(['success' => true, 'message' => 'Compra de divisas registrada correctamente.']);
    }

    public function getResumenMensual(): void
    {
        $tipo = $_GET['tipo'] ?? 'banco';
        $id = (int) ($_GET['id'] ?? 0);
        $mes = (int) ($_GET['mes'] ?? 0);
        $anio = (int) ($_GET['anio'] ?? 0);

        if ($id <= 0 || $mes <= 0 || $anio <= 2020) {
            throw new Exception("Parámetros de búsqueda inválidos.", 400);
        }

        $resumen = $this->contabilidadService->getResumenMensual($tipo, $id, $mes, $anio);
        $this->sendJsonResponse(['success' => true, 'resumen' => $resumen]);
    }

    public function getContabilidadGlobal(): void
    {
        try {
            $bancos = $this->contabilidadService->getSaldosBancos();

            $this->sendJsonResponse([
                'success' => true,
                'origen' => $bancos
            ]);

        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function transferenciaInterna(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
            return;
        }

        try {
            $adminId = $this->ensureLoggedIn();
            $input = $this->getJsonInput();

            if (empty($input['origen_id']) || empty($input['destino_id']) || empty($input['monto_salida'])) {
                throw new Exception("Faltan datos para la transferencia");
            }

            $this->contabilidadService->registrarTransferencia(
                (int) $input['origen_id'],
                (int) $input['destino_id'],
                (float) $input['monto_salida'],
                (float) ($input['monto_entrada'] ?? $input['monto_salida']),
                $adminId
            );

            $this->sendJsonResponse(['success' => true, 'message' => 'Transferencia registrada correctamente']);

        } catch (Exception $e) {
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}