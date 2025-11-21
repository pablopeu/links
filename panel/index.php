<?php
require_once '../config.php';

error_log('Iniciando panel/index.php');
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si ya está autenticado
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    error_log('Usuario ya autenticado, redirigiendo a dashboard.php');
    header('Location: dashboard.php');
    exit;
}

$secure_config = include SECURE_CONFIG_PATH;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USER && password_verify($password, $secure_config['password'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        error_log('Autenticación exitosa, redirigiendo a dashboard.php');
        header('Location: dashboard.php');
        exit;
    } else {
        error_log('Intento de autenticación fallido');
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-container { max-width: 400px; width: 100%; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title text-center">Iniciar Sesión</h3>
                <p class="text-center text-muted"><?= htmlspecialchars(APP_URL) ?></p>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>