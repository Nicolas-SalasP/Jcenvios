<?php
namespace App\Services;

use App\Repositories\ContabilidadRepository;
use App\Repositories\CountryRepository;
use App\Services\LogService;
use App\Database\Database;
use Exception;

class ContabilidadService
{
    private ContabilidadRepository $contabilidadRepo;
    private CountryRepository $countryRepo;
    private LogService $logService;
    private $dbConnection;

    public function __construct(
        ContabilidadRepository $contabilidadRepo,
        CountryRepository $countryRepo,
        LogService $logService,
        Database $db
    ) {
        $this->contabilidadRepo = $contabilidadRepo;
        $this->countryRepo = $countryRepo;
        $this->logService = $logService;
        $this->dbConnection = $db->getConnection();
    }

    private function getOrCreateSaldo(int $paisId): array
    {
        $saldo = $this->contabilidadRepo->getSaldoPorPais($paisId);
        if ($saldo) {
            return $saldo;
        }

        $moneda = $this->countryRepo->findMonedaById($paisId);
        if (!$moneda) {
            throw new Exception("No se puede crear saldo. País $paisId no tiene moneda definida.", 500);
        }

        $this->contabilidadRepo->crearRegistroSaldo($paisId, $moneda);
        $saldo = $this->contabilidadRepo->getSaldoPorPais($paisId);

        if (!$saldo) {
            throw new Exception("Error fatal: No se pudo crear ni obtener el registro de saldo para el País ID $paisId.", 500);
        }
        return $saldo;
    }

    public function registrarGasto(int $paisId, float $montoTx, float $montoComision, int $adminId, int $txId): bool
    {
        if ($montoTx <= 0)
            return true;

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnterior = (float) $saldo['SaldoActual'];

            $montoTotalGasto = $montoTx + $montoComision;
            $saldoNuevo = $saldoAnterior - $montoTotalGasto;

            if ($montoTx > 0) {
                $this->contabilidadRepo->registrarMovimiento($saldoId, $adminId, $txId, 'GASTO_TX', $montoTx, $saldoAnterior, $saldoAnterior - $montoTx);
                $saldoAnterior = $saldoAnterior - $montoTx;
            }

            if ($montoComision > 0) {
                $this->contabilidadRepo->registrarMovimiento($saldoId, $adminId, $txId, 'GASTO_COMISION', $montoComision, $saldoAnterior, $saldoNuevo);
            }

            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNuevo);

            $this->dbConnection->commit();

            $this->logService->logAction($adminId, 'Contabilidad Gasto TX', "TX ID: $txId. Saldo $saldoId debitado en $montoTotalGasto. Nuevo Saldo: $saldoNuevo");
            return true;

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            error_log("Error al registrar gasto contable para TX $txId: " . $e->getMessage());
            return false;
        }
    }

    public function agregarFondos(int $paisId, float $monto, int $adminId, string $tipo): void
    {
        if ($monto <= 0)
            throw new Exception("El monto a agregar debe ser positivo.", 400);

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnterior = (float) $saldo['SaldoActual'];
            $saldoNuevo = $saldoAnterior + $monto;

            $tipoMovimiento = ($tipo === 'inicial') ? 'SALDO_INICIAL' : 'RECARGA';

            $this->contabilidadRepo->registrarMovimiento($saldoId, $adminId, null, $tipoMovimiento, $monto, $saldoAnterior, $saldoNuevo);
            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Contabilidad Recarga', "Saldo $saldoId recargado en $monto. Nuevo Saldo: $saldoNuevo");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al agregar fondos: " . $e->getMessage(), 500);
        }
    }

    public function getSaldosDashboard(): array
    {
        return $this->contabilidadRepo->getSaldosDashboard();
    }

    public function getResumenMensual(int $paisId, int $mes, int $anio): array
    {
        $saldo = $this->contabilidadRepo->getSaldoPorPais($paisId);
        $paisNombre = $this->countryRepo->findNameById($paisId) ?? "ID $paisId";
        $moneda = $this->countryRepo->findMonedaById($paisId) ?? 'N/A';

        if (!$saldo) {
            return [
                'Pais' => $paisNombre,
                'Moneda' => $moneda,
                'Mes' => $mes,
                'Anio' => $anio,
                'TotalGastado' => 0.00,
                'Movimientos' => []
            ];
        }

        $totalGastado = $this->contabilidadRepo->getGastosMensuales($saldo['SaldoID'], (string) $mes, (string) $anio);
        $movimientos = $this->contabilidadRepo->getMovimientosDelMes($saldo['SaldoID'], (string) $mes, (string) $anio);

        return [
            'Pais' => $saldo['NombrePais'],
            'Moneda' => $saldo['MonedaCodigo'],
            'Mes' => $mes,
            'Anio' => $anio,
            'TotalGastado' => $totalGastado,
            'Movimientos' => $movimientos
        ];
    }

    public function corregirGastoComision(int $paisId, float $comisionAnterior, float $comisionNueva, int $adminId, int $txId): bool
    {
        $diferencia = $comisionNueva - $comisionAnterior;

        if ($diferencia == 0)
            return true;

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnteriorEnBD = (float) $saldo['SaldoActual'];
            $saldoNuevo = $saldoAnteriorEnBD - $diferencia;
            $this->contabilidadRepo->registrarMovimiento(
                $saldoId,
                $adminId,
                $txId,
                'GASTO_COMISION',
                $diferencia,
                $saldoAnteriorEnBD,
                $saldoNuevo
            );

            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Corrección Comisión', "TX ID: $txId. Ajuste: $diferencia. Nuevo Saldo: $saldoNuevo");
            return true;

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            error_log("Error al corregir comisión contable para TX $txId: " . $e->getMessage());
            throw new Exception("Error al ajustar el saldo contable.", 500);
        }
    }
}