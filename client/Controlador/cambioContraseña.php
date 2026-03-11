<?php
require_once '../Modelo/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y validar datos
$username = trim($_POST['username'] ?? '');
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($username) || empty($email) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

try {
    $db = Db::conectar();
    
    // Verificar credenciales
    $stmt = $db->prepare("
        SELECT u.idUsuario 
        FROM usuarios u
        JOIN loginusuarios l ON u.idUsuario = l.idUsuario
        WHERE u.nombreUsuario = ? AND l.correo = ?
    ");
    $stmt->execute([$username, $email]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario o correo electrónico incorrectos']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userId = $user['idUsuario'];
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Actualizar contraseña en usuarios
        $updateUser = $db->prepare("UPDATE usuarios SET contraseña = ? WHERE idUsuario = ?");
        $updateUser->execute([$new_password, $userId]);
        
        // Actualizar contraseña en loginusuarios
        $updateLogin = $db->prepare("UPDATE loginusuarios SET contraseña = ? WHERE idUsuario = ?");
        $updateLogin->execute([$new_password, $userId]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error al actualizar contraseña: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
    }
    
} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
