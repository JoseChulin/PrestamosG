<?php
// Index principal para el lado cliente
// Incluir modelos necesarios
require_once('Modelo/conexion.php');
require_once('Modelo/Usuario.php');
require_once('Modelo/CrudUsuario.php');
require_once('Modelo/Prestamo.php');
require_once('Modelo/CrudPrestamo.php');

// Redirigir al login si no está autenticado
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: Vista/Login.html');
    exit;
} else {
    header('Location: Vista/PanelUsuario.html');
    exit;
}
?>