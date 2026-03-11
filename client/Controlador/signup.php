<?php
require_once '../Modelo/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Método no permitido']));
}

// Obtener datos del formulario
$nombre = trim($_POST['nombre']);
$apellidoP = trim($_POST['apellidoP']);
$apellidoM = trim($_POST['apellidoM']);
$username = trim($_POST['username']);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = trim($_POST['phone']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// Validaciones básicas
$camposRequeridos = [
    'nombre' => 'Nombre(s)',
    'apellidoP' => 'Apellido paterno',
    'apellidoM' => 'Apellido materno',
    'username' => 'Nombre de usuario',
    'email' => 'Correo electrónico',
    'phone' => 'Teléfono',
    'password' => 'Contraseña'
];

foreach ($camposRequeridos as $campo => $nombreCampo) {
    if (empty($$campo)) {
        exit(json_encode(['success' => false, 'message' => "El campo {$nombreCampo} es obligatorio"]));
    }
}

if ($password !== $confirm_password) {
    exit(json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']));
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    exit(json_encode(['success' => false, 'message' => 'El teléfono debe tener 10 dígitos']));
}

try {
    $db = Db::conectar();
    
    // Verificar correo y usuario existentes
    $checkEmail = $db->prepare("SELECT idUsuario FROM usuarios WHERE correo = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->rowCount() > 0) {
        exit(json_encode(['success' => false, 'message' => 'El correo ya está registrado']));
    }

    $checkUsername = $db->prepare("SELECT idUsuario FROM usuarios WHERE nombreUsuario = ?");
    $checkUsername->execute([$username]);
    if ($checkUsername->rowCount() > 0) {
        exit(json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe']));
    }

    // Obtener el próximo ID disponible para usuarios
    $nextIdQuery = $db->query("SELECT COALESCE(MAX(idUsuario), 0) + 1 AS nextId FROM usuarios");
    $nextId = $nextIdQuery->fetchColumn();

    // Obtener el próximo ID disponible para loginusuarios
    $nextLoginIdQuery = $db->query("SELECT COALESCE(MAX(idLoginUsuario), 0) + 1 AS nextId FROM loginusuarios");
    $nextLoginId = $nextLoginIdQuery->fetchColumn();

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Insertar en tabla usuarios
        $insertUser = $db->prepare("
            INSERT INTO usuarios (idUsuario, nombreCliente, nombreUsuario, correo, contraseña, apellidoP, apellidoM, telefono, fechaRegistro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        
        $insertUser->execute([
            $nextId,
            $nombre,
            $username,
            $email,
            $password,
            $apellidoP,
            $apellidoM,
            $phone
        ]);

        // Insertar en loginusuarios con ID explícito
        $insertLogin = $db->prepare("
            INSERT INTO loginusuarios (idLoginUsuario, idUsuario, nombreUsuario, correo, contraseña)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $insertLogin->execute([
            $nextLoginId,
            $nextId,
            $username,
            $email,
            $password
        ]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Registro exitoso']);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error en transacción: " . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Error al registrar el usuario: ' . $e->getMessage()]));
    }

} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Error en el servidor']));
}
?>
