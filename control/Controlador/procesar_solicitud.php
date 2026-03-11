<?php
require_once '../Modelo/conexion.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] != 1) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['idPrestamo']) || !isset($data['accion'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Datos incompletos']));
}

try {
    $db = Db::conectar();
    $idPrestamista = $_SESSION['user_id'];
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // 1. Verificar que el préstamo pertenece al prestamista
    $query = "SELECT p.idPrestamo, p.idUsuario, p.idPlan, p.montoSolicitado, 
                     pl.tasaInteres, pl.duracion as semanas
              FROM prestamos p
              JOIN planesprestamos pl ON p.idPlan = pl.idPlan
              WHERE p.idPrestamo = ? AND p.idEmpleado = ? AND p.estado = 2";
    $stmt = $db->prepare($query);
    $stmt->execute([$data['idPrestamo'], $idPrestamista]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prestamo) {
        $db->rollBack();
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Préstamo no encontrado o no autorizado']));
    }
    
    // 2. Determinar nuevo estado (1 = aprobado, 3 = rechazado)
    $nuevoEstado = $data['accion'] === 'aceptar' ? 1 : 3;
    
    // 3. Actualizar estado del préstamo
    $query = "UPDATE prestamos SET estado = ? WHERE idPrestamo = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$nuevoEstado, $data['idPrestamo']]);
    
    // Solo generar calendario si se está aceptando
    if ($nuevoEstado === 1) {
        // 4. Calcular detalles del pago
        $montoPago = $prestamo['montoSolicitado'] / $prestamo['semanas'];
        $fechaActual = new DateTime();
        
        // 5. Generar fechas de pago (cada semana)
        for ($i = 1; $i <= $prestamo['semanas']; $i++) {
            $fechaVencimiento = clone $fechaActual;
            $fechaVencimiento->add(new DateInterval("P{$i}W")); // Añadir i semanas
            
            // 6. Insertar en calendario de pagos 
            $query = "INSERT INTO calendariopagos (
                        idPrestamo, 
                        numeroCouta, 
                        fechaVencimiento, 
                        estado, 
                        montoPago, 
                        montoPagado, 
                        tasaInteres,
                        fechaRegistro
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $data['idPrestamo'],
                $i,
                $fechaVencimiento->format('Y-m-d'),
                0, // Estado 0 = pendiente
                round($montoPago, 2),
                0.00, // Monto pagado inicial
                $prestamo['tasaInteres'],
                $fechaActual->format('Y-m-d')
            ]);
        }
    }
    
    // 7. Crear notificación para el usuario
    $tipoNotificacion = $nuevoEstado === 1 ? 1 : 2; // 1 = Aprobación, 2 = Rechazo
    $mensaje = $nuevoEstado === 1 ? 
        'Su préstamo ha sido aprobado. Se han generado las fechas de pago.' : 
        'Su préstamo ha sido rechazado';
    
    $query = "INSERT INTO notificaciones 
              (idUsuario, idEmpleado, mensaje, idTipoNotificacion, estado) 
              VALUES (?, ?, ?, ?, 1)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $prestamo['idUsuario'],
        $idPrestamista,
        $mensaje,
        $tipoNotificacion
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Error en procesar_solicitud: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>
