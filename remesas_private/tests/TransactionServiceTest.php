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
}
