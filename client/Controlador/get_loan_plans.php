<?php
require_once '../Modelo/conexion.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    $db = Db::conectar();
    
    // Obtener solo los planes con ID 1, 2, 3, 4
    $stmt = $db->prepare("
        SELECT idPlan, nombrePlan, tasaInteres, duracion, monto, descripcion, fechaRegistro
        FROM planesprestamos 
        WHERE idPlan IN (1, 2, 3, 4)
        ORDER BY idPlan ASC
    ");
    $stmt->execute();
    $plans = $stmt->fetchAll();

    if (empty($plans)) {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron planes de préstamo disponibles'
        ]);
        exit;
    }

    // Formatear los datos para el frontend
    $formattedPlans = array_map(function($plan) {
        return [
            'idPlan' => (int)$plan['idPlan'],
            'nombrePlan' => $plan['nombrePlan'],
            'tasaInteres' => (int)$plan['tasaInteres'],
            'duracion' => (int)$plan['duracion'],
            'monto' => (float)$plan['monto'],
            'descripcion' => $plan['descripcion'],
            'fechaRegistro' => $plan['fechaRegistro']
        ];
    }, $plans);

    echo json_encode([
        'success' => true,
        'plans' => $formattedPlans,
        'total' => count($formattedPlans)
    ]);

} catch (PDOException $e) {
    error_log("Error en get_loan_plans: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los planes de préstamo'
    ]);
} catch (Exception $e) {
    error_log("Error general en get_loan_plans: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>
