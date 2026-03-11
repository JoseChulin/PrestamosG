<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['idPrestamo'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID de préstamo no proporcionado']));
}

try {
    $db = Db::conectar();
    $idPrestamo = $_GET['idPrestamo'];
    $idPrestamista = $_SESSION['user_id'];
    
    // Verificar que el préstamo pertenece al prestamista
    $query = "SELECT 1 FROM prestamos WHERE idPrestamo = ? AND idEmpleado = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo, $idPrestamista]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'No tienes permiso para ver este calendario']));
    }
    
    // Obtener calendario de pagos
    $query = "SELECT numeroCouta, fechaVencimiento, montoPago, estado 
              FROM calendariopagos 
              WHERE idPrestamo = ?
              ORDER BY numeroCouta";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo]);
    
    $calendario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $calendario
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_calendario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
