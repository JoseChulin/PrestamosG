<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 2) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    // Consulta para obtener pagos vencidos con al menos un pago posterior pendiente
    $query = "SELECT cp.idPago, cp.idPrestamo, cp.numeroCouta, 
                     cp.fechaVencimiento, cp.montoPago, 
                     DATEDIFF(CURDATE(), cp.fechaVencimiento) as diasRetraso,
                     u.nombreCliente, u.apellidoP, u.apellidoM,
                     (SELECT COUNT(*) FROM calendariopagos 
                      WHERE idPrestamo = cp.idPrestamo 
                      AND numeroCouta > cp.numeroCouta 
                      AND estado = 0) as pagosPosterioresPendientes
              FROM calendariopagos cp
              JOIN prestamos p ON cp.idPrestamo = p.idPrestamo
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              WHERE cp.estado = 0 
              AND cp.fechaVencimiento < CURDATE()
              HAVING pagosPosterioresPendientes > 0
              ORDER BY cp.fechaVencimiento ASC
              LIMIT 10";
    
    $stmt = $db->query($query);
    $pagosAtrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos para la respuesta
    $formattedPayments = array_map(function($pago) {
        return [
            'idPago' => $pago['idPago'],
            'idPrestamo' => 'PR-' . $pago['idPrestamo'],
            'cuota' => $pago['numeroCouta'],
            'diasRetraso' => $pago['diasRetraso'],
            'cliente' => $pago['nombreCliente'] . ' ' . $pago['apellidoP'] . ' ' . $pago['apellidoM'],
            'montoPendiente' => number_format($pago['montoPago'], 2),
            'fechaVencimiento' => date('d/m/Y', strtotime($pago['fechaVencimiento'])),
            'pagosPosterioresPendientes' => $pago['pagosPosterioresPendientes']
        ];
    }, $pagosAtrasados);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $formattedPayments]);
} catch (PDOException $e) {
    error_log("Error en get_late_payments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
