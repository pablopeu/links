<?php
// TEMPORAL: Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';

// Función de autenticación
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: index.php');
        exit;
    }
}

requireAuth();

// Función para obtener metadatos de una URL
function getUrlMetadata($url) {
    $metadata = [
        'title' => '',
        'description' => '',
        'image' => '',
        'status' => 'error',
        'message' => 'No se pudo procesar la URL'
    ];
    
    // Validar URL primero
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $metadata['message'] = 'URL inválida';
        return $metadata;
    }
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; URLShortener/1.0)\r\n",
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            $metadata['message'] = 'No se pudo acceder a la URL';
            return $metadata;
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
        if (preg_match('/<meta\s+property="og:image"\s+content="([^"]*)"/is', $html, $matches)) {
            $imageUrl = trim($matches[1]);
            if (!empty($imageUrl)) {
                // Convertir URL relativa a absoluta si es necesario
                if (strpos($imageUrl, 'http') !== 0) {
                    $baseUrl = parse_url($url);
                    $scheme = $baseUrl['scheme'] ?? 'https';
                    $host = $baseUrl['host'] ?? '';
                    $port = isset($baseUrl['port']) ? ':' . $baseUrl['port'] : '';
                    $base = $scheme . '://' . $host . $port;
                    $metadata['image'] = $base . '/' . ltrim($imageUrl, '/');
                } else {
                    $metadata['image'] = $imageUrl;
                }
            }
        }
        
        // Determinar estado
        if (!empty($metadata['title']) || !empty($metadata['description']) || !empty($metadata['image'])) {
            $metadata['status'] = 'success';
            $metadata['message'] = 'Metadatos obtenidos correctamente';
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

// Procesar solicitud AJAX para obtener metadatos (DEBE estar ANTES de cualquier output)
if (isset($_GET['action']) && $_GET['action'] === 'get_metadata' && isset($_GET['url'])) {
    $url = $_GET['url'];
    $metadata = getUrlMetadata($url);
    header('Content-Type: application/json');
    echo json_encode($metadata);
    exit;
}

// Inicializar variables
$slug = $_GET['slug'] ?? '';
$error = '';
$success = '';

if (empty($slug)) {
    header('Location: dashboard.php');
    exit;
}

// Cargar datos
try {
    $data = loadData();
    $redirect = $data['redirects'][$slug] ?? null;
    
    if (!$redirect) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error cargando datos: ' . $e->getMessage());
    $error = 'Error al cargar los datos del enlace';
}

// Precalcular hasExistingMetadata para el JavaScript
$hasExistingMetadata = false;
if (ENABLE_PREVIEW && isset($redirect['metatags'])) {
    $metatags = $redirect['metatags'];
    $hasExistingMetadata = !empty($metatags['title']) || !empty($metatags['description']) || !empty($metatags['image']);
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_slug = trim($_POST['slug'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validaciones básicas
    if (empty($new_slug)) {
        $error = 'El slug no puede estar vacío';
    } elseif (empty($url)) {
        $error = 'La URL no puede estar vacía';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{1,20}$/', $new_slug)) {
        $error = 'El slug debe contener solo letras, números, guiones y guiones bajos (máx 20 caracteres)';
    } else {
        // Procesar meta tags si está habilitado el preview
        $metatags = [];
        if (ENABLE_PREVIEW) {
            $metatags = [
                'title' => trim($_POST['meta_title'] ?? ''),
                'description' => trim($_POST['meta_description'] ?? ''),
                'image' => trim($_POST['meta_image'] ?? '')
            ];
            
            // Valores por defecto
            if (empty($metatags['title'])) {
                $metatags['title'] = !empty($description) ? $description : 'Enlace acortado - ' . parse_url(APP_URL, PHP_URL_HOST);
            }
            if (empty($metatags['description'])) {
                $metatags['description'] = !empty($description) ? $description : 'Enlace acortado por ' . parse_url(APP_URL, PHP_URL_HOST);
            }
            if (empty($metatags['image'])) {
                $metatags['image'] = APP_URL . '/preview-default.jpg';
            }
        }

        // Validar URL
        if (!validateUrl($url)) {
            $error = 'La URL no es válida';
        } elseif ($new_slug !== $slug && isset($data['redirects'][$new_slug])) {
            $error = 'El slug ya está en uso';
        }

        if (!$error) {
            try {
                // Actualizar redirección
                unset($data['redirects'][$slug]);
                $data['redirects'][$new_slug] = [
                    'url' => $url,
                    'description' => $description,
                    'created' => $redirect['created'] ?? date('Y-m-d H:i:s'),
                    'clicks' => $redirect['clicks'] ?? 0,
                    'metatags' => $metatags
                ];
                
                if (saveData($data)) {
                    header('Location: dashboard.php?updated=1');
                    exit;
                } else {
                    $error = 'Error al guardar los cambios en la base de datos';
                }
            } catch (Exception $e) {
                error_log('Error guardando datos: ' . $e->getMessage());
                $error = 'Error interno al guardar los cambios';
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
    <title>Editar Enlace - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        #metadataBtn { transition: all 0.3s ease; }
        .metadata-status { font-size: 0.9em; margin-top: 5px; }
        .current-metadata { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
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
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Editar Enlace</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Error al procesar la solicitud
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="linkForm">
                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug (<?= htmlspecialchars(APP_URL) ?>/...)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= htmlspecialchars(APP_URL) ?>/</span>
                                    <input type="text" class="form-control" id="slug" name="slug" 
                                           value="<?= htmlspecialchars($slug) ?>" required 
                                           pattern="[a-zA-Z0-9_-]{1,20}" 
                                           title="Máximo 20 caracteres alfanuméricos, guiones o guiones bajos">
                                </div>
                                <small class="form-text text-muted">Máx. 20 caracteres (letras, números, -, _)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="url" class="form-label">URL de destino *</label>
                                <input type="url" class="form-control" id="url" name="url" 
                                       value="<?= htmlspecialchars($redirect['url'] ?? '') ?>" required
                                       placeholder="https://ejemplo.com">
                            </div>
                            
                            <?php if (ENABLE_PREVIEW): ?>
                            <div class="mb-3">
                                <label class="form-label">Configuración de Preview</label>
                                
                                <!-- Mostrar metadatos actuales si existen -->
                                <?php if ($hasExistingMetadata): ?>
                                <div class="current-metadata mb-3">
                                    <h6><i class="fas fa-info-circle"></i> Metadatos Actuales:</h6>
                                    <?php if (!empty($redirect['metatags']['title'])): ?>
                                        <p><strong>Título:</strong> <?= htmlspecialchars($redirect['metatags']['title']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($redirect['metatags']['description'])): ?>
                                        <p><strong>Descripción:</strong> <?= htmlspecialchars($redirect['metatags']['description']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($redirect['metatags']['image'])): ?>
                                        <p><strong>Imagen:</strong> <a href="<?= htmlspecialchars($redirect['metatags']['image']) ?>" target="_blank">Ver imagen</a></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <button type="button" class="btn <?= $hasExistingMetadata ? 'btn-warning' : 'btn-danger' ?>" id="metadataBtn">
                                        <i class="fas fa-cloud-download-alt"></i> 
                                        <?= $hasExistingMetadata ? 'Actualizar Metadatos' : 'Obtener Metadatos Automáticamente' ?>
                                    </button>
                                    <span id="metadataStatus" class="metadata-status"></span>
                                </div>
                                <small class="form-text text-muted">
                                    El botón buscará automáticamente el título, descripción e imagen del sitio destino para el preview en redes sociales.
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción (opcional)</label>
                                <textarea class="form-control" id="description" name="description" rows="2" 
                                          placeholder="Descripción del enlace"><?= htmlspecialchars($redirect['description'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Campos ocultos para metadatos -->
                            <?php if (ENABLE_PREVIEW): ?>
                            <input type="hidden" id="meta_title" name="meta_title" value="<?= htmlspecialchars($redirect['metatags']['title'] ?? '') ?>">
                            <input type="hidden" id="meta_description" name="meta_description" value="<?= htmlspecialchars($redirect['metatags']['description'] ?? '') ?>">
                            <input type="hidden" id="meta_image" name="meta_image" value="<?= htmlspecialchars($redirect['metatags']['image'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar metadatos automáticamente
        <?php if (ENABLE_PREVIEW): ?>
        document.getElementById('metadataBtn').addEventListener('click', function() {
            const url = document.getElementById('url').value;
            const btn = this;
            const statusDiv = document.getElementById('metadataStatus');
            
            if (!url) {
                statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Por favor, ingresa una URL primero</span>';
                return;
            }
            
            // Validar URL básica
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> La URL debe comenzar con http:// o https://</span>';
                return;
            }
            
            // Mostrar loading
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando metadatos...';
            btn.disabled = true;
            btn.className = 'btn btn-secondary';
            statusDiv.innerHTML = '<span class="text-info"><i class="fas fa-sync-alt"></i> Buscando metadatos en el sitio destino...</span>';
            
            // Hacer la solicitud
            fetch('?action=get_metadata&url=' + encodeURI(url))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error del servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Respuesta recibida:', data);
                    
                    if (data.status === 'error') {
                        btn.className = 'btn btn-danger';
                        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ' + (data.message || 'Error al obtener metadatos') + '</span>';
                    } else if (data.status === 'warning') {
                        btn.className = 'btn btn-warning';
                        statusDiv.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ' + (data.message || 'Metadatos limitados encontrados') + '</span>';
                        
                        // Guardar los metadatos encontrados
                        if (data.title) {
                            document.getElementById('meta_title').value = data.title;
                        }
                        if (data.description) {
                            document.getElementById('meta_description').value = data.description;
                        }
                        if (data.image) {
                            document.getElementById('meta_image').value = data.image;
                        }
                    } else if (data.status === 'success') {
                        btn.className = 'btn btn-success';
                        statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ' + (data.message || 'Metadatos obtenidos correctamente') + '</span>';
                        
                        // Guardar todos los metadatos encontrados
                        document.getElementById('meta_title').value = data.title || '';
                        document.getElementById('meta_description').value = data.description || '';
                        document.getElementById('meta_image').value = data.image || '';

                        // Si no hay descripción, sugerir usar el título
                        const descriptionField = document.getElementById('description');
                        if (!descriptionField.value && data.title) {
                            descriptionField.value = data.title;
                        }
                    } else {
                        btn.className = 'btn btn-danger';
                        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Respuesta inesperada del servidor</span>';
                    }
                })
                .catch(error => {
                    console.error('Error en fetch:', error);
                    btn.className = 'btn btn-danger';
                    statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Error de conexión: ' + error.message + '</span>';
                })
                .finally(() => {
                    // Restaurar botón
                    btn.disabled = false;
                });
        });

        // Resetear el botón cuando cambia la URL
        document.getElementById('url').addEventListener('input', function() {
            const btn = document.getElementById('metadataBtn');
            const statusDiv = document.getElementById('metadataStatus');
            const hasExistingMetadata = <?php echo $hasExistingMetadata ? 'true' : 'false'; ?>;
            
            btn.className = hasExistingMetadata ? 'btn btn-warning' : 'btn btn-danger';
            btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> ' + (hasExistingMetadata ? 'Actualizar Metadatos' : 'Obtener Metadatos Automáticamente');
            statusDiv.innerHTML = '';
        });

        // Validar slug en tiempo real
        document.getElementById('slug').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_-]/g, '');
            if (this.value.length > 20) {
                this.value = this.value.substring(0, 20);
            }
        });
        <?php endif; ?>

        // Validación del formulario
        document.getElementById('linkForm').addEventListener('submit', function(e) {
            const slug = document.getElementById('slug').value;
            const url = document.getElementById('url').value;
            
            if (!slug || !url) {
                e.preventDefault();
                alert('Por favor, completa todos los campos obligatorios');
                return;
            }
            
            if (!slug.match(/^[a-zA-Z0-9_-]{1,20}$/)) {
                e.preventDefault();
                alert('El slug debe contener solo letras, números, guiones y guiones bajos (máximo 20 caracteres)');
                return;
            }
            
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                e.preventDefault();
                alert('La URL debe comenzar con http:// o https://');
                return;
            }
        });
    </script>
</body>
</html>