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

        if ($monto <= 0) {
            throw new Exception("El monto debe ser positivo.", 400);
        }
        if (!empty($data['bancoId'])) {
            $this->contabilidadService->agregarFondosBanco((int) $data['bancoId'], $monto, $adminId);
        } elseif (!empty($data['paisId'])) {
            $this->contabilidadService->agregarFondosPais((int) $data['paisId'], $monto, $adminId);
        } else {
            throw new Exception("Datos incompletos: Se requiere bancoId o paisId.", 400);
        }

        $this->sendJsonResponse(['success' => true, 'message' => 'Fondos agregados con éxito.']);
    }

    public function registrarGastoVario(): void
    {
        $adminId = $this->ensureLoggedIn();
        $data = $this->getJsonInput();
        $monto = (float) ($data['monto'] ?? 0);
        $motivo = trim($data['motivo'] ?? '');

        if ($monto <= 0 || empty($motivo)) {
            throw new Exception("Faltan datos: monto o motivo.", 400);
        }

        if (!empty($data['paisId'])) {
            $this->contabilidadService->registrarGastoPais((int) $data['paisId'], $monto, $motivo, $adminId);
        } else {
            throw new Exception("Debe especificar el país para el retiro.", 400);
        }

        $this->sendJsonResponse(['success' => true, 'message' => 'Retiro registrado correctamente.']);
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
        $tipo = $_GET['tipo'] ?? 'pais';
        $id = (int) ($_GET['id'] ?? 0);
        $mes = (int) ($_GET['mes'] ?? 0);
        $anio = (int) ($_GET['anio'] ?? 0);

        if ($id <= 0 || $mes <= 0 || $anio <= 2020) {
            throw new Exception("Parámetros de búsqueda inválidos.", 400);
        }

        $resumen = $this->contabilidadService->getResumenMensual($tipo, $id, $mes, $anio);
        $this->sendJsonResponse(['success' => true, 'resumen' => $resumen]);
    }
}