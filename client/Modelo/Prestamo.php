<?php
// Modelo: Prestamo
class Prestamo {
    private $idPrestamo;
    private $idUsuario;
    private $monto;
    private $tasaInteres;
    private $plazo;
    private $fechaSolicitud;
    private $estado;

    public function __construct() {}

    public function getIdPrestamo() {
        return $this->idPrestamo;
    }
    public function setIdPrestamo($idPrestamo) {
        $this->idPrestamo = $idPrestamo;
    }

    public function getIdUsuario() {
        return $this->idUsuario;
    }
    public function setIdUsuario($idUsuario) {
        $this->idUsuario = $idUsuario;
    }

    public function getMonto() {
        return $this->monto;
    }
    public function setMonto($monto) {
        $this->monto = $monto;
    }

    public function getTasaInteres() {
        return $this->tasaInteres;
    }
    public function setTasaInteres($tasaInteres) {
        $this->tasaInteres = $tasaInteres;
    }

    public function getPlazo() {
        return $this->plazo;
    }
    public function setPlazo($plazo) {
        $this->plazo = $plazo;
    }

    public function getFechaSolicitud() {
        return $this->fechaSolicitud;
    }
    public function setFechaSolicitud($fechaSolicitud) {
        $this->fechaSolicitud = $fechaSolicitud;
    }

    public function getEstado() {
        return $this->estado;
    }
    public function setEstado($estado) {
        $this->estado = $estado;
    }
}
?>