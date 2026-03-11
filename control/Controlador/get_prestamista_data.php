<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    // Obtener datos del prestamista actual
    $query = "SELECT e.idEmpleado, e.nombreEmpleado as name, te.nombrePuesto as position 
              FROM empleado e
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              WHERE e.idEmpleado = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $prestamista = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($prestamista) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $prestamista
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Prestamista no encontrado']);
    }
} catch (PDOException $e) {
    error_log("Error en get_prestamista_data: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
