<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 3) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    $query = "SELECT e.idEmpleado, e.nombreEmpleado, te.nombrePuesto, e.telefono, l.correo, e.fechaRegistro
              FROM empleado e
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              JOIN loginempleados l ON e.idEmpleado = l.idEmpleado
              ORDER BY e.nombreEmpleado";
    
    $stmt = $db->query($query);
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $employees
    ]);
} catch (PDOException $e) {
    error_log("Error al obtener lista de empleados: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener datos']);
}
?>
