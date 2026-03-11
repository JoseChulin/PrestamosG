<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 3) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    // con una vista en la base de datos
    $query = "SELECT * FROM vista_estadisticas_dashboard";
    $stmt = $db->query($query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'totalEmployees' => $stats['total_empleados'],
            'pendingComplaints' => $stats['quejas_pendientes'],
            'newEmployees' => $stats['nuevos_empleados'],
            'pendingEvaluations' => $stats['evaluaciones_pendientes']
        ]
    ]);
} catch (PDOException $e) {
    error_log("Error en get_dashboard_stats: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
