<?php
require_once '../Modelo/conexion.php';
session_start();

header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['idUsuario'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Obtener parámetros
    if (!isset($_GET['paymentId']) || !isset($_GET['loanId'])) {
        throw new Exception('Parámetros incompletos');
    }
    
    $paymentId = (int)$_GET['paymentId'];
    $loanId = (int)$_GET['loanId'];
    $userId = (int)$_SESSION['idUsuario'];
    
    // Obtener conexión
    $db = Db::conectar();
    
    // Verificar que el préstamo pertenece al usuario
    $stmt = $db->prepare("SELECT p.*, pp.nombrePlan 
                         FROM prestamos p
                         JOIN planesprestamos pp ON p.idPlan = pp.idPlan
                         JOIN usuarios u ON p.idUsuario = u.idUsuario
                         WHERE p.idPrestamo = ? AND p.idUsuario = ?");
    $stmt->execute([$loanId, $userId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        throw new Exception('Préstamo no encontrado o no autorizado');
    }
    
    // Obtener detalles del pago
    $stmt = $db->prepare("SELECT * FROM calendariopagos WHERE idPago = ? AND idPrestamo = ?");
    $stmt->execute([$paymentId, $loanId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception('Pago no encontrado');
    }
    
    // Verificar si el pago está pendiente
    if ($payment['estado'] != 0) {
        throw new Exception('Este pago ya ha sido procesado');
    }
    
    // Obtener información del cliente
    $stmt = $db->prepare("SELECT nombreCliente FROM usuarios WHERE idUsuario = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Añadir nombre del cliente al préstamo
    $loan['nombreCliente'] = $user['nombreCliente'];
    
    // Contar pagos restantes
    $stmt = $db->prepare("SELECT COUNT(*) as remaining FROM calendariopagos 
                         WHERE idPrestamo = ? AND estado = 0");
    $stmt->execute([$loanId]);
    $remainingResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $remainingPayments = $remainingResult['remaining'];
    
    // Verificar si es el último pago
    $isLastPayment = ($remainingPayments == 1);
    
    // Devolver respuesta
    echo json_encode([
        'success' => true,
        'loan' => $loan,
        'payment' => $payment,
        'remainingPayments' => $remainingPayments,
        'isLastPayment' => $isLastPayment
    ]);

} catch (PDOException $e) {
    error_log("Error en get_payment_details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener detalles del pago'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
