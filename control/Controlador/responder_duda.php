<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acceso no autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['idNotificacion']) || empty($data['respuesta'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Datos incompletos']));
}

try {
    $db = Db::conectar();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // 1. Obtener información completa de la duda
    $stmt = $db->prepare("SELECT n.*, u.nombreCliente 
                         FROM notificaciones n
                         JOIN usuarios u ON n.idUsuario = u.idUsuario
                         WHERE n.idNotificacion = ? AND n.idEmpleado = ?");
    $stmt->execute([$data['idNotificacion'], $_SESSION['user_id']]);
    $duda = $stmt->fetch();
    
    if (!$duda) {
        $db->rollBack();
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Duda no encontrada']));
    }
    
    // 2. Marcar como respondida
    $stmt = $db->prepare("UPDATE notificaciones SET estado = 1 WHERE idNotificacion = ?");
    $stmt->execute([$data['idNotificacion']]);
    
    // 3. Crear notificación de respuesta 
    $stmt = $db->prepare("INSERT INTO notificaciones 
                         (idUsuario, idEmpleado, mensaje, idTipoNotificacion, estado, fechaEnvio)
                         VALUES (?, ?, ?, 8, 0, NOW())");
    $respuestaCompleta = "Respuesta a tu duda del " . $duda['fechaEnvio'] . ":\n" . $data['respuesta'];
    $stmt->execute([
        $duda['idUsuario'],
        $_SESSION['user_id'],
        $respuestaCompleta
    ]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Respuesta enviada']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error en responder_duda: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
