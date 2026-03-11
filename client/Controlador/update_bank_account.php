<?php
require_once '../Modelo/conexion.php';
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['idUsuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    // Validar datos
    if (empty($data['bank']) || empty($data['accountNumber'])) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    if (!preg_match('/^\d{16,20}$/', $data['accountNumber'])) {
        throw new Exception('El número de cuenta debe tener entre 16 y 20 dígitos');
    }

    // Verificar si ya existe una cuenta
    $stmt = $db->prepare("SELECT idCuenta FROM cuentabancaria WHERE idUsuario = ?");
    $stmt->execute([$userId]);
    $existingAccount = $stmt->fetch();

    if ($existingAccount) {
        // Actualizar cuenta existente
        $stmt = $db->prepare("
            UPDATE cuentabancaria 
            SET banco = ?, numeroCuenta = ?, fechaRegistro = NOW()
            WHERE idCuenta = ?
        ");
        $stmt->execute([$data['bank'], $data['accountNumber'], $existingAccount['idCuenta']]);
    } else {
        // Obtener el próximo ID disponible
        $stmt = $db->query("SELECT COALESCE(MAX(idCuenta), 0) + 1 AS nextId FROM cuentabancaria");
        $nextId = $stmt->fetch(PDO::FETCH_ASSOC)['nextId'];
        
        // Asegurarnos de que el ID mínimo sea 1
        $nextId = max(1, $nextId);

        // Crear nueva cuenta
        $stmt = $db->prepare("
            INSERT INTO cuentabancaria 
            (idCuenta, idUsuario, banco, numeroCuenta, fechaRegistro)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nextId, $userId, $data['bank'], $data['accountNumber']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cuenta bancaria actualizada correctamente'
    ]);
    
} catch (PDOException $e) {
    error_log("Error en update_bank_account: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
