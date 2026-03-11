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
    logError("Usuario no autenticado en update_user_profile", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
logError("Datos recibidos en update_user_profile", $input);

if (!$input) {
    logError("Datos JSON inválidos");
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

// Validar campos requeridos
$requiredFields = ['nombreCliente', 'apellidoP', 'nombreUsuario', 'correo'];
foreach ($requiredFields as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        echo json_encode([
            'success' => false,
            'message' => "El campo {$field} es requerido"
        ]);
        exit;
    }
}

// Validar email
if (!filter_var($input['correo'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'El formato del correo electrónico no es válido'
    ]);
    exit;
}

// Validar teléfono si se proporciona
if (!empty($input['telefono']) && !preg_match('/^\d{10}$/', preg_replace('/\D/', '', $input['telefono']))) {
    echo json_encode([
        'success' => false,
        'message' => 'El teléfono debe tener 10 dígitos'
    ]);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Iniciando actualización de perfil", [
        'userId' => $userId,
        'data' => $input
    ]);
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Verificar si el nombre de usuario ya existe (excluyendo el usuario actual)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM loginusuarios 
        WHERE nombreUsuario = ? AND idUsuario != ?
    ");
    $stmt->execute([$input['nombreUsuario'], $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'El nombre de usuario ya está en uso'
        ]);
        exit;
    }
    
    // Verificar si el correo ya existe (excluyendo el usuario actual)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM loginusuarios 
        WHERE correo = ? AND idUsuario != ?
    ");
    $stmt->execute([$input['correo'], $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'El correo electrónico ya está en uso'
        ]);
        exit;
    }
    
    // Si se está cambiando la contraseña, verificar la actual
    $updatePassword = false;
    if (!empty($input['newPassword'])) {
        if (empty($input['currentPassword'])) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Debes proporcionar tu contraseña actual para cambiarla'
            ]);
            exit;
        }
        
        // Verificar contraseña actual
        $stmt = $db->prepare("SELECT contraseña FROM loginusuarios WHERE idUsuario = ?");
        $stmt->execute([$userId]);
        $currentPasswordHash = $stmt->fetchColumn();
        
        if (!$currentPasswordHash || !password_verify($input['currentPassword'], $currentPasswordHash)) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ]);
            exit;
        }
        
        // Validar nueva contraseña
        if (strlen($input['newPassword']) < 8) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'La nueva contraseña debe tener al menos 8 caracteres'
            ]);
            exit;
        }
        
        $updatePassword = true;
    }
    
    // Actualizar tabla usuarios
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET nombreCliente = ?, apellidoP = ?, apellidoM = ?, telefono = ?
        WHERE idUsuario = ?
    ");
    
    $result = $stmt->execute([
        $input['nombreCliente'],
        $input['apellidoP'],
        $input['apellidoM'] ?? '',
        $input['telefono'] ?? '',
        $userId
    ]);
    
    if (!$result) {
        $db->rollBack();
        logError("Error actualizando tabla usuarios", $stmt->errorInfo());
        throw new Exception('Error al actualizar información personal');
    }
    
    // Actualizar tabla loginusuarios
    if ($updatePassword) {
        $hashedPassword = password_hash($input['newPassword'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            UPDATE loginusuarios 
            SET nombreUsuario = ?, correo = ?, contraseña = ?
            WHERE idUsuario = ?
        ");
        
        $result = $stmt->execute([
            $input['nombreUsuario'],
            $input['correo'],
            $hashedPassword,
            $userId
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE loginusuarios 
            SET nombreUsuario = ?, correo = ?
            WHERE idUsuario = ?
        ");
        
        $result = $stmt->execute([
            $input['nombreUsuario'],
            $input['correo'],
            $userId
        ]);
    }
    
    if (!$result) {
        $db->rollBack();
        logError("Error actualizando tabla loginusuarios", $stmt->errorInfo());
        throw new Exception('Error al actualizar información de cuenta');
    }
    
    // También actualizar tabla usuarios con correo y nombreUsuario para mantener consistencia
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET nombreUsuario = ?, correo = ?
        WHERE idUsuario = ?
    ");
    
    $stmt->execute([
        $input['nombreUsuario'],
        $input['correo'],
        $userId
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    logError("Perfil actualizado exitosamente", ['userId' => $userId]);
    
    $message = 'Perfil actualizado correctamente';
    if ($updatePassword) {
        $message .= ' (incluyendo contraseña)';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    logError("Error PDO en update_user_profile", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    logError("Error general en update_user_profile", [
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
