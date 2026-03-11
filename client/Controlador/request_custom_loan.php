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
logError("Datos recibidos en request_custom_loan", $input);

if (!$input) {
    logError("Datos JSON inválidos");
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$monto = $input['monto'] ?? null;
$tipo = $input['tipo'] ?? null;
$meses = $input['meses'] ?? null;

// Validaciones
if (!$monto || !$tipo || !$meses) {
    logError("Campos faltantes", ['monto' => $monto, 'tipo' => $tipo, 'meses' => $meses]);
    echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
    exit;
}

if ($monto < 1000 || $monto > 100000) {
    echo json_encode(['success' => false, 'message' => 'El monto debe estar entre $1,000 y $100,000']);
    exit;
}

if ($meses < 6 || $meses > 60) {
    echo json_encode(['success' => false, 'message' => 'El plazo debe estar entre 6 y 60 meses']);
    exit;
}

// Definir tasas de interés
$tasas = [
    'personal' => 12,
    'automovil' => 8,
    'hipotecario' => 6
];

if (!isset($tasas[$tipo])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de préstamo inválido']);
    exit;
}

$tasaInteres = $tasas[$tipo];

try {
    $db = Db::conectar();
    $userId = $_SESSION['idUsuario'];
    
    logError("Iniciando solicitud de préstamo personalizado", [
        'userId' => $userId,
        'monto' => $monto,
        'tipo' => $tipo,
        'meses' => $meses,
        'tasa' => $tasaInteres
    ]);
    
    // Verificar si ya tiene un préstamo activo
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM prestamos 
        WHERE idUsuario = ? AND (estado = 1 OR estado = 2)
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasActiveLoan = $result['count'] > 0;
    
    if ($hasActiveLoan) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya tienes un préstamo activo. No puedes solicitar otro.'
        ]);
        exit;
    }
    
    // 1. Crear plan personalizado primero
    $stmt = $db->query("SELECT COALESCE(MAX(idPlan), 0) + 1 AS nextId FROM planesprestamos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextPlanId = max(1, $result['nextId']);
    
    $nombrePlan = "Plan Personalizado " . ucfirst($tipo);
    $descripcion = "Plan personalizado para préstamo " . $tipo . " de $" . number_format($monto, 2) . " a " . $meses . " meses";
    
    logError("Creando plan personalizado", [
        'planId' => $nextPlanId,
        'nombre' => $nombrePlan
    ]);
    
    $stmt = $db->prepare("
        INSERT INTO planesprestamos (
            idPlan, nombrePlan, tasaInteres, duracion, 
            monto, descripcion, fechaRegistro
        ) VALUES (?, ?, ?, ?, ?, ?, CURDATE())
    ");
    
    $result = $stmt->execute([
        $nextPlanId,
        $nombrePlan,
        $tasaInteres,
        $meses,
        $monto,
        $descripcion
    ]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error creando plan personalizado", $errorInfo);
        throw new Exception('Error al crear el plan personalizado: ' . implode(', ', $errorInfo));
    }
    
    // 2. Crear el préstamo
    $stmt = $db->query("SELECT COALESCE(MAX(idPrestamo), 0) + 1 AS nextId FROM prestamos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextPrestamoId = max(1, $result['nextId']);
    
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
    } else {
        logError("No se encontraron prestamistas, usando empleado por defecto");
        $prestamista = 2; // Según tu BD, el empleado ID 2 es prestamista
    }
    
    logError("Creando préstamo", [
        'prestamoId' => $nextPrestamoId,
        'prestamista' => $prestamista
    ]);
    
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
        $nextPlanId,
        $prestamista,
        $monto,
        $tasaInteres,
        $meses
    ]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        logError("Error creando préstamo", $errorInfo);
        throw new Exception('Error al crear el préstamo: ' . implode(', ', $errorInfo));
    }
    
    logError("Préstamo personalizado creado exitosamente", [
        'loanId' => $nextPrestamoId,
        'planId' => $nextPlanId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud de préstamo personalizado enviada correctamente',
        'loanId' => $nextPrestamoId,
        'planId' => $nextPlanId,
        'planName' => $nombrePlan,
        'amount' => $monto,
        'interestRate' => $tasaInteres,
        'term' => $meses
    ]);
    
} catch (PDOException $e) {
    logError("Error PDO en request_custom_loan", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Verificar si es error de clave duplicada
    if ($e->getCode() == 23000) {
        try {
            // Intentar con nuevos IDs
            $stmt = $db->query("SELECT COALESCE(MAX(idPlan), 0) + 1 AS nextPlanId, (SELECT COALESCE(MAX(idPrestamo), 0) + 1 FROM prestamos) AS nextPrestamoId FROM planesprestamos");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextPlanId = max(1, $result['nextPlanId']);
            $nextPrestamoId = max(1, $result['nextPrestamoId']);
            
            logError("Reintentando con nuevos IDs", [
                'planId' => $nextPlanId,
                'prestamoId' => $nextPrestamoId
            ]);
            
            // Crear plan
            $stmt = $db->prepare("
                INSERT INTO planesprestamos (
                    idPlan, nombrePlan, tasaInteres, duracion, 
                    monto, descripcion, fechaRegistro
                ) VALUES (?, ?, ?, ?, ?, ?, CURDATE())
            ");
            
            $stmt->execute([
                $nextPlanId,
                $nombrePlan,
                $tasaInteres,
                $meses,
                $monto,
                $descripcion
            ]);
            
            // Crear préstamo
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
                $nextPlanId,
                $prestamista,
                $monto,
                $tasaInteres,
                $meses
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitud de préstamo personalizado enviada correctamente',
                    'loanId' => $nextPrestamoId,
                    'planId' => $nextPlanId
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
    logError("Error general en request_custom_loan", [
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
