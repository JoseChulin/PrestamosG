<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 3) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['name'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Parámetro de búsqueda requerido']));
}

$searchTerm = '%' . $_GET['name'] . '%';

try {
    $db = Db::conectar();
    
    $query = "SELECT e.idEmpleado, e.nombreEmpleado, te.nombrePuesto, e.telefono, e.correo, e.fechaRegistro, e.estado
              FROM empleado e
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              WHERE e.nombreEmpleado LIKE :searchTerm
              OR e.idEmpleado = :exactId
              ORDER BY e.nombreEmpleado";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':searchTerm', $searchTerm);
    
    // Si el término de búsqueda es numérico, buscar también por ID exacto
    $exactId = is_numeric($_GET['name']) ? (int)$_GET['name'] : -1;
    $stmt->bindParam(':exactId', $exactId, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $employees
    ]);
} catch (PDOException $e) {
    error_log("Error en búsqueda de empleados: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
