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
    
    // Obtener datos del empleado
    $query = "SELECT e.*, l.contraseña, te.nombrePuesto 
              FROM empleado e
              JOIN loginempleados l ON e.idEmpleado = l.idEmpleado
              JOIN tipoempleado te ON e.idTipoEmpleado = te.idTipo
              WHERE l.correo = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $password === $user['contraseña']) {
        $_SESSION['user_id'] = $user['idEmpleado'];
        $_SESSION['user_type'] = $user['idTipoEmpleado'];
        $_SESSION['logged_in'] = true;
        $_SESSION['user_name'] = $user['nombreEmpleado'];
        $_SESSION['user_role'] = $user['nombrePuesto'];
        $_SESSION['user_email'] = $user['correo'];
        $_SESSION['user_phone'] = $user['telefono'];
        
        $redirectPage = '';
        switch ($user['idTipoEmpleado']) {
            case 1: $redirectPage = 'Prestamista.html'; break;
            case 2: $redirectPage = 'Gerente.html'; break;
            case 3: $redirectPage = 'Encargado.html'; break;
            default:
                echo json_encode(['success' => false, 'message' => 'Tipo de empleado no válido']);
                exit;
        }
        
        echo json_encode(['success' => true, 'redirect' => $redirectPage]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    }
} catch (PDOException $e) {
    error_log("Error en el login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor. Por favor intente más tarde.']);
}
?>
