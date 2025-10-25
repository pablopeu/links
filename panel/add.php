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

// Función para obtener metadatos de una URL
function getUrlMetadata($url) {
    $metadata = [
        'title' => '',
        'description' => '',
        'image' => ''
    ];
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; URLShortener/1.0)\r\n"
            ]
        ]);
        
        $html = file_get_contents($url, false, $context);
        if ($html === false) {
            return ['status' => 'error', 'message' => 'No se pudo acceder a la URL'];
        }
        
        // Buscar título
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $metadata['title'] = trim(html_entity_decode($matches[1]));
        }
        
        // Buscar meta description
        if (preg_match('/<meta\s+name="description"\s+content="(.*?)"/is', $html, $matches)) {
            $metadata['description'] = trim(html_entity_decode($matches[1]));
        }
        
        // Buscar og:description
        if (preg_match('/<meta\s+property="og:description"\s+content="(.*?)"/is', $html, $matches)) {
            $metadata['description'] = trim(html_entity_decode($matches[1]));
        }
        
        // Buscar og:title
        if (preg_match('/<meta\s+property="og:title"\s+content="(.*?)"/is', $html, $matches)) {
            $metadata['title'] = trim(html_entity_decode($matches[1]));
        }
        
        // Buscar og:image
        if (preg_match('/<meta\s+property="og:image"\s+content="(.*?)"/is', $html, $matches)) {
            $metadata['image'] = trim($matches[1]);
            // Convertir URL relativa a absoluta si es necesario
            if (strpos($metadata['image'], 'http') !== 0) {
                $baseUrl = parse_url($url);
                $metadata['image'] = $baseUrl['scheme'] . '://' . $baseUrl['host'] . 
                                   (isset($baseUrl['port']) ? ':' . $baseUrl['port'] : '') . 
                                   '/' . ltrim($metadata['image'], '/');
            }
        }
        
        // Si no hay og:image, buscar la primera imagen grande
        if (empty($metadata['image']) && preg_match('/<img[^>]+src="([^">]+)"/i', $html, $matches)) {
            $imageUrl = trim($matches[1]);
            // Convertir URL relativa a absoluta si es necesario
            if (strpos($imageUrl, 'http') !== 0) {
                $baseUrl = parse_url($url);
                $metadata['image'] = $baseUrl['scheme'] . '://' . $baseUrl['host'] . 
                                   (isset($baseUrl['port']) ? ':' . $baseUrl['port'] : '') . 
                                   '/' . ltrim($imageUrl, '/');
            } else {
                $metadata['image'] = $imageUrl;
            }
        }
        
        // Determinar estado
        if (!empty($metadata['title']) || !empty($metadata['description']) || !empty($metadata['image'])) {
            $metadata['status'] = 'success';
        } else {
            $metadata['status'] = 'warning';
            $metadata['message'] = 'No se encontraron metadatos en el sitio destino';
        }
        
    } catch (Exception $e) {
        error_log('Error obteniendo metadatos: ' . $e->getMessage());
        $metadata['status'] = 'error';
        $metadata['message'] = 'Error al acceder al sitio: ' . $e->getMessage();
    }
    
    return $metadata;
}

if ($_POST) {
    $url = trim($_POST['url']);
    $description = trim($_POST['description'] ?? '');
    $custom_slug = trim($_POST['slug'] ?? '');
    
    // Procesar meta tags si está habilitado el preview
    $metatags = [];
    if (ENABLE_PREVIEW) {
        $metatags = [
            'title' => trim($_POST['meta_title'] ?? ''),
            'description' => trim($_POST['meta_description'] ?? ''),
            'image' => trim($_POST['meta_image'] ?? '')
        ];
        
        // Si no se proporcionó título, usar descripción
        if (empty($metatags['title']) && !empty($description)) {
            $metatags['title'] = $description;
        }
        
        // Si no se proporcionó descripción, usar la normal
        if (empty($metatags['description']) && !empty($description)) {
            $metatags['description'] = $description;
        }
        
        // Valores por defecto si todo está vacío
        if (empty($metatags['title'])) {
            $metatags['title'] = 'Enlace acortado - ' . parse_url(APP_URL, PHP_URL_HOST);
        }
        if (empty($metatags['description'])) {
            $metatags['description'] = 'Enlace acortado por ' . parse_url(APP_URL, PHP_URL_HOST);
        }
        if (empty($metatags['image'])) {
            $metatags['image'] = APP_URL . '/preview-default.jpg';
        }
    }
    
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
                'clicks' => 0,
                'metatags' => $metatags
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

// Procesar solicitud AJAX para obtener metadatos
if (isset($_GET['action']) && $_GET['action'] === 'get_metadata' && isset($_GET['url'])) {
    $url = $_GET['url'];
    if (validateUrl($url)) {
        $metadata = getUrlMetadata($url);
        header('Content-Type: application/json');
        echo json_encode($metadata);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'URL inválida']);
    }
    exit;
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
        #metadataBtn { transition: all 0.3s ease; }
        .metadata-status { font-size: 0.9em; margin-top: 5px; }
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
                        
                        <form method="POST" id="linkForm">
                            <div class="row">
                                <!-- Campo Slug Personalizado -->
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
                                
                                <!-- Campo Slug Automático -->
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Slug Automático</label>
                                    <input type="text" class="form-control bg-light slug-auto" id="autoSlug" readonly 
                                           value="<?= generateSlug() ?>" placeholder="Auto-generado"
                                           style="min-width: 120px; font-size: 0.9em;">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">URL de Destino <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="url" name="url" required 
                                       placeholder="https://ejemplo.com" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
                            </div>
                            
                            <?php if (ENABLE_PREVIEW): ?>
                            <div class="mb-3">
                                <label class="form-label">Configuración de Preview</label>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-danger" id="metadataBtn">
                                        <i class="fas fa-cloud-download-alt"></i> Obtener Metadatos Automáticamente
                                    </button>
                                    <span id="metadataStatus" class="metadata-status"></span>
                                </div>
                                <small class="form-text text-muted">
                                    El botón buscará automáticamente el título, descripción e imagen del sitio destino para el preview en redes sociales.
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" name="description" rows="2" 
                                          placeholder="Ej: Mi sitio web personal"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Campos ocultos para metadatos -->
                            <?php if (ENABLE_PREVIEW): ?>
                            <input type="hidden" id="meta_title" name="meta_title" value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
                            <input type="hidden" id="meta_description" name="meta_description" value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>">
                            <input type="hidden" id="meta_image" name="meta_image" value="<?= htmlspecialchars($_POST['meta_image'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
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

        // Cargar metadatos automáticamente
        <?php if (ENABLE_PREVIEW): ?>
        document.getElementById('metadataBtn').addEventListener('click', function() {
            const url = document.getElementById('url').value;
            const btn = this;
            const statusDiv = document.getElementById('metadataStatus');
            
            if (!url) {
                statusDiv.innerHTML = '<span class="text-danger">Por favor, ingresa una URL primero</span>';
                return;
            }
            
            // Validar URL
            try {
                new URL(url);
            } catch (e) {
                statusDiv.innerHTML = '<span class="text-danger">Por favor, ingresa una URL válida</span>';
                return;
            }
            
            // Mostrar loading
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando metadatos...';
            btn.disabled = true;
            statusDiv.innerHTML = '<span class="text-info">Buscando metadatos en el sitio destino...</span>';
            
            // Hacer la solicitud
            fetch('?action=get_metadata&url=' + encodeURIComponent(url))
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'error') {
                        btn.className = 'btn btn-danger';
                        statusDiv.innerHTML = '<span class="text-danger">❌ ' + data.message + '</span>';
                    } else if (data.status === 'warning') {
                        btn.className = 'btn btn-warning';
                        statusDiv.innerHTML = '<span class="text-warning">⚠️ ' + data.message + '</span>';
                        
                        // Guardar los metadatos encontrados (aunque sean pocos)
                        if (data.title) document.getElementById('meta_title').value = data.title;
                        if (data.description) document.getElementById('meta_description').value = data.description;
                        if (data.image) document.getElementById('meta_image').value = data.image;
                    } else if (data.status === 'success') {
                        btn.className = 'btn btn-success';
                        statusDiv.innerHTML = '<span class="text-success">✅ Metadatos obtenidos correctamente</span>';
                        
                        // Guardar todos los metadatos encontrados
                        document.getElementById('meta_title').value = data.title || '';
                        document.getElementById('meta_description').value = data.description || '';
                        document.getElementById('meta_image').value = data.image || '';
                        
                        // Si no hay descripción, sugerir usar el título
                        const descriptionField = document.querySelector('textarea[name="description"]');
                        if (!descriptionField.value && data.title) {
                            descriptionField.value = data.title;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.className = 'btn btn-danger';
                    statusDiv.innerHTML = '<span class="text-danger">❌ Error al cargar los metadatos</span>';
                })
                .finally(() => {
                    // Restaurar botón
                    btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Obtener Metadatos Automáticamente';
                    btn.disabled = false;
                });
        });

        // Resetear el botón cuando cambia la URL
        document.getElementById('url').addEventListener('input', function() {
            const btn = document.getElementById('metadataBtn');
            const statusDiv = document.getElementById('metadataStatus');
            btn.className = 'btn btn-danger';
            statusDiv.innerHTML = '';
        });
        <?php endif; ?>

        // Auto-generar slug cuando se escribe la URL (solo si el campo slug está vacío)
        document.getElementById('url').addEventListener('blur', function() {
            const slugField = document.querySelector('input[name="slug"]');
            const autoSlugField = document.getElementById('autoSlug');
            
            if (!slugField.value && this.value) {
                // Si no hay slug personalizado, generar uno automático
                generateSlug();
            }
        });
    </script>
</body>
</html>

<?php
// Generar slug AJAX
if ($_GET['action'] === 'generate' && !isset($_GET['url'])) {
    echo generateSlug();
    exit;
}
?>