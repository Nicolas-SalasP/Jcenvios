<?php
require_once __DIR__ . '/../config.php';

use PHPUnit\Framework\TestCase;
use App\Services\TransactionService;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\FormaPagoRepository;
use App\Repositories\EstadoTransaccionRepository;
use App\Repositories\CuentasAdminRepository;
use App\Repositories\RateRepository;
use App\Repositories\ResellerAccountsRepository;
use App\Services\NotificationService;
use App\Services\PDFService;
use App\Services\FileHandlerService;
use App\Services\ContabilidadService;

class TransactionServiceTest extends TestCase
{
    /**
     * Arma un TransactionService con todas las dependencias mockeadas.
     * $overrides permite reemplazar mocks puntuales (ej. 'userRepo' => $miMock)
     * para no repetir el boilerplate en cada test.
     */
    private function buildService(array $overrides = []): TransactionService
    {
        $defaults = [
            'txRepo' => $this->createMock(TransactionRepository::class),
            'userRepo' => $this->createMock(UserRepository::class),
            'notifService' => $this->createMock(NotificationService::class),
            'pdfService' => $this->createMock(PDFService::class),
            'fileHandler' => $this->createMock(FileHandlerService::class),
            'estadoTxRepo' => $this->createMock(EstadoTransaccionRepository::class),
            'formaPagoRepo' => $this->createMock(FormaPagoRepository::class),
            'contabService' => $this->createMock(ContabilidadService::class),
            'cuentasRepo' => $this->createMock(CuentasBeneficiariasRepository::class),
            'cuentasAdminRepo' => $this->createMock(CuentasAdminRepository::class),
            'rateRepo' => $this->createMock(RateRepository::class),
            'resellerAccountsRepo' => $this->createMock(ResellerAccountsRepository::class),
        ];
        $deps = array_merge($defaults, $overrides);

        return new TransactionService(
            $deps['txRepo'],
            $deps['userRepo'],
            $deps['notifService'],
            $deps['pdfService'],
            $deps['fileHandler'],
            $deps['estadoTxRepo'],
            $deps['formaPagoRepo'],
            $deps['contabService'],
            $deps['cuentasRepo'],
            $deps['cuentasAdminRepo'],
            $deps['rateRepo'],
            $deps['resellerAccountsRepo']
        );
    }

    private function beneficiarioValido(): array
    {
        return [
            'CuentaID' => 5,
            'PaisID' => 3,
            'TitularPrimerNombre' => 'Juan',
            'TitularPrimerApellido' => 'Perez',
            'NombreBanco' => 'Banco Test',
            'NumeroCuenta' => '123',
            'TitularNumeroDocumento' => '111',
            'NumeroTelefono' => '555'
        ];
    }

    private function datosTransaccionBase(): array
    {
        return [
            'userID' => 1,
            'cuentaID' => 5,
            'tasaID' => 1,
            'montoOrigen' => 50000,
            'monedaOrigen' => 'CLP',
            'montoDestino' => 0,
            'monedaDestino' => 'VES',
            'formaDePago' => 'Transferencia'
        ];
    }

    public function testNoSePuedeCrearTransaccionSiUsuarioNoVerificado()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 99,
            'VerificacionEstado' => 'Pendiente',
            'Telefono' => '+56911111111'
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Tu cuenta debe estar verificada");

        $service->createTransaction([
            'userID' => 99,
            'cuentaID' => 1,
            'montoOrigen' => 50000
        ]);
    }

    public function testNoSePuedeCrearTransaccionConMontoNegativo()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn($this->beneficiarioValido());

        $service = $this->buildService(['userRepo' => $userRepo, 'cuentasRepo' => $cuentasRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("monto debe ser mayor a cero");

        $service->createTransaction(array_merge($this->datosTransaccionBase(), ['montoOrigen' => -100]));
    }

    public function testNoSePuedeCrearTransaccionSiUsuarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Usuario no encontrado");

        $service->createTransaction(['userID' => 999, 'cuentaID' => 1, 'montoOrigen' => 1000]);
    }

    public function testNoSePuedeCrearTransaccionSiFaltaTelefono()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => ''
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("teléfono");

        $service->createTransaction(['userID' => 1, 'cuentaID' => 1, 'montoOrigen' => 1000]);
    }

    public function testNoSePuedeCrearTransaccionSiFaltaCampoObligatorio()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $datos = $this->datosTransaccionBase();
        unset($datos['cuentaID']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Faltan datos");

        $service->createTransaction($datos);
    }

    public function testNoSePuedeCrearTransaccionSiBeneficiarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn(null);

        $service = $this->buildService([
            'userRepo' => $userRepo,
            'estadoTxRepo' => $estadoTxRepo,
            'cuentasRepo' => $cuentasRepo,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Beneficiario no encontrado");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testNoSePuedeCrearTransaccionSiBeneficiarioSinCuentaNiTelefono()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $beneficiario = $this->beneficiarioValido();
        $beneficiario['NumeroCuenta'] = '';
        $beneficiario['NumeroTelefono'] = '';

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn($beneficiario);

        $service = $this->buildService([
            'userRepo' => $userRepo,
            'estadoTxRepo' => $estadoTxRepo,
            'cuentasRepo' => $cuentasRepo,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no posee un número de cuenta");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testNoSePuedeCrearTransaccionSiTasaNoDisponible()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn($this->beneficiarioValido());

        $rateRepo = $this->createMock(RateRepository::class);
        $rateRepo->method('findCurrentRate')->willReturn(null);

        $service = $this->buildService([
            'userRepo' => $userRepo,
            'estadoTxRepo' => $estadoTxRepo,
            'cuentasRepo' => $cuentasRepo,
            'rateRepo' => $rateRepo,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("tasa ha cambiado");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testNoSePuedeCrearTransaccionSiRutaDesactivada()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000'
        ]);

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn($this->beneficiarioValido());

        $rateRepo = $this->createMock(RateRepository::class);
        $rateRepo->method('findCurrentRate')->willReturn([
            'TasaID' => 1,
            'ValorTasa' => 3.8,
            'RutaActiva' => 0,
        ]);

        $service = $this->buildService([
            'userRepo' => $userRepo,
            'estadoTxRepo' => $estadoTxRepo,
            'cuentasRepo' => $cuentasRepo,
            'rateRepo' => $rateRepo,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("ruta está temporalmente desactivada");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testCancelarTransaccionFallaSiEstadoNoLoPermite()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('cancel')->willReturn(0); // 0 filas afectadas = estado no permite cancelar

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $service = $this->buildService(['txRepo' => $txRepo, 'estadoTxRepo' => $estadoTxRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se puede cancelar");

        $service->cancelTransaction(123, 1);
    }

    public function testAdminRejectPaymentFallaSiEstadoNoLoPermite()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(['TransaccionID' => 1, 'Email' => 'a@a.com', 'PrimerNombre' => 'Juan']);
        $txRepo->method('updateStatus')->willReturn(0); // 0 filas afectadas = no se pudo rechazar

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se pudo rechazar");

        $service->adminRejectPayment(1, 123, 'motivo', false);
    }

    public function testAdminRejectPaymentFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->adminRejectPayment(1, 999, 'motivo', false);
    }

    // --- requestResume ---

    public function testRequestResumeFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no tienes permiso");

        $service->requestResume(1, 10, 'mensaje', 1);
    }

    public function testRequestResumeFallaSiNoPerteneceAlUsuario()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['TransaccionID' => 1, 'UserID' => 999]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no tienes permiso");

        $service->requestResume(1, 10, 'mensaje', 1);
    }

    public function testRequestResumeFallaSiNombreBeneficiarioVacio()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['TransaccionID' => 1, 'UserID' => 10]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("nombre del beneficiario es obligatorio");

        $service->requestResume(1, 10, 'mensaje', 1, ['nombre' => '  ']);
    }

    public function testRequestResumeExitosoSinCorreccionBeneficiario()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['TransaccionID' => 1, 'UserID' => 10]);
        $txRepo->expects($this->never())->method('updateBeneficiarySnapshot');
        $txRepo->method('requestResume')->willReturn(true);

        $fileHandler = $this->createMock(FileHandlerService::class);
        $fileHandler->expects($this->once())->method('deleteOrderPdf');

        $service = $this->buildService(['txRepo' => $txRepo, 'fileHandler' => $fileHandler]);

        $result = $service->requestResume(1, 10, 'mensaje', 1);

        $this->assertTrue($result);
    }

    public function testRequestResumeExitosoConCorreccionBeneficiario()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['TransaccionID' => 1, 'UserID' => 10]);
        $txRepo->expects($this->once())->method('updateBeneficiarySnapshot');
        $txRepo->method('requestResume')->willReturn(true);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->requestResume(1, 10, 'mensaje', 1, ['nombre' => 'Juan Perez']);

        $this->assertTrue($result);
    }

    // --- authorizeRiskyTransaction ---

    public function testAuthorizeRiskyTransactionRetornaFalseSiNoActualiza()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('updateStatus')->willReturn(0);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->authorizeRiskyTransaction(1, 1);

        $this->assertFalse($result);
    }

    public function testAuthorizeRiskyTransactionExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('updateStatus')->willReturn(1);
        $txRepo->method('getFullTransactionDetails')->willReturn([
            'UserID' => 10,
            'FormaPagoID' => 1,
            'PaisOrigenID' => 1,
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(['Telefono' => '+56900000000']);

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->method('findActiveByFormaPagoAndPais')->willReturn(null);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService([
            'txRepo' => $txRepo,
            'userRepo' => $userRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
            'notifService' => $notifService,
        ]);

        $result = $service->authorizeRiskyTransaction(1, 1);

        $this->assertTrue($result);
    }

    // --- toggleMontoEditPermission ---

    public function testToggleMontoEditPermissionFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->toggleMontoEditPermission(1, 1, 1);
    }

    // --- updatePausedTransactionAmount ---

    public function testUpdatePausedAmountFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no pertenece a tu usuario");

        $service->updatePausedTransactionAmount(1, 10, 20000);
    }

    public function testUpdatePausedAmountFallaSiNoTienePermiso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 0,
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Bloqueo de Seguridad");

        $service->updatePausedTransactionAmount(1, 10, 20000);
    }

    public function testUpdatePausedAmountFallaSiMontoEsCero()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 1,
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("mayor a 0");

        $service->updatePausedTransactionAmount(1, 10, 0);
    }

    public function testUpdatePausedAmountFallaSiSuperaElMaximo()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 1,
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("máximo permitido");

        $service->updatePausedTransactionAmount(1, 10, 60000);
    }

    public function testUpdatePausedAmountFallaSiMontoOriginalEsCero()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 1,
            'MontoOrigen' => 0,
            'MontoDestino' => 0,
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("monto original de la transacción es 0");

        $service->updatePausedTransactionAmount(1, 10, 20000);
    }

    public function testUpdatePausedAmountFallaSiNoActualizaEnBd()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 1,
            'MontoOrigen' => 10000,
            'MontoDestino' => 38000,
            'ComisionDestino' => 100,
            'ComisionRevendedor' => 50,
        ]);
        $txRepo->method('updateTransactionAmounts')->willReturn(false);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al actualizar la base de datos");

        $service->updatePausedTransactionAmount(1, 10, 20000);
    }

    public function testUpdatePausedAmountExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn([
            'UserID' => 10,
            'PermitirEdicionMonto' => 1,
            'MontoOrigen' => 10000,
            'MontoDestino' => 38000,
            'ComisionDestino' => 100,
            'ComisionRevendedor' => 50,
        ]);
        $txRepo->method('updateTransactionAmounts')->willReturn(true);
        $txRepo->expects($this->once())->method('logMontoAudit');

        $service = $this->buildService(['txRepo' => $txRepo]);

        $service->updatePausedTransactionAmount(1, 10, 20000);
        $this->assertTrue(true);
    }

    // --- confirmReceipt ---

    public function testConfirmReceiptFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->confirmReceipt(1, 10, true);
    }

    public function testConfirmReceiptFallaSiNoPerteneceAlUsuario()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn(['UserID' => 999]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No tienes permisos");

        $service->confirmReceipt(1, 10, true);
    }

    public function testConfirmReceiptFallaSiNoEstaExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn([
            'UserID' => 10,
            'EstadoNombre' => 'En Proceso',
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("ya pagadas");

        $service->confirmReceipt(1, 10, true);
    }

    public function testConfirmReceiptFallaSiYaFueConfirmadoRecibido()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn([
            'UserID' => 10,
            'EstadoNombre' => 'Exitoso',
            'ConfirmacionRecepcion' => 'recibido',
        ]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se puede deshacer");

        $service->confirmReceipt(1, 10, true);
    }

    public function testConfirmReceiptFallaSiNoActualizaEnBd()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn([
            'UserID' => 10,
            'EstadoNombre' => 'Exitoso',
            'ConfirmacionRecepcion' => 'pendiente',
        ]);
        $txRepo->method('updateConfirmacionRecepcion')->willReturn(false);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se pudo actualizar el estado");

        $service->confirmReceipt(1, 10, true);
    }

    public function testConfirmReceiptExitosoRecibido()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn([
            'UserID' => 10,
            'EstadoNombre' => 'Exitoso',
            'ConfirmacionRecepcion' => 'pendiente',
        ]);
        $txRepo->method('updateConfirmacionRecepcion')->willReturn(true);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->confirmReceipt(1, 10, true);

        $this->assertEquals('recibido', $result['status']);
        $this->assertTrue($result['lockedFromChange']);
    }

    public function testConfirmReceiptExitosoNoRecibido()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getConfirmacionRecepcion')->willReturn([
            'UserID' => 10,
            'EstadoNombre' => 'Exitoso',
            'ConfirmacionRecepcion' => 'pendiente',
        ]);
        $txRepo->method('updateConfirmacionRecepcion')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['txRepo' => $txRepo, 'notifService' => $notifService]);

        $result = $service->confirmReceipt(1, 10, false);

        $this->assertEquals('no_recibido', $result['status']);
        $this->assertFalse($result['lockedFromChange']);
    }

    // --- autoCancelExpired ---

    public function testAutoCancelExpiredRetornaCantidadCancelada()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('autoCancelExpired')->willReturn(7);

        $service = $this->buildService(['txRepo' => $txRepo, 'estadoTxRepo' => $estadoTxRepo]);

        $result = $service->autoCancelExpired(4);

        $this->assertEquals(7, $result);
    }

    // --- extendPaymentDeadline ---

    public function testExtendPaymentDeadlineFallaSiIdInvalido()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("inválido");

        $service->extendPaymentDeadline(0, 10);
    }

    public function testExtendPaymentDeadlineFallaSiLimiteAlcanzado()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('extendPaymentDeadline')->willReturn(['success' => false, 'reason' => 'limit_reached']);

        $service = $this->buildService(['txRepo' => $txRepo, 'estadoTxRepo' => $estadoTxRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("máximo de extensiones");

        $service->extendPaymentDeadline(1, 10);
    }

    public function testExtendPaymentDeadlineFallaSiNoElegible()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('extendPaymentDeadline')->willReturn(['success' => false, 'reason' => 'not_eligible']);

        $service = $this->buildService(['txRepo' => $txRepo, 'estadoTxRepo' => $estadoTxRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no está Pendiente de Pago");

        $service->extendPaymentDeadline(1, 10);
    }

    public function testExtendPaymentDeadlineExitoso()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('extendPaymentDeadline')->willReturn(['success' => true, 'extensionesUsadas' => 1]);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['txRepo' => $txRepo, 'estadoTxRepo' => $estadoTxRepo, 'notifService' => $notifService]);

        $result = $service->extendPaymentDeadline(1, 10);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['extensionesUsadas']);
    }

    // --- getTransactionsByUser / getAdminAlerts (passthrough simples) ---

    public function testGetTransactionsByUserRetornaLoDelRepo()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getAllByUser')->willReturn([['TransaccionID' => 1], ['TransaccionID' => 2]]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->getTransactionsByUser(10);

        $this->assertCount(2, $result);
    }

    public function testGetAdminAlertsRetornaLoDelRepo()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getAdminAlertsData')->willReturn(['alertas' => 3]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->getAdminAlerts();

        $this->assertEquals(['alertas' => 3], $result);
    }
}
