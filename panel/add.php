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

$data = loadData();
$message = '';

if ($_POST) {
    $url = trim($_POST['url']);
    $description = trim($_POST['description'] ?? '');
    $custom_slug = trim($_POST['slug'] ?? '');
    
    if (!validateUrl($url)) {
        $message = '<div class="alert alert-danger">URL inválida</div>';
    } else {
        $slug = $custom_slug ?: generateSlug();
        
        // Verificar slug único
        if ($custom_slug && isset($data['redirects'][$custom_slug])) {
            $message = '<div class="alert alert-danger">Slug ya existe</div>';
        } else {
            $data['redirects'][$slug] = [
                'url' => $url,
                'description' => $description,
                'created' => date('Y-m-d H:i:s'),
                'clicks' => 0
            ];
            $data['stats']['total_redirects'] = count($data['redirects']);
            
            if (saveData($data)) {
                header('Location: dashboard.php?success=1');
                exit;
            } else {
                $message = '<div class="alert alert-danger">Error guardando</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Enlace - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .slug-custom { min-width: 200px; }
        .slug-auto { min-width: 120px; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-link"></i> <?= htmlspecialchars(APP_URL) ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Volver</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-plus"></i> Nuevo Enlace Corto</h4>
                    </div>
                    <div class="card-body">
                        <?= $message ?>
                        
                        <form method="POST">
                            <div class="row">
                                <!-- Campo Slug Personalizado - MÁS GRANDE -->
                                <div class="col-md-9 mb-3">
                                    <label class="form-label">Slug Personalizado (opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= htmlspecialchars(APP_URL) ?>/</span>
                                        <input type="text" class="form-control slug-custom" name="slug" 
                                               placeholder="ej: mi-pagina-web" maxlength="20" pattern="[a-zA-Z0-9_-]+"
                                               style="min-width: 250px;">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generateSlug()">
                                            <i class="fas fa-dice"></i> Aleatorio
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Máx. 20 caracteres (letras, números, -, _)</small>
                                </div>
                                
                                <!-- Campo Slug Automático - MÁS PEQUEÑO -->
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Slug Automático</label>
                                    <input type="text" class="form-control bg-light slug-auto" id="autoSlug" readonly 
                                           value="<?= generateSlug() ?>" placeholder="Auto-generado"
                                           style="min-width: 120px; font-size: 0.9em;">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">URL de Destino <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="url" required 
                                       placeholder="https://ejemplo.com" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" name="description" rows="2" 
                                          placeholder="Ej: Mi sitio web personal"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancelar</a>
                                <button type="submit" class="btn btn-success">Crear Enlace</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generateSlug() {
            fetch('?action=generate')
                .then(response => response.text())
                .then(slug => {
                    document.getElementById('autoSlug').value = slug;
                });
        }

        // Validar slug en tiempo real
        document.querySelector('input[name="slug"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_-]/g, '');
        });

        // Copiar slug automático al campo personalizado al hacer clic
        document.getElementById('autoSlug').addEventListener('click', function() {
            const customSlugField = document.querySelector('input[name="slug"]');
            if (!customSlugField.value) {
                customSlugField.value = this.value;
            }
        });
    </script>
</body>
</html>

<?php
// Generar slug AJAX
if ($_GET['action'] === 'generate') {
    echo generateSlug();
    exit;
}
?>