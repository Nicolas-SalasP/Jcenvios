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
        Database $db,
        ?CuentasAdminRepository $cuentasAdminRepo = null
    ) {
        $this->contabilidadRepo = $contabilidadRepo;
        $this->countryRepo = $countryRepo;
        // Parámetro opcional para poder inyectar un mock en tests; si no se
        // pasa, se mantiene el comportamiento original (instanciarlo acá).
        $this->cuentasAdminRepo = $cuentasAdminRepo ?? new CuentasAdminRepository($db);
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
                throw new Exception("Cuenta bancaria no encontrada.", 404);
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
            throw new Exception("Error al recargar cuenta: " . $e->getMessage(), $e->getCode() ?: 500);
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
                throw new Exception("Cuenta bancaria no encontrada.", 404);
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
            throw new Exception("Error al retirar fondos de la cuenta: " . $e->getMessage(), $e->getCode() ?: 500);
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
                throw new Exception("Cuenta origen no encontrada.", 404);

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
                throw new Exception("Cuenta destino no encontrada.", 404);

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
            throw new Exception("Error en transferencia: " . $e->getMessage(), $e->getCode() ?: 500);
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
            throw new Exception("No existe una cuenta bancaria de destino configurada para este país.", 409);
        }

        $this->registrarTransferencia($bancoOrigenId, $cuentaDestinoId, $montoSalida, $montoEntrada, $adminId);
    }

    // =========================================================================
    // SECCIÓN 4: AUTOMATIZACIONES (MANTENIDO SIN CAMBIOS)
    // =========================================================================

    public function registrarIngresoVenta(int $cuentaAdminId, float $monto, int $adminId, int $txId): void
    {
        $this->dbConnection->begin_transaction();
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta) {
                $this->dbConnection->rollback();
                return;
            }

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
            $this->dbConnection->commit();
        } catch (Exception $e) {
            $this->dbConnection->rollback();
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
        $this->dbConnection->begin_transaction();
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta) {
                $this->dbConnection->rollback();
                return;
            }

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
            $this->dbConnection->commit();
        } catch (Exception $e) {
            $this->dbConnection->rollback();
            error_log("Error egreso pago: " . $e->getMessage());
        }
    }

    /**
     * Revierte el ingreso por venta de una transacción que se cancela/rechaza
     * después de que el admin ya confirmó el pago (estado "En Proceso").
     *
     * Sin esto el saldo de la cuenta admin quedaba inflado para siempre: el
     * INGRESO_VENTA de adminConfirmPayment nunca se deshacía.
     *
     * Idempotente: opera sobre el NETO pendiente de revertir por cuenta
     * (ver ContabilidadRepository::getNetoIngresoVentaPorCuenta), así que
     * llamarlo dos veces sobre la misma transacción no descuenta dos veces.
     * Si nunca hubo ingreso (o ya se revirtió), no hace nada y devuelve true.
     *
     * Devuelve bool —a diferencia de registrarIngresoVenta/registrarEgresoPago,
     * que son void— justamente para que el llamador pueda enterarse del fallo
     * y dejar rastro. Ver el manejo en TransactionService.
     */
    public function revertirIngresoVenta(int $txId, int $actorId): bool
    {
        $this->dbConnection->begin_transaction();
        try {
            // El lock y la lectura del neto van DENTRO de la transacción: el
            // "SUM(INGRESO_VENTA) - SUM(REVERSA_VENTA)" es lo que hace idempotente
            // a esta operación, y leerlo afuera y sin lock permitía que dos
            // reversas concurrentes de la misma transacción (cliente cancelando y
            // admin rechazando a la vez) vieran ambas Neto > 0 y descontaran las
            // dos.
            $this->contabilidadRepo->lockMovimientosDeTransaccion($txId);

            $pendientes = $this->contabilidadRepo->getNetoIngresoVentaPorCuenta($txId);
            if (empty($pendientes)) {
                // Nada que revertir: no hubo ingreso, o ya se revirtió.
                $this->dbConnection->commit();
                return true;
            }

            foreach ($pendientes as $fila) {
                $cuentaAdminId = (int) $fila['CuentaAdminID'];
                $montoRevertir = (float) $fila['Neto'];

                // ForUpdate: updateSaldo escribe un valor absoluto calculado a
                // partir de esta lectura, así que sin bloquear la fila un
                // registrarIngresoVenta concurrente sobre la misma cuenta se
                // perdería (last write wins).
                $cuenta = $this->cuentasAdminRepo->getByIdForUpdate($cuentaAdminId);
                if (!$cuenta) {
                    throw new Exception("Cuenta admin #$cuentaAdminId no encontrada al revertir TX #$txId.", 500);
                }

                $saldoAnt = (float) $cuenta['SaldoActual'];
                $saldoNew = $saldoAnt - $montoRevertir;

                // A diferencia de registrarIngresoVenta, acá SÍ se chequea el
                // retorno: si el INSERT del movimiento falla y se actualiza el
                // saldo igual, el libro y el saldo quedan desincronizados.
                $ok = $this->contabilidadRepo->registrarMovimientoBanco(
                    $cuentaAdminId,
                    $actorId,
                    $txId,
                    'REVERSA_VENTA',
                    $montoRevertir,
                    $saldoAnt,
                    $saldoNew,
                    "Reversa por cancelación TX #$txId"
                );
                if (!$ok) {
                    throw new Exception("No se pudo registrar el movimiento de reversa para TX #$txId.", 500);
                }

                $this->cuentasAdminRepo->updateSaldo($cuentaAdminId, $saldoNew);
            }

            $this->dbConnection->commit();
            return true;
        } catch (Exception $e) {
            $this->dbConnection->rollback();
            error_log("[CONTAB][REVERSA_FALLIDA] TX #$txId: " . $e->getMessage());
            return false;
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
        $movimientos = [];
        $entidadNombre = '';
        $moneda = '';

        if ($tipo === 'banco') {
            $cuenta = $this->cuentasAdminRepo->getById($id);
            if (!$cuenta)
                throw new Exception("Cuenta bancaria no encontrada.", 404);

            $entidadNombre = $cuenta['Banco'] . ' - ' . $cuenta['Titular'];
            $moneda = $this->countryRepo->findMonedaById($cuenta['PaisID']) ?? '???';
            $movimientos = $this->contabilidadRepo->getMovimientosBancoDelMes($id, $mesStr, $anioStr);
        } else {
            $saldo = $this->contabilidadRepo->getSaldoPorPais($id);
            $movimientos = $this->contabilidadRepo->getMovimientosDelMes($id, $mesStr, $anioStr);
            $entidadNombre = "Caja País/Destino"; 
            $moneda = "N/A";
        }

        $totalGastado = 0.0;
        foreach ($movimientos as $mov) {
            if (in_array($mov['TipoMovimiento'], ['GASTO_VARIO', 'GASTO_TX', 'GASTO_COMISION', 'RETIRO_DIVISAS'])) {
                $totalGastado += (float) $mov['Monto'];
            }
        }

        return [
            'Entidad' => $entidadNombre,
            'Moneda' => $moneda,
            'TotalGastado' => $totalGastado,
            'Movimientos' => $movimientos
        ];
    }
}