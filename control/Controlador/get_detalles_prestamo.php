<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['idPrestamo'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID de préstamo no proporcionado']));
}

try {
    $db = Db::conectar();
    $idPrestamo = $_GET['idPrestamo'];
    $idPrestamista = $_SESSION['user_id'];
    
    // Verificar que el préstamo pertenece al prestamista
    $query = "SELECT 1 FROM prestamos WHERE idPrestamo = ? AND idEmpleado = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo, $idPrestamista]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'No tienes permiso para ver este préstamo']));
    }
    
    // Obtener información básica del préstamo
    $query = "SELECT 
                p.idPrestamo,
                p.montoSolicitado,
                p.fechaSolicitud,
                p.estado,
                pl.nombrePlan,
                pl.tasaInteres,
                CONCAT(u.nombreCliente, ' ', u.apellidoP, ' ', u.apellidoM) as cliente,
                u.telefono,
                SUM(CASE WHEN cp.estado = 1 THEN cp.montoPagado ELSE 0 END) as total_pagado
              FROM prestamos p
              JOIN planesprestamos pl ON p.idPlan = pl.idPlan
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              LEFT JOIN calendariopagos cp ON p.idPrestamo = cp.idPrestamo
              WHERE p.idPrestamo = ?
              GROUP BY p.idPrestamo";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener próximos pagos (los 5 próximos)
    $query = "SELECT 
                fechaVencimiento, 
                montoPago, 
                estado
              FROM calendariopagos
              WHERE idPrestamo = ?
              ORDER BY fechaVencimiento ASC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamo]);
    $proximos_pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => array_merge($prestamo, ['proximos_pagos' => $proximos_pagos])
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_detalles_prestamo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
