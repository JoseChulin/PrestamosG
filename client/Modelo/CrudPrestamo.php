<?php
require_once('conexion.php');
require_once('Prestamo.php');

class CrudPrestamo {
    public function __construct() {}

    public function obtenerPrestamosPorUsuario($idUsuario) {
        $bd = Db::conectar();
        $seleccionar = $bd->prepare('SELECT * FROM prestamos WHERE idUsuario = :idUsuario');
        $seleccionar->bindValue(':idUsuario', $idUsuario);
        $seleccionar->execute();
        $prestamos = [];
        foreach ($seleccionar->fetchAll() as $fila) {
            $prestamo = new Prestamo();
            $prestamo->setIdPrestamo($fila['idPrestamo']);
            $prestamo->setIdUsuario($fila['idUsuario']);
            $prestamo->setMonto($fila['monto']);
            $prestamo->setTasaInteres($fila['tasaInteres']);
            $prestamo->setPlazo($fila['plazo']);
            $prestamo->setFechaSolicitud($fila['fechaSolicitud']);
            $prestamo->setEstado($fila['estado']);
            $prestamos[] = $prestamo;
        }
        return $prestamos;
    }

    public function insertar($prestamo) {
        $bd = Db::conectar();
        $insertar = $bd->prepare('INSERT INTO prestamos (idUsuario, monto, tasaInteres, plazo, fechaSolicitud, estado) VALUES (:idUsuario, :monto, :tasaInteres, :plazo, :fechaSolicitud, :estado)');
        $insertar->bindValue(':idUsuario', $prestamo->getIdUsuario());
        $insertar->bindValue(':monto', $prestamo->getMonto());
        $insertar->bindValue(':tasaInteres', $prestamo->getTasaInteres());
        $insertar->bindValue(':plazo', $prestamo->getPlazo());
        $insertar->bindValue(':fechaSolicitud', $prestamo->getFechaSolicitud());
        $insertar->bindValue(':estado', $prestamo->getEstado());
        $insertar->execute();
    }

    public function actualizarEstado($idPrestamo, $estado) {
        $bd = Db::conectar();
        $actualizar = $bd->prepare('UPDATE prestamos SET estado = :estado WHERE idPrestamo = :idPrestamo');
        $actualizar->bindValue(':idPrestamo', $idPrestamo);
        $actualizar->bindValue(':estado', $estado);
        $actualizar->execute();
    }
}
?>