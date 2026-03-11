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
    
    // Obtener dudas con sus respuestas
    $query = "SELECT 
                n.idNotificacion,
                u.idUsuario,
                CONCAT(u.nombreCliente, ' ', u.apellidoP) as nombreCliente,
                n.mensaje as duda,
                n.fechaEnvio as fechaDuda,
                r.mensaje as respuesta,
                r.fechaEnvio as fechaRespuesta
              FROM notificaciones n
              JOIN usuarios u ON n.idUsuario = u.idUsuario
              LEFT JOIN notificaciones r ON (
                  r.idUsuario = n.idUsuario AND 
                  r.idTipoNotificacion = 8 AND
                  r.idEmpleado = ? AND
                  r.fechaEnvio > n.fechaEnvio
              )
              WHERE n.idTipoNotificacion = 7 
              AND n.idEmpleado = ?
              ORDER BY u.nombreCliente, n.fechaEnvio DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$idPrestamista, $idPrestamista]);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por cliente
    $dudasPorCliente = [];
    foreach ($resultados as $row) {
        $clienteId = $row['idUsuario'];
        if (!isset($dudasPorCliente[$clienteId])) {
            $dudasPorCliente[$clienteId] = [
                'cliente' => $row['nombreCliente'],
                'dudas' => []
            ];
        }
        
        $dudasPorCliente[$clienteId]['dudas'][] = [
            'id' => $row['idNotificacion'],
            'duda' => $row['duda'],
            'fechaDuda' => $row['fechaDuda'],
            'respuesta' => $row['respuesta'],
            'fechaRespuesta' => $row['fechaRespuesta']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_values($dudasPorCliente)
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_dudas_con_respuestas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
