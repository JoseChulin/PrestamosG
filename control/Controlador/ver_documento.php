<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit('Acceso no autorizado');
}

if (!isset($_GET['idPrestamo']) || !isset($_GET['tipo'])) {
    http_response_code(400);
    exit('Parámetros inválidos');
}

try {
    $db = Db::conectar();
    $idPrestamo = $_GET['idPrestamo'];
    $tipo = $_GET['tipo'];
    
    // Verificar que el préstamo pertenece al prestamista
    $query = "SELECT p.idPrestamo 
              FROM prestamos p
              WHERE p.idPrestamo = ? AND p.idEmpleado = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        exit('No tienes permiso para ver este documento');
    }
    
    // Obtener el documento
    $query = "SELECT d.Documento, td.descripcion as tipo
              FROM documentos d
              JOIN tipodocumento td ON d.idTipoDocumento = td.idTipoDocumento
              WHERE d.idPrestamo = ? AND td.descripcion = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo, $tipo]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$documento) {
        http_response_code(404);
        exit('Documento no encontrado');
    }
    
    // Mostrar el documento (solo si son pdf)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $tipo . '.pdf"');
    echo $documento['Documento'];
    
} catch (PDOException $e) {
    error_log("Error en ver_documento: " . $e->getMessage());
    http_response_code(500);
    exit('Error al cargar el documento');
}
