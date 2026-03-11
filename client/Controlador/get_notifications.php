<?php
session_start();
require_once '../Modelo/conexion.php';

header('Content-Type: application/json');

// Función para logging de errores
function logError($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data) {
        $logMessage .= " - Data: " . json_encode($data);
    }
    error_log($logMessage);
}

// Verificar autenticación
if (!isset($_SESSION['idUsuario']) || empty($_SESSION['idUsuario'])) {
    logError("Usuario no autenticado en get_notifications", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Obteniendo notificaciones", ['userId' => $userId]);
    
    // Obtener notificaciones excluyendo dudas (tipo 7) y respuestas (tipo 8)
    $stmt = $db->prepare("
        SELECT 
            n.idNotificacion,
            n.mensaje,
            n.idTipoNotificacion,
            n.estado,
            n.fechaEnvio,
            tn.descripcion as tipoDescripcion,
            e.nombreEmpleado
        FROM notificaciones n
        INNER JOIN tiposnotificacion tn ON n.idTipoNotificacion = tn.idTipoNotificacion
        LEFT JOIN empleado e ON n.idEmpleado = e.idEmpleado
        WHERE n.idUsuario = ? 
        AND n.idTipoNotificacion NOT IN (7, 8)
        ORDER BY n.fechaEnvio DESC
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logError("Notificaciones encontradas", ['count' => count($notifications)]);
    
    // Formatear notificaciones para el frontend
    $formattedNotifications = array_map(function($notification) {
        return [
            'idNotificacion' => (int)$notification['idNotificacion'],
            'mensaje' => $notification['mensaje'],
            'idTipoNotificacion' => (int)$notification['idTipoNotificacion'],
            'tipoDescripcion' => $notification['tipoDescripcion'],
            'estado' => (int)$notification['estado'],
            'fechaEnvio' => $notification['fechaEnvio'],
            'nombreEmpleado' => $notification['nombreEmpleado'],
            'isRead' => (int)$notification['estado'] === 1,
            'isUnread' => (int)$notification['estado'] === 0
        ];
    }, $notifications);
    
    // Contar notificaciones no leídas
    $stmt = $db->prepare("
        SELECT COUNT(*) as unreadCount
        FROM notificaciones 
        WHERE idUsuario = ? 
        AND estado = 0 
        AND idTipoNotificacion NOT IN (7, 8)
    ");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'total' => count($formattedNotifications),
        'unreadCount' => (int)$unreadCount
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en get_notifications", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener notificaciones: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en get_notifications", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
