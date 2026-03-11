<?php
// Modelo: Pago
class Pago {
    private $idPago;
    private $idPrestamo;
    private $montoPago;
    private $fechaPago;
    private $fechaVencimiento;
    private $estado;
    private $idTransaccion;

    public function __construct() {}

    public function getIdPago() {
        return $this->idPago;
    }
    public function setIdPago($idPago) {
        $this->idPago = $idPago;
    }

    public function getIdPrestamo() {
        return $this->idPrestamo;
    }
    public function setIdPrestamo($idPrestamo) {
        $this->idPrestamo = $idPrestamo;
    }

    public function getMontoPago() {
        return $this->montoPago;
    }
    public function setMontoPago($montoPago) {
        $this->montoPago = $montoPago;
    }

    public function getFechaPago() {
        return $this->fechaPago;
    }
    public function setFechaPago($fechaPago) {
        $this->fechaPago = $fechaPago;
    }

    public function getFechaVencimiento() {
        return $this->fechaVencimiento;
    }
    public function setFechaVencimiento($fechaVencimiento) {
        $this->fechaVencimiento = $fechaVencimiento;
    }

    public function getEstado() {
        return $this->estado;
    }
    public function setEstado($estado) {
        $this->estado = $estado;
    }

    public function getIdTransaccion() {
        return $this->idTransaccion;
    }
    public function setIdTransaccion($idTransaccion) {
        $this->idTransaccion = $idTransaccion;
    }
}
?>