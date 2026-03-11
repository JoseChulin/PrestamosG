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
    if (!isset($_GET['transactionId'])) {
        throw new Exception('ID de transacción no proporcionado');
    }
    
    $transactionId = (int)$_GET['transactionId'];
    $userId = (int)$_SESSION['idUsuario'];
    
    // Obtener conexión
    $db = Db::conectar();
    
    // Obtener detalles del pago
    $stmt = $db->prepare("
        SELECT p.*, cp.idPrestamo, cp.numeroCouta, cp.fechaVencimiento, cp.montoPago as montoOriginal
        FROM pagos p
        JOIN calendariopagos cp ON p.idPago = cp.idPago
        JOIN prestamos pr ON cp.idPrestamo = pr.idPrestamo
        WHERE p.idTransaccion = ? AND pr.idUsuario = ?
    ");
    $stmt->execute([$transactionId, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception('Pago no encontrado o no autorizado');
    }
    
    // Obtener información del préstamo
    $stmt = $db->prepare("
        SELECT p.*, pp.nombrePlan, u.nombreCliente
        FROM prestamos p
        JOIN planesprestamos pp ON p.idPlan = pp.idPlan
        JOIN usuarios u ON p.idUsuario = u.idUsuario
        WHERE p.idPrestamo = ?
    ");
    $stmt->execute([$payment['idPrestamo']]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener información del voucher si existe
    $stmt = $db->prepare("
        SELECT * FROM voucherpago
        WHERE idPago = ?
    ");
    $stmt->execute([$payment['idPago']]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'payment' => $payment,
        'loan' => $loan,
        'voucher' => $voucher ?: null,
        'receipt' => [
            'transactionId' => $payment['idTransaccion'],
            'reference' => $payment['referencia'],
            'amount' => $payment['montoPago'],
            'date' => $payment['fechaPago'],
            'paymentNumber' => $payment['numeroCouta'],
            'dueDate' => $payment['fechaVencimiento'],
            'originalAmount' => $payment['montoOriginal'],
            'clientName' => $loan['nombreCliente'],
            'loanId' => $loan['idPrestamo'],
            'planName' => $loan['nombrePlan']
        ]
    ];
    
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error en get_payment_receipt: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener el comprobante de pago: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

