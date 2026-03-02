<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lógica de versión para CSS (Cache busting)
$cssFilePath = __DIR__ . '/../../../public_html/assets/css/style.css';
$cssVersion = file_exists($cssFilePath) ? hash_file('md5', $cssFilePath) : '1.0.0';

// Título por defecto
$pageTitleDisplay = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | JC Envios' : 'JC Envios | Envíos Rápidos';

// Pre-calcular roles y estados
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_rol_name'] ?? '';
$is_admin = ($user_role === 'Admin');
$is_operator = ($user_role === 'Operador');
$two_fa_enabled = (isset($_SESSION['twofa_enabled']) && $_SESSION['twofa_enabled'] == 1);
$verifStatusId = isset($_SESSION['verification_status_id']) ? (int)$_SESSION['verification_status_id'] : 0;

// Lógica de Foto de Perfil
$photoUrl = BASE_URL . '/assets/img/SoloLogoNegroSinFondo.png';
if ($is_logged_in && isset($_SESSION['user_photo_url'])) {
    $photoPath = $_SESSION['user_photo_url'];
    $physicalPhotoPath = __DIR__ . '/../../../uploads/' . $photoPath;
    if (file_exists($physicalPhotoPath)) {
        $photoVersion = hash_file('md5', $physicalPhotoPath);
        $photoUrl = BASE_URL . '/admin/view_secure_file.php?file=' . urlencode($photoPath) . '&v=' . $photoVersion;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitleDisplay; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo $cssVersion; ?>">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png">

    <style>
        .main-header {
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }

        .navbar-brand img {
            height: 45px;
            transition: transform 0.3s;
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .nav-link {
            font-weight: 500;
            color: #555;
            transition: color 0.2s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #0d6efd !important;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .dropdown-menu-custom {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 10px;
            min-width: 220px;
        }

        .dropdown-item {
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.95rem;
            color: #444;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .dropdown-item i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
            display: inline-block;
        }

        /* --- ESTILOS PARA BADGES ANIMADOS --- */
        .badge-anim {
            transition: transform 0.3s ease-in-out;
        }
        .badge-anim.pulse {
            transform: scale(1.2);
        }
        
        /* Contenedor flexible para alinear texto y badges sin que se rompan feo */
        .nav-link-badges {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        /* --- ALERTA INFORMATIVA GLOBAL --- */
        #holiday-alert-bar {
            background: linear-gradient(45deg, #ffc107, #ff9800); 
            color: #000;
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.6s cubic-bezier(0.19, 1, 0.22, 1); 
        }
        /* CORRECCIÓN MÓVIL: Aumentado de 100px a 400px para que el texto largo no se corte al hacer wrap */
        #holiday-alert-bar.show { max-height: 400px; } 

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: white;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                padding: 20px;
                border-top: 1px solid #eee;
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05);
                z-index: 1050;
            }

            .nav-item {
                padding: 8px 0;
                border-bottom: 1px solid #f8f9fa;
            }

            /* CORRECCIÓN MÓVIL: Alinear avatar y botón de sonido horizontalmente */
            .user-actions-mobile {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                width: 100%;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #f8f9fa;
            }

            .dropdown-menu-custom {
                box-shadow: none;
                border: 1px solid #eee;
                background: #fdfdfd;
                margin-top: 5px;
                position: static !important; /* Despegar del absolute nativo en móviles */
                transform: none !important;
            }
        }
    </style>

    <?php if (isset($pageScript) && $pageScript === 'seguridad.js'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>

    <?php if ($is_logged_in && $verifStatusId !== 3): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                let isChecking = false;
                const CHECK_INTERVAL = 30000;

                const checkStatus = async () => {
                    if (isChecking || document.hidden) return;
                    isChecking = true;
                    try {
                        const baseUrl = "<?php echo BASE_URL; ?>";
                        const response = await fetch(`${baseUrl}/api/?accion=checkSessionStatus&_=${new Date().getTime()}`);
                        if (response.ok) {
                            const data = await response.json();
                            if (data.success && data.needs_refresh) {
                                window.location.reload();
                            }
                        }
                    } catch (error) {
                    } finally {
                        isChecking = false;
                    }
                };
                setInterval(checkStatus, CHECK_INTERVAL);
                document.addEventListener("visibilitychange", () => {
                    if (!document.hidden) checkStatus();
                });
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkSystemAlerts = async () => {
                try {
                    const baseUrl = "<?php echo BASE_URL; ?>";
                    const response = await fetch(`${baseUrl}/api/?accion=checkSystemStatus&_=${new Date().getTime()}`);
                    if (response.ok) {
                        const data = await response.json();
                        const alertBar = document.getElementById('holiday-alert-bar');
                        if (alertBar) {
                            if (data.holiday_warning) {
                                document.getElementById('holiday-title').textContent = data.holiday_warning.title || 'AVISO';
                                document.getElementById('holiday-message').textContent = data.holiday_warning.message;
                                
                                const endDate = new Date(data.holiday_warning.ends_at);
                                const options = { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' };
                                document.getElementById('holiday-date').textContent = endDate.toLocaleDateString('es-CL', options);

                                alertBar.classList.remove('d-none');
                                setTimeout(() => alertBar.classList.add('show'), 100);
                            } else {
                                alertBar.classList.remove('show');
                                setTimeout(() => alertBar.classList.add('d-none'), 600);
                            }
                        }
                    }
                } catch (error) { console.error("Error verificando alertas:", error); }
            };

            checkSystemAlerts();
            setInterval(checkSystemAlerts, 60000); // Consultar cada 1 minuto
        });
    </script>
</head>

<body class="d-flex flex-column min-vh-100 bg-light" data-base-url="<?php echo htmlspecialchars(BASE_URL); ?>"
    data-csrf-token="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

    <header class="main-header sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light py-2">
            <div class="container position-relative">
                <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/index.php">
                    <img src="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png" alt="Logo">
                </a>

                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">

                        <?php if ($is_logged_in): ?>

                            <?php if (($is_admin || $is_operator) && !$two_fa_enabled): ?>
                                <li class="nav-item">
                                    <a class="nav-link text-danger fw-bold bg-danger bg-opacity-10 rounded px-3"
                                        href="<?php echo BASE_URL; ?>/dashboard/seguridad.php">
                                        <i class="bi bi-shield-exclamation me-1"></i> Activar 2FA
                                    </a>
                                </li>

                            <?php elseif ($is_admin): ?>
                                <li class="nav-item"><a class="nav-link"
                                        href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                                <li class="nav-item">
                                    <a class="nav-link fw-bold nav-link-badges" href="<?php echo BASE_URL; ?>/admin/index.php">
                                        Órdenes 
                                        <span id="badge-verificacion" class="badge rounded-pill bg-primary d-none badge-anim" title="En Verificación">0</span>
                                        <span id="badge-proceso" class="badge rounded-pill bg-info text-dark d-none badge-anim" title="En Proceso">0</span>
                                        <span id="badge-pausadas" class="badge rounded-pill bg-warning text-dark d-none badge-anim" title="Pausadas">0</span>
                                        <span id="badge-riesgo" class="badge rounded-pill bg-dark d-none badge-anim" title="Riesgo">0</span>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/admin/pendientes.php">Pendientes</a></li>

                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        Gestión
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-custom animate__animated animate__fadeIn">
                                        <li><h6 class="dropdown-header text-uppercase small text-muted">Administración</h6></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/usuarios.php"><i class="bi bi-people text-primary me-2"></i> Usuarios</a></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/verificaciones.php"><i class="bi bi-person-badge text-dark me-2"></i> Verificaciones</a></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/cuentas.php"><i class="bi bi-bank text-success me-2"></i> Ctas. Bancarias</a></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/paises.php"><i class="bi bi-globe-americas text-info me-2"></i> Países</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header text-uppercase small text-muted">Sistema</h6></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/tasas.php"><i class="bi bi-currency-exchange text-warning me-2"></i> Tasas</a></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/feriados.php"><i class="bi bi-calendar-event text-danger me-2"></i> Feriados</a></li>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/logs.php"><i class="bi bi-clipboard-data text-secondary me-2"></i> Bitácora</a></li>
                                    </ul>
                                </li>
                                <li class="nav-item"><a class="nav-link"
                                        href="<?php echo BASE_URL; ?>/admin/contabilidad.php">Contabilidad</a></li>

                            <?php elseif ($is_operator): ?>
                                <li class="nav-item">
                                    <a class="nav-link fw-bold nav-link-badges" href="<?php echo BASE_URL; ?>/operador/index.php">
                                        Órdenes
                                        <span id="badge-verificacion" class="badge rounded-pill bg-primary d-none badge-anim" title="En Verificación">0</span>
                                        <span id="badge-proceso" class="badge rounded-pill bg-info text-dark d-none badge-anim" title="En Proceso">0</span>
                                        <span id="badge-pausadas" class="badge rounded-pill bg-warning text-dark d-none badge-anim" title="Pausadas">0</span>
                                    </a>
                                </li>
                                <li class="nav-item"><a class="nav-link text-primary"
                                        href="<?php echo BASE_URL; ?>/operador/pendientes.php">Pendientes</a></li>

                            <?php else: ?>
                                <li class="nav-item"><a class="nav-link"
                                        href="<?php echo BASE_URL; ?>/dashboard/index.php">Enviar Dinero</a></li>
                                <li class="nav-item"><a class="nav-link"
                                        href="<?php echo BASE_URL; ?>/dashboard/historial.php">Historial</a></li>
                            <?php endif; ?>

                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">Inicio</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/quienes-somos.php">Nosotros</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/contacto.php">Contacto</a></li>
                        <?php endif; ?>
                    </ul>

                    <div class="d-flex align-items-center user-actions-mobile gap-2 mt-3 mt-lg-0">
                        <?php if ($is_logged_in): ?>
                            
                            <?php if ($is_admin || $is_operator): ?>
                            <button id="btn-toggle-sound" class="btn btn-sm btn-outline-secondary rounded-circle me-1" title="Alertas de Sonido">
                                <i class="bi bi-bell-fill" id="icon-sound-status"></i>
                            </button>
                            <?php endif; ?>

                            <div class="dropdown flex-grow-1 flex-lg-grow-0">
                                <a href="#"
                                    class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark p-1 rounded hover-bg-light justify-content-end justify-content-lg-start"
                                    id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="d-flex flex-column text-end text-lg-start lh-1 me-2 me-lg-0 ms-lg-2 order-1 order-lg-2">
                                        <span class="fw-bold small"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($user_role ?: 'Cliente'); ?></small>
                                    </div>
                                    <img src="<?php echo $photoUrl; ?>" alt="Perfil" class="user-avatar rounded-circle order-2 order-lg-1">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom animate__animated animate__fadeIn">
                                    <li><h6 class="dropdown-header">Mi Cuenta</h6></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/dashboard/perfil.php"><i class="bi bi-person me-2"></i> Perfil</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/dashboard/seguridad.php"><i class="bi bi-shield-lock me-2"></i> Seguridad</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Salir</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/login.php"
                                class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm w-100">
                                <i class="bi bi-person-circle me-2"></i> Iniciar Sesión
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <div id="holiday-alert-bar" class="d-none shadow-sm text-center w-100 border-top border-warning">
            <div class="container py-3">
                <div class="d-flex align-items-center justify-content-center flex-wrap gap-2">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <span class="badge bg-dark text-white fw-bold" id="holiday-title">AVISO</span>
                    <span class="fw-medium" id="holiday-message">...</span>
                    <span class="d-none d-md-inline">|</span>
                    <span class="small d-block d-md-inline w-100 w-md-auto">
                        Válido hasta: <strong id="holiday-date">...</strong>
                    </span>
                </div>
            </div>
        </div>
    </header>
    
    <?php if ($is_admin || $is_operator): ?>
    <audio id="admin-alert-sound" preload="auto">
        <source src="<?= BASE_URL ?>/assets/audio/notification.mp3" type="audio/mpeg">
    </audio>
    <?php endif; ?>

    <main class="flex-grow-1">