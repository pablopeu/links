<?php
require_once '../config.php';

error_log('Iniciando logout.php');
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
error_log('Sesión cerrada, redirigiendo a index.php');
header('Location: index.php');
exit;
?>