<?php
session_start();
require_once '../Modelo/conexion.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['idUsuario']) || empty($_SESSION['idUsuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado',
        'debug' => [
            'session_exists' => isset($_SESSION['idUsuario']),
            'session_value' => $_SESSION['idUsuario'] ?? 'no_value',
            'all_session' => $_SESSION
        ]
    ]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$bank = trim($input['bank'] ?? '');
$accountNumber = trim($input['accountNumber'] ?? '');
$accountHolder = trim($input['accountHolder'] ?? '');

// Validaciones
if (empty($bank) || empty($accountNumber)) {
    echo json_encode(['success' => false, 'message' => 'Banco y número de cuenta son requeridos']);
    exit;
}

// Validar número de cuenta (solo números, 16-20 dígitos)
$cleanAccountNumber = preg_replace('/\D/', '', $accountNumber);
if (strlen($cleanAccountNumber) < 16 || strlen($cleanAccountNumber) > 20) {
    echo json_encode(['success' => false, 'message' => 'El número de cuenta debe tener entre 16 y 20 dígitos']);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    // Verificar si ya existe una cuenta bancaria para este usuario
    $stmt = $db->prepare("SELECT idCuenta FROM cuentabancaria WHERE idUsuario = ?");
    $stmt->execute([$userId]);
    $existingAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAccount) {
        // Actualizar cuenta existente
        $stmt = $db->prepare("
            UPDATE cuentabancaria 
            SET banco = ?, numeroCuenta = ?, fechaRegistro = NOW()
            WHERE idUsuario = ?
        ");
        $result = $stmt->execute([$bank, $cleanAccountNumber, $userId]);
        
        if (!$result) {
            throw new Exception('Error al actualizar la cuenta bancaria');
        }
        
        $message = 'Cuenta bancaria actualizada correctamente';
    } else {
        // Obtener el siguiente ID disponible manualmente
        $stmt = $db->query("SELECT COALESCE(MAX(idCuenta), 0) + 1 AS nextId FROM cuentabancaria");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result['nextId'];
        
        // Asegurar que el ID mínimo sea 1
        $nextId = max(1, $nextId);
        
        // Crear nueva cuenta con ID manual
        $stmt = $db->prepare("
            INSERT INTO cuentabancaria (idCuenta, idUsuario, banco, numeroCuenta, fechaRegistro)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$nextId, $userId, $bank, $cleanAccountNumber]);
        
        if (!$result) {
            throw new Exception('Error al crear la cuenta bancaria');
        }
        
        $message = 'Cuenta bancaria creada correctamente con ID: ' . $nextId;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    error_log("Error en save_account (PDO): " . $e->getMessage());
    
    // Verificar si es error de clave duplicada
    if ($e->getCode() == 23000) {
        // Intentar con el siguiente ID disponible
        try {
            $stmt = $db->query("SELECT COALESCE(MAX(idCuenta), 0) + 1 AS nextId FROM cuentabancaria");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextId = $result['nextId'];
            
            $stmt = $db->prepare("
                INSERT INTO cuentabancaria (idCuenta, idUsuario, banco, numeroCuenta, fechaRegistro)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([$nextId, $userId, $bank, $cleanAccountNumber]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuenta bancaria creada correctamente con ID: ' . $nextId
                ]);
                exit;
            }
        } catch (Exception $retryError) {
            error_log("Error en retry: " . $retryError->getMessage());
        }
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en save_account: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
