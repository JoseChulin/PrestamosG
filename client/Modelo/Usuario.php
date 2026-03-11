<?php
// Modelo: Usuario
class Usuario {
    private $idUsuario;
    private $nombreCliente;
    private $correo;
    private $telefono;
    private $nombreUsuario;

    public function __construct() {}

    public function getIdUsuario() {
        return $this->idUsuario;
    }
    public function setIdUsuario($idUsuario) {
        $this->idUsuario = $idUsuario;
    }

    public function getNombreCliente() {
        return $this->nombreCliente;
    }
    public function setNombreCliente($nombreCliente) {
        $this->nombreCliente = $nombreCliente;
    }

    public function getCorreo() {
        return $this->correo;
    }
    public function setCorreo($correo) {
        $this->correo = $correo;
    }

    public function getTelefono() {
        return $this->telefono;
    }
    public function setTelefono($telefono) {
        $this->telefono = $telefono;
    }

    public function getNombreUsuario() {
        return $this->nombreUsuario;
    }
    public function setNombreUsuario($nombreUsuario) {
        $this->nombreUsuario = $nombreUsuario;
    }
}
?>