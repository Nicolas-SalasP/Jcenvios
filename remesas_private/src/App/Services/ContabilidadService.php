<?php
namespace App\Services;

use App\Repositories\ContabilidadRepository;
use App\Repositories\CountryRepository;
use App\Repositories\CuentasAdminRepository;
use App\Services\LogService;
use App\Database\Database;
use Exception;

class ContabilidadService
{
    private ContabilidadRepository $contabilidadRepo;
    private CountryRepository $countryRepo;
    private CuentasAdminRepository $cuentasAdminRepo;
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
        $this->cuentasAdminRepo = new CuentasAdminRepository($db);
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
            throw new Exception("País $paisId no tiene moneda definida.", 500);
        }

        $this->contabilidadRepo->crearRegistroSaldo($paisId, $moneda);
        return $this->contabilidadRepo->getSaldoPorPais($paisId);
    }

    // =========================================================================
    // SECCIÓN 1: GESTIÓN DE BANCOS (UNIFICADA ORIGEN Y DESTINO)
    // =========================================================================

    public function agregarFondosBanco(int $cuentaAdminId, float $monto, int $adminId, string $descripcion = ''): void
    {
        if ($monto <= 0) {
            throw new Exception("El monto debe ser positivo.", 400);
        }

        $this->dbConnection->begin_transaction();
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta) {
                throw new Exception("Cuenta bancaria no encontrada.");
            }

            $saldoAnterior = (float) $cuenta['SaldoActual'];
            $saldoNuevo = $saldoAnterior + $monto;

            $this->contabilidadRepo->registrarMovimientoBanco(
                $cuentaAdminId,
                $adminId,
                null,
                'RECARGA',
                $monto,
                $saldoAnterior,
                $saldoNuevo,
                $descripcion
            );
            $this->cuentasAdminRepo->updateSaldo($cuentaAdminId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Recarga Cuenta', "Cuenta #$cuentaAdminId: +$monto. $descripcion");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al recargar cuenta: " . $e->getMessage());
        }
    }

    public function registrarRetiroBanco(int $cuentaAdminId, float $monto, string $motivo, int $adminId): void
    {
        if ($monto <= 0) {
            throw new Exception("El monto debe ser positivo.", 400);
        }

        $this->dbConnection->begin_transaction();
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta) {
                throw new Exception("Cuenta bancaria no encontrada.");
            }

            $saldoAnterior = (float) $cuenta['SaldoActual'];
            $saldoNuevo = $saldoAnterior - $monto;

            $this->contabilidadRepo->registrarMovimientoBanco(
                $cuentaAdminId,
                $adminId,
                null,
                'GASTO_VARIO',
                $monto,
                $saldoAnterior,
                $saldoNuevo,
                $motivo
            );

            $this->cuentasAdminRepo->updateSaldo($cuentaAdminId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Retiro Cuenta', "Cuenta #$cuentaAdminId: -$monto. Motivo: $motivo");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al retirar fondos de la cuenta: " . $e->getMessage());
        }
    }

    // =========================================================================
    // SECCIÓN 2: ALIAS PARA COMPATIBILIDAD (ELIMINA ERROR 500)
    // =========================================================================

    public function agregarFondosPais(int $bancoId, float $monto, int $adminId, string $descripcion = ''): void
    {
        $this->agregarFondosBanco($bancoId, $monto, $adminId, $descripcion);
    }
    public function registrarGastoPais(int $bancoId, float $monto, string $motivo, int $adminId): void
    {
        $this->registrarRetiroBanco($bancoId, $monto, $motivo, $adminId);
    }

    // =========================================================================
    // SECCIÓN 3: MOVIMIENTOS ENTRE CUENTAS (TRANSFERENCIAS)
    // =========================================================================

    public function registrarTransferencia(int $origenId, int $destinoId, float $salida, float $entrada, int $adminId): void
    {
        $this->dbConnection->begin_transaction();
        try {
            $bancoOri = $this->cuentasAdminRepo->getById($origenId);
            if (!$bancoOri)
                throw new Exception("Cuenta origen no encontrada.");

            $saldoOriAnt = (float) $bancoOri['SaldoActual'];
            $saldoOriNew = $saldoOriAnt - $salida;

            $this->contabilidadRepo->registrarMovimientoBanco(
                $origenId,
                $adminId,
                null,
                'RETIRO_DIVISAS',
                $salida,
                $saldoOriAnt,
                $saldoOriNew,
                "Transferencia a Cuenta #$destinoId"
            );
            $this->cuentasAdminRepo->updateSaldo($origenId, $saldoOriNew);

            $bancoDes = $this->cuentasAdminRepo->getById($destinoId);
            if (!$bancoDes)
                throw new Exception("Cuenta destino no encontrada.");

            $saldoDesAnt = (float) $bancoDes['SaldoActual'];
            $saldoDesNew = $saldoDesAnt + $entrada;

            $this->contabilidadRepo->registrarMovimientoBanco(
                $destinoId,
                $adminId,
                null,
                'COMPRA_DIVISA',
                $entrada,
                $saldoDesAnt,
                $saldoDesNew,
                "Fondeo desde Cuenta #$origenId"
            );
            $this->cuentasAdminRepo->updateSaldo($destinoId, $saldoDesNew);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Transferencia Interna', "De #$origenId a #$destinoId. Salida: $salida, Entrada: $entrada");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error en transferencia: " . $e->getMessage());
        }
    }

    public function procesarCompraDivisas(int $bancoOrigenId, int $paisDestinoId, float $montoSalida, float $montoEntrada, int $adminId): void
    {
        $cuentas = $this->contabilidadRepo->getSaldosBancos();
        $cuentaDestinoId = null;

        foreach ($cuentas as $c) {
            if ((int) $c['PaisID'] === $paisDestinoId && $c['Rol'] === 'Destino') {
                $cuentaDestinoId = (int) $c['CuentaAdminID'];
                break;
            }
        }

        if (!$cuentaDestinoId) {
            throw new Exception("No existe una cuenta bancaria de destino configurada para este país.");
        }

        $this->registrarTransferencia($bancoOrigenId, $cuentaDestinoId, $montoSalida, $montoEntrada, $adminId);
    }

    // =========================================================================
    // SECCIÓN 4: AUTOMATIZACIONES (MANTENIDO SIN CAMBIOS)
    // =========================================================================

    public function registrarIngresoVenta(int $cuentaAdminId, float $monto, int $adminId, int $txId): void
    {
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta)
                return;

            $saldoAnt = (float) $cuenta['SaldoActual'];
            $saldoNew = $saldoAnt + $monto;

            $this->contabilidadRepo->registrarMovimientoBanco(
                $cuentaAdminId,
                $adminId,
                $txId,
                'INGRESO_VENTA',
                $monto,
                $saldoAnt,
                $saldoNew,
                "Ingreso por venta TX #$txId"
            );

            $this->cuentasAdminRepo->updateSaldo($cuentaAdminId, $saldoNew);
        } catch (Exception $e) {
            error_log("Error ingreso venta: " . $e->getMessage());
        }
    }

    public function registrarGasto(int $paisId, float $montoTx, float $montoComision, int $adminId, int $txId): bool
    {
        if ($montoTx <= 0)
            return true;

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnt = (float) $saldo['SaldoActual'];

            $total = $montoTx + $montoComision;
            $saldoNew = $saldoAnt - $total;

            if ($montoTx > 0) {
                $this->contabilidadRepo->registrarMovimiento(
                    $saldoId,
                    $adminId,
                    $txId,
                    'GASTO_TX',
                    $montoTx,
                    $saldoAnt,
                    $saldoAnt - $montoTx,
                    "Pago TX #$txId"
                );
                $saldoAnt -= $montoTx;
            }

            if ($montoComision > 0) {
                $this->contabilidadRepo->registrarMovimiento(
                    $saldoId,
                    $adminId,
                    $txId,
                    'GASTO_COMISION',
                    $montoComision,
                    $saldoAnt,
                    $saldoNew,
                    "Comisión TX #$txId"
                );
            }

            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNew);
            $this->dbConnection->commit();
            return true;
        } catch (Exception $e) {
            $this->dbConnection->rollback();
            return false;
        }
    }

    public function corregirGastoComision(int $paisId, float $oldComm, float $newComm, int $adminId, int $txId): bool
    {
        $diff = $newComm - $oldComm;
        if ($diff == 0)
            return true;

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $ant = (float) $saldo['SaldoActual'];
            $new = $ant - $diff;

            $this->contabilidadRepo->registrarMovimiento(
                $saldoId,
                $adminId,
                $txId,
                'GASTO_COMISION',
                $diff,
                $ant,
                $new,
                "Corrección Comisión TX #$txId"
            );

            $this->contabilidadRepo->actualizarSaldo($saldoId, $new);
            $this->dbConnection->commit();
            return true;
        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw $e;
        }
    }

    public function registrarEgresoPago(int $cuentaAdminId, float $monto, int $adminId, int $txId): void
    {
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta)
                return;

            $saldoAnt = (float) $cuenta['SaldoActual'];
            $saldoNew = $saldoAnt - $monto;
            $this->contabilidadRepo->registrarMovimientoBanco(
                $cuentaAdminId,
                $adminId,
                $txId,
                'GASTO_TX',
                $monto,
                $saldoAnt,
                $saldoNew,
                "Pago a beneficiario TX #$txId"
            );
            $this->cuentasAdminRepo->updateSaldo($cuentaAdminId, $saldoNew);
        } catch (Exception $e) {
            error_log("Error egreso pago: " . $e->getMessage());
        }
    }

    // =========================================================================
    // SECCIÓN 5: REPORTES Y LECTURA
    // =========================================================================

    public function getSaldosDashboard(): array
    {
        return $this->contabilidadRepo->getSaldosDashboard();
    }

    public function getSaldosBancosDashboard(): array
    {
        return $this->contabilidadRepo->getSaldosBancos();
    }

    public function getSaldosPaises(): array
    {
        return $this->contabilidadRepo->getSaldosDashboard();
    }

    public function getSaldosBancos(): array
    {
        return $this->contabilidadRepo->getSaldosBancos();
    }

    public function getResumenMensual(string $tipo, int $id, int $mes, int $anio): array
    {
        $mesStr = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $anioStr = (string) $anio;

        $cuenta = $this->cuentasAdminRepo->getById($id);
        if (!$cuenta) {
            throw new Exception("Cuenta bancaria no encontrada.");
        }

        $movimientos = $this->contabilidadRepo->getMovimientosBancoDelMes($id, $mesStr, $anioStr);
        $moneda = $this->countryRepo->findMonedaById($cuenta['PaisID']) ?? '???';

        return [
            'Entidad' => $cuenta['Banco'] . ' - ' . $cuenta['Titular'],
            'Moneda' => $moneda,
            'TotalGastado' => 0,
            'Movimientos' => $movimientos
        ];
    }
}