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
                                <h5><i class="fas fa-cog"></i> Estado</h5>
                                <h3>Activo</h3>
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
                                            <th>Clicks</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($redirects as $slug => $redirect): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?= APP_URL ?>/<?= $slug ?>" target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-external-link-alt text-primary"></i>
                                                        <?= htmlspecialchars(APP_URL) ?>/<?= $slug ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(substr($redirect['url'], 0, 40)) ?>...
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($redirect['description'] ?? '-') ?></td>
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
                                                    <button onclick="deleteLink('<?= $slug ?>')" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteLink(slug) {
            if (confirm('¿Eliminar este enlace? Esta acción no se puede deshacer.')) {
                fetch('?delete=' + slug, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
            }
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
    </script>
</body>
</html>

<?php
// Eliminar enlace via POST
if ($_POST['delete'] ?? false) {
    $data = loadData();
    $slug = $_POST['delete'];
    if (isset($data['redirects'][$slug])) {
        unset($data['redirects'][$slug]);
        $data['stats']['total_redirects'] = count($data['redirects']);
        if (saveData($data)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Error guardando']);
        }
    } else {
        echo json_encode(['error' => 'Enlace no encontrado']);
    }
    exit;
}
?>