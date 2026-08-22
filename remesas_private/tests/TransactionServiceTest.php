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
use App\Repositories\TasaEspecialRepository;
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
            'tasaEspecialRepo' => $this->createMock(TasaEspecialRepository::class),
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
            $deps['resellerAccountsRepo'],
            $deps['tasaEspecialRepo']
        );
    }

    private function beneficiarioValido(): array
    {
        return [
            'CuentaID' => 5,
            'PaisID' => 3,
            'TitularPrimerNombre' => 'Juan',
            'TitularSegundoNombre' => null,
            'TitularPrimerApellido' => 'Perez',
            'TitularSegundoApellido' => null,
            'NombreBanco' => 'Banco Test',
            'NumeroCuenta' => '123',
            'TitularNumeroDocumento' => '111',
            'NumeroTelefono' => '555',
            'CCI' => null,
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

    // --- getEstadoIdByName ---

    public function testGetEstadoIdByNameRetornaIdSiExiste()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(5);

        $service = $this->buildService(['estadoTxRepo' => $estadoTxRepo]);

        $this->assertEquals(5, $service->getEstadoIdByName('Cancelado'));
    }

    public function testGetEstadoIdByNameFallaSiNoExiste()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService(['estadoTxRepo' => $estadoTxRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no encontrado");

        $service->getEstadoIdByName('EstadoInventado');
    }

    public function testGetEstadoIdByNameFallbackParaPendienteAprobacion()
    {
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService(['estadoTxRepo' => $estadoTxRepo]);

        $this->assertEquals(7, $service->getEstadoIdByName('Pendiente de Aprobación'));
    }

    // --- adminUpdateCommission ---

    public function testAdminUpdateCommissionFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->adminUpdateCommission(1, 999, 100);
    }

    public function testAdminUpdateCommissionNoHaceNadaSiComisionEsIgual()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(['ComisionDestino' => 100.0]);
        $txRepo->expects($this->never())->method('updateCommission');

        $service = $this->buildService(['txRepo' => $txRepo]);

        $service->adminUpdateCommission(1, 5, 100.0);
        $this->assertTrue(true);
    }

    public function testAdminUpdateCommissionFallaSiNoActualiza()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(['ComisionDestino' => 100.0]);
        $txRepo->method('updateCommission')->willReturn(false);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al actualizar la comisión");

        $service->adminUpdateCommission(1, 5, 150.0);
    }

    public function testAdminUpdateCommissionExitosoCorrigeGastoEnContabilidad()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn([
            'ComisionDestino' => 100.0,
            'PaisDestinoID' => 3,
        ]);
        $txRepo->method('updateCommission')->willReturn(true);

        $contabService = $this->createMock(ContabilidadService::class);
        $contabService->expects($this->once())->method('corregirGastoComision')
            ->with(3, 100.0, 150.0, 1, 5);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService([
            'txRepo' => $txRepo,
            'contabService' => $contabService,
            'notifService' => $notifService,
        ]);

        $service->adminUpdateCommission(1, 5, 150.0);
        $this->assertTrue(true);
    }

    // --- adminResumeTransaction ---

    public function testAdminResumeTransactionExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('updateStatus')->willReturn(1);
        $txRepo->method('getFullTransactionDetails')->willReturn(['TransaccionID' => 5]);

        $pdfService = $this->createMock(PDFService::class);
        $pdfService->method('generateOrder')->willReturn('contenido-pdf');

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService([
            'txRepo' => $txRepo,
            'pdfService' => $pdfService,
            'notifService' => $notifService,
        ]);

        $this->assertTrue($service->adminResumeTransaction(5, 1, 'nota de prueba'));
    }

    public function testAdminResumeTransactionFallaSiNoActualiza()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('updateStatus')->willReturn(0);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->assertFalse($service->adminResumeTransaction(5, 1));
    }

    // --- getResellerStats ---

    public function testGetResellerStatsRetornaLoDelRepo()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getResellerStats')->willReturn(['total' => 5000]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->getResellerStats(10, '2026-01-01', '2026-01-31');

        $this->assertEquals(['total' => 5000], $result);
    }

    // --- forceUpdateState ---

    public function testForceUpdateStateFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->forceUpdateState(999, 3);
    }

    public function testForceUpdateStateExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['EstadoID' => 2]);
        $txRepo->method('updateStatus')->willReturn(1);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->assertTrue($service->forceUpdateState(5, 3));
    }

    public function testForceUpdateStateFallaSiNoActualiza()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getById')->willReturn(['EstadoID' => 2]);
        $txRepo->method('updateStatus')->willReturn(0);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->assertFalse($service->forceUpdateState(5, 3));
    }

    // --- getPreviousSendsToSameAccount ---

    public function testGetPreviousSendsFallaSiTransaccionNoExiste()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn(null);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Transacción no encontrada");

        $service->getPreviousSendsToSameAccount(999);
    }

    public function testGetPreviousSendsExitoso()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('getFullTransactionDetails')->willReturn([
            'UserID' => 10,
            'BeneficiarioNumeroCuenta' => '123456',
            'BeneficiarioTelefono' => null,
        ]);
        $txRepo->method('getPreviousSendsToSameAccount')->willReturn([['TransaccionID' => 1]]);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $result = $service->getPreviousSendsToSameAccount(5);

        $this->assertCount(1, $result);
    }

    // --- createTransaction: más validaciones y ramas de éxito ---

    private function mocksParaCreateExitoso(array $tasaOverrides = []): array
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000',
            'PaisID' => 1,
        ]);
        $userRepo->method('getReferidoPor')->willReturn(null);

        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $estadoTxRepo->method('findIdByName')->willReturn(1);

        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn($this->beneficiarioValido());

        $rateRepo = $this->createMock(RateRepository::class);
        $rateRepo->method('findCurrentRate')->willReturn(array_merge([
            'TasaID' => 1,
            'ValorTasa' => 3.8,
        ], $tasaOverrides));

        $formaPagoRepo = $this->createMock(FormaPagoRepository::class);
        $formaPagoRepo->method('findIdByName')->willReturn(1);

        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('findRecentActiveByUserAndBeneficiary')->willReturn(null);
        $txRepo->method('create')->willReturn(999);
        $txRepo->method('getFullTransactionDetails')->willReturn([
            'TransaccionID' => 999,
            'FormaPagoID' => 1,
            'PaisOrigenID' => 1,
        ]);

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->method('findActiveByFormaPagoAndPais')->willReturn(null);

        $pdfService = $this->createMock(PDFService::class);
        $pdfService->method('generateOrder')->willReturn('contenido-pdf');

        $fileHandler = $this->createMock(FileHandlerService::class);
        $fileHandler->method('savePdfTemporarily')->willReturn('http://x/orden.pdf');

        $notifService = $this->createMock(NotificationService::class);
        $notifService->method('sendOrderToClientWhatsApp')->willReturn(true);

        return [
            'userRepo' => $userRepo,
            'estadoTxRepo' => $estadoTxRepo,
            'cuentasRepo' => $cuentasRepo,
            'rateRepo' => $rateRepo,
            'formaPagoRepo' => $formaPagoRepo,
            'txRepo' => $txRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
            'pdfService' => $pdfService,
            'fileHandler' => $fileHandler,
            'notifService' => $notifService,
        ];
    }

    public function testCreateTransactionFallaSiFormaPagoInvalida()
    {
        $mocks = $this->mocksParaCreateExitoso();
        $formaPagoRepo = $this->createMock(FormaPagoRepository::class);
        $formaPagoRepo->method('findIdByName')->willReturn(null);
        $mocks['formaPagoRepo'] = $formaPagoRepo;

        $service = $this->buildService($mocks);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no válida");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testCreateTransactionFallaSiHayOrdenDuplicadaReciente()
    {
        $mocks = $this->mocksParaCreateExitoso();
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->method('findRecentActiveByUserAndBeneficiary')->willReturn(['TransaccionID' => 111]);
        $mocks['txRepo'] = $txRepo;

        $service = $this->buildService($mocks);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("orden activa reciente");

        $service->createTransaction($this->datosTransaccionBase());
    }

    public function testCreateTransactionExitosoRutaRiesgosaQuedaPendienteAprobacion()
    {
        $mocks = $this->mocksParaCreateExitoso(['EsRiesgoso' => 1]);
        // En ruta riesgosa no debe llegar a generar PDF ni enviar WhatsApp/Email.
        $pdfService = $this->createMock(PDFService::class);
        $pdfService->expects($this->never())->method('generateOrder');
        $mocks['pdfService'] = $pdfService;

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');
        $notifService->expects($this->never())->method('sendNewOrderEmail');
        $mocks['notifService'] = $notifService;

        $service = $this->buildService($mocks);

        $result = $service->createTransaction($this->datosTransaccionBase());

        $this->assertEquals('requires_approval', $result['status']);
        $this->assertEquals(999, $result['id']);
    }

    public function testCreateTransactionExitosoRutaNormalCalculaMontoPorMultiplicacion()
    {
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $capturedData = null;
        $txRepo = $mocks['txRepo'];
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['montoOrigen'] = 10000;
        $result = $service->createTransaction($datos);

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(38000.0, $capturedData['montoDestino']); // 10000 * 3.8, ruta normal (no inversa)
    }

    public function testCreateTransactionUsaTasaEspecialActivaEnVezDeLaPublica()
    {
        // ValorTasa pública = 3.8, pero el cliente tiene una tasa especial
        // activa de 4.5 para esta ruta exacta (origen=1, destino=3) — debe
        // usarse esa, no la pública.
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $tasaEspecialRepo = $this->createMock(TasaEspecialRepository::class);
        $tasaEspecialRepo->method('findActiveForUserAndRoute')
            ->with(1, 1, 3)
            ->willReturn(['TasaEspecialID' => 77, 'ValorTasa' => 4.5]);
        $tasaEspecialRepo->expects($this->once())->method('claim')->with(77)->willReturn(true);
        $tasaEspecialRepo->expects($this->once())->method('attachTransaccion')->with(77, 999);
        $mocks['tasaEspecialRepo'] = $tasaEspecialRepo;

        $capturedData = null;
        $txRepo = $mocks['txRepo'];
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['montoOrigen'] = 10000;
        $result = $service->createTransaction($datos);

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(45000.0, $capturedData['montoDestino']); // 10000 * 4.5 (tasa especial), no 3.8
        $this->assertEquals(4.5, $capturedData['tasaCapturada']);
    }

    public function testCreateTransactionSinTasaEspecialUsaTasaPublicaYNoLlamaMarkUsed()
    {
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $tasaEspecialRepo = $this->createMock(TasaEspecialRepository::class);
        $tasaEspecialRepo->method('findActiveForUserAndRoute')->willReturn(null);
        $tasaEspecialRepo->expects($this->never())->method('claim');
        $tasaEspecialRepo->expects($this->never())->method('attachTransaccion');
        $mocks['tasaEspecialRepo'] = $tasaEspecialRepo;

        $service = $this->buildService($mocks);

        $result = $service->createTransaction($this->datosTransaccionBase());

        $this->assertEquals('created', $result['status']);
    }

    public function testCreateTransactionTasaEspecialYaReclamadaPorOtraRequestUsaTasaPublica()
    {
        // Simula la carrera: findActiveForUserAndRoute encuentra la fila activa,
        // pero otra request concurrente ya la reclamó (claim() -> false). Debe
        // caer a la tasa pública, no la especial, y no llamar attachTransaccion.
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $tasaEspecialRepo = $this->createMock(TasaEspecialRepository::class);
        $tasaEspecialRepo->method('findActiveForUserAndRoute')
            ->willReturn(['TasaEspecialID' => 99, 'ValorTasa' => 5.0]);
        $tasaEspecialRepo->expects($this->once())->method('claim')->with(99)->willReturn(false);
        $tasaEspecialRepo->expects($this->never())->method('attachTransaccion');
        $mocks['tasaEspecialRepo'] = $tasaEspecialRepo;

        $capturedData = null;
        $txRepo = $mocks['txRepo'];
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['montoOrigen'] = 10000;
        $result = $service->createTransaction($datos);

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(38000.0, $capturedData['montoDestino']); // 10000 * 3.8 (pública, no 5.0)
    }

    public function testCreateTransactionExitosoRutaInversaCalculaMontoPorDivision()
    {
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $capturedData = null;
        $txRepo = $mocks['txRepo'];
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['paisOrigenID'] = 2; // ruta "2-3" (Col -> Ven) está en $inverseRoutes
        $datos['montoOrigen'] = 3800;
        $result = $service->createTransaction($datos);

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(1000.0, $capturedData['montoDestino']); // 3800 / 3.8, ruta inversa
    }

    public function testCreateTransactionUsaTasaEspecialEnRutaInversaRespetaDivision()
    {
        // La tasa especial no cambia el modo de cálculo (multiplicación/división),
        // solo el valor — en ruta inversa (Col -> Ven) sigue dividiendo, con el
        // valor especial (4.0) en vez del público (3.8).
        $mocks = $this->mocksParaCreateExitoso(['ValorTasa' => 3.8]);

        $tasaEspecialRepo = $this->createMock(TasaEspecialRepository::class);
        $tasaEspecialRepo->method('findActiveForUserAndRoute')
            ->with(1, 2, 3)
            ->willReturn(['TasaEspecialID' => 88, 'ValorTasa' => 4.0]);
        $tasaEspecialRepo->expects($this->once())->method('claim')->with(88)->willReturn(true);
        $tasaEspecialRepo->expects($this->once())->method('attachTransaccion')->with(88, 999);
        $mocks['tasaEspecialRepo'] = $tasaEspecialRepo;

        $capturedData = null;
        $txRepo = $mocks['txRepo'];
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['paisOrigenID'] = 2; // ruta "2-3" (Col -> Ven) está en $inverseRoutes
        $datos['montoOrigen'] = 4000;
        $result = $service->createTransaction($datos);

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(1000.0, $capturedData['montoDestino']); // 4000 / 4.0 (tasa especial), no 3.8
        $this->assertEquals(4.0, $capturedData['tasaCapturada']);
    }

    public function testCreateTransactionExitosoCalculaComisionRevendedor()
    {
        $mocks = $this->mocksParaCreateExitoso();
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 1,
            'VerificacionEstado' => 'Verificado',
            'Telefono' => '+5690000000',
            'PaisID' => 1,
            'Rol' => 'Revendedor',
            'RolID' => 4,
            'PorcentajeComision' => 2,
        ]);
        $userRepo->method('getReferidoPor')->willReturn(null);
        $mocks['userRepo'] = $userRepo;

        $txRepo = $mocks['txRepo'];
        $txRepo->method('getResellerCommissionRate')->willReturn(2.0);
        $capturedData = null;
        $txRepo->method('create')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return 999;
        });

        $service = $this->buildService($mocks);

        $datos = $this->datosTransaccionBase();
        $datos['montoOrigen'] = 10000;
        $service->createTransaction($datos);

        $this->assertEquals(200.0, $capturedData['comisionRevendedor']); // 10000 * 2%
    }

    // --- pause (passthrough simple) ---

    public function testPauseLlamaAlRepoConLosParametrosCorrectos()
    {
        $txRepo = $this->createMock(TransactionRepository::class);
        $txRepo->expects($this->once())
            ->method('pauseTransaction')
            ->with(5, 'motivo de prueba', 6)
            ->willReturn(true);

        $service = $this->buildService(['txRepo' => $txRepo]);

        $this->assertTrue($service->pause(5, 'motivo de prueba'));
    }
}
