<?php
require_once '../Modelo/conexion.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Por favor complete todos los campos']);
    exit;
}

try {
    $db = Db::conectar();
    
    // Obtener datos del usuario
    $query = "SELECT u.*, l.contraseña 
              FROM usuarios u
              JOIN loginusuarios l ON u.idUsuario = l.idUsuario
              WHERE l.correo = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $password === $user['contraseña']) {
        // Establecer todas las variables de sesión necesarias
        $_SESSION['idUsuario'] = $user['idUsuario']; 
        $_SESSION['logged_in'] = true;
        $_SESSION['user_name'] = $user['nombreCliente'];
        $_SESSION['user_email'] = $user['correo'];
        $_SESSION['user_phone'] = $user['telefono'];
        $_SESSION['username'] = $user['nombreUsuario'];
        
        echo json_encode(['success' => true, 'redirect' => 'PanelUsuario.html']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    }
} catch (PDOException $e) {
    error_log("Error en el login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor. Por favor intente más tarde.']);
}
?>
