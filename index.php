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
error_log('Datos cargados: ' . print_r($data, true));
$redirect = $data['redirects'][$slug] ?? null;

if ($redirect) {
    // Incrementar contador
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

// 404 - Mostrar página de error con cuenta regresiva A LA URL CONFIGURADA
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
?>