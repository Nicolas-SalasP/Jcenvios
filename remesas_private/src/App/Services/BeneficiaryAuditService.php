<?php
namespace App\Services;

use App\Repositories\BeneficiaryAuditRepository;
use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\TransactionRepository;
use Exception;

class BeneficiaryAuditService
{
    private BeneficiaryAuditRepository $auditRepo;
    private CuentasBeneficiariasRepository $cuentasRepo;
    private TransactionRepository $txRepo;

    public function __construct(
        BeneficiaryAuditRepository $auditRepo,
        CuentasBeneficiariasRepository $cuentasRepo,
        TransactionRepository $txRepo
    ) {
        $this->auditRepo = $auditRepo;
        $this->cuentasRepo = $cuentasRepo;
        $this->txRepo = $txRepo;
    }

    public function requestModification(int $adminId, int $cuentaId, int $userId, array $campos, string $motivo): void
    {
        $cuenta = $this->cuentasRepo->findById($cuentaId);
        if (!$cuenta || $cuenta['UserID'] != $userId) {
            throw new Exception("El beneficiario no existe o no pertenece a este cliente.", 403);
        }

        $ordenesActivas = $this->txRepo->countActiveTransactionsByAccount($cuentaId);
        if ($ordenesActivas > 0) {
            throw new Exception("No se puede editar: Este beneficiario está vinculado a una orden en proceso. Cancela o pausa la orden primero.", 400);
        }

        $this->auditRepo->createEditRequest($cuentaId, $userId, $adminId, $campos, $motivo);
    }

    public function respondToRequest(int $userId, int $solicitudId, string $respuesta): void
    {
        if (!in_array($respuesta, ['Aprobada', 'Rechazada'])) {
            throw new Exception("Respuesta no válida.", 400);
        }
        $solicitud = $this->auditRepo->getRequestById($solicitudId);
        if (!$solicitud || $solicitud['UserID'] != $userId) {
            throw new Exception("Solicitud no encontrada o no pertenece a tu usuario.", 403);
        }
        $success = $this->auditRepo->updateRequestStatus($solicitudId, $userId, $respuesta);
        if (!$success) {
            throw new Exception("No se pudo actualizar el estado de la solicitud.", 400);
        }
        if ($respuesta === 'Aprobada') {
            $this->cuentasRepo->toggleAdminPermission($solicitud['CuentaID'], $userId, 1);
        }
    }

    public function executeApprovedModification(int $adminId, int $cuentaId, int $userId, array $nuevosDatos): void
    {
        $solicitud = $this->auditRepo->getApprovedRequest($cuentaId);
        if (!$solicitud || $solicitud['UserID'] != $userId) {
            throw new Exception("ALERTA DE SEGURIDAD: No posees autorización.", 403);
        }
        $estadoAnterior = $this->auditRepo->getBeneficiarySnapshot($cuentaId);
        $this->cuentasRepo->adminUpdateBeneficiary($cuentaId, $nuevosDatos);
        $estadoNuevo = $this->auditRepo->getBeneficiarySnapshot($cuentaId);
        $this->auditRepo->logAudit($cuentaId, $adminId, 'Modificacion', $estadoAnterior, $estadoNuevo, $solicitud['SolicitudID']);
        $this->auditRepo->updateRequestStatus($solicitud['SolicitudID'], $userId, 'Ejecutada');
        $this->cuentasRepo->toggleAdminPermission($cuentaId, $userId, 0);
    }

    public function getBeneficiaryHistory(int $cuentaId): array
    {
        return $this->auditRepo->getHistoryByAccount($cuentaId);
    }

    public function getPendingRequestsForUser(int $userId): array
    {
        return $this->auditRepo->getPendingRequestsForUser($userId);
    }


}