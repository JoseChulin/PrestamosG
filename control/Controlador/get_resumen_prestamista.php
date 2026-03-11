<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    $idPrestamista = $_SESSION['user_id'];
    
    // Préstamos activos
    $query = "SELECT COUNT(*) FROM prestamos WHERE idEmpleado = ? AND estado = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamista]);
    $prestamosActivos = $stmt->fetchColumn();
    
    // Pagos hoy
    $hoy = date('Y-m-d');
    $query = "SELECT COUNT(*) FROM calendariopagos 
              WHERE fechaVencimiento = ? AND estado = 0 
              AND idPrestamo IN (SELECT idPrestamo FROM prestamos WHERE idEmpleado = ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$hoy, $idPrestamista]);
    $pagosHoy = $stmt->fetchColumn();
    
    // Morosidad
    $query = "SELECT COUNT(*) FROM calendariopagos 
              WHERE fechaVencimiento < ? AND estado = 0 
              AND idPrestamo IN (SELECT idPrestamo FROM prestamos WHERE idEmpleado = ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$hoy, $idPrestamista]);
    $morosidad = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'prestamosActivos' => $prestamosActivos,
            'pagosHoy' => $pagosHoy,
            'morosidad' => $morosidad
        ]
    ]);
} catch (PDOException $e) {
    error_log("Error en get_resumen_prestamista: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
