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
    private function buildService(array $overrides = []): UserService
    {
        $defaults = [
            'userRepo' => $this->createMock(UserRepository::class),
            'notifService' => $this->createMock(NotificationService::class),
            'fileHandler' => $this->createMock(FileHandlerService::class),
            'estadoRepo' => $this->createMock(EstadoVerificacionRepository::class),
            'rolRepo' => $this->createMock(RolRepository::class),
            'tipoDocRepo' => $this->createMock(TipoDocumentoRepository::class),
            'logService' => $this->createMock(LogService::class),
        ];
        $deps = array_merge($defaults, $overrides);

        return new UserService(
            $deps['userRepo'],
            $deps['notifService'],
            $deps['fileHandler'],
            $deps['estadoRepo'],
            $deps['rolRepo'],
            $deps['tipoDocRepo'],
            $deps['logService']
        );
    }

    private function datosRegistroValidos(): array
    {
        return [
            'primerNombre' => 'Juan',
            'primerApellido' => 'Perez',
            'email' => 'juan@test.com',
            'password' => 'password123',
            'tipoDocumento' => 'Cédula',
            'numeroDocumento' => '12345678',
            'phoneNumber' => '912345678',
            'phoneCode' => '+56',
            'tipoPersona' => 'Persona Natural',
        ];
    }

    // --- loginUser ---

    public function testLoginFallaConContrasenaIncorrecta()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn([
            'UserID' => 1,
            'PasswordHash' => password_hash('123456', PASSWORD_DEFAULT),
            'LockoutUntil' => null
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Credenciales incorrectas");

        $service->loginUser('usuario@test.com', 'contrasena_mala');
    }

    public function testLoginFallaSiUsuarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Credenciales incorrectas");

        $service->loginUser('noexiste@test.com', 'cualquiera');
    }

    // --- registerUser ---

    public function testRegisterUserFallaSiFaltaCampoObligatorio()
    {
        $service = $this->buildService();

        $datos = $this->datosRegistroValidos();
        unset($datos['email']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("obligatorio");

        $service->registerUser($datos);
    }

    public function testRegisterUserFallaSiTipoPersonaInvalido()
    {
        $service = $this->buildService();

        $datos = $this->datosRegistroValidos();
        $datos['tipoPersona'] = 'Robot';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no es válido");

        $service->registerUser($datos);
    }

    public function testRegisterUserFallaSiPasswordCorta()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);

        $service = $this->buildService(['rolRepo' => $rolRepo]);

        $datos = $this->datosRegistroValidos();
        $datos['password'] = '123';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("al menos 8 caracteres");

        $service->registerUser($datos);
    }

    public function testRegisterUserFallaSiEmailInvalido()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);

        $service = $this->buildService(['rolRepo' => $rolRepo]);

        $datos = $this->datosRegistroValidos();
        $datos['email'] = 'no-es-un-email';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("correo electrónico no es válido");

        $service->registerUser($datos);
    }

    public function testRegisterUserFallaSiRolNoEncontrado()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService(['rolRepo' => $rolRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Rol no encontrado");

        $service->registerUser($this->datosRegistroValidos());
    }

    public function testRegisterUserFallaSiTipoDocumentoInvalido()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);

        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService(['rolRepo' => $rolRepo, 'tipoDocRepo' => $tipoDocRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no válido");

        $service->registerUser($this->datosRegistroValidos());
    }

    public function testRegisterUserFallaSiEstadoNoVerificadoNoExiste()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);

        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(1);

        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService([
            'rolRepo' => $rolRepo,
            'tipoDocRepo' => $tipoDocRepo,
            'estadoRepo' => $estadoRepo,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No Verificado' no encontrado");

        $service->registerUser($this->datosRegistroValidos());
    }

    public function testRegisterUserExitoso()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);

        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(1);

        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(1);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('create')->willReturn(42);
        $userRepo->method('findByEmail')->willReturn(['UserID' => 42, 'Email' => 'juan@test.com']);

        $service = $this->buildService([
            'rolRepo' => $rolRepo,
            'tipoDocRepo' => $tipoDocRepo,
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
        ]);

        $result = $service->registerUser($this->datosRegistroValidos());

        $this->assertEquals(42, $result['UserID']);
    }

    // --- toggleUserBlock ---

    public function testToggleUserBlockFallaSiSeBloqueaASiMismo()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No puedes bloquearte a ti mismo");

        $service->toggleUserBlock(5, 5, 'blocked');
    }

    public function testToggleUserBlockFallaSiEsAdminPrincipal()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("administrador principal");

        $service->toggleUserBlock(5, 1, 'blocked');
    }

    public function testToggleUserBlockFallaSiEstadoInvalido()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Acción de bloqueo no válida");

        $service->toggleUserBlock(5, 10, 'estado-raro');
    }

    public function testToggleUserBlockFallaSiRepoNoActualiza()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('updateLoginAttempts')->willReturn(false);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se pudo actualizar el estado de bloqueo");

        $service->toggleUserBlock(5, 10, 'blocked');
    }

    public function testToggleUserBlockExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('updateLoginAttempts')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->toggleUserBlock(5, 10, 'blocked');
        $this->assertTrue(true);
    }

    public function testToggleUserBlockDesbloqueoExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('updateLoginAttempts')->with(10, 0, null)->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->toggleUserBlock(5, 10, 'active');
        $this->assertTrue(true);
    }

    // --- adminUpdateUserRole ---

    public function testAdminUpdateUserRoleFallaSiCambiaPropioRol()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No puedes cambiar tu propio rol");

        $service->adminUpdateUserRole(5, 5, 2);
    }

    public function testAdminUpdateUserRoleFallaSiEsAdminPrincipal()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("administrador principal");

        $service->adminUpdateUserRole(5, 1, 2);
    }

    public function testAdminUpdateUserRoleFallaSiLimiteDeAdminsAlcanzado()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countAdmins')->willReturn(3);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Máximo 3 Admins permitidos");

        $service->adminUpdateUserRole(5, 10, 1);
    }

    public function testAdminUpdateUserRoleFallaSiLimiteDeOperadoresAlcanzado()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countByRole')->willReturn(2);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Máximo 2 Operadores permitidos");

        $service->adminUpdateUserRole(5, 10, 5);
    }

    public function testAdminUpdateUserRoleExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('updateRole')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->adminUpdateUserRole(5, 10, 2);
        $this->assertTrue(true);
    }

    // --- adminDeleteUser ---

    public function testAdminDeleteUserFallaSiSeEliminaASiMismo()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No puedes eliminarte a ti mismo");

        $service->adminDeleteUser(5, 5);
    }

    public function testAdminDeleteUserFallaSiEsSuperAdmin()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Super Administrador");

        $service->adminDeleteUser(5, 1);
    }

    public function testAdminDeleteUserExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('delete')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->adminDeleteUser(5, 10);
        $this->assertTrue(true);
    }

    // --- updateVerificationStatus ---

    public function testUpdateVerificationStatusFallaSiEstadoInvalido()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no válido para esta acción");

        $service->updateVerificationStatus(1, 10, 'EstadoInventado');
    }

    public function testUpdateVerificationStatusFallaSiUsuarioNoExiste()
    {
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(3);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(null);

        $service = $this->buildService(['estadoRepo' => $estadoRepo, 'userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Usuario no encontrado");

        $service->updateVerificationStatus(1, 999, 'Verificado');
    }

    public function testUpdateVerificationStatusExitoso()
    {
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(3);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(['UserID' => 10]);
        $userRepo->method('updateVerificationStatus')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService([
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
            'notifService' => $notifService,
        ]);

        $service->updateVerificationStatus(1, 10, 'Verificado');
        $this->assertTrue(true);
    }

    // --- getOrCreateReferralCode ---

    public function testGetOrCreateReferralCodeRetornaExistente()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getReferralCode')->willReturn('ABC12345');
        $userRepo->expects($this->never())->method('setReferralCode');

        $service = $this->buildService(['userRepo' => $userRepo]);

        $code = $service->getOrCreateReferralCode(10);

        $this->assertEquals('ABC12345', $code);
    }

    public function testGetOrCreateReferralCodeGeneraNuevoSiNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getReferralCode')->willReturn(null);
        $userRepo->method('codeExists')->willReturn(false);
        $userRepo->method('setReferralCode')->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $code = $service->getOrCreateReferralCode(10);

        $this->assertEquals(8, strlen($code));
    }

    // --- verifyUserPassword ---

    public function testVerifyUserPasswordCorrecta()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getEmailById')->willReturn('juan@test.com');
        $userRepo->method('findByEmail')->willReturn([
            'PasswordHash' => password_hash('miPassword', PASSWORD_DEFAULT),
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertTrue($service->verifyUserPassword(10, 'miPassword'));
    }

    public function testVerifyUserPasswordIncorrecta()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getEmailById')->willReturn('juan@test.com');
        $userRepo->method('findByEmail')->willReturn([
            'PasswordHash' => password_hash('miPassword', PASSWORD_DEFAULT),
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->verifyUserPassword(10, 'passwordMala'));
    }

    // --- disable2FA ---

    public function testDisable2FAExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('disable2FA')->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertTrue($service->disable2FA(10));
    }

    public function testDisable2FAFalla()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('disable2FA')->willReturn(false);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->disable2FA(10));
    }

    // --- verifyBackupCode ---

    public function testVerifyBackupCodeInvalidoSiNoHayCodigos()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getBackupCodes')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->verifyBackupCode(10, 'CODIGO123'));
    }

    // --- getApprovedUsers ---

    public function testGetApprovedUsersSinEstadoVerificadoEncontrado()
    {
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(null);

        $service = $this->buildService(['estadoRepo' => $estadoRepo]);

        $result = $service->getApprovedUsers(10, 0);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals([], $result['usuarios']);
    }

    public function testGetApprovedUsersConDatos()
    {
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(3);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countByVerificationStatus')->willReturn(2);
        $userRepo->method('findByVerificationStatus')->willReturn([['UserID' => 1], ['UserID' => 2]]);

        $service = $this->buildService(['estadoRepo' => $estadoRepo, 'userRepo' => $userRepo]);

        $result = $service->getApprovedUsers(10, 0);

        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['usuarios']);
    }

    // --- performPasswordReset ---

    public function testPerformPasswordResetFallaSiPasswordCorta()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("al menos 8 caracteres");

        $service->performPasswordReset('token123', '123');
    }

    public function testPerformPasswordResetFallaSiTokenInvalido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findValidResetToken')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token no válido o expirado");

        $service->performPasswordReset('token-invalido', 'nuevaPassword123');
    }

    public function testPerformPasswordResetExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findValidResetToken')->willReturn(['UserID' => 10, 'ResetID' => 1]);
        $userRepo->method('updatePassword')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->performPasswordReset('token-valido', 'nuevaPassword123');
        $this->assertTrue(true);
    }

    // --- getUserProfile ---

    public function testGetUserProfileFallaSiNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no encontrado");

        $service->getUserProfile(999);
    }

    public function testGetUserProfileNoIncluyePasswordHash()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 10,
            'Email' => 'juan@test.com',
            'PasswordHash' => 'hash-secreto',
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $profile = $service->getUserProfile(10);

        $this->assertArrayNotHasKey('PasswordHash', $profile);
    }

    // --- adminUpdateUserData ---

    public function testAdminUpdateUserDataFallaSiIdInvalido()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("ID de usuario inválido");

        $service->adminUpdateUserData(1, ['userId' => 0]);
    }

    public function testAdminUpdateUserDataFallaSiFaltanCampos()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("obligatorios");

        $service->adminUpdateUserData(1, ['userId' => 10, 'primerNombre' => 'Juan']);
    }

    // --- updateUserProfile ---

    public function testUpdateUserProfileSinFotoNuevaMantieneLaActual()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 10,
            'Telefono' => '+56911111111',
            'FotoPerfilURL' => 'profile_pics/foto_vieja.jpg',
        ]);
        $userRepo->expects($this->never())->method('updateProfileInfo');

        $service = $this->buildService(['userRepo' => $userRepo]);

        $result = $service->updateUserProfile(10, [], null);

        $this->assertEquals('profile_pics/foto_vieja.jpg', $result['fotoPerfilUrl']);
    }

    public function testUpdateUserProfileConFotoNuevaActualiza()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 10,
            'Telefono' => '+56911111111',
            'FotoPerfilURL' => 'profile_pics/foto_vieja.jpg',
        ]);
        $userRepo->expects($this->once())->method('updateProfileInfo');

        $fileHandler = $this->createMock(FileHandlerService::class);
        $fileHandler->method('saveProfilePicture')->willReturn('profile_pics/foto_nueva.jpg');

        $service = $this->buildService(['userRepo' => $userRepo, 'fileHandler' => $fileHandler]);

        $result = $service->updateUserProfile(10, [], ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/tmp/x']);

        $this->assertEquals('profile_pics/foto_nueva.jpg', $result['fotoPerfilUrl']);
    }

    // --- processVerificationRequest ---

    public function testProcessVerificationRequestFallaSiFaltanDocumentos()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("ambos lados del documento");

        $service->processVerificationRequest(10, [], ['selfie' => ['error' => UPLOAD_ERR_OK]]);
    }

    public function testProcessVerificationRequestFallaSiFaltaSelfie()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("selfie en vivo es obligatoria");

        $service->processVerificationRequest(10, [], [
            'docFrente' => ['error' => UPLOAD_ERR_OK],
            'docReverso' => ['error' => UPLOAD_ERR_OK],
        ]);
    }

    public function testProcessVerificationRequestExitoso()
    {
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(2);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(['UserID' => 10, 'Telefono' => '+56911111111']);
        $userRepo->method('updateVerificationDocuments')->willReturn(true);

        $fileHandler = $this->createMock(FileHandlerService::class);
        $fileHandler->method('saveProfilePicture')->willReturn('profile_pics/selfie.jpg');
        $fileHandler->method('saveVerificationFile')->willReturn('verifications/doc.jpg');

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService([
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
            'fileHandler' => $fileHandler,
            'notifService' => $notifService,
        ]);

        $service->processVerificationRequest(10, [], [
            'docFrente' => ['error' => UPLOAD_ERR_OK],
            'docReverso' => ['error' => UPLOAD_ERR_OK],
            'selfie' => ['error' => UPLOAD_ERR_OK],
        ]);
        $this->assertTrue(true);
    }

    // --- generateAndSend2FACode ---

    public function testGenerateAndSend2FACodeFallaSiConfigNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FAConfig')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->generateAndSend2FACode(10));
    }

    public function testGenerateAndSend2FACodeUsaSmsSiMetodoEsSms()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FAConfig')->willReturn(['twofa_method' => 'sms', 'Telefono' => '+56900000000']);
        $userRepo->method('saveTemp2FACode')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('send2FACodeTwilio')
            ->with('+56900000000', $this->anything(), 'sms')
            ->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $this->assertTrue($service->generateAndSend2FACode(10));
    }

    public function testGenerateAndSend2FACodeUsaEmailPorDefecto()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FAConfig')->willReturn(['Email' => 'a@a.com', 'PrimerNombre' => 'Juan']);
        $userRepo->method('saveTemp2FACode')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('send2FACodeEmail')->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $this->assertTrue($service->generateAndSend2FACode(10));
    }

    public function testGenerateAndSend2FACodeFallaSiNoGuardaCodigo()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FAConfig')->willReturn(['Email' => 'a@a.com']);
        $userRepo->method('saveTemp2FACode')->willReturn(false);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->generateAndSend2FACode(10));
    }

    // --- verifyTemp2FACode ---

    public function testVerifyTemp2FACodeValido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('verifyAndClearTempCode')->willReturn(true);

        $logService = $this->createMock(LogService::class);
        $logService->expects($this->once())->method('logAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'logService' => $logService]);

        $this->assertTrue($service->verifyTemp2FACode(10, '123456'));
    }

    public function testVerifyTemp2FACodeInvalido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('verifyAndClearTempCode')->willReturn(false);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->verifyTemp2FACode(10, '000000'));
    }

    // --- verifyAndEnable2FA / verifyUser2FACode ---

    public function testVerifyAndEnable2FAFallaSiNoHaySecreto()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FASecret')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No se encontró secreto 2FA");

        $service->verifyAndEnable2FA(10, '123456');
    }

    public function testVerifyUser2FACodeFalseSiNoHaySecreto()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('get2FASecret')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertFalse($service->verifyUser2FACode(10, '123456'));
    }

    // --- getReferidoPor ---

    public function testGetReferidoPorRetornaLoDelRepo()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getReferidoPor')->willReturn(55);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->assertEquals(55, $service->getReferidoPor(10));
    }

    // --- requestPasswordReset ---

    public function testRequestPasswordResetNoHaceNadaSiUsuarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn(null);
        $userRepo->expects($this->never())->method('createResetToken');

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->requestPasswordReset('noexiste@test.com');
        $this->assertTrue(true);
    }

    public function testRequestPasswordResetEnviaEmailSiUsuarioExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByEmail')->willReturn(['UserID' => 10]);
        $userRepo->method('createResetToken')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('sendPasswordResetEmail');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->requestPasswordReset('existe@test.com');
        $this->assertTrue(true);
    }

    // --- updateProfilePicPath / updateVerificationDocPath ---

    public function testUpdateProfilePicPathFallaSiUsuarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Usuario no encontrado");

        $service->updateProfilePicPath(999, 'profile_pics/nueva.jpg');
    }

    public function testUpdateProfilePicPathExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(['UserID' => 10, 'Telefono' => '+56900000000']);
        $userRepo->method('updateProfileInfo')->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $service->updateProfilePicPath(10, 'profile_pics/nueva.jpg');
        $this->assertTrue(true);
    }

    public function testUpdateVerificationDocPathFallaSiUsuarioNoExiste()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn(null);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Usuario no encontrado");

        $service->updateVerificationDocPath(999, 'frente', 'verifications/doc.jpg');
    }

    public function testUpdateVerificationDocPathFallaSiTipoDesconocido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 10,
            'DocumentoImagenURL_Frente' => '',
            'DocumentoImagenURL_Reverso' => '',
            'VerificacionEstadoID' => 1,
        ]);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Tipo de documento desconocido");

        $service->updateVerificationDocPath(10, 'lateral', 'verifications/doc.jpg');
    }

    public function testUpdateVerificationDocPathExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUserById')->willReturn([
            'UserID' => 10,
            'DocumentoImagenURL_Frente' => '',
            'DocumentoImagenURL_Reverso' => '',
            'VerificacionEstadoID' => 1,
        ]);
        $userRepo->method('updateVerificationDocuments')->willReturn(true);

        $service = $this->buildService(['userRepo' => $userRepo]);

        $service->updateVerificationDocPath(10, 'frente', 'verifications/doc.jpg');
        $this->assertTrue(true);
    }

    // --- registerUser: vinculación de código de referido ---

    public function testRegisterUserVinculaCodigoReferidoSiValido()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);
        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(1);
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(1);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('create')->willReturn(42);
        $userRepo->method('findByEmail')->willReturn(['UserID' => 42, 'Email' => 'juan@test.com']);
        $userRepo->method('findResellerIdByCode')->willReturn(7); // referrer distinto del nuevo usuario
        $userRepo->expects($this->once())->method('setReferidoPor')->with(42, 7);

        $service = $this->buildService([
            'rolRepo' => $rolRepo,
            'tipoDocRepo' => $tipoDocRepo,
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
        ]);

        $datos = $this->datosRegistroValidos();
        $datos['codigoReferido'] = 'abc12345';
        $service->registerUser($datos);
    }

    public function testRegisterUserNoVinculaSiCodigoReferidoEsPropio()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);
        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(1);
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(1);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('create')->willReturn(42);
        $userRepo->method('findByEmail')->willReturn(['UserID' => 42, 'Email' => 'juan@test.com']);
        $userRepo->method('findResellerIdByCode')->willReturn(42); // mismo usuario recién creado
        $userRepo->expects($this->never())->method('setReferidoPor');

        $service = $this->buildService([
            'rolRepo' => $rolRepo,
            'tipoDocRepo' => $tipoDocRepo,
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
        ]);

        $datos = $this->datosRegistroValidos();
        $datos['codigoReferido'] = 'ABC12345';
        $service->registerUser($datos);
    }

    public function testRegisterUserSinCodigoReferidoNoIntentaVincular()
    {
        $rolRepo = $this->createMock(RolRepository::class);
        $rolRepo->method('findIdByName')->willReturn(2);
        $tipoDocRepo = $this->createMock(TipoDocumentoRepository::class);
        $tipoDocRepo->method('findIdByName')->willReturn(1);
        $estadoRepo = $this->createMock(EstadoVerificacionRepository::class);
        $estadoRepo->method('findIdByName')->willReturn(1);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('create')->willReturn(42);
        $userRepo->method('findByEmail')->willReturn(['UserID' => 42, 'Email' => 'juan@test.com']);
        $userRepo->expects($this->never())->method('findResellerIdByCode');
        $userRepo->expects($this->never())->method('setReferidoPor');

        $service = $this->buildService([
            'rolRepo' => $rolRepo,
            'tipoDocRepo' => $tipoDocRepo,
            'estadoRepo' => $estadoRepo,
            'userRepo' => $userRepo,
        ]);

        $service->registerUser($this->datosRegistroValidos());
    }

    // --- verifyBackupCode (round-trip real de cifrado, sin mocks de crypto) ---

    private function invokePrivate(object $obj, string $method, array $args)
    {
        $ref = new ReflectionMethod(get_class($obj), $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    public function testVerifyBackupCodeValidoLoConsumeYaNoSirveDosVeces()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $codigos = ['AAAA1111', 'BBBB2222', 'CCCC3333'];
        $encriptado = $this->invokePrivate($service, 'encryptData', [json_encode($codigos)]);

        $userRepo->method('getBackupCodes')->willReturn($encriptado);
        $userRepo->expects($this->once())->method('updateBackupCodes');

        $this->assertTrue($service->verifyBackupCode(10, 'BBBB2222'));
    }

    public function testVerifyBackupCodeInvalidoNoConsume()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $codigos = ['AAAA1111', 'BBBB2222'];
        $encriptado = $this->invokePrivate($service, 'encryptData', [json_encode($codigos)]);

        $userRepo->method('getBackupCodes')->willReturn($encriptado);
        $userRepo->expects($this->never())->method('updateBackupCodes');

        $this->assertFalse($service->verifyBackupCode(10, 'CODIGO-NO-EXISTE'));
    }

    // --- generateUser2FASecret / verifyAndEnable2FA / verifyUser2FACode (con Google2FA real) ---

    public function testGenerateUser2FASecretRetornaSecretYQrCodeUrl()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('update2FASecret');

        $service = $this->buildService(['userRepo' => $userRepo]);

        $result = $service->generateUser2FASecret(10, 'juan@test.com');

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qrCodeUrl', $result);
        $this->assertNotEmpty($result['secret']);
    }

    public function testVerifyAndEnable2FAExitosoConCodigoValido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secretKey = $google2fa->generateSecretKey();
        $encriptado = $this->invokePrivate($service, 'encryptData', [$secretKey]);
        $codigoValido = $google2fa->getCurrentOtp($secretKey);

        $userRepo->method('get2FASecret')->willReturn($encriptado);
        $userRepo->method('enable2FA')->willReturn(true);

        $this->assertTrue($service->verifyAndEnable2FA(10, $codigoValido));
    }

    public function testVerifyAndEnable2FAFallaConCodigoInvalido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secretKey = $google2fa->generateSecretKey();
        $encriptado = $this->invokePrivate($service, 'encryptData', [$secretKey]);

        $userRepo->method('get2FASecret')->willReturn($encriptado);

        $this->assertFalse($service->verifyAndEnable2FA(10, '000000'));
    }

    public function testVerifyUser2FACodeExitosoConCodigoValido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secretKey = $google2fa->generateSecretKey();
        $encriptado = $this->invokePrivate($service, 'encryptData', [$secretKey]);
        $codigoValido = $google2fa->getCurrentOtp($secretKey);

        $userRepo->method('get2FASecret')->willReturn($encriptado);

        $this->assertTrue($service->verifyUser2FACode(10, $codigoValido));
    }

    public function testVerifyUser2FACodeFallaConCodigoInvalido()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $service = $this->buildService(['userRepo' => $userRepo]);

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secretKey = $google2fa->generateSecretKey();
        $encriptado = $this->invokePrivate($service, 'encryptData', [$secretKey]);

        $userRepo->method('get2FASecret')->willReturn($encriptado);

        $this->assertFalse($service->verifyUser2FACode(10, '000000'));
    }

    public function testAdminUpdateUserDataExitoso()
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('updateGeneralData')->willReturn(true);

        $notifService = $this->createMock(NotificationService::class);
        $notifService->expects($this->once())->method('logAdminAction');

        $service = $this->buildService(['userRepo' => $userRepo, 'notifService' => $notifService]);

        $service->adminUpdateUserData(1, [
            'userId' => 10,
            'primerNombre' => 'Juan',
            'primerApellido' => 'Perez',
            'telefono' => '+56911111111',
        ]);
        $this->assertTrue(true);
    }
}
