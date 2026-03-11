<?php
// Index principal para el lado administrativo
// Incluir modelos necesarios
require_once('Modelo/conexion.php');
// Aquí incluir otros modelos si es necesario

// Redirigir al login si no está autenticado
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: Vista/Login.html');
    exit;
} else {
    // Redirigir según el rol
    if ($_SESSION['rol'] == 'gerente') {
        header('Location: Vista/Gerente.html');
    } elseif ($_SESSION['rol'] == 'prestamista') {
        header('Location: Vista/Prestamista.html');
    } elseif ($_SESSION['rol'] == 'encargado') {
        header('Location: Vista/Encargado.html');
    } else {
        header('Location: Vista/Index.html');
    }
    exit;
}
?>