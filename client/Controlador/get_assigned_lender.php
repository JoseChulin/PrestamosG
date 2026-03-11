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
    logError("Usuario no autenticado en get_assigned_lender", $_SESSION);
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
    
    logError("Obteniendo prestamista asignado", ['userId' => $userId]);
    
    // Primero verificar si el usuario tiene un préstamo activo o solicitud
    $stmt = $db->prepare("
        SELECT p.idEmpleado, e.nombreEmpleado, e.correo, e.telefono
        FROM prestamos p
        INNER JOIN empleado e ON p.idEmpleado = e.idEmpleado
        WHERE p.idUsuario = ? AND (p.estado = 1 OR p.estado = 2)
        ORDER BY p.fechaSolicitud DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $assignedLender = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignedLender) {
        // Usuario tiene préstamo, usar ese prestamista
        logError("Prestamista encontrado por préstamo existente", $assignedLender);
        
        echo json_encode([
            'success' => true,
            'lender' => [
                'idEmpleado' => (int)$assignedLender['idEmpleado'],
                'nombreEmpleado' => $assignedLender['nombreEmpleado'],
                'correo' => $assignedLender['correo'],
                'telefono' => $assignedLender['telefono']
            ],
            'assignmentType' => 'existing_loan'
        ]);
    } else {
        // Usuario no tiene préstamo, asignar prestamista al azar
        logError("Usuario sin préstamo, asignando prestamista al azar");
        
        $stmt = $db->prepare("
            SELECT idEmpleado, nombreEmpleado, correo, telefono
            FROM empleado 
            WHERE idTipoEmpleado = 1 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $randomLender = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($randomLender) {
            logError("Prestamista aleatorio asignado", $randomLender);
            
            echo json_encode([
                'success' => true,
                'lender' => [
                    'idEmpleado' => (int)$randomLender['idEmpleado'],
                    'nombreEmpleado' => $randomLender['nombreEmpleado'],
                    'correo' => $randomLender['correo'],
                    'telefono' => $randomLender['telefono']
                ],
                'assignmentType' => 'random'
            ]);
        } else {
            // No hay prestamistas disponibles, usar empleado por defecto
            logError("No hay prestamistas disponibles, usando empleado por defecto");
            
            $stmt = $db->prepare("
                SELECT idEmpleado, nombreEmpleado, correo, telefono
                FROM empleado 
                WHERE idEmpleado = 2
            ");
            $stmt->execute();
            $defaultLender = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($defaultLender) {
                echo json_encode([
                    'success' => true,
                    'lender' => [
                        'idEmpleado' => (int)$defaultLender['idEmpleado'],
                        'nombreEmpleado' => $defaultLender['nombreEmpleado'],
                        'correo' => $defaultLender['correo'],
                        'telefono' => $defaultLender['telefono']
                    ],
                    'assignmentType' => 'default'
                ]);
            } else {
                throw new Exception('No hay prestamistas disponibles en el sistema');
            }
        }
    }
    
} catch (PDOException $e) {
    logError("Error PDO en get_assigned_lender", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener prestamista: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Error general en get_assigned_lender", [
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
