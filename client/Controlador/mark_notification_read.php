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
    logError("Usuario no autenticado en mark_notification_read", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
logError("Datos recibidos en mark_notification_read", $input);

if (!$input) {
    logError("Datos JSON inválidos");
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$notificationId = $input['notificationId'] ?? null;

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'ID de notificación requerido']);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Marcando notificación como leída", [
        'userId' => $userId,
        'notificationId' => $notificationId
    ]);
    
    // Marcar notificación como leída (solo si pertenece al usuario)
    $stmt = $db->prepare("
        UPDATE notificaciones 
        SET estado = 1 
        WHERE idNotificacion = ? AND idUsuario = ?
    ");
    
    $result = $stmt->execute([$notificationId, $userId]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error al marcar notificación como leída", $errorInfo);
        throw new Exception('Error al actualizar notificación: ' . implode(', ', $errorInfo));
    }
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Notificación no encontrada o no pertenece al usuario'
        ]);
        exit;
    }
    
    logError("Notificación marcada como leída exitosamente", [
        'notificationId' => $notificationId,
        'rowsAffected' => $rowsAffected
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificación marcada como leída'
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en mark_notification_read", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en mark_notification_read", [
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
