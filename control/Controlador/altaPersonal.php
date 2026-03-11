<?php
require_once '../Modelo/conexion.php';

// Debug: Verificar si el script se está ejecutando
error_log("Script altaPersonal.php accedido");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Recibir datos
$data = [
    'idEmpleado' => $_POST['idEmpleado'] ?? null,
    'idTipoEmpleado' => $_POST['tipoEmpleado'] ?? null,
    'nombre' => $_POST['nombre'] ?? null,
    'telefono' => $_POST['telefono'] ?? null,
    'correo' => $_POST['correo'] ?? null,
    'password' => $_POST['password'] ?? null,
    'idLoginEmpleado' => $_POST['idLoginEmpleado'] ?? null
];

// Validaciones básicas
$errors = [];

if (empty($data['idEmpleado']) || !is_numeric($data['idEmpleado'])) {
    $errors[] = 'ID Empleado inválido (debe ser número)';
}

if (empty($data['idTipoEmpleado']) || !in_array($data['idTipoEmpleado'], [1, 2, 3])) {
    $errors[] = 'Tipo de empleado inválido (1, 2 o 3)';
}

if (empty($data['nombre']) || strlen($data['nombre']) > 50) {
    $errors[] = 'Nombre inválido (máx 50 caracteres)';
}

if (empty($data['telefono']) || !preg_match('/^[0-9]{10}$/', $data['telefono'])) {
    $errors[] = 'Teléfono inválido (deben ser 10 dígitos)';
}

if (empty($data['correo']) || !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Correo electrónico inválido';
}

if (empty($data['password']) || strlen($data['password']) < 8) {
    $errors[] = 'La contraseña debe tener al menos 8 caracteres';
}

if (empty($data['idLoginEmpleado']) || !is_numeric($data['idLoginEmpleado'])) {
    $errors[] = 'ID Login inválido (debe ser número)';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode('; ', $errors)
    ]);
    exit;
}

try {
    $db = Db::conectar();
    
    // Verificar si el ID de empleado ya existe
    $stmtCheck = $db->prepare("SELECT idEmpleado FROM empleado WHERE idEmpleado = ?");
    $stmtCheck->execute([$data['idEmpleado']]);
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El ID de empleado ya existe']);
        exit;
    }

    // Insertar empleado
    $stmtEmp = $db->prepare("INSERT INTO empleado 
        (idEmpleado, idTipoEmpleado, nombreEmpleado, telefono, correo) 
        VALUES (?, ?, ?, ?, ?)");
    
    $successEmp = $stmtEmp->execute([
        $data['idEmpleado'],
        $data['idTipoEmpleado'],
        $data['nombre'],
        $data['telefono'],
        $data['correo']
    ]);

    if (!$successEmp) {
        throw new Exception("Error al insertar empleado");
    }

    // Insertar login (CONTRASEÑA EN TEXTO PLANO)
    $stmtLogin = $db->prepare("INSERT INTO loginempleados 
        (idLoginEmpleado, idEmpleado, correo, contraseña) 
        VALUES (?, ?, ?, ?)");
    
    $successLogin = $stmtLogin->execute([
        $data['idLoginEmpleado'],
        $data['idEmpleado'],
        substr($data['correo'], 0, 30),
        $data['password']  // Se guarda la contraseña en texto plano
    ]);

    if (!$successLogin) {
        throw new Exception("Error al insertar credenciales");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Empleado registrado exitosamente'
    ]);

} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error General: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
