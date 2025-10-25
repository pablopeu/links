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
            height: 15px; 
            border-bottom-left-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }
        .link-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .short-url {
            flex-grow: 1;
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

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-tachometer-alt"></i> Panel de Control</h2>

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
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5><i class="fas fa-link"></i> Enlaces</h5>
                                <h3><?= count($redirects) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5><i class="fas fa-mouse-pointer"></i> Clicks</h5>
                                <h3><?= $data['stats']['total_clicks'] ?? 0 ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5><i class="fas fa-key"></i> Cambiar Contraseña</h5>
                                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="fas fa-key"></i> Cambiar ahora
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5><i class="fas fa-share-alt"></i> Preview</h5>
                                <h3><?= ENABLE_PREVIEW ? '✅ Activado' : '❌ Desactivado' ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón nuevo enlace -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-list"></i> Mis Enlaces</h4>
                    <a href="add.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nuevo Enlace
                    </a>
                </div>

                <!-- Tabla de enlaces -->
                <?php if (empty($redirects)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No tienes enlaces aún. 
                        <a href="add.php">¡Crea el primero!</a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
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
                            <!-- Barra negra decorativa -->
                            <div class="table-footer"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
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