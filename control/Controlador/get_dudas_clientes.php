<?php
require_once '../Modelo/conexion.php';

session_start();

// Verificación
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acceso no autorizado']));
}

try {
    $db = Db::conectar();
    
    $query = "SELECT n.idNotificacion, n.mensaje, n.fechaEnvio,
                     CONCAT(u.nombreCliente, ' ', u.apellidoP) as nombreCliente,
                     u.telefono
              FROM notificaciones n
              JOIN usuarios u ON n.idUsuario = u.idUsuario
              WHERE n.idTipoNotificacion = 7 
              AND n.estado = 0
              AND n.idEmpleado = ?
              ORDER BY n.fechaEnvio DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    
    $dudas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dudas as &$duda) {
        $duda['fechaFormateada'] = date('d/m/Y H:i', strtotime($duda['fechaEnvio']));
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $dudas,
        'count' => count($dudas)
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_dudas_clientes: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener dudas']);
}
?>
