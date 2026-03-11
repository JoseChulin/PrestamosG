<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 2) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['searchTerm'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Término de búsqueda requerido']));
}

$searchTerm = '%' . $_GET['searchTerm'] . '%';

try {
    $db = Db::conectar();
    
    // Buscar por ID de préstamo o nombre de cliente
    $query = "SELECT p.idPrestamo, u.nombreCliente, u.apellidoP, u.apellidoM, 
                     p.montoSolicitado, p.fechaSolicitud,
                     CASE p.estado 
                        WHEN 1 THEN 'Activo'
                        WHEN 2 THEN 'En revisión'
                        WHEN 3 THEN 'Rechazado'
                        WHEN 4 THEN 'Finalizado'
                        ELSE 'Desconocido'
                     END as estado
              FROM prestamos p
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              WHERE p.idPrestamo LIKE :searchId
              OR CONCAT(u.nombreCliente, ' ', u.apellidoP, ' ', u.apellidoM) LIKE :searchName
              ORDER BY p.fechaSolicitud DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':searchId', str_replace('PR-', '', $_GET['searchTerm']));
    $stmt->bindValue(':searchName', $searchTerm);
    $stmt->execute();
    
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los resultados
    $formattedLoans = array_map(function($prestamo) {
        return [
            'id' => 'PR-' . $prestamo['idPrestamo'],
            'cliente' => $prestamo['nombreCliente'] . ' ' . $prestamo['apellidoP'] . ' ' . $prestamo['apellidoM'],
            'monto' => number_format($prestamo['montoSolicitado'], 2),
            'fecha' => date('d/m/Y', strtotime($prestamo['fechaSolicitud'])),
            'estado' => $prestamo['estado']
        ];
    }, $prestamos);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $formattedLoans]);
} catch (PDOException $e) {
    error_log("Error en search_loans: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
