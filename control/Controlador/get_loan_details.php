<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID inválido']));
}

try {
    $db = Db::conectar();
    
    // Consulta para obtener detalles del préstamo
    $query = "SELECT p.idPrestamo, u.nombreCliente, u.apellidoP, u.apellidoM, 
                     p.montoSolicitado, p.tasaInteres, p.plazoMeses, p.fechaSolicitud,
                     pl.nombrePlan as plan, e.nombreEmpleado as empleado,
                     CASE p.estado 
                        WHEN 1 THEN 'Activo'
                        WHEN 2 THEN 'En revisión'
                        WHEN 3 THEN 'Rechazado'
                        WHEN 4 THEN 'Finalizado'
                        ELSE 'Desconocido'
                     END as estado
              FROM prestamos p
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              JOIN planesprestamos pl ON p.idPlan = pl.idPlan
              JOIN empleado e ON p.idEmpleado = e.idEmpleado
              WHERE p.idPrestamo = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
    $stmt->execute();
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prestamo) {
        echo json_encode(['success' => false, 'message' => 'Préstamo no encontrado']);
        exit;
    }
    
    // Consulta para obtener el historial de pagos
    $queryPagos = "SELECT numeroCouta, fechaVencimiento, montoPago, 
                          CASE estado 
                            WHEN 1 THEN 'Pagado'
                            ELSE 'Pendiente'
                          END as estado
                   FROM calendariopagos
                   WHERE idPrestamo = :id
                   ORDER BY numeroCouta";
    
    $stmtPagos = $db->prepare($queryPagos);
    $stmtPagos->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
    $stmtPagos->execute();
    $pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear la respuesta
    $response = [
        'success' => true,
        'data' => [
            'prestamo' => [
                'id' => 'PR-' . $prestamo['idPrestamo'],
                'cliente' => $prestamo['nombreCliente'] . ' ' . $prestamo['apellidoP'] . ' ' . $prestamo['apellidoM'],
                'monto' => number_format($prestamo['montoSolicitado'], 2),
                'tasaInteres' => $prestamo['tasaInteres'] . '% anual',
                'plazo' => $prestamo['plazoMeses'] . ' meses',
                'fechaInicio' => date('d/m/Y', strtotime($prestamo['fechaSolicitud'])),
                'plan' => $prestamo['plan'],
                'empleado' => $prestamo['empleado'],
                'estado' => $prestamo['estado']
            ],
            'pagos' => $pagos
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Error en get_loan_details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
