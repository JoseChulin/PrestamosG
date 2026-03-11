<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 3) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Datos inválidos']));
}

try {
    $db = Db::conectar();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // 1. Actualizar tabla empleado
    $query = "UPDATE empleado SET 
              nombreEmpleado = :nombre,
              idTipoEmpleado = :puesto,
              telefono = :telefono
              WHERE idEmpleado = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $data['nombre']);
    $stmt->bindParam(':puesto', $data['puesto'], PDO::PARAM_INT);
    $stmt->bindParam(':telefono', $data['telefono']);
    $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // 2. Actualizar tabla loginempleados (correo y contraseña)
    $query = "UPDATE loginempleados SET 
              correo = :email";
    
    // Solo agregar contraseña si se proporcionó
    if (!empty($data['password'])) {
        $query .= ", contraseña = :password";
    }
    
    $query .= " WHERE idEmpleado = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data['email']);
    if (!empty($data['password'])) {
        $stmt->bindParam(':password', $data['password']);
    }
    $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Empleado actualizado correctamente']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error al actualizar empleado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar empleado']);
}
?>
