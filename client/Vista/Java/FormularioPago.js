document.addEventListener("DOMContentLoaded", () => {
  // Referencias a elementos del DOM
  const paymentForm = document.getElementById("paymentForm")
  const paymentAmountInput = document.getElementById("paymentAmountInput")
  const errorMessage = document.getElementById("errorMessage")
  const successMessage = document.getElementById("successMessage")
  const loading = document.getElementById("loading")
  const receiptModal = document.getElementById("receiptModal")
  const receiptContent = document.getElementById("receiptContent")
  const printReceiptBtn = document.getElementById("printReceiptBtn")
  const closeReceiptBtn = document.getElementById("closeReceiptBtn")
  const closeModalBtn = document.querySelector(".close-modal")

  // Variables para almacenar datos del pago
  let paymentData = null
  let minAmount = 0
  let isLastPayment = false

  // Inicialización
  init()

  // Función principal de inicialización
  async function init() {
    try {
      // Verificar autenticación
      await checkAuth()

      // Obtener ID del pago de la URL
      const urlParams = new URLSearchParams(window.location.search)
      const paymentId = urlParams.get("paymentId")
      const loanId = urlParams.get("loanId")

      if (!paymentId || !loanId) {
        showMessage(errorMessage, "Parámetros de pago no válidos")
        return
      }

      // Cargar detalles del pago
      await loadPaymentDetails(paymentId, loanId)

      // Configurar event listeners
      setupEventListeners()
    } catch (error) {
      console.error("Error en inicialización:", error)
      showMessage(errorMessage, error.message || "Error al inicializar el formulario")
    }
  }

  // Verificar autenticación
  async function checkAuth() {
    showLoading(true)

    try {
      const response = await fetch("PHP/check_auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        window.location.href = "Login.html"
        throw new Error("No autenticado")
      }

      showLoading(false)
    } catch (error) {
      showLoading(false)
      throw new Error("Error al verificar autenticación")
    }
  }

  // Cargar detalles del pago
  async function loadPaymentDetails(paymentId, loanId) {
    showLoading(true)

    try {
      const response = await fetch(`PHP/get_payment_details.php?paymentId=${paymentId}&loanId=${loanId}`)

      if (!response.ok) {
        throw new Error("Error al obtener detalles del pago")
      }

      const data = await response.json()

      if (data.error) {
        throw new Error(data.error)
      }

      // Guardar datos para uso posterior
      paymentData = data
      minAmount = Number.parseFloat(data.payment.montoPago)
      isLastPayment = data.isLastPayment

      // Actualizar la interfaz con los datos recibidos
      updatePaymentInterface(data)

      showLoading(false)
    } catch (error) {
      showLoading(false)
      throw error
    }
  }

  // Actualizar la interfaz con los datos del pago
  function updatePaymentInterface(data) {
    // Detalles del préstamo
    document.getElementById("clientName").textContent = data.loan.nombreCliente || "N/A"
    document.getElementById("loanId").textContent = data.loan.idPrestamo || "N/A"
    document.getElementById("planName").textContent = data.loan.nombrePlan || "N/A"
    document.getElementById("interestRate").textContent = `${data.loan.tasaInteres || 0}%`
    document.getElementById("totalAmount").textContent = formatCurrency(data.loan.montoSolicitado || 0)
    document.getElementById("term").textContent = `${data.loan.plazoMeses || 0} meses`

    // Detalles del pago
    document.getElementById("paymentId").textContent = data.payment.idPago || "N/A"
    document.getElementById("paymentNumber").textContent = data.payment.numeroCouta || "N/A"
    document.getElementById("dueDate").textContent = formatDate(data.payment.fechaVencimiento) || "N/A"
    document.getElementById("paymentAmount").textContent = formatCurrency(data.payment.montoPago || 0)
    document.getElementById("paymentStatus").textContent = getStatusText(data.payment.estado)
    document.getElementById("remainingPayments").textContent = data.remainingPayments || "0"

    // Campos ocultos del formulario
    document.getElementById("hiddenLoanId").value = data.loan.idPrestamo
    document.getElementById("hiddenPaymentId").value = data.payment.idPago
    document.getElementById("hiddenMinAmount").value = data.payment.montoPago
    document.getElementById("hiddenIsLastPayment").value = data.isLastPayment

    // Establecer el valor predeterminado del monto a pagar
    paymentAmountInput.value = data.payment.montoPago
    paymentAmountInput.min = data.payment.montoPago

    // Si es el último pago, establecer el máximo igual al mínimo
    if (data.isLastPayment) {
      paymentAmountInput.max = data.payment.montoPago
    }
  }

  // Configurar event listeners
  function setupEventListeners() {
    // Validación del monto de pago
    paymentAmountInput.addEventListener("input", validatePaymentAmount)

    // Envío del formulario
    paymentForm.addEventListener("submit", handlePaymentSubmit)

    // Botones del modal de comprobante
    printReceiptBtn.addEventListener("click", printReceipt)
    closeReceiptBtn.addEventListener("click", closeReceiptModal)
    closeModalBtn.addEventListener("click", closeReceiptModal)

    // Cerrar modal al hacer clic fuera
    window.addEventListener("click", (e) => {
      if (e.target === receiptModal) {
        closeReceiptModal()
      }
    })
  }

  // Validar monto de pago
  function validatePaymentAmount() {
    const amount = Number.parseFloat(paymentAmountInput.value)

    if (isNaN(amount) || amount < minAmount) {
      paymentAmountInput.setCustomValidity(`El monto no puede ser menor a ${formatCurrency(minAmount)}`)
    } else if (isLastPayment && amount > minAmount) {
      paymentAmountInput.setCustomValidity(`Este es el último pago. No puede pagar más de ${formatCurrency(minAmount)}`)
    } else {
      paymentAmountInput.setCustomValidity("")
    }

    paymentAmountInput.reportValidity()
  }

  // Manejar envío del formulario de pago
  async function handlePaymentSubmit(e) {
    e.preventDefault()

    // Validar monto nuevamente
    const amount = Number.parseFloat(paymentAmountInput.value)

    if (isNaN(amount) || amount < minAmount) {
      showMessage(errorMessage, `El monto no puede ser menor a ${formatCurrency(minAmount)}`)
      return
    }

    if (isLastPayment && amount > minAmount) {
      showMessage(errorMessage, `Este es el último pago. No puede pagar más de ${formatCurrency(minAmount)}`)
      return
    }

    // Recopilar datos del formulario
    const formData = new FormData(paymentForm)
    const paymentData = {
      loanId: Number.parseInt(formData.get("loanId")),
      paymentId: Number.parseInt(formData.get("paymentId")),
      amount: Number.parseFloat(formData.get("amount")),
      paymentMethod: formData.get("paymentMethod"),
      notes: formData.get("notes") || "",
    }

    // Procesar pago
    await processPayment(paymentData)
  }

  // Procesar pago
  async function processPayment(paymentData) {
    showLoading(true)

    try {
      const response = await fetch("PHP/process_payment.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(paymentData),
      })

      const result = await response.json()

      if (!result.success) {
        throw new Error(result.message || "Error al procesar el pago")
      }

      // Mostrar mensaje de éxito
      showMessage(successMessage, "Pago procesado correctamente", false)

      // Deshabilitar el formulario
      disableForm()

      // Mostrar comprobante
      showReceipt(result.payment)
    } catch (error) {
      console.error("Error:", error)
      showMessage(errorMessage, error.message || "Error al procesar el pago")
    } finally {
      showLoading(false)
    }
  }

  // Deshabilitar formulario después de pago exitoso
  function disableForm() {
    const formElements = paymentForm.elements

    for (let i = 0; i < formElements.length; i++) {
      formElements[i].disabled = true
    }
  }

  // Mostrar comprobante de pago
  function showReceipt(payment) {
    // Crear contenido del comprobante
    const receiptHTML = `
      <div class="receipt">
        <div class="receipt-header">
          <h3>COMPROBANTE DE PAGO</h3>
          <p>Fecha: ${formatDate(payment.date)}</p>
          <p>Referencia: ${payment.reference}</p>
        </div>
        
        <div class="receipt-body">
          <div class="receipt-row">
            <span class="receipt-label">Cliente:</span>
            <span>${payment.loanInfo.nombreCliente}</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-label">Préstamo ID:</span>
            <span>${payment.loanInfo.idPrestamo}</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-label">Transacción ID:</span>
            <span>${payment.transactionId}</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-label">Monto Pagado:</span>
            <span>${formatCurrency(payment.amount)}</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-label">Método de Pago:</span>
            <span>${getPaymentMethodText(payment.paymentMethod)}</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-label">Estado:</span>
            <span>Completado</span>
          </div>
          ${payment.isLastPayment ? '<div class="receipt-row"><span class="receipt-label">Nota:</span><span>Préstamo completamente pagado</span></div>' : ""}
        </div>
        
        <div class="receipt-footer">
          <p>Gracias por su pago</p>
          <p>Este comprobante es válido como constancia de pago</p>
        </div>
      </div>
    `

    // Actualizar contenido del modal
    receiptContent.innerHTML = receiptHTML

    // Mostrar modal
    receiptModal.style.display = "flex"
  }

  // Imprimir comprobante
  function printReceipt() {
    window.print()
  }

  // Cerrar modal de comprobante
  function closeReceiptModal() {
    receiptModal.style.display = "none"

    // Redirigir al panel de usuario después de cerrar
    setTimeout(() => {
      window.location.href = "PanelUsuario.html"
    }, 500)
  }

  // Función para mostrar mensajes
  function showMessage(element, message, isError = true) {
    element.textContent = message
    element.style.display = "block"

    if (!isError) {
      successMessage.style.display = "block"
      errorMessage.style.display = "none"
    } else {
      errorMessage.style.display = "block"
      successMessage.style.display = "none"
    }

    // Ocultar después de 5 segundos
    setTimeout(() => {
      element.style.display = "none"
    }, 5000)
  }

  // Función para mostrar/ocultar el spinner de carga
  function showLoading(show) {
    loading.style.display = show ? "flex" : "none"
  }

  // Funciones de utilidad

  // Formatear moneda
  function formatCurrency(amount) {
    return (
      "$" +
      Number.parseFloat(amount)
        .toFixed(2)
        .replace(/\d(?=(\d{3})+\.)/g, "$&,")
    )
  }

  // Formatear fecha
  function formatDate(dateString) {
    if (!dateString) return "N/A"

    const date = new Date(dateString)

    if (isNaN(date.getTime())) return dateString

    return date.toLocaleDateString("es-MX", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    })
  }

  // Obtener texto de estado
  function getStatusText(status) {
    switch (Number.parseInt(status)) {
      case 0:
        return "Pendiente"
      case 1:
        return "Pagado"
      case 2:
        return "Vencido"
      default:
        return "Desconocido"
    }
  }

  // Obtener texto de método de pago
  function getPaymentMethodText(method) {
    switch (method) {
      case "transferencia":
        return "Transferencia Bancaria"
      case "efectivo":
        return "Efectivo"
      case "tarjeta":
        return "Tarjeta de Crédito/Débito"
      default:
        return method || "No especificado"
    }
  }
})
