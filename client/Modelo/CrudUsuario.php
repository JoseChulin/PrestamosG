<?php
require_once('conexion.php');
require_once('Usuario.php');

class CrudUsuario {
    public function __construct() {}

    public function obtenerUsuarioPorCorreo($correo) {
        $bd = Db::conectar();
        $seleccionar = $bd->prepare('SELECT * FROM usuarios WHERE correo = :correo');
        $seleccionar->bindValue(':correo', $correo);
        $seleccionar->execute();
        $fila = $seleccionar->fetch();
        if (!$fila) return null;
        $usuario = new Usuario();
        $usuario->setIdUsuario($fila['idUsuario']);
        $usuario->setNombreCliente($fila['nombreCliente']);
        $usuario->setCorreo($fila['correo']);
        $usuario->setTelefono($fila['telefono']);
        $usuario->setNombreUsuario($fila['nombreUsuario']);
        return $usuario;
    }

    public function insertar($usuario) {
        $bd = Db::conectar();
        $insertar = $bd->prepare('INSERT INTO usuarios (nombreCliente, correo, telefono, nombreUsuario) VALUES (:nombreCliente, :correo, :telefono, :nombreUsuario)');
        $insertar->bindValue(':nombreCliente', $usuario->getNombreCliente());
        $insertar->bindValue(':correo', $usuario->getCorreo());
        $insertar->bindValue(':telefono', $usuario->getTelefono());
        $insertar->bindValue(':nombreUsuario', $usuario->getNombreUsuario());
        $insertar->execute();
    }

    public function actualizar($usuario) {
        $bd = Db::conectar();
        $actualizar = $bd->prepare('UPDATE usuarios SET nombreCliente = :nombreCliente, correo = :correo, telefono = :telefono, nombreUsuario = :nombreUsuario WHERE idUsuario = :idUsuario');
        $actualizar->bindValue(':idUsuario', $usuario->getIdUsuario());
        $actualizar->bindValue(':nombreCliente', $usuario->getNombreCliente());
        $actualizar->bindValue(':correo', $usuario->getCorreo());
        $actualizar->bindValue(':telefono', $usuario->getTelefono());
        $actualizar->bindValue(':nombreUsuario', $usuario->getNombreUsuario());
        $actualizar->execute();
    }

    public function eliminar($idUsuario) {
        $bd = Db::conectar();
        $eliminar = $bd->prepare('DELETE FROM usuarios WHERE idUsuario = :idUsuario');
        $eliminar->bindValue(':idUsuario', $idUsuario);
        $eliminar->execute();
    }
}
?>