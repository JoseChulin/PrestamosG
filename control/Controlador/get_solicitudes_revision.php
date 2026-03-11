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
    
    $query = "SELECT p.idPrestamo, p.montoSolicitado, p.fechaSolicitud, 
                     p.idUsuario, p.idPlan,
                     CONCAT(u.nombreCliente, ' ', u.apellidoP, ' ', u.apellidoM) as nombreCliente,
                     pl.nombrePlan
              FROM prestamos p
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              JOIN planesprestamos pl ON p.idPlan = pl.idPlan
              WHERE p.estado = 2 AND p.idEmpleado = ?
              ORDER BY p.fechaSolicitud DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamista]);
    
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener documentos e historial para cada solicitud
    foreach ($solicitudes as &$solicitud) {
        // Documentos adjuntos
        $queryDocs = "SELECT td.descripcion as tipoDocumento, d.fechaSubida
                      FROM documentos d
                      JOIN tipodocumento td ON d.idTipoDocumento = td.idTipoDocumento
                      WHERE d.idPrestamo = ?";
        $stmtDocs = $db->prepare($queryDocs);
        $stmtDocs->execute([$solicitud['idPrestamo']]);
        $solicitud['documentos'] = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
        
        // Historial de préstamos del cliente
        $queryHistorial = "SELECT p.idPrestamo, p.montoSolicitado, p.fechaSolicitud, 
                                  p.estado, pl.nombrePlan as plan
                           FROM prestamos p
                           JOIN planesprestamos pl ON p.idPlan = pl.idPlan
                           WHERE p.idUsuario = ? AND p.idPrestamo != ?
                           ORDER BY p.fechaSolicitud DESC";
        $stmtHistorial = $db->prepare($queryHistorial);
        $stmtHistorial->execute([$solicitud['idUsuario'], $solicitud['idPrestamo']]);
        $solicitud['historial'] = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $solicitudes
    ]);
} catch (PDOException $e) {
    error_log("Error en get_solicitudes_revision: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
