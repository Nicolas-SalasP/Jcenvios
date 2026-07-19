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

class UserServiceTest extends TestCase
{
    public function testLoginFallaConContrasenaIncorrecta()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn([
            'UserID' => 1,
            'PasswordHash' => password_hash('123456', PASSWORD_DEFAULT),
            'LockoutUntil' => null
        ]);

        $notifService = $this->createMock(NotificationService::class);
        $fileHandler = $this->createMock(FileHandlerService::class);
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $rolRepo = $this->createMock(RolRepository::class);
        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $logService = $this->createMock(LogService::class);

        $service = new UserService($userRepo, $notifService, $fileHandler, $estadoRepo, $rolRepo, $tipoDocRepo, $logService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Credenciales incorrectas");

        $service->loginUser('usuario@test.com', 'contrasena_mala');
    }
}