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
    logError("Usuario no autenticado en mark_all_notifications_read", $_SESSION);
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
    
    logError("Marcando todas las notificaciones como leídas", ['userId' => $userId]);
    
    // Marcar todas las notificaciones como leídas (excluyendo dudas y respuestas)
    $stmt = $db->prepare("
        UPDATE notificaciones 
        SET estado = 1 
        WHERE idUsuario = ? 
        AND estado = 0 
        AND idTipoNotificacion NOT IN (7, 8)
    ");
    
    $result = $stmt->execute([$userId]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error al marcar todas las notificaciones como leídas", $errorInfo);
        throw new Exception('Error al actualizar notificaciones: ' . implode(', ', $errorInfo));
    }
    
    $rowsAffected = $stmt->rowCount();
    
    logError("Notificaciones marcadas como leídas", [
        'userId' => $userId,
        'rowsAffected' => $rowsAffected
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Todas las notificaciones marcadas como leídas',
        'updatedCount' => $rowsAffected
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en mark_all_notifications_read", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en mark_all_notifications_read", [
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
