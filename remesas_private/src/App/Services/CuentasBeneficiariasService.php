<?php
namespace App\Services;

use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\TipoBeneficiarioRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Services\NotificationService;
use Exception;

class CuentasBeneficiariasService
{
    private CuentasBeneficiariasRepository $cuentasBeneficiariasRepository;
    private NotificationService $notificationService;
    private TipoBeneficiarioRepository $tipoBeneficiarioRepo;
    private TipoDocumentoRepository $tipoDocumentoRepo;

    public function __construct(
        CuentasBeneficiariasRepository $cuentasBeneficiariasRepository,
        NotificationService $notificationService,
        TipoBeneficiarioRepository $tipoBeneficiarioRepo,
        TipoDocumentoRepository $tipoDocumentoRepo
    ) {
        $this->cuentasBeneficiariasRepository = $cuentasBeneficiariasRepository;
        $this->notificationService = $notificationService;
        $this->tipoBeneficiarioRepo = $tipoBeneficiarioRepo;
        $this->tipoDocumentoRepo = $tipoDocumentoRepo;
    }

    public function getAccountsByUser(int $userId, ?int $paisId = null): array
    {
        $cuentas = $this->cuentasBeneficiariasRepository->findByUserId($userId);

        if ($paisId !== null) {
            $cuentas = array_filter($cuentas, fn($cuenta) => isset($cuenta['PaisID']) && $cuenta['PaisID'] == $paisId);
            $cuentas = array_values($cuentas);
        }

        return $cuentas;
    }

    public function getAccountDetails(int $userId, int $cuentaId): ?array
    {
        $cuenta = $this->cuentasBeneficiariasRepository->findByIdAndUserId($cuentaId, $userId);
        if (!$cuenta) {
            throw new Exception("Cuenta no encontrada o no te pertenece.", 404);
        }
        return $cuenta;
    }

    private function validateAndPrepareBeneficiaryData(array $data): array
    {
        $requiredFields = [
            'alias',
            'tipoBeneficiario',
            'primerNombre',
            'primerApellido',
            'tipoDocumento',
            'numeroDocumento',
            'nombreBanco'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                throw new Exception("El campo '$field' es obligatorio.", 400);
            }
        }
        if ($data['numeroCuenta'] === 'PAGO MOVIL') {
            if (empty($data['numeroTelefono'])) {
                throw new Exception("El teléfono es obligatorio para Pago Móvil.", 400);
            }
        } else {
            if (empty($data['numeroCuenta'])) {
                throw new Exception("El número de cuenta es obligatorio.", 400);
            }
            $cuentaLimpia = preg_replace('/[^0-9]/', '', $data['numeroCuenta']);
            if (strlen($cuentaLimpia) > 20) {
                throw new Exception("El número de cuenta no puede exceder los 20 dígitos.", 400);
            }
        }

        $doc = trim($data['numeroDocumento']);
        $tipoDoc = $data['tipoDocumento'];

        $docNumbers = preg_replace('/[^0-9]/', '', $doc);

        if (stripos($tipoDoc, 'Cédula') !== false || stripos($tipoDoc, 'RIF') !== false) {
            if (strlen($docNumbers) > 9) {
                throw new Exception("El número de documento (Cédula/RIF) no puede tener más de 9 dígitos.", 400);
            }
        }

        $tipoBeneficiarioID = $this->tipoBeneficiarioRepo->findIdByName($data['tipoBeneficiario']);
        if (!$tipoBeneficiarioID) {
            if (is_numeric($data['tipoBeneficiario']))
                $tipoBeneficiarioID = (int) $data['tipoBeneficiario'];
            else
                throw new Exception("Tipo de beneficiario no válido.", 400);
        }
        $data['tipoBeneficiarioID'] = $tipoBeneficiarioID;

        $tipoDocumentoID = $this->tipoDocumentoRepo->findIdByName($data['tipoDocumento']);
        if (!$tipoDocumentoID) {
            if (is_numeric($data['tipoDocumento']))
                $tipoDocumentoID = (int) $data['tipoDocumento'];
            else
                throw new Exception("Tipo de documento no válido.", 400);
        }
        $data['titularTipoDocumentoID'] = $tipoDocumentoID;

        $data['segundoNombre'] = $data['segundoNombre'] ?? null;
        $data['segundoApellido'] = $data['segundoApellido'] ?? null;
        if (empty($data['numeroTelefono']))
            $data['numeroTelefono'] = null;

        return $data;
    }

    public function addAccount(int $userId, array $data): int
    {
        $data = $this->validateAndPrepareBeneficiaryData($data);
        $data['UserID'] = $userId;

        if (!isset($data['paisID']) || empty($data['paisID'])) {
            throw new Exception("El campo 'paisID' es obligatorio.", 400);
        }

        try {
            $newId = $this->cuentasBeneficiariasRepository->create($data);
            $this->notificationService->logAdminAction($userId, 'Usuario añadió cuenta beneficiaria', "Alias: {$data['alias']} - ID: {$newId}");
            return $newId;
        } catch (Exception $e) {
            error_log("Error al crear cuenta: " . $e->getMessage());
            throw new Exception("Error al guardar el beneficiario.", 500);
        }
    }

    public function updateAccount(int $userId, int $cuentaId, array $data): bool
    {
        $data = $this->validateAndPrepareBeneficiaryData($data);

        try {
            $success = $this->cuentasBeneficiariasRepository->update($cuentaId, $userId, $data);
            $this->notificationService->logAdminAction($userId, 'Usuario actualizó beneficiario', "Alias: {$data['alias']} - ID: {$cuentaId}");
            return $success;
        } catch (Exception $e) {
            error_log("Error al actualizar cuenta: " . $e->getMessage());
            throw new Exception("Error al actualizar el beneficiario.", 500);
        }
    }

    public function deleteAccount(int $userId, int $cuentaId): bool
    {
        try {
            $success = $this->cuentasBeneficiariasRepository->delete($cuentaId, $userId);
            if ($success) {
                $this->notificationService->logAdminAction($userId, 'Usuario eliminó beneficiario', "ID: {$cuentaId}");
            }
            return $success;

        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                throw new Exception("No se puede eliminar este beneficiario porque está asociado a transacciones pasadas.", 409);
            }
            error_log("Error SQL eliminar cuenta: " . $e->getMessage());
            throw new Exception("Error de base de datos al eliminar.", 500);
        } catch (Exception $e) {
            if ($e->getCode() == 409)
                throw $e;
            throw new Exception("Error al eliminar el beneficiario.", 500);
        }
    }
}