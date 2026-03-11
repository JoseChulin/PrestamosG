<?php
class Db {
    private static $conexion = NULL;
    
    private function __construct() {}
    
    public static function conectar() {
        try {
            // Opciones de configuración para PDO
            $pdo_options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Manejo de errores
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4", // Codificación UTF-8
                PDO::ATTR_PERSISTENT => true, // Conexiones persistentes
                PDO::ATTR_EMULATE_PREPARES => false, // Preparaciones reales
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Formato de resultados
            ];
            
            // Crear conexión
            self::$conexion = new PDO(
                'mysql:host=localhost;dbname=sistemapagos', // Servidor y base de datos
                'root', // Usuario
                '', // Contraseña 
                $pdo_options 
            );
            
            return self::$conexion;
        } catch(PDOException $e) {
            // Registro detallado del error
            error_log("Error de conexión: " . $e->getMessage());
            
            // Mensaje amigable para producción (sin detalles técnicos)
            die("Error al conectar con la base de datos. Por favor intente más tarde.");
        }
    }
}
?>