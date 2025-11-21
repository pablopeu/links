<?php
// Este archivo es el index.php principal
error_log('Iniciando index.php');
require_once 'config.php';

error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);

// Obtener el slug correctamente considerando subcarpetas
$request_uri = $_SERVER['REQUEST_URI'];
$script_path = dirname($_SERVER['SCRIPT_NAME']);

// Remover el path del script si estamos en subcarpeta
if ($script_path !== '/' && strpos($request_uri, $script_path) === 0) {
    $request_uri = substr($request_uri, strlen($script_path));
}

$slug = trim($request_uri, '/');
error_log('Slug solicitado: ' . $slug);

error_log('Redirección 404 configurada: ' . REDIRECT_404_URL);

// Detectar si es un bot de redes sociales (para mostrar preview)
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_social_bot = false;
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
        $is_social_bot = true;
        error_log('Bot detectado: ' . $bot);
        break;
    }
}

// También permitir preview manual
$is_preview = isset($_GET['preview']);

if (empty($slug) || $slug === 'panel') {
    error_log('Redirigiendo a PANEL_URL: ' . PANEL_URL);
    header('Location: ' . PANEL_URL);
    exit;
}

// Si el slug contiene parámetros de query string, limpiarlo
if (strpos($slug, '?') !== false) {
    $slug = substr($slug, 0, strpos($slug, '?'));
}

error_log('Cargando datos...');
$data = loadData();
$redirect = $data['redirects'][$slug] ?? null;

if ($redirect) {
    // Si es un bot de redes sociales y el preview está habilitado, mostrar meta tags
    if (ENABLE_PREVIEW && ($is_social_bot || $is_preview)) {
        error_log('Mostrando preview para slug: ' . $slug);
        showSocialPreview($redirect, $slug);
        exit;
    }
    
    // Incrementar contador y redirigir normalmente
    $redirect['clicks'] = ($redirect['clicks'] ?? 0) + 1;
    $data['redirects'][$slug] = $redirect;
    $data['stats']['total_clicks']++;
    error_log('Guardando datos para slug: ' . $slug);
    
    if (saveData($data)) {
        error_log('Redirigiendo a: ' . $redirect['url']);
        header('Location: ' . $redirect['url'], true, 301);
        exit;
    } else {
        error_log('Error guardando data.json');
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error: No se pudo guardar data.json');
    }
}

// 404 - Mostrar página de error
error_log('404 - Slug no encontrado: ' . $slug);
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - No Encontrado - <?= htmlspecialchars(APP_URL) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-container { max-width: 600px; text-align: center; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="display-1 text-danger">404</h1>
        <h2>Enlace no encontrado</h2>
        <p>El enlace que buscas no existe o ha sido eliminado.</p>
        <p>Serás redirigido a <a href="<?= REDIRECT_404_URL ?>"><?= htmlspecialchars(parse_url(REDIRECT_404_URL, PHP_URL_HOST)) ?></a> en <span id="countdown">5</span> segundos.</p>
        <a href="<?= REDIRECT_404_URL ?>" class="btn btn-primary">Ir ahora</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = '<?= REDIRECT_404_URL ?>';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
exit;

// Función para mostrar preview en redes sociales
function showSocialPreview($redirect, $slug) {
    $metatags = $redirect['metatags'] ?? [];
    $url_destino = $redirect['url'];
    $titulo = $metatags['title'] ?? ($redirect['description'] ?? 'Enlace acortado');
    $descripcion = $metatags['description'] ?? ($redirect['description'] ?? 'Enlace acortado por ' . parse_url(APP_URL, PHP_URL_HOST));
    $imagen = $metatags['image'] ?? (APP_URL . '/preview-default.jpg');
    $url_corto = APP_URL . '/' . $slug;
    
    // Meta tags Open Graph
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html prefix="og: https://ogp.me/ns#">
<head>
    <title><?= htmlspecialchars($titulo) ?></title>
    <meta name="description" content="<?= htmlspecialchars($descripcion) ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($titulo) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descripcion) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($imagen) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($url_corto) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars(parse_url(APP_URL, PHP_URL_HOST)) ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($titulo) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($descripcion) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($imagen) ?>">
    
    <!-- Redirección para usuarios normales -->
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($url_destino) ?>">
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1><?= htmlspecialchars($titulo) ?></h1>
        <p><?= htmlspecialchars($descripcion) ?></p>
        <?php if ($imagen): ?>
            <img src="<?= htmlspecialchars($imagen) ?>" alt="Preview" style="max-width: 100%; height: auto; border-radius: 10px;">
        <?php endif; ?>
        <p style="margin-top: 20px;">
            <a href="<?= htmlspecialchars($url_destino) ?>" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                Continuar al enlace
            </a>
        </p>
        <p style="color: #666; font-size: 0.9em;">
            Enlace acortado por <?= htmlspecialchars(parse_url(APP_URL, PHP_URL_HOST)) ?>
        </p>
    </div>
</body>
</html>
    <?php
}
?>