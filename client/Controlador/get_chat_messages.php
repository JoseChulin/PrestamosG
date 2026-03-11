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
    logError("Usuario no autenticado en get_chat_messages", $_SESSION);
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
    
    logError("Obteniendo mensajes de chat", ['userId' => $userId]);
    
    // Obtener mensajes de dudas (tipo 7) y respuestas (tipo 8) del usuario
    $stmt = $db->prepare("
        SELECT 
            n.idNotificacion,
            n.mensaje,
            n.idTipoNotificacion,
            n.fechaEnvio,
            n.estado,
            e.nombreEmpleado
        FROM notificaciones n
        LEFT JOIN empleado e ON n.idEmpleado = e.idEmpleado
        WHERE n.idUsuario = ? 
        AND (n.idTipoNotificacion = 7 OR n.idTipoNotificacion = 8)
        ORDER BY n.fechaEnvio ASC
    ");
    
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logError("Mensajes encontrados", ['count' => count($messages)]);
    
    // Formatear mensajes para el frontend
    $formattedMessages = array_map(function($msg) {
        return [
            'idNotificacion' => (int)$msg['idNotificacion'],
            'mensaje' => $msg['mensaje'],
            'idTipoNotificacion' => (int)$msg['idTipoNotificacion'],
            'fechaEnvio' => $msg['fechaEnvio'],
            'estado' => (int)$msg['estado'],
            'nombreEmpleado' => $msg['nombreEmpleado'],
            'isUserMessage' => (int)$msg['idTipoNotificacion'] === 7,
            'isLenderMessage' => (int)$msg['idTipoNotificacion'] === 8
        ];
    }, $messages);
    
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'total' => count($formattedMessages)
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en get_chat_messages", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener mensajes: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en get_chat_messages", [
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
