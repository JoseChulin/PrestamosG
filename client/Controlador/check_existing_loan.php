<?php
session_start();
require_once '../Modelo/conexion.php';

header('Content-Type: application/json');

// Función para logging de errores
function logError($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data) {
        $logMessage .= " - Data: " . json_encode($data);
    }
    error_log($logMessage);
}

// Verificar autenticación
if (!isset($_SESSION['idUsuario']) || empty($_SESSION['idUsuario'])) {
    logError("Usuario no autenticado en check_existing_loan", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Verificando préstamos existentes", ['userId' => $userId]);
    
    // Verificar si el usuario tiene préstamos activos (estado 1 = aprobado, estado 2 = en proceso)
    $stmt = $db->prepare("
        SELECT idPrestamo, estado, montoSolicitado, fechaSolicitud 
        FROM prestamos 
        WHERE idUsuario = ? AND (estado = 1 OR estado = 2)
        ORDER BY fechaSolicitud DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $existingLoan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    logError("Resultado de verificación", [
        'hasLoan' => !empty($existingLoan),
        'loanData' => $existingLoan
    ]);
    
    if ($existingLoan) {
        echo json_encode([
            'success' => false,
            'hasActiveLoan' => true,
            'message' => 'Ya tienes un préstamo activo o en proceso. No puedes solicitar otro préstamo.',
            'loanDetails' => [
                'id' => $existingLoan['idPrestamo'],
                'amount' => $existingLoan['montoSolicitado'],
                'status' => $existingLoan['estado'],
                'date' => $existingLoan['fechaSolicitud']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasActiveLoan' => false,
            'message' => 'Puedes solicitar un préstamo'
        ]);
    }
    
} catch (PDOException $e) {
    logError("Error PDO en check_existing_loan", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar préstamos existentes: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en check_existing_loan", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
