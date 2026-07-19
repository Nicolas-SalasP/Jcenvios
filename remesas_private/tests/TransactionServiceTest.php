<?php

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
    public function testNoSePuedeCrearTransaccionSiUsuarioNoVerificado()
    {
        $userRepo = $this->createMock(UserRepository::class);
        
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 99,
            'VerificacionEstado' => 'Pendiente', 
            'Telefono' => '+56911111111'
        ]);

        $txRepo = $this->createMock(TransactionRepository::class);
        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $pdfService = $this->createMock(PDFService::class);
        $fileHandler = $this->createMock(FileHandlerService::class);
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $formaPagoRepo = $this->createMock(FormaPagoRepository::class);
        $contabService = $this->createMock(ContabilidadService::class);
        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $rateRepo = $this->createMock(RateRepository::class);
        $resellerAccountsRepo = $this->createMock(ResellerAccountsRepository::class);

        $service = new TransactionService(
            $txRepo, $userRepo, $notifService, $pdfService,
            $fileHandler, $estadoTxRepo, $formaPagoRepo,
            $contabService, $cuentasRepo, $cuentasAdminRepo, $rateRepo, $resellerAccountsRepo
        );

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

        $txRepo = $this->createMock(TransactionRepository::class);
        $cuentasRepo = $this->createMock(CuentasBeneficiariasRepository::class);
        $cuentasRepo->method('findByIdAndUserId')->willReturn([
            'CuentaID' => 5,
            'TitularPrimerNombre' => 'Juan',
            'TitularPrimerApellido' => 'Perez',
            'NombreBanco' => 'Banco Test',
            'NumeroCuenta' => '123',
            'TitularNumeroDocumento' => '111',
            'NumeroTelefono' => '555'
        ]);
        
        $formaPagoRepo = $this->createMock(FormaPagoRepository::class);
        $formaPagoRepo->method('findIdByName')->willReturn(1);
        $notifService = $this->createMock(NotificationService::class);
        $pdfService = $this->createMock(PDFService::class);
        $fileHandler = $this->createMock(FileHandlerService::class);
        $estadoTxRepo = $this->createMock(EstadoTransaccionRepository::class);
        $contabService = $this->createMock(ContabilidadService::class);
        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $rateRepo = $this->createMock(RateRepository::class);
        $resellerAccountsRepo = $this->createMock(ResellerAccountsRepository::class);

        $service = new TransactionService(
            $txRepo, $userRepo, $notifService, $pdfService,
            $fileHandler, $estadoTxRepo, $formaPagoRepo,
            $contabService, $cuentasRepo, $cuentasAdminRepo, $rateRepo, $resellerAccountsRepo
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("monto debe ser mayor a cero");

        $service->createTransaction([
            'userID' => 1,
            'cuentaID' => 5,
            'tasaID' => 1,
            'montoOrigen' => -100,
            'monedaOrigen' => 'CLP',
            'montoDestino' => 0,
            'monedaDestino' => 'VES',
            'formaDePago' => 'Transferencia'
        ]);
    }
}