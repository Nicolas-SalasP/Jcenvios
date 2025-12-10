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
    // SECCIÓN 1: GESTIÓN DE BANCOS (ORIGEN)
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
            $this->logService->logAction($adminId, 'Recarga Banco', "Banco #$cuentaAdminId: +$monto. $descripcion");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al recargar banco: " . $e->getMessage());
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
            $this->logService->logAction($adminId, 'Retiro Banco', "Banco #$cuentaAdminId: -$monto. Motivo: $motivo");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al retirar fondos del banco: " . $e->getMessage());
        }
    }

    // =========================================================================
    // SECCIÓN 2: GESTIÓN DE PAÍSES (DESTINO)
    // =========================================================================

    public function agregarFondosPais(int $paisId, float $monto, int $adminId, string $descripcion = ''): void
    {
        if ($monto <= 0) {
            throw new Exception("El monto debe ser positivo.", 400);
        }

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnterior = (float) $saldo['SaldoActual'];
            $saldoNuevo = $saldoAnterior + $monto;

            $this->contabilidadRepo->registrarMovimiento(
                $saldoId, 
                $adminId, 
                null, 
                'RECARGA', 
                $monto, 
                $saldoAnterior, 
                $saldoNuevo, 
                $descripcion
            );
            
            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Recarga País', "País $paisId: +$monto. $descripcion");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al agregar fondos al país: " . $e->getMessage());
        }
    }

    public function registrarGastoPais(int $paisId, float $monto, string $motivo, int $adminId): void
    {
        if ($monto <= 0) {
            throw new Exception("El monto debe ser positivo.", 400);
        }

        $this->dbConnection->begin_transaction();
        try {
            $saldo = $this->getOrCreateSaldo($paisId);
            $saldoId = $saldo['SaldoID'];
            $saldoAnterior = (float) $saldo['SaldoActual'];
            $saldoNuevo = $saldoAnterior - $monto;
            
            $this->contabilidadRepo->registrarMovimiento(
                $saldoId, 
                $adminId, 
                null, 
                'GASTO_VARIO', 
                $monto, 
                $saldoAnterior, 
                $saldoNuevo, 
                $motivo
            );
            
            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Retiro País', "País $paisId: -$monto. Motivo: $motivo");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error al retirar fondos del país: " . $e->getMessage());
        }
    }

    // =========================================================================
    // SECCIÓN 3: MOVIMIENTOS ENTRE CAJAS
    // =========================================================================

    public function procesarCompraDivisas(int $bancoOrigenId, int $paisDestinoId, float $montoSalida, float $montoEntrada, int $adminId): void
    {
        $this->dbConnection->begin_transaction();
        try {
            // 1. Validar y debitar Banco
            $banco = $this->cuentasAdminRepo->getById($bancoOrigenId);
            if (!$banco) {
                throw new Exception("Banco no encontrado.");
            }

            $saldoBancoAnt = (float) $banco['SaldoActual'];
            $saldoBancoNuevo = $saldoBancoAnt - $montoSalida;
            
            $this->contabilidadRepo->registrarMovimientoBanco(
                $bancoOrigenId,
                $adminId,
                null,
                'RETIRO_DIVISAS',
                $montoSalida,
                $saldoBancoAnt,
                $saldoBancoNuevo,
                "Compra de divisas para País #$paisDestinoId"
            );
            $this->cuentasAdminRepo->updateSaldo($bancoOrigenId, $saldoBancoNuevo);

            // 2. Validar y acreditar País
            $saldoPais = $this->getOrCreateSaldo($paisDestinoId);
            $saldoId = $saldoPais['SaldoID'];
            $saldoPaisAnt = (float) $saldoPais['SaldoActual'];
            $saldoPaisNuevo = $saldoPaisAnt + $montoEntrada;
            
            $this->contabilidadRepo->registrarMovimiento(
                $saldoId,
                $adminId,
                null,
                'COMPRA_DIVISA',
                $montoEntrada,
                $saldoPaisAnt,
                $saldoPaisNuevo,
                "Ingreso desde Banco #$bancoOrigenId"
            );
            $this->contabilidadRepo->actualizarSaldo($saldoId, $saldoPaisNuevo);

            $this->dbConnection->commit();
            $this->logService->logAction($adminId, 'Compra Divisas', "Banco #$bancoOrigenId (-$montoSalida) -> País #$paisDestinoId (+$montoEntrada)");

        } catch (Exception $e) {
            $this->dbConnection->rollback();
            throw new Exception("Error compra divisas: " . $e->getMessage());
        }
    }

    // =========================================================================
    // SECCIÓN 4: AUTOMATIZACIONES (TRANSACCIONES)
    // =========================================================================
    public function registrarIngresoVenta(int $cuentaAdminId, float $monto, int $adminId, int $txId): void
    {
        try {
            $cuenta = $this->cuentasAdminRepo->getById($cuentaAdminId);
            if (!$cuenta) return;
            
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
        if ($montoTx <= 0) return true;
        
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
        if ($diff == 0) return true;
        
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

    public function getResumenMensual(string $tipo, int $id, int $mes, int $anio): array
    {
        $mesStr = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $anioStr = (string) $anio;

        if ($tipo === 'pais') {
            $saldo = $this->contabilidadRepo->getSaldoPorPais($id);
            if (!$saldo) {
                throw new Exception("País no encontrado.");
            }

            $movimientos = $this->contabilidadRepo->getMovimientosDelMes($saldo['SaldoID'], $mesStr, $anioStr);
            $total = $this->contabilidadRepo->getGastosMensuales($saldo['SaldoID'], $mesStr, $anioStr);

            return [
                'Entidad' => $saldo['NombrePais'],
                'Moneda' => $saldo['MonedaCodigo'],
                'TotalGastado' => $total,
                'Movimientos' => $movimientos
            ];
        } elseif ($tipo === 'banco') {
            $cuenta = $this->cuentasAdminRepo->getById($id);
            if (!$cuenta) {
                throw new Exception("Banco no encontrado.");
            }
            
            $movimientos = $this->contabilidadRepo->getMovimientosBancoDelMes($id, $mesStr, $anioStr);
            $moneda = '???';
            if ($cuenta['PaisID']) {
                $moneda = $this->countryRepo->findMonedaById($cuenta['PaisID']) ?? '???';
            }

            return [
                'Entidad' => $cuenta['Banco'] . ' - ' . $cuenta['Titular'],
                'Moneda' => $moneda,
                'TotalGastado' => 0,
                'Movimientos' => $movimientos
            ];
        }
        
        return [];
    }
}