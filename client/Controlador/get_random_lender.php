<?php
require_once '../Modelo/conexion.php';

function getRandomLender() {
    try {
        $db = Db::conectar();
        
        // Obtener empleados tipo 1 (prestamistas) - usando nombres correctos de la BD
        $stmt = $db->prepare("
            SELECT idEmpleado 
            FROM empleado 
            WHERE idTipoEmpleado = 1 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $lender = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lender) {
            return $lender['idEmpleado'];
        } else {
            // Si no hay prestamistas, usar el empleado ID 2 que existe en tu BD
            return 2;
        }
        
    } catch (PDOException $e) {
        error_log("Error al obtener prestamista: " . $e->getMessage());
        return 2; // Fallback al empleado ID 2
    }
}
?>
