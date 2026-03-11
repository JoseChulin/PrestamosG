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
    logError("Usuario no autenticado en send_message", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
logError("Datos recibidos en send_message", $input);

if (!$input) {
    logError("Datos JSON inválidos");
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$message = trim($input['message'] ?? '');
$lenderId = $input['lenderId'] ?? null;

// Validaciones
if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
    exit;
}

if (strlen($message) > 200) {
    echo json_encode(['success' => false, 'message' => 'El mensaje no puede exceder 200 caracteres']);
    exit;
}

if (!$lenderId) {
    echo json_encode(['success' => false, 'message' => 'ID del prestamista requerido']);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Enviando mensaje", [
        'userId' => $userId,
        'lenderId' => $lenderId,
        'messageLength' => strlen($message)
    ]);
    
    // Verificar que el prestamista existe
    $stmt = $db->prepare("SELECT idEmpleado FROM empleado WHERE idEmpleado = ? AND idTipoEmpleado = 1");
    $stmt->execute([$lenderId]);
    $lenderExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lenderExists) {
        echo json_encode(['success' => false, 'message' => 'Prestamista no válido']);
        exit;
    }
    
    // Obtener el siguiente ID disponible para notificaciones
    $stmt = $db->query("SELECT COALESCE(MAX(idNotificacion), 0) + 1 AS nextId FROM notificaciones");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNotificationId = max(1, $result['nextId']);
    
    logError("Siguiente ID de notificación", ['nextId' => $nextNotificationId]);
    
    // Insertar mensaje como notificación tipo "Dudas" (ID 7)
    $stmt = $db->prepare("
        INSERT INTO notificaciones (
            idNotificacion, idUsuario, idEmpleado, mensaje, 
            idTipoNotificacion, estado, fechaEnvio
        ) VALUES (?, ?, ?, ?, 7, 1, NOW())
    ");
    
    $result = $stmt->execute([
        $nextNotificationId,
        $userId,
        $lenderId,
        $message
    ]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error en la inserción del mensaje", $errorInfo);
        throw new Exception('Error al enviar el mensaje: ' . implode(', ', $errorInfo));
    }
    
    logError("Mensaje enviado exitosamente", ['notificationId' => $nextNotificationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente',
        'notificationId' => $nextNotificationId
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en send_message", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Verificar si es error de clave duplicada
    if ($e->getCode() == 23000) {
        try {
            // Intentar con el siguiente ID disponible
            $stmt = $db->query("SELECT COALESCE(MAX(idNotificacion), 0) + 1 AS nextId FROM notificaciones");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextNotificationId = max(1, $result['nextId']);
            
            logError("Reintentando con nuevo ID", ['newId' => $nextNotificationId]);
            
            $stmt = $db->prepare("
                INSERT INTO notificaciones (
                    idNotificacion, idUsuario, idEmpleado, mensaje, 
                    idTipoNotificacion, estado, fechaEnvio
                ) VALUES (?, ?, ?, ?, 7, 0, NOW())
            ");
            
            $result = $stmt->execute([
                $nextNotificationId,
                $userId,
                $lenderId,
                $message
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje enviado correctamente',
                    'notificationId' => $nextNotificationId
                ]);
                exit;
            }
        } catch (Exception $retryError) {
            logError("Error en retry", $retryError->getMessage());
        }
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'debug' => [
            'code' => $e->getCode(),
            'sqlState' => $e->errorInfo[0] ?? 'unknown'
        ]
    ]);
} catch (Exception $e) {
    logError("Error general en send_message", [
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
