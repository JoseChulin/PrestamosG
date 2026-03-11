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
    logError("Usuario no autenticado en get_user_profile", $_SESSION);
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
    
    logError("Obteniendo perfil del usuario", ['userId' => $userId]);
    
    // Obtener datos del usuario de ambas tablas
    $stmt = $db->prepare("
        SELECT 
            u.idUsuario,
            u.nombreCliente,
            u.apellidoP,
            u.apellidoM,
            u.telefono,
            u.fechaRegistro,
            lu.nombreUsuario,
            lu.correo
        FROM usuarios u
        INNER JOIN loginusuarios lu ON u.idUsuario = lu.idUsuario
        WHERE u.idUsuario = ?
    ");
    
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        logError("Perfil no encontrado", ['userId' => $userId]);
        echo json_encode([
            'success' => false,
            'message' => 'Perfil de usuario no encontrado'
        ]);
        exit;
    }
    
    logError("Perfil encontrado", $profile);
    
    // Formatear datos para el frontend
    $profileData = [
        'idUsuario' => (int)$profile['idUsuario'],
        'nombreCliente' => $profile['nombreCliente'],
        'apellidoP' => $profile['apellidoP'],
        'apellidoM' => $profile['apellidoM'],
        'telefono' => $profile['telefono'],
        'fechaRegistro' => $profile['fechaRegistro'],
        'nombreUsuario' => $profile['nombreUsuario'],
        'correo' => $profile['correo']
    ];
    
    echo json_encode([
        'success' => true,
        'profile' => $profileData,
        'message' => 'Perfil cargado correctamente'
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en get_user_profile", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener el perfil: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en get_user_profile", [
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
