<?php

namespace App\Services;

use App\Database\Database;

class LogService
{
    /** errno de MySQL para violación de clave foránea al insertar/actualizar. */
    private const ERR_FK_CONSTRAINT = 1452;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Escribe una entrada en la bitácora.
     *
     * La bitácora NUNCA debe ser la causa de que falle una operación de negocio:
     * abortar una liquidación, una verificación de usuario o un ajuste de tasas
     * porque no se pudo escribir el registro de auditoría es peor que perder el
     * registro. Por eso este método no propaga errores de escritura — los deja
     * en `error_log` con el detalle completo para que no queden invisibles.
     *
     * Caso concreto que motivó esto: `logs.UserID` tiene FK a `usuarios.UserID`
     * (`logs_ibfk_1`). Un admin eliminado con la sesión todavía abierta en su
     * navegador sigue mandando su UserID, y el INSERT revienta con errno 1452.
     * Desde PHP 8.1 mysqli lanza `mysqli_sql_exception`, así que eso se
     * propagaba hasta romper la acción entera con un 500. Afectaba a TODOS los
     * llamadores (UserService, PricingService, LiquidacionService,
     * CuentasBeneficiariasService, ...), no a uno solo.
     *
     * Ante ese caso se reintenta una vez con `UserID = NULL` (la columna lo
     * acepta) conservando el UserID original en el texto del detalle, para no
     * perder la trazabilidad de quién hizo la acción.
     *
     * Nota sobre transacciones: un error de FK en MySQL/InnoDB revierte SOLO la
     * sentencia que falló, no la transacción en curso — está verificado contra
     * la BD real. Tragar el error acá deja la transacción intacta y usable, así
     * que los lugares que loguean dentro de una transacción a propósito (ej. el
     * ajuste manual de liquidaciones) siguen funcionando igual: si la operación
     * hace rollback, el log se revierte con ella.
     */
    public function logAction(?int $userId, string $action, string $details): void
    {
        try {
            $this->insertLog($userId, $action, $details);
        } catch (\mysqli_sql_exception $e) {
            if ($userId !== null && $e->getCode() === self::ERR_FK_CONSTRAINT) {
                // El usuario de la sesión ya no existe en `usuarios`.
                error_log(
                    "LogService: UserID {$userId} no existe en `usuarios` (FK logs_ibfk_1). "
                    . "Se registra la acción '{$action}' con UserID NULL."
                );

                $detailsSinUsuario = "[UserID {$userId} — usuario ya no existe] " . $details;

                try {
                    $this->insertLog(null, $action, $detailsSinUsuario);
                    return;
                } catch (\Throwable $e2) {
                    error_log(
                        "LogService: tampoco se pudo escribir la bitácora con UserID NULL. "
                        . "Acción='{$action}'. Detalles='{$details}'. Error: " . $e2->getMessage()
                    );
                    return;
                }
            }

            error_log(
                "LogService: fallo al escribir la bitácora (errno {$e->getCode()}). "
                . "UserID=" . ($userId ?? 'NULL') . ". Acción='{$action}'. Detalles='{$details}'. "
                . "Error: " . $e->getMessage()
            );
        } catch (\Throwable $e) {
            // prepare() fallido, conexión caída, etc. Mismo criterio: se registra
            // en error_log pero no se tumba la operación de negocio.
            error_log(
                "LogService: fallo al escribir la bitácora. UserID=" . ($userId ?? 'NULL')
                . ". Acción='{$action}'. Detalles='{$details}'. "
                . "Error: " . get_class($e) . ' — ' . $e->getMessage()
            );
        }
    }

    /**
     * INSERT crudo en `logs`. Deja que los errores salgan: quien lo llama decide
     * qué hacer con ellos.
     */
    private function insertLog(?int $userId, string $action, string $details): void
    {
        $sql = "INSERT INTO logs (UserID, Accion, Detalles) VALUES (?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        try {
            $stmt->bind_param("iss", $userId, $action, $details);
            // Si mysqli_report está en OFF (lo pone `scripts/migrate.php`),
            // execute() devuelve false en vez de lanzar. Se normaliza a
            // excepción para que el manejo de arriba sea uno solo.
            if ($stmt->execute() === false) {
                throw new \mysqli_sql_exception($stmt->error, $stmt->errno);
            }
        } finally {
            $stmt->close();
        }
    }

    /**
     * Purga (DELETE) registros de la bitácora con más de $months meses de antigüedad.
     * Es tabla de auditoría de acciones administrativas, no dato con retención legal
     * obligatoria (eso está cubierto aparte para AML/KYC), por eso se borra sin archivar.
     *
     * @return int Cantidad de filas eliminadas.
     */
    public function purgeOldLogs(int $months = 12): int
    {
        $sql = "DELETE FROM logs WHERE Timestamp < (NOW() - INTERVAL ? MONTH)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $months);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}