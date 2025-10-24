<?php
// install.php - Instalador para URL Shortener

// Primero verificar si estamos eliminando el instalador
if (isset($_GET['delete_installer'])) {
    // Calcular la URL base para redirección
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $base_url = 'https://' . $_SERVER['HTTP_HOST'] . $script_path;
    $base_url = rtrim($base_url, '/');
    
    // Intentar eliminar el instalador
    if (file_exists('install.php')) {
        if (unlink('install.php')) {
            // Redirigir al panel después de eliminar
            header('Location: ' . $base_url . '/panel');
            exit;
        }
    }
    
    // Si no se pudo eliminar, redirigir de todos modos
    header('Location: ' . $base_url . '/panel');
    exit;
}

// Luego verificar si el sistema ya está instalado
if (file_exists('config.php')) {
    die('❌ El sistema ya está instalado. Borra install.php');
}

$errors = [];
$success = false;
$base_url = '';
$script_path = dirname($_SERVER['SCRIPT_NAME']);

// La carpeta segura ahora será FIJA dentro de la instalación
$secure_folder = 'secure_config';

// Detectar rutas automáticamente
$auto_detected_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'mi.dominio.com') . $script_path;
$auto_detected_url = rtrim($auto_detected_url, '/');
$auto_detected_base_path = $_SERVER['DOCUMENT_ROOT'] . $script_path;

if ($_POST) {
    // Validar y procesar instalación
    $domain = trim($_POST['domain'] ?? '');
    $base_path = trim($_POST['base_path'] ?? '');
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = trim($_POST['admin_pass'] ?? '');
    $force_https = isset($_POST['force_https']);
    
    // Limpiar y normalizar URLs
    $domain = rtrim($domain, '/');
    $base_url = $domain;
    
    // Si estamos en una subcarpeta, detectarla automáticamente
    if ($script_path !== '/' && strpos($domain, $script_path) === false) {
        $base_url = $domain . $script_path;
    }
    
    // Validaciones
    if (empty($domain)) $errors[] = "El dominio es obligatorio";
    if (empty($base_path)) $errors[] = "La ruta base es obligatoria";
    if (empty($admin_user)) $errors[] = "El usuario admin es obligatorio";
    if (empty($admin_pass)) $errors[] = "La contraseña es obligatoria";
    if (strlen($admin_pass) < 8) $errors[] = "La contraseña debe tener al menos 8 caracteres";
    
    if (!preg_match('/^https?:\/\//', $domain)) {
        $errors[] = "El dominio debe incluir http:// o https://";
    }
    
    if (!is_dir($base_path) || !is_writable($base_path)) {
        $errors[] = "La ruta base no existe o no tiene permisos de escritura: " . $base_path;
    }
    
    if (empty($errors)) {
        // Crear carpeta secure_config si no existe
        $secure_folder_path = $base_path . '/' . $secure_folder;
        if (!is_dir($secure_folder_path)) {
            if (!mkdir($secure_folder_path, 0755, true)) {
                $errors[] = "No se pudo crear la carpeta secure_config: " . $secure_folder_path;
            }
        }
        
        if (empty($errors)) {
            // Crear .htaccess para proteger la carpeta secure_config
            $htaccess_secure_content = "Order deny,allow\nDeny from all\n";
            if (!file_put_contents($secure_folder_path . '/.htaccess', $htaccess_secure_content)) {
                $errors[] = "No se pudo crear .htaccess en secure_config";
            }
            
            // Crear secure_config.php
            $secure_config = [
                'data_path' => $base_path . '/data.json',
                'error_path' => $base_path . '/error/',
                'session_name' => 'mi_url_shortener_' . bin2hex(random_bytes(8)),
                'password' => password_hash($admin_pass, PASSWORD_DEFAULT),
                'admin_user' => $admin_user,
                'base_url' => $base_url,
                'force_https' => $force_https
            ];
            
            $secure_config_content = "<?php\nreturn " . var_export($secure_config, true) . ";\n?>";
            
            $secure_config_file = $secure_folder_path . '/secure_config.php';
            if (file_put_contents($secure_config_file, $secure_config_content)) {
                chmod($secure_config_file, 0600);
                
                // Crear config.php principal
                $config_content = generateConfigContent($base_url, $base_path, $secure_config_file, $admin_user);
                
                if (file_put_contents('config.php', $config_content)) {
                    // Manejar data.json - preservar si existe
                    $data_file = $base_path . '/data.json';
                    $backup_path = $base_path . '/jsonbackups';
                    
                    // Crear carpeta de backups
                    if (!is_dir($backup_path)) {
                        if (!mkdir($backup_path, 0755, true)) {
                            $errors[] = "No se pudo crear la carpeta de backups: " . $backup_path;
                        } else {
                            chmod($backup_path, 0755);
                        }
                    }
                    
                    // Verificar si data.json existe
                    if (!file_exists($data_file)) {
                        // Crear data.json inicial
                        $initial_data = [
                            'redirects' => [],
                            'stats' => [
                                'total_redirects' => 0,
                                'total_clicks' => 0,
                                'created' => date('Y-m-d H:i:s')
                            ]
                        ];
                        
                        if (file_put_contents($data_file, json_encode($initial_data, JSON_PRETTY_PRINT))) {
                            chmod($data_file, 0644);
                        } else {
                            $errors[] = "No se pudo crear data.json en: " . $data_file;
                        }
                    } else {
                        // Data.json ya existe, verificar que sea válido
                        $json_content = file_get_contents($data_file);
                        $data = json_decode($json_content, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            // Hacer backup del archivo corrupto
                            $corrupt_backup = $backup_path . '/data.json.corrupt.' . date('YmdHis');
                            copy($data_file, $corrupt_backup);
                            
                            // Crear nuevo data.json
                            $initial_data = [
                                'redirects' => [],
                                'stats' => [
                                    'total_redirects' => 0,
                                    'total_clicks' => 0,
                                    'created' => date('Y-m-d H:i:s')
                                ]
                            ];
                            file_put_contents($data_file, json_encode($initial_data, JSON_PRETTY_PRINT));
                            chmod($data_file, 0644);
                        } else {
                            // Data.json es válido, preservarlo
                            chmod($data_file, 0644);
                        }
                    }
                    
                    // Generar .htaccess principal dinámicamente
                    $htaccess_content = generateHtaccess($force_https, $script_path);
                    if (file_put_contents('.htaccess', $htaccess_content)) {
                        $htaccess_success = true;
                    } else {
                        $htaccess_success = false;
                    }
                    
                    // Verificar instalación
                    $installation_ok = verifyInstallation($secure_config_file, $data_file);
                    
                    if ($installation_ok && empty($errors)) {
                        $success = true;
                    } else {
                        $errors[] = "La instalación se completó pero hay errores de verificación.";
                    }
                } else {
                    $errors[] = "No se pudo crear config.php";
                }
            } else {
                $errors[] = "No se pudo crear secure_config.php en: " . $secure_folder_path;
            }
        }
    }
}

// Función para generar contenido de config.php
function generateConfigContent($base_url, $base_path, $secure_config_file, $admin_user) {
    return "<?php
// Configuración automática - Instalador
\$secure_config_path = '$secure_config_file';

if (!file_exists(\$secure_config_path)) {
    die('Error: Archivo de configuración segura no encontrado en: ' . \$secure_config_path);
}

\$secure_config = include \$secure_config_path;

define('APP_URL', '$base_url');
define('PANEL_URL', APP_URL . '/panel');
define('DATA_PATH', \$secure_config['data_path']);
define('ERROR_PATH', \$secure_config['error_path']);
define('SESSION_NAME', \$secure_config['session_name']);
define('BACKUP_PATH', '$base_path/jsonbackups/');
define('SECURE_CONFIG_PATH', \$secure_config_path);
define('ADMIN_USER', \$secure_config['admin_user']);

// Log para depuración
error_log('APP_URL: ' . APP_URL);
error_log('DATA_PATH: ' . DATA_PATH);
error_log('SECURE_CONFIG_PATH: ' . SECURE_CONFIG_PATH);

// Iniciar sesión
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funciones helper
function loadData(): array {
    if (!file_exists(DATA_PATH)) {
        error_log('Error: No se puede acceder a ' . DATA_PATH);
        die('Error: data.json no encontrado en: ' . DATA_PATH);
    }
    \$json = file_get_contents(DATA_PATH);
    if (\$json === false) {
        error_log('Error: No se puede leer el contenido de ' . DATA_PATH);
        die('Error: No se puede leer data.json');
    }
    \$data = json_decode(\$json, true);
    if (\$data === null) {
        error_log('Error: JSON inválido en ' . DATA_PATH);
        die('Error: Formato JSON inválido');
    }
    return \$data ?: ['redirects' => [], 'stats' => ['total_redirects' => 0, 'total_clicks' => 0]];
}

function saveData(array \$data): bool {
    // Crear carpeta de backups si no existe
    if (!is_dir(BACKUP_PATH)) {
        if (!mkdir(BACKUP_PATH, 0755, true)) {
            error_log('Error: No se pudo crear la carpeta ' . BACKUP_PATH);
        }
        chmod(BACKUP_PATH, 0755);
    }
    
    // Crear backup antes de guardar
    if (file_exists(DATA_PATH)) {
        \$backup = BACKUP_PATH . 'data.json.backup.' . date('YmdHis');
        if (!copy(DATA_PATH, \$backup)) {
            error_log('Error: No se pudo crear backup en ' . \$backup);
        }
        chmod(\$backup, 0644);
    }
    
    \$result = file_put_contents(DATA_PATH, json_encode(\$data, JSON_PRETTY_PRINT));
    if (\$result === false) {
        error_log('Error: No se pudo escribir en ' . DATA_PATH);
        die('Error: No se pudo guardar data.json');
    }
    chmod(DATA_PATH, 0644);
    return \$result !== false;
}

function generateSlug(int \$length = 6): string {
    \$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    \$slug = '';
    do {
        \$slug = '';
        for (\$i = 0; \$i < \$length; \$i++) {
            \$slug .= \$chars[random_int(0, strlen(\$chars) - 1)];
        }
        \$data = loadData();
    } while (isset(\$data['redirects'][\$slug]));
    return \$slug;
}

function validateUrl(?string \$url): ?string {
    if (empty(\$url)) return null;
    \$url = filter_var(\$url, FILTER_VALIDATE_URL);
    return \$url && strpos(\$url, 'javascript:') === false ? \$url : null;
}
?>";
}

// Función para verificar la instalación
function verifyInstallation($secure_config_path, $data_json_path) {
    $checks = [];
    
    $checks['secure_config'] = file_exists($secure_config_path);
    $checks['secure_config_readable'] = $checks['secure_config'] ? is_readable($secure_config_path) : false;
    $checks['data_json'] = file_exists($data_json_path);
    $checks['data_json_readable'] = $checks['data_json'] ? is_readable($data_json_path) : false;
    $checks['config_php'] = file_exists('config.php');
    $checks['config_php_readable'] = $checks['config_php'] ? is_readable('config.php') : false;
    
    error_log('Verificación de instalación: ' . print_r($checks, true));
    
    return !in_array(false, $checks, true);
}

// Función para generar .htaccess dinámico BASADO EN EL ORIGINAL
function generateHtaccess($force_https, $script_path) {
    // Calcular la ruta base para redirección de errores
    $error_redirect_path = $script_path;
    if ($error_redirect_path === '/') {
        $error_redirect_path = '';
    }
    
    $htaccess = "# php -- BEGIN cPanel-generated handler, do not edit\n";
    $htaccess .= "# Set the \"ea-php74\" package as the default \"PHP\" programming language.\n";
    $htaccess .= "<IfModule mime_module>\n";
    $htaccess .= "  AddHandler application/x-httpd-ea-php74 .php .php7 .phtml\n";
    $htaccess .= "</IfModule>\n";
    $htaccess .= "# php -- END cPanel-generated handler, do not edit\n\n";
    
    $htaccess .= "# Habilitar mod_rewrite\n";
    $htaccess .= "<IfModule mod_rewrite.c>\n";
    $htaccess .= "  RewriteEngine On\n";
    $htaccess .= "  RewriteBase /\n";
    $htaccess .= "  \n";
    
    if ($force_https) {
        $htaccess .= "  # Forzar HTTPS\n";
        $htaccess .= "  RewriteCond %{HTTPS} off\n";
        $htaccess .= "  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
        $htaccess .= "  \n";
    }
    
    $htaccess .= "  # Excluir carpetas del sistema y archivos existentes\n";
    $htaccess .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "  RewriteCond %{REQUEST_URI} !^/panel/\n";
    $htaccess .= "  RewriteCond %{REQUEST_URI} !^/error/\n";
    $htaccess .= "  RewriteRule ^(.*)$ index.php [L,QSA]\n";
    $htaccess .= "  \n";
    
    $htaccess .= "  # Redirigir 404s a la página de error personalizada\n";
    $htaccess .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "  RewriteCond %{REQUEST_URI} ^/panel/\n";
    $htaccess .= "  RewriteRule ^(.*)$ {$error_redirect_path}/error/index.html [L,R=302]\n";
    $htaccess .= "</IfModule>\n";

    return $htaccess;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - URL Shortener</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .install-container { max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .requirements { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .url-preview { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="install-container">
        <h1 class="text-center mb-4">🚀 Instalador URL Shortener</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <h4>✅ Instalación Completa</h4>
                <p><strong>Usuario:</strong> <?= htmlspecialchars($_POST['admin_user']) ?></p>
                <p><strong>URL Base:</strong> <?= htmlspecialchars($base_url) ?></p>
                <p><strong>Panel de control:</strong> <a href="<?= htmlspecialchars($base_url) ?>/panel" target="_blank"><?= htmlspecialchars($base_url) ?>/panel</a></p>
                
                <div class="alert alert-warning mt-3">
                    <h5>⚠️ IMPORTANTE</h5>
                    <p>Por seguridad, debes borrar este archivo de instalación.</p>
                    <div class="text-center mt-3">
                        <a href="?delete_installer=1" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar el instalador? Esta acción no se puede deshacer.')">
                            🗑️ Eliminar install.php
                        </a>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="<?= htmlspecialchars($base_url) ?>/panel" class="btn btn-success btn-lg">Ir al Panel de Control</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Requisitos del sistema -->
            <div class="requirements">
                <h5>📋 Requisitos del Sistema</h5>
                <ul class="mb-0">
                    <li>PHP 7.4+ <?= version_compare(PHP_VERSION, '7.4.0', '>=') ? '✅' : '❌' ?></li>
                    <li>JSON habilitado <?= extension_loaded('json') ? '✅' : '❌' ?></li>
                    <li>Permisos de escritura <?= is_writable('.') ? '✅' : '❌' ?></li>
                    <li>Session support <?= function_exists('session_start') ? '✅' : '❌' ?></li>
                </ul>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5>❌ Errores:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dominio Base *</label>
                        <input type="url" class="form-control" name="domain" value="<?= htmlspecialchars($_POST['domain'] ?? $auto_detected_url) ?>" required placeholder="https://mi.dominio.com">
                        <small class="form-text text-muted">URL completa con http:// o https://</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ruta Base *</label>
                        <input type="text" class="form-control" name="base_path" value="<?= htmlspecialchars($_POST['base_path'] ?? $auto_detected_base_path) ?>" required>
                        <small class="form-text text-muted">Ruta absoluta donde se guardarán los datos</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usuario Administrador *</label>
                        <input type="text" class="form-control" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="admin_pass" required minlength="8">
                        <small class="form-text text-muted">Mínimo 8 caracteres</small>
                    </div>
                </div>

                <!-- Opción forzar HTTPS -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="force_https" name="force_https" <?= (isset($_POST['force_https']) || !isset($_POST)) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="force_https">Forzar HTTPS (redirección automática)</label>
                    <small class="form-text text-muted d-block">Redirige automáticamente todas las peticiones HTTP a HTTPS</small>
                </div>

                <!-- Preview de URLs -->
                <div class="url-preview">
                    <h6>🔍 URLs que se generarán:</h6>
                    <p><strong>URL Base:</strong> <code id="url-preview-base"><?= htmlspecialchars($auto_detected_url) ?></code></p>
                    <p><strong>Panel de control:</strong> <code id="url-preview-panel"><?= htmlspecialchars($auto_detected_url) ?>/panel</code></p>
                    <p><strong>Ejemplo de enlace:</strong> <code id="url-preview-link"><?= htmlspecialchars($auto_detected_url) ?>/abc123</code></p>
                </div>

                <div class="alert alert-info">
                    <h6>💡 Características incluidas:</h6>
                    <ul class="mb-0">
                        <li><strong>Configuración segura integrada:</strong> Carpeta protegida con .htaccess</li>
                        <li><strong>Backups automáticos:</strong> Se crea un backup cada vez que se modifica data.json</li>
                        <li><strong>Preservación de datos:</strong> Si existe data.json previo, se preserva y valida</li>
                        <li><strong>Interfaz moderna:</strong> Panel de control responsive y fácil de usar</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">Instalar Sistema</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Actualizar preview en tiempo real
        document.querySelector('input[name="domain"]').addEventListener('input', function() {
            const baseUrl = this.value.replace(/\/+$/, '');
            document.getElementById('url-preview-base').textContent = baseUrl;
            document.getElementById('url-preview-panel').textContent = baseUrl + '/panel';
            document.getElementById('url-preview-link').textContent = baseUrl + '/abc123';
        });
    </script>
</body>
</html>