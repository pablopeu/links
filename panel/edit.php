<?php
require_once '../config.php';

// Función de autenticación
function requireAuth() {
    session_name(SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: index.php');
        exit;
    }
}

requireAuth();

$slug = $_GET['slug'] ?? '';
$data = loadData();
$redirect = $data['redirects'][$slug] ?? null;
$error = $success = '';

if (!$redirect) {
    error_log('Slug no encontrado: ' . $slug);
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_slug = trim($_POST['slug'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validar nuevo slug
    if ($new_slug !== $slug) {
        if (empty($new_slug) || !preg_match('/^[a-zA-Z0-9]{1,20}$/', $new_slug)) {
            $error = 'El slug debe ser alfanumérico y tener entre 1 y 20 caracteres.';
        } elseif (isset($data['redirects'][$new_slug])) {
            $error = 'El slug ya está en uso.';
        }
    }

    // Validar URL
    if (!$error && !validateUrl($url)) {
        $error = 'La URL no es válida.';
    }

    if (!$error) {
        // Actualizar redirección
        unset($data['redirects'][$slug]);
        $data['redirects'][$new_slug] = [
            'url' => $url,
            'description' => $description,
            'created' => $redirect['created'],
            'clicks' => $redirect['clicks'] ?? 0
        ];
        if (saveData($data)) {
            error_log('Redirección actualizada: ' . $new_slug);
            header('Location: dashboard.php');
            exit;
        } else {
            error_log('Error guardando data.json');
            $error = 'Error al guardar los cambios.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Enlace - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-link"></i> <?= htmlspecialchars(APP_URL) ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Editar Enlace</h3>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug (<?= htmlspecialchars(APP_URL) ?>/...)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= htmlspecialchars(APP_URL) ?>/</span>
                                    <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($slug) ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="url" class="form-label">URL de destino</label>
                                <input type="url" class="form-control" id="url" name="url" value="<?= htmlspecialchars($redirect['url']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($redirect['description'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>