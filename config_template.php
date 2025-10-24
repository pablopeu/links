<?php
// Funciones helper
function loadData(): array {
    if (!file_exists(DATA_PATH)) {
        error_log('Error: No se puede acceder a ' . DATA_PATH);
        die('Error: data.json no encontrado');
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
    $backup = BACKUP_PATH . 'data.json.backup.' . date('YmdHis');
    if (!copy(DATA_PATH, $backup)) {
        error_log('Error: No se pudo crear backup en ' . $backup);
    }
    $result = file_put_contents(DATA_PATH, json_encode($data, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log('Error: No se pudo escribir en ' . DATA_PATH);
        die('Error: No se pudo guardar data.json');
    }
    chmod(DATA_PATH, 0644);
    chmod($backup, 0644);
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
    $url = filter_var($url, FILTER_VALIDATE_URL);
    return $url && strpos($url, 'javascript:') === false ? $url : null;
}
?>