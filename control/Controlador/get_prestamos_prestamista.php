<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    $idPrestamista = $_SESSION['user_id'];
    
    $query = "SELECT 
                p.idPrestamo,
                p.montoSolicitado,
                p.fechaSolicitud,
                p.estado,
                pl.nombrePlan,
                CONCAT(u.nombreCliente, ' ', u.apellidoP, ' ', u.apellidoM) as cliente,
                u.telefono,
                COUNT(cp.idPago) as num_pagos,
                SUM(CASE WHEN cp.estado = 1 THEN cp.montoPagado ELSE 0 END) as total_pagado
              FROM prestamos p
              JOIN planesprestamos pl ON p.idPlan = pl.idPlan
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              LEFT JOIN calendariopagos cp ON p.idPrestamo = cp.idPrestamo
              WHERE p.idEmpleado = ?
              GROUP BY p.idPrestamo
              ORDER BY p.fechaSolicitud DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamista]);
    
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $prestamos
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_prestamos_prestamista: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
