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
    
    // Obtener información del plan
    $stmt = $db->prepare("SELECT * FROM planesprestamos WHERE idPlan = :planId");
    $stmt->bindParam(':planId', $data['planId'], PDO::PARAM_INT);
    $stmt->execute();
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Plan no encontrado']);
        exit;
    }
    
    // Obtener el próximo ID disponible
    $stmt = $db->query("SELECT MAX(idPrestamo) + 1 AS nextId FROM prestamos");
    $nextId = $stmt->fetch(PDO::FETCH_ASSOC)['nextId'] ?? 1;
    
    // Obtener un empleado prestamista (tipo 1) aleatorio
    $stmt = $db->prepare("SELECT idEmpleado FROM empleado WHERE idTipoEmpleado = 1 ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception("No hay empleados prestamistas disponibles");
    }
    
    // Crear solicitud de préstamo (estado 2 = en revisión)
    $stmt = $db->prepare("
        INSERT INTO prestamos 
        (idPrestamo, idUsuario, idPlan, idEmpleado, tasaInteres, plazoMeses, montoSolicitado, estado, fechaSolicitud)
        VALUES (:id, :userId, :planId, :employeeId, :interest, :term, :amount, 2, NOW())
    ");
    
    $stmt->bindParam(':id', $nextId, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':planId', $data['planId'], PDO::PARAM_INT);
    $stmt->bindParam(':employeeId', $employee['idEmpleado'], PDO::PARAM_INT);
    $stmt->bindParam(':interest', $plan['tasaInteres'], PDO::PARAM_INT);
    $stmt->bindParam(':term', $plan['duracion'], PDO::PARAM_INT);
    $stmt->bindParam(':amount', $plan['monto'], PDO::PARAM_STR);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Solicitud de préstamo enviada',
        'loanId' => $nextId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
