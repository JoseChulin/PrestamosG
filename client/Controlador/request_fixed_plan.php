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
    logError("Usuario no autenticado", $_SESSION);
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'No autenticado'
    ]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
logError("Datos recibidos en request_fixed_plan", $input);

if (!$input) {
    logError("Datos JSON inválidos");
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$planId = $input['planId'] ?? null;

if (!$planId) {
    logError("ID del plan no proporcionado");
    echo json_encode(['success' => false, 'message' => 'ID del plan requerido']);
    exit;
}

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Iniciando solicitud de plan fijo", [
        'userId' => $userId,
        'planId' => $planId
    ]);
    
    // Verificar si ya tiene un préstamo activo (estado 1 = aprobado, estado 2 = en proceso)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM prestamos 
        WHERE idUsuario = ? AND (estado = 1 OR estado = 2)
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasActiveLoan = $result['count'] > 0;
    
    logError("Verificación de préstamo activo", [
        'hasActiveLoan' => $hasActiveLoan,
        'count' => $result['count']
    ]);
    
    if ($hasActiveLoan) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya tienes un préstamo activo. No puedes solicitar otro.'
        ]);
        exit;
    }
    
    // Obtener datos del plan
    $stmt = $db->prepare("
        SELECT * FROM planesprestamos WHERE idPlan = ?
    ");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        logError("Plan no encontrado", ['planId' => $planId]);
        echo json_encode(['success' => false, 'message' => 'Plan no encontrado']);
        exit;
    }
    
    logError("Plan encontrado", $plan);
    
    // Obtener el siguiente ID disponible para préstamos
    $stmt = $db->query("SELECT COALESCE(MAX(idPrestamo), 0) + 1 AS nextId FROM prestamos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextPrestamoId = max(1, $result['nextId']);
    
    logError("Siguiente ID de préstamo", ['nextId' => $nextPrestamoId]);
    
    // Obtener prestamista al azar (empleados con idTipoEmpleado = 1)
    $stmt = $db->prepare("
        SELECT idEmpleado 
        FROM empleado 
        WHERE idTipoEmpleado = 1 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->execute();
    $lenderResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lenderResult) {
        $prestamista = $lenderResult['idEmpleado'];
        logError("Prestamista encontrado", ['prestamista' => $prestamista]);
    } else {
        // Si no hay empleados tipo 1, usar el empleado ID 2 que existe en tu BD
        logError("No se encontraron prestamistas, usando empleado por defecto");
        $prestamista = 2; // Según tu BD, el empleado ID 2 es prestamista
    }
    
    logError("Prestamista asignado", ['prestamista' => $prestamista]);
    
    // Crear el préstamo (usando los nombres de columnas correctos de tu BD)
    $stmt = $db->prepare("
        INSERT INTO prestamos (
            idPrestamo, idUsuario, idPlan, idEmpleado, 
            montoSolicitado, tasaInteres, plazoMeses, 
            fechaSolicitud, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 2)
    ");
    
    $insertData = [
        $nextPrestamoId,
        $userId,
        $planId,
        $prestamista,
        $plan['monto'],
        $plan['tasaInteres'],
        $plan['duracion']
    ];
    
    logError("Datos para insertar préstamo", $insertData);
    
    $result = $stmt->execute($insertData);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error en la inserción del préstamo", $errorInfo);
        throw new Exception('Error al crear el préstamo: ' . implode(', ', $errorInfo));
    }
    
    logError("Préstamo creado exitosamente", ['loanId' => $nextPrestamoId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud de préstamo enviada correctamente',
        'loanId' => $nextPrestamoId,
        'planName' => $plan['nombrePlan'],
        'amount' => $plan['monto']
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en request_fixed_plan", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Verificar si es error de clave duplicada
    if ($e->getCode() == 23000) {
        try {
            // Intentar con el siguiente ID disponible
            $stmt = $db->query("SELECT COALESCE(MAX(idPrestamo), 0) + 1 AS nextId FROM prestamos");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextPrestamoId = max(1, $result['nextId']);
            
            logError("Reintentando con nuevo ID", ['newId' => $nextPrestamoId]);
            
            $stmt = $db->prepare("
                INSERT INTO prestamos (
                    idPrestamo, idUsuario, idPlan, idEmpleado, 
                    montoSolicitado, tasaInteres, plazoMeses, 
                    fechaSolicitud, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 2)
            ");
            
            $result = $stmt->execute([
                $nextPrestamoId,
                $userId,
                $planId,
                $prestamista,
                $plan['monto'],
                $plan['tasaInteres'],
                $plan['duracion']
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitud de préstamo enviada correctamente',
                    'loanId' => $nextPrestamoId
                ]);
                exit;
            }
        } catch (Exception $retryError) {
            logError("Error en retry", $retryError->getMessage());
        }
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'debug' => [
            'code' => $e->getCode(),
            'sqlState' => $e->errorInfo[0] ?? 'unknown'
        ]
    ]);
} catch (Exception $e) {
    logError("Error general en request_fixed_plan", [
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
