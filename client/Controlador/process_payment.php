<?php
require_once '../Modelo/conexion.php';
session_start();

header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['idUsuario'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Validar datos recibidos
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['loanId'], $data['paymentId'], $data['amount'])) {
        throw new Exception('Datos incompletos para procesar el pago');
    }

    $loanId = (int)$data['loanId'];
    $paymentId = (int)$data['paymentId'];
    $amount = (float)$data['amount'];
    $paymentMethod = $data['paymentMethod'] ?? 'No especificado';
    $notes = $data['notes'] ?? '';
    $userId = (int)$_SESSION['idUsuario'];

    // Obtener conexión
    $db = Db::conectar();
    
    // Verificar que el préstamo pertenece al usuario
    $stmt = $db->prepare("SELECT * FROM prestamos WHERE idPrestamo = ? AND idUsuario = ?");
    $stmt->execute([$loanId, $userId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        throw new Exception('Préstamo no encontrado o no autorizado');
    }
    
    // Obtener detalles del pago actual
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
    
    // Verificar monto mínimo
    if ($amount < $payment['montoPago']) {
        throw new Exception('El monto no puede ser menor al monto requerido');
    }
    
    // Verificar si es el último pago
    $stmt = $db->prepare("SELECT COUNT(*) as remaining FROM calendariopagos 
                         WHERE idPrestamo = ? AND estado = 0");
    $stmt->execute([$loanId]);
    $remainingResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $remainingPayments = $remainingResult['remaining'];
    $isLastPayment = ($remainingPayments == 1);
    
    // Si es el último pago, no permitir pagar más del monto requerido
    if ($isLastPayment && $amount > $payment['montoPago']) {
        throw new Exception('Este es el último pago. No puede pagar más del monto requerido');
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // 1. Actualizar estado del pago actual en calendariopagos
    $stmt = $db->prepare("UPDATE calendariopagos SET estado = 1, montoPagado = ? WHERE idPago = ?");
    $stmt->execute([$amount, $paymentId]);
    
    // 2. Calcular excedente si lo hay
    $excess = $amount - $payment['montoPago'];
    
    // 3. Si hay excedente y no es el último pago, aplicarlo al siguiente pago
    if ($excess > 0 && !$isLastPayment) {
        // Obtener el siguiente pago pendiente
        $stmt = $db->prepare("SELECT * FROM calendariopagos 
                             WHERE idPrestamo = ? AND estado = 0 
                             ORDER BY numeroCouta ASC LIMIT 1");
        $stmt->execute([$loanId]);
        $nextPayment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nextPayment) {
            // Si el excedente cubre completamente el siguiente pago
            if ($excess >= $nextPayment['montoPago']) {
                // Marcar el siguiente pago como pagado
                $stmt = $db->prepare("UPDATE calendariopagos 
                                     SET estado = 1, montoPagado = ? 
                                     WHERE idPago = ?");
                $stmt->execute([$nextPayment['montoPago'], $nextPayment['idPago']]);
                
                // Registrar el pago automático del siguiente pago
                $nextTransactionId = getNextTransactionId($db);
                $nextReference = 'PAGO' . strtoupper(uniqid());
                
                $stmt = $db->prepare("INSERT INTO pagos (idTransaccion, idPago, montoPago, fechaPago, referencia, estado) 
                                     VALUES (?, ?, ?, CURDATE(), ?, 1)");
                $stmt->execute([$nextTransactionId, $nextPayment['idPago'], $nextPayment['montoPago'], $nextReference . '-AUTO']);
            }
        }
    }
    
    // 4. Verificar si todos los pagos están completados
    $stmt = $db->prepare("SELECT COUNT(*) as pendientes 
                         FROM calendariopagos 
                         WHERE idPrestamo = ? AND estado = 0");
    $stmt->execute([$loanId]);
    $result = $stmt->fetch();
    $pendingPayments = $result['pendientes'];
    
    // 5. Si no hay pagos pendientes, actualizar estado del préstamo a finalizado (4)
    if ($pendingPayments == 0) {
        $stmt = $db->prepare("UPDATE prestamos SET estado = 4 WHERE idPrestamo = ?");
        $stmt->execute([$loanId]);
    }
    
    // 6. Obtener el siguiente ID de transacción
    $transactionId = getNextTransactionId($db);
    
    // 7. Generar referencia única
    $reference = 'PAGO' . strtoupper(uniqid());
    
    // 8. Insertar nuevo registro en pagos
    $stmt = $db->prepare("INSERT INTO pagos (idTransaccion, idPago, montoPago, fechaPago, referencia, estado) 
                         VALUES (?, ?, ?, CURDATE(), ?, 1)");
    $stmt->execute([$transactionId, $paymentId, $amount, $reference]);
    
    // 9. Generar voucher de pago
    $voucherId = generateVoucher($db, $paymentId, $reference);
    
    // Confirmar transacción
    $db->commit();
    
    // Obtener información del préstamo para la respuesta
    $stmt = $db->prepare("SELECT p.*, u.nombreCliente 
                         FROM prestamos p
                         JOIN usuarios u ON p.idUsuario = u.idUsuario
                         WHERE p.idPrestamo = ?");
    $stmt->execute([$loanId]);
    $loanInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Respuesta
    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado correctamente' . ($pendingPayments == 0 ? ' (Préstamo finalizado)' : ''),
        'payment' => [
            'transactionId' => $transactionId,
            'reference' => $reference,
            'amount' => $amount,
            'date' => date('Y-m-d'),
            'loanInfo' => $loanInfo,
            'isLastPayment' => ($pendingPayments == 0),
            'paymentMethod' => $paymentMethod,
            'notes' => $notes,
            'voucherId' => $voucherId
        ]
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en process_payment: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el pago en la base de datos'
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Función para obtener el siguiente ID de transacción
function getNextTransactionId($db) {
    $stmt = $db->query("SELECT MAX(idTransaccion) as max_id FROM pagos");
    $result = $stmt->fetch();
    return $result['max_id'] ? $result['max_id'] + 1 : 1;
}

// Función para generar voucher
function generateVoucher($db, $paymentId, $reference) {
    // Generar URL del voucher
    $voucherUrl = '/vouchers/' . $reference . '.pdf';
    
    // Obtener el siguiente ID de voucher
    $stmt = $db->query("SELECT MAX(idVoucher) as max_id FROM voucherpago");
    $result = $stmt->fetch();
    $voucherId = $result['max_id'] ? $result['max_id'] + 1 : 1;
    
    // Insertar registro de voucher
    $stmt = $db->prepare("INSERT INTO voucherpago (idVoucher, idPago, urlDocumento, fechaRegistro) 
                         VALUES (?, ?, ?, CURDATE())");
    $stmt->execute([$voucherId, $paymentId, $voucherUrl]);
    
    return $voucherId;
}
?>


