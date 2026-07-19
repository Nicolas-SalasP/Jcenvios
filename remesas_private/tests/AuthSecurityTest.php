<?php

require_once __DIR__ . '/../config.php';

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Repositories\EstadoVerificacionRepository;
use App\Repositories\RolRepository;
use App\Repositories\TipoDocumentoRepository;
use App\Services\NotificationService;
use App\Services\FileHandlerService;
use App\Services\LogService;

class AuthSecurityTest extends TestCase
{
    public function testLoginFallaSiCuentaEstaBloqueada()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn([
            'UserID' => 1,
            'PasswordHash' => password_hash('password123', PASSWORD_DEFAULT),
            'LockoutUntil' => '2030-01-01 00:00:00'
        ]);

        $notifService = $this->createMock(NotificationService::class);
        $fileHandler = $this->createMock(FileHandlerService::class);
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $rolRepo = $this->createMock(RolRepository::class);
        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $logService = $this->createMock(LogService::class);

        $service = new UserService(
            $userRepo,
            $notifService,
            $fileHandler,
            $estadoRepo,
            $rolRepo,
            $tipoDocRepo,
            $logService
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("bloqueada temporalmente");

        $service->loginUser('bloqueado@test.com', 'password123');
    }
}