<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 2) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// Leer el input JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['idPago']) || !is_numeric($data['idPago'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID de pago inválido']));
}

try {
    $db = Db::conectar();
    
    // Registrar el pago
    $query = "UPDATE calendariopagos 
              SET estado = 1, montoPagado = montoPago, fechaRegistro = NOW() 
              WHERE idPago = :idPago";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':idPago', $data['idPago'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Verificar si se actualizó correctamente
    if ($stmt->rowCount() > 0) {
        // Registrar la transacción en la tabla pagos
        $queryPago = "INSERT INTO pagos (idPago, montoPago, fechaPago, referencia, estado)
                      SELECT idPago, montoPago, NOW(), CONCAT('PAGO-', idPago), 1
                      FROM calendariopagos
                      WHERE idPago = :idPago";
        
        $stmtPago = $db->prepare($queryPago);
        $stmtPago->bindParam(':idPago', $data['idPago'], PDO::PARAM_INT);
        $stmtPago->execute();
        
        echo json_encode(['success' => true, 'message' => 'Pago registrado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el pago especificado']);
    }
} catch (PDOException $e) {
    error_log("Error en register_payment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
