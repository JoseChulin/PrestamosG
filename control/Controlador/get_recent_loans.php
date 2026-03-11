<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    $query = "SELECT p.idPrestamo, u.nombreCliente, u.apellidoP, u.apellidoM, p.montoSolicitado, p.estado, p.fechaSolicitud
              FROM prestamos p
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              ORDER BY p.fechaSolicitud DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos para la respuesta
    $formattedLoans = array_map(function($prestamo) {
        return [
            'id' => 'PR-' . $prestamo['idPrestamo'],
            'cliente' => $prestamo['nombreCliente'] . ' ' . $prestamo['apellidoP'] . ' ' . $prestamo['apellidoM'],
            'monto' => number_format($prestamo['montoSolicitado'], 2),
            'estado' => getEstadoPrestamo($prestamo['estado']),
            'fecha' => $prestamo['fechaSolicitud']
        ];
    }, $prestamos);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $formattedLoans]);
} catch (PDOException $e) {
    error_log("Error en get_recent_loans: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}

function getEstadoPrestamo($estado) {
    switch ($estado) {
        case 1: return 'Activo';
        case 2: return 'En revisión';
        case 3: return 'Rechazado';
        case 4: return 'Finalizado';
        default: return 'Desconocido';
    }
}
?>
