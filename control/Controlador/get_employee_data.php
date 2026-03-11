<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    $query = "SELECT e.idEmpleado, e.nombreEmpleado, te.nombrePuesto, e.correo, e.telefono, e.fechaRegistro 
              FROM empleado e
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              WHERE e.idEmpleado = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $employee['idEmpleado'],
                'name' => $employee['nombreEmpleado'],
                'position' => $employee['nombrePuesto'],
                'email' => $employee['correo'],
                'phone' => $employee['telefono'],
                'registrationDate' => $employee['fechaRegistro']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Empleado no encontrado']);
    }
} catch (PDOException $e) {
    error_log("Error en get_employee_data: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
