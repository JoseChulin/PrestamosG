<?php
require_once '../Modelo/conexion.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_GET['type']) || !isset($_GET['start']) || !isset($_GET['end'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Parámetros incompletos']));
}

$type = $_GET['type'];
$startDate = $_GET['start'];
$endDate = $_GET['end'];

try {
    $db = Db::conectar();
    
    switch ($type) {
        case 'ingresos':
            $html = generateIncomeReport($db, $startDate, $endDate);
            break;
        case 'prestamos':
            $html = generateLoansReport($db, $startDate, $endDate);
            break;
        case 'morosidad':
            $html = generateDelinquencyReport($db, $startDate, $endDate);
            break;
        case 'clientes':
            $html = generateClientsReport($db, $startDate, $endDate);
            break;
        default:
            throw new Exception("Tipo de reporte no válido");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'html' => $html]);
} catch (Exception $e) {
    error_log("Error en generate_report: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generateIncomeReport($db, $startDate, $endDate) {
    $query = "SELECT DATE(cp.fechaRegistro) as fecha, SUM(cp.montoPagado) as total, 
                     COUNT(*) as pagos, p.nombrePlan as tipo
              FROM calendariopagos cp
              JOIN prestamos pr ON cp.idPrestamo = pr.idPrestamo
              JOIN planesprestamos p ON pr.idPlan = p.idPlan
              WHERE cp.estado = 1 
              AND cp.fechaRegistro BETWEEN :start AND :end
              GROUP BY DATE(cp.fechaRegistro), p.nombrePlan
              ORDER BY fecha";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<h2>Resumen de Ingresos</h2>';

    if (empty($results)) {
        return $html . '<p>No hay ingresos registrados en este período</p>';
    }

    $total = array_sum(array_column($results, 'total'));

    $html .= '<div class="summary-card">
                <h3>Total Recaudado: $' . number_format($total, 2) . '</h3>
                <p>Período: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) . '</p>
              </div>';

    $html .= '<table class="report-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Tipo de Préstamo</th>
                    <th>Cantidad de Pagos</th>
                    <th>Total Recaudado</th>
                  </tr>
                </thead>
                <tbody>';

    foreach ($results as $row) {
        $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($row['fecha'])) . '</td>
                    <td>' . htmlspecialchars($row['tipo']) . '</td>
                    <td>' . $row['pagos'] . '</td>
                    <td>$' . number_format($row['total'], 2) . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

function generateLoansReport($db, $startDate, $endDate) {
    $query = "SELECT p.idPrestamo, u.nombreCliente, u.apellidoP, u.apellidoM, 
                     p.montoSolicitado, p.tasaInteres, p.plazoMeses, p.fechaSolicitud,
                     pl.nombrePlan as plan, 
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
              WHERE p.fechaSolicitud BETWEEN :start AND :end
              ORDER BY p.fechaSolicitud DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<h2>Reporte de Préstamos</h2>';

    if (empty($loans)) {
        return $html . '<p>No hay préstamos registrados en este período</p>';
    }

    $html .= '<div class="summary-card">
                <h3>Total de Préstamos: ' . count($loans) . '</h3>
                <p>Período: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) . '</p>
              </div>';

    $html .= '<table class="report-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Monto</th>
                    <th>Plan</th>
                    <th>Tasa</th>
                    <th>Plazo</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>';

    foreach ($loans as $loan) {
        $nombreCompleto = htmlspecialchars($loan['nombreCliente'] . ' ' . $loan['apellidoP'] . ' ' . $loan['apellidoM']);
        $html .= '<tr>
                    <td>PR-' . $loan['idPrestamo'] . '</td>
                    <td>' . $nombreCompleto . '</td>
                    <td>$' . number_format($loan['montoSolicitado'], 2) . '</td>
                    <td>' . htmlspecialchars($loan['plan']) . '</td>
                    <td>' . $loan['tasaInteres'] . '%</td>
                    <td>' . $loan['plazoMeses'] . ' meses</td>
                    <td>' . date('d/m/Y', strtotime($loan['fechaSolicitud'])) . '</td>
                    <td>' . $loan['estado'] . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

function generateDelinquencyReport($db, $startDate, $endDate) {
    $query = "SELECT cp.idPago, cp.idPrestamo, cp.numeroCuota, cp.fechaVencimiento, 
                     cp.montoPago, DATEDIFF(CURDATE(), cp.fechaVencimiento) as diasRetraso,
                     u.nombreCliente, u.apellidoP, u.apellidoM, p.montoSolicitado
              FROM calendariopagos cp
              JOIN prestamos p ON cp.idPrestamo = p.idPrestamo
              JOIN usuarios u ON p.idUsuario = u.idUsuario
              WHERE cp.estado = 0 
              AND cp.fechaVencimiento BETWEEN :start AND :end
              ORDER BY cp.fechaVencimiento ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<h2>Reporte de Morosidad</h2>';

    if (empty($payments)) {
        return $html . '<p>No hay pagos atrasados en este período</p>';
    }

    $total = array_sum(array_column($payments, 'montoPago'));

    $html .= '<div class="summary-card">
                <h3>Total Pendiente: $' . number_format($total, 2) . '</h3>
                <p>Período: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) . '</p>
              </div>';

    $html .= '<table class="report-table">
                <thead>
                  <tr>
                    <th>ID Préstamo</th>
                    <th>Cliente</th>
                    <th>Cuota #</th>
                    <th>Monto</th>
                    <th>Fecha Vencimiento</th>
                    <th>Días de Retraso</th>
                  </tr>
                </thead>
                <tbody>';

    foreach ($payments as $payment) {
        $cliente = htmlspecialchars($payment['nombreCliente'] . ' ' . $payment['apellidoP'] . ' ' . $payment['apellidoM']);
        $html .= '<tr>
                    <td>PR-' . $payment['idPrestamo'] . '</td>
                    <td>' . $cliente . '</td>
                    <td>' . $payment['numeroCuota'] . '</td>
                    <td>$' . number_format($payment['montoPago'], 2) . '</td>
                    <td>' . date('d/m/Y', strtotime($payment['fechaVencimiento'])) . '</td>
                    <td>' . $payment['diasRetraso'] . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

function generateClientsReport($db, $startDate, $endDate) {
    $query = "SELECT u.idUsuario, u.nombreCliente, u.apellidoP, u.apellidoM, u.telefono, u.correo,
                     COUNT(p.idPrestamo) as totalPrestamos,
                     SUM(CASE WHEN p.estado = 1 THEN 1 ELSE 0 END) as prestamosActivos,
                     MAX(p.fechaSolicitud) as ultimoPrestamo
              FROM usuarios u
              LEFT JOIN prestamos p ON u.idUsuario = p.idUsuario
              WHERE u.fechaRegistro BETWEEN :start AND :end
              GROUP BY u.idUsuario
              ORDER BY u.nombreCliente";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<h2>Reporte de Clientes</h2>';

    if (empty($clients)) {
        return $html . '<p>No hay clientes registrados en este período</p>';
    }

    $html .= '<div class="summary-card">
                <h3>Total de Clientes: ' . count($clients) . '</h3>
                <p>Período: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) . '</p>
              </div>';

    $html .= '<table class="report-table">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Préstamos Totales</th>
                    <th>Préstamos Activos</th>
                    <th>Último Préstamo</th>
                  </tr>
                </thead>
                <tbody>';

    foreach ($clients as $client) {
        $nombre = htmlspecialchars($client['nombreCliente'] . ' ' . $client['apellidoP'] . ' ' . $client['apellidoM']);
        $html .= '<tr>
                    <td>' . $nombre . '</td>
                    <td>' . htmlspecialchars($client['telefono']) . '</td>
                    <td>' . htmlspecialchars($client['correo']) . '</td>
                    <td>' . $client['totalPrestamos'] . '</td>
                    <td>' . $client['prestamosActivos'] . '</td>
                    <td>' . ($client['ultimoPrestamo'] ? date('d/m/Y', strtotime($client['ultimoPrestamo'])) : 'N/A') . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}
?>
