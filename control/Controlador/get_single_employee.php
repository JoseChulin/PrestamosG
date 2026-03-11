<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 3) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID inválido']));
}

try {
    $db = Db::conectar();
    
    // Consulta para obtener datos del empleado y su login
    $query = "SELECT e.*, te.nombrePuesto, l.correo as email_login 
              FROM empleado e
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              JOIN loginempleados l ON e.idEmpleado = l.idEmpleado
              WHERE e.idEmpleado = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        // Unificar los datos de ambas tablas
        $employeeData = [
            'idEmpleado' => $employee['idEmpleado'],
            'nombreEmpleado' => $employee['nombreEmpleado'],
            'nombrePuesto' => $employee['nombrePuesto'],
            'idTipoEmpleado' => $employee['idTipoEmpleado'],
            'telefono' => $employee['telefono'],
            'correo' => $employee['email_login'], // Usamos el correo de loginempleados
            'fechaRegistro' => $employee['fechaRegistro']
        ];
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $employeeData
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Empleado no encontrado']);
    }
} catch (PDOException $e) {
    error_log("Error al obtener empleado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener datos']);
}
?>
