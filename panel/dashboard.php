<?php
// Este archivo es panel/dashboard.php
require_once '../config.php';

function requireAuth(): void {
    error_log('Iniciando requireAuth');
    session_name(SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    error_log('requireAuth - Sesión autenticada: ' . (isset($_SESSION['authenticated']) ? 'Sí' : 'No'));
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        error_log('No autenticado, redirigiendo a index.php');
        header('Location: index.php');
        exit;
    }
}

requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $data = loadData();
    $slug = $_POST['delete'];
    if (isset($data['redirects'][$slug])) {
        unset($data['redirects'][$slug]);
        $data['stats']['total_redirects'] = count($data['redirects']);
        if (saveData($data)) {
            // Redirigir para evitar reenvío del formulario
            header('Location: dashboard.php?deleted=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_new_password'] ?? '';
    $error = '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $secure_config = include SECURE_CONFIG_PATH;
        if (!password_verify($current_password, $secure_config['password'])) {
            $error = 'Contraseña actual incorrecta.';
        } else {
            // Actualizar contraseña
            $secure_config['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            $config_content = "<?php\nreturn " . var_export($secure_config, true) . ";\n?>";
            if (file_put_contents(SECURE_CONFIG_PATH, $config_content) === false) {
                error_log('Error: No se pudo escribir en secure_config.php');
                $error = 'Error al guardar la nueva contraseña.';
            } else {
                error_log('Contraseña actualizada correctamente');
                // Invalidar todas las sesiones
                session_destroy();
                session_name(SESSION_NAME);
                session_start();
                $_SESSION['authenticated'] = true;
                $_SESSION['username'] = ADMIN_USER;
                $success = 'Contraseña actualizada correctamente.';
            }
        }
    }
}

error_log('Iniciando dashboard.php');
error_log('DATA_PATH en dashboard: ' . DATA_PATH);
error_log('data.json existe en dashboard: ' . (file_exists(DATA_PATH) ? 'Sí' : 'No'));

$data = loadData();
error_log('Datos cargados en dashboard: ' . print_r($data, true));
$redirects = $data['redirects'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-badge { font-size: 0.7em; }
        .copy-btn { 
            opacity: 0.7; 
            transition: all 0.3s ease;
            padding: 2px 6px;
            font-size: 0.8em;
        }
        .copy-btn:hover { 
            opacity: 1; 
            transform: scale(1.1);
        }
        .table-footer { 
            background-color: #343a40; 
            height: 12px; 
            border-bottom-left-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
        }
        .link-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .short-url {
            flex-grow: 1;
        }
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .navbar {
            min-height: 50px;
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }
        .container-main {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 50px);
            padding-top: 0.5rem;
        }
        .links-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .table-container {
            flex: 1;
            overflow-y: auto;
        }
        .card-table-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .stats-card {
            height: 70px;
            padding: 0.5rem;
            transition: all 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 0.5rem 1rem;
            margin-bottom: 0.75rem;
        }
        /* Mantener alturas compactas pero con tipografías normales */
        .table-compact td, .table-compact th {
            padding: 0.5rem 0.75rem;
        }
        /* Cards clickeables */
        .clickable-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .clickable-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stats-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }
        .stats-text {
            display: flex;
            flex-direction: column;
        }
        .stats-number {
            font-size: 1.5rem;
            font-weight: bold;
            line-height: 1;
        }
        .stats-title {
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        /* Estilos para las tarjetas de acción */
        .action-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }
        .action-card-text {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .action-card-line {
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.2;
            margin-bottom: 0;
        }
        .action-card-icon {
            font-size: 1.8rem;
            opacity: 0.7;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-link"></i> <?= htmlspecialchars(APP_URL) ?></a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </span>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>

    <div class="container container-main">
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Enlace eliminado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Enlace actualizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Enlace creado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Modal para cambiar contraseña -->
        <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changePasswordModalLabel"><i class="fas fa-key"></i> Cambiar Contraseña</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="" id="password-form">
                            <input type="hidden" name="change_password" value="1">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_new_password" class="form-label">Confirmar Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                                    <span class="input-group-text" id="password-match-icon"></span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="submit-password" disabled>Cambiar Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card text-white bg-primary stats-card">
                    <div class="card-body p-2">
                        <div class="stats-content">
                            <div class="stats-text">
                                <h6 class="stats-title"><i class="fas fa-link"></i> Enlaces</h6>
                                <div class="stats-number"><?= count($redirects) ?></div>
                            </div>
                            <i class="fas fa-link fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success stats-card">
                    <div class="card-body p-2">
                        <div class="stats-content">
                            <div class="stats-text">
                                <h6 class="stats-title"><i class="fas fa-mouse-pointer"></i> Clicks</h6>
                                <div class="stats-number"><?= $data['stats']['total_clicks'] ?? 0 ?></div>
                            </div>
                            <i class="fas fa-mouse-pointer fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info stats-card clickable-card" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <div class="card-body p-2 h-100">
                        <div class="action-card-content">
                            <div class="action-card-text">
                                <p class="action-card-line">Cambiar</p>
                                <p class="action-card-line">Contraseña</p>
                            </div>
                            <i class="fas fa-key action-card-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a href="add.php" class="text-decoration-none">
                    <div class="card text-white bg-warning stats-card clickable-card">
                        <div class="card-body p-2 h-100">
                            <div class="action-card-content">
                                <div class="action-card-text">
                                    <p class="action-card-line">Nuevo</p>
                                    <p class="action-card-line">Enlace</p>
                                </div>
                                <i class="fas fa-plus action-card-icon"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Tabla de enlaces -->
        <div class="links-container">
            <?php if (empty($redirects)): ?>
                <div class="alert alert-info text-center py-2">
                    <i class="fas fa-info-circle"></i> No tienes enlaces aún. 
                    <a href="add.php">¡Crea el primero!</a>
                </div>
            <?php else: ?>
                <div class="card h-100">
                    <div class="card-table-container">
                        <div class="table-container">
                            <table class="table table-hover table-compact mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Enlace Corto</th>
                                        <th>Destino</th>
                                        <th>Descripción</th>
                                        <?php if (ENABLE_PREVIEW): ?>
                                        <th>Preview</th>
                                        <?php endif; ?>
                                        <th>Clicks</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($redirects as $slug => $redirect): ?>
                                        <tr>
                                            <td>
                                                <div class="link-container">
                                                    <div class="short-url">
                                                        <a href="<?= APP_URL ?>/<?= $slug ?>" target="_blank" class="text-decoration-none">
                                                            <i class="fas fa-external-link-alt text-primary"></i>
                                                            <?= htmlspecialchars(APP_URL) ?>/<?= $slug ?>
                                                        </a>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-secondary copy-btn" 
                                                            onclick="copyToClipboard('<?= APP_URL ?>/<?= $slug ?>', this)" 
                                                            title="Copiar enlace al portapapeles">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($redirect['url'], 0, 40)) ?>...
                                                </small>
                                            </td>
                                            <td><?= htmlspecialchars($redirect['description'] ?? '-') ?></td>
                                            <?php if (ENABLE_PREVIEW): ?>
                                            <td>
                                                <?php if (isset($redirect['metatags'])): ?>
                                                    <span class="badge bg-success preview-badge" title="Preview configurado">
                                                        <i class="fas fa-check"></i> Sí
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary preview-badge" title="Sin preview configurado">
                                                        <i class="fas fa-times"></i> No
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-primary"><?= $redirect['clicks'] ?? 0 ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($redirect['created'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="edit.php?slug=<?= urlencode($slug) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Eliminar este enlace? Esta acción no se puede deshacer.')">
                                                    <input type="hidden" name="delete" value="<?= $slug ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Barra negra decorativa - SIEMPRE VISIBLE -->
                        <div class="table-footer"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para copiar al portapapeles - CORREGIDA
        function copyToClipboard(text, element) {
            const button = element;
            
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar feedback visual
                const originalHTML = button.innerHTML;
                
                button.innerHTML = '<i class="fas fa-check text-success"></i>';
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-outline-success');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-outline-success');
                    button.classList.add('btn-outline-secondary');
                }, 1500);
                
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
                // Fallback para navegadores más antiguos
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    
                    // Mostrar feedback visual también para el fallback
                    const originalHTML = button.innerHTML;
                    
                    button.innerHTML = '<i class="fas fa-check text-success"></i>';
                    button.classList.remove('btn-outline-secondary');
                    button.classList.add('btn-outline-success');
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.classList.remove('btn-outline-success');
                        button.classList.add('btn-outline-secondary');
                    }, 1500);
                    
                } catch (err) {
                    console.error('Fallback: Error al copiar', err);
                    alert('Error al copiar el enlace');
                }
                document.body.removeChild(textArea);
            });
        }

        // Validación en tiempo real de contraseñas
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_new_password');
        const passwordMatchIcon = document.getElementById('password-match-icon');
        const submitButton = document.getElementById('submit-password');
        const passwordForm = document.getElementById('password-form');
        const changePasswordModal = document.getElementById('changePasswordModal');

        function validatePasswords() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (newPassword === '' || confirmPassword === '') {
                passwordMatchIcon.innerHTML = '';
                submitButton.disabled = true;
            } else if (newPassword === confirmPassword) {
                passwordMatchIcon.innerHTML = '<i class="fas fa-check text-success"></i>';
                submitButton.disabled = false;
            } else {
                passwordMatchIcon.innerHTML = '<i class="fas fa-times text-danger"></i>';
                submitButton.disabled = true;
            }
        }

        newPasswordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);

        // Vaciar campos al cerrar el modal
        changePasswordModal.addEventListener('hidden.bs.modal', function () {
            passwordForm.reset();
            passwordMatchIcon.innerHTML = '';
            submitButton.disabled = true;
        });

        // Mejorar la experiencia de copiado en dispositivos táctiles
        document.querySelectorAll('.copy-btn').forEach(button => {
            button.addEventListener('touchstart', function(e) {
                e.preventDefault();
                const url = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                copyToClipboard(url, this);
            });
        });
    </script>
</body>
</html>