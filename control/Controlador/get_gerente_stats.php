<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 2) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

try {
    $db = Db::conectar();
    
    // Consulta para préstamos activos
    $queryPrestamos = "SELECT SUM(montoSolicitado) as total FROM prestamos WHERE estado = 1";
    $stmt = $db->query($queryPrestamos);
    $prestamosActivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Consulta para ingresos del mes (suma de pagos realizados este mes)
    $mesActual = date('m');
    $queryIngresos = "SELECT SUM(montoPagado) as total FROM calendariopagos 
                      WHERE estado = 1 AND MONTH(fechaRegistro) = :mes";
    $stmt = $db->prepare($queryIngresos);
    $stmt->bindParam(':mes', $mesActual);
    $stmt->execute();
    $ingresosMes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Consulta para tasa de morosidad (pagos atrasados / total pagos)
    $queryMorosidad = "SELECT 
                        (SELECT COUNT(*) FROM calendariopagos WHERE estado = 2 AND fechaVencimiento < CURDATE()) as atrasados,
                        (SELECT COUNT(*) FROM calendariopagos) as total";
    $stmt = $db->query($queryMorosidad);
    $morosidad = $stmt->fetch(PDO::FETCH_ASSOC);
    $tasaMorosidad = ($morosidad['total'] > 0) ? round(($morosidad['atrasados'] / $morosidad['total']) * 100, 2) : 0;
    
    // Consulta para clientes activos
    $queryClientes = "SELECT COUNT(DISTINCT idUsuario) as total FROM prestamos WHERE estado = 1";
    $stmt = $db->query($queryClientes);
    $clientesActivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'prestamosActivos' => number_format($prestamosActivos, 2),
            'ingresosMes' => number_format($ingresosMes, 2),
            'tasaMorosidad' => $tasaMorosidad,
            'clientesActivos' => $clientesActivos
        ]
    ]);
} catch (PDOException $e) {
    error_log("Error en get_gerente_stats: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
