<?php
// Configuración automática - Instalador
$secure_config_path = '/home2/uv0023/public_html/mi//secure_config/secure_config.php';

if (!file_exists($secure_config_path)) {
    die('Error: Archivo de configuración segura no encontrado en: ' . $secure_config_path);
}

$secure_config = include $secure_config_path;

define('APP_URL', 'https://mi.peu.net');
define('PANEL_URL', APP_URL . '/panel');
define('REDIRECT_404_URL', 'https://peu.net');
define('ENABLE_PREVIEW', true);
define('DATA_PATH', $secure_config['data_path']);
define('ERROR_PATH', $secure_config['error_path']);
define('SESSION_NAME', $secure_config['session_name']);
define('BACKUP_PATH', '/home2/uv0023/public_html/mi//jsonbackups/');
define('SECURE_CONFIG_PATH', $secure_config_path);
define('ADMIN_USER', $secure_config['admin_user']);

// Log para depuración
error_log('APP_URL: ' . APP_URL);
error_log('REDIRECT_404_URL: ' . REDIRECT_404_URL);
error_log('ENABLE_PREVIEW: ' . (ENABLE_PREVIEW ? 'true' : 'false'));
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
    $json = file_get_contents(DATA_PATH);
    if ($json === false) {
        error_log('Error: No se puede leer el contenido de ' . DATA_PATH);
        die('Error: No se puede leer data.json');
    }
    $data = json_decode($json, true);
    if ($data === null) {
        error_log('Error: JSON inválido en ' . DATA_PATH);
        die('Error: Formato JSON inválido');
    }
    return $data ?: ['redirects' => [], 'stats' => ['total_redirects' => 0, 'total_clicks' => 0]];
}

function saveData(array $data): bool {
    // Crear carpeta de backups si no existe
    if (!is_dir(BACKUP_PATH)) {
        if (!mkdir(BACKUP_PATH, 0755, true)) {
            error_log('Error: No se pudo crear la carpeta ' . BACKUP_PATH);
        }
        chmod(BACKUP_PATH, 0755);
    }
    
    // Crear backup antes de guardar
    if (file_exists(DATA_PATH)) {
        $backup = BACKUP_PATH . 'data.json.backup.' . date('YmdHis');
        if (!copy(DATA_PATH, $backup)) {
            error_log('Error: No se pudo crear backup en ' . $backup);
        }
        chmod($backup, 0644);
    }
    
    $result = file_put_contents(DATA_PATH, json_encode($data, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log('Error: No se pudo escribir en ' . DATA_PATH);
        die('Error: No se pudo guardar data.json');
    }
    chmod(DATA_PATH, 0644);
    return $result !== false;
}

function generateSlug(int $length = 6): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $slug = '';
    do {
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $data = loadData();
    } while (isset($data['redirects'][$slug]));
    return $slug;
}

function validateUrl(?string $url): ?string {
    if (empty($url)) return null;
    $url = filter_var($url, FILTER_VALIDATE_URL);
    return $url && strpos($url, 'javascript:') === false ? $url : null;
}

// Función para detectar bots de redes sociales
function isSocialBot() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $social_bots = [
        'facebookexternalhit',
        'Twitterbot', 
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'Slackbot',
        'Discordbot',
        'SkypeUriPreview'
    ];
    
    foreach ($social_bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return true;
        }
    }
    return false;
}
?>