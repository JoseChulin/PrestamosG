document.addEventListener("DOMContentLoaded", () => {
  console.log("=== PANEL DE USUARIO INICIADO ===")

  // === VARIABLES GLOBALES ===
  let userData = null
  let currentPaymentId = null
  let currentLoanId = null
  let currentPaymentAmount = null

  // === REFERENCIAS A ELEMENTOS DEL DOM ===
  const userDropdown = document.getElementById("userDropdown")
  const dropdownContent = document.getElementById("dropdownContent")
  const logoutBtn = document.getElementById("logoutBtn")
  const addCardBtn = document.getElementById("addCardBtn")
  const menuItems = document.querySelectorAll(".menu-item")
  const filterBtns = document.querySelectorAll(".filter-btn")
  const paymentBtn = document.querySelector(".payment-btn")
  const loadingSpinner = document.getElementById("loading-spinner")
  const errorMessage = document.getElementById("error-message")

  // === FUNCIONES AUXILIARES ===

  // Función para mostrar/ocultar spinner de carga
  function showLoading(show = true) {
    if (loadingSpinner) {
      loadingSpinner.style.display = show ? "flex" : "none"
    }
  }

  // Función para mostrar mensajes de error o éxito
  function showMessage(message, isError = true) {
    if (errorMessage) {
      errorMessage.textContent = message
      errorMessage.style.display = "block"

      // Cambiar color según si es error o éxito
      errorMessage.style.backgroundColor = isError ? "#f44336" : "#4CAF50"

      setTimeout(() => {
        errorMessage.style.display = "none"
      }, 3000)
    } else {
      // Fallback a alert si no existe el elemento
      alert(message)
    }
  }

  // Formatear moneda
  function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) {
      return "$0.00"
    }

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

  // Función para obtener el texto del estado
  function getStatusText(status) {
    switch (Number(status)) {
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

  // Función para obtener la clase CSS del estado
  function getStatusClass(status) {
    switch (Number(status)) {
      case 0:
        return "pending"
      case 1:
        return "completed"
      case 2:
        return "overdue"
      default:
        return ""
    }
  }

  // === FUNCIONES DE CONEXIÓN CON PHP ===

  // Verificar estado de autenticación
  async function checkAuth() {
    try {
      const response = await fetch("../Controlador/check_auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        window.location.href = "Login.html"
        return false
      }
      return true
    } catch (error) {
      console.error("Error verificando autenticación:", error)
      window.location.href = "Login.html"
      return false
    }
  }

  // Cargar datos del usuario
  async function loadUserData() {
    showLoading(true)

    try {
      const response = await fetch("../Controlador/user_data.php", {
        method: "GET",
        credentials: "same-origin",
      })

      if (!response.ok) {
        throw new Error("Error al cargar datos del usuario")
      }

      const data = await response.json()

      if (data.error) {
        throw new Error(data.error)
      }

      userData = data
      console.log("Datos del usuario cargados:", userData)

      // Actualizar la interfaz con los datos del usuario
      updateUserInterface(data)

      showLoading(false)
      return data
    } catch (error) {
      console.error("Error al cargar datos:", error)
      showLoading(false)
      showMessage("Error al cargar los datos: " + error.message)
      return null
    }
  }

  // Actualizar la interfaz con los datos del usuario
  function updateUserInterface(data) {
    // Actualizar información del usuario
    if (data.user) {
      const usernameDisplay = document.getElementById("usernameDisplay")
      const userAvatar = document.getElementById("userAvatar")

      if (usernameDisplay) {
        usernameDisplay.textContent = data.user.name || "Usuario"
      }
      if (userAvatar) {
        userAvatar.textContent = (data.user.name || "U").charAt(0).toUpperCase()
      }
    }

    // Actualizar datos de próximo pago
    if (data.nextPayments && data.nextPayments.length > 0) {
      const nextPayment = data.nextPayments[0]
      const nextPaymentDate = document.getElementById("nextPaymentDate")
      const paymentAmount = document.getElementById("paymentAmount")

      if (nextPaymentDate) {
        nextPaymentDate.textContent = formatDate(nextPayment.fechaVencimiento || nextPayment.date)
      }
      if (paymentAmount) {
        paymentAmount.textContent = formatCurrency(nextPayment.montoPago || nextPayment.amount)
      }

      // Guardar datos para el pago
      currentPaymentId = nextPayment.idPago || nextPayment.id
      currentLoanId = data.loan?.idPrestamo || data.loan?.id
      currentPaymentAmount = nextPayment.montoPago || nextPayment.amount

      // Actualizar botón de pago
      updatePaymentButton()
    } else {
      const nextPaymentDate = document.getElementById("nextPaymentDate")
      const paymentAmount = document.getElementById("paymentAmount")

      if (nextPaymentDate) {
        nextPaymentDate.textContent = "Sin pagos pendientes"
      }
      if (paymentAmount) {
        paymentAmount.textContent = "$0.00"
      }

      // Deshabilitar botón de pago
      if (paymentBtn) {
        paymentBtn.disabled = true
        paymentBtn.classList.add("disabled")
      }
    }

    // Actualizar banco
    const bankName = document.getElementById("bankName")
    if (data.user && data.user.account && data.user.account.bank) {
      if (bankName) {
        bankName.textContent = data.user.account.bank
      }
    } else {
      if (bankName) {
        bankName.textContent = "Sin cuenta registrada"
      }
    }

    // Actualizar datos de tarjeta/cuenta bancaria
    const cardNumber = document.getElementById("cardNumber")
    const cardName = document.getElementById("cardName")
    const cardExpiry = document.getElementById("cardExpiry")

    if (data.user && data.user.account && data.user.account.number) {
      const accountNumber = data.user.account.number
      // Enmascarar el número de cuenta
      const maskedNumber = accountNumber
        .replace(/(\d{4})/g, "$1 ")
        .trim()
        .replace(/\d(?=\d{4})/g, "*")

      if (cardNumber) {
        cardNumber.textContent = maskedNumber
      }
      if (cardName) {
        cardName.textContent = (data.user.name || "").toUpperCase()
      }
      if (cardExpiry) {
        cardExpiry.textContent = "12/25" // Valor por defecto
      }
    } else {
      if (cardNumber) {
        cardNumber.textContent = "**** **** **** ****"
      }
      if (cardName) {
        cardName.textContent = "SIN CUENTA REGISTRADA"
      }
      if (cardExpiry) {
        cardExpiry.textContent = "MM/AA"
      }
    }

    // Cargar historial con botones de comprobante
    loadHistoryWithReceipts("all", data.payments || [])
  }

  // Función para mostrar el botón de pago solo cuando hay pagos pendientes
  function updatePaymentButton() {
    if (!paymentBtn) return

    if (userData && userData.nextPayments && userData.nextPayments.length > 0) {
      const nextPayment = userData.nextPayments[0]

      // Habilitar el botón solo si hay un pago pendiente
      if (nextPayment.estado === 0 || nextPayment.status === 0) {
        paymentBtn.disabled = false
        paymentBtn.classList.remove("disabled")

        // Remover event listeners anteriores
        const newPaymentBtn = paymentBtn.cloneNode(true)
        paymentBtn.parentNode.replaceChild(newPaymentBtn, paymentBtn)

        newPaymentBtn.addEventListener("click", () => {
          console.log("Redirigiendo a formulario de pago...")
          // Redirigir al formulario de pago con los parámetros necesarios
          window.location.href = `FormularioPago.html?paymentId=${currentPaymentId}&loanId=${currentLoanId}`
        })
      } else {
        paymentBtn.disabled = true
        paymentBtn.classList.add("disabled")
      }
    } else {
      paymentBtn.disabled = true
      paymentBtn.classList.add("disabled")
    }
  }

  // Cargar historial con botones de comprobante
  function loadHistoryWithReceipts(filter, payments = []) {
    const tableBody = document.querySelector("#historyTable tbody")

    if (!tableBody) {
      console.log("No se encontró la tabla de historial")
      return
    }

    tableBody.innerHTML = ""
    console.log("Cargando historial con pagos:", payments)

    // Filtrar pagos según el tipo
    let filteredPayments = payments
    if (filter !== "all") {
      const now = new Date()
      if (filter === "monthly") {
        const oneMonthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate())
        filteredPayments = payments.filter((payment) => {
          const paymentDate = new Date(payment.fechaPago || payment.date)
          return paymentDate >= oneMonthAgo
        })
      } else if (filter === "weekly") {
        const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
        filteredPayments = payments.filter((payment) => {
          const paymentDate = new Date(payment.fechaPago || payment.date)
          return paymentDate >= oneWeekAgo
        })
      }
    }

    if (filteredPayments.length === 0) {
      const row = document.createElement("tr")
      row.innerHTML = `<td colspan="6" class="no-data">No hay datos disponibles</td>`
      tableBody.appendChild(row)
    } else {
      filteredPayments.forEach((payment) => {
        const row = document.createElement("tr")
        const status = payment.estado || payment.status || 0
        const statusText = getStatusText(status)
        const statusClass = getStatusClass(status)
        const paymentDate = payment.fechaPago || payment.date || "N/A"
        const paymentAmount = payment.montoPago || payment.amount || 0
        const transactionId = payment.idTransaccion || payment.transactionId
        const paymentId = payment.idPago || payment.id

        console.log("Procesando pago:", {
          paymentId,
          transactionId,
          status,
          amount: paymentAmount,
        })

        // Añadir botón de comprobante solo para pagos completados
        const receiptButton =
          status == 1 && transactionId
            ? `<button class="receipt-btn" data-transaction="${transactionId}" data-payment="${paymentId}">
             <i class="fas fa-receipt"></i> Comprobante
           </button>`
            : `<span class="no-receipt">-</span>`

        row.innerHTML = `
        <td>Pago #${paymentId || "N/A"}</td>
        <td>Pago de préstamo</td>
        <td>${formatDate(paymentDate)}</td>
        <td>${formatCurrency(paymentAmount)}</td>
        <td><span class="status ${statusClass}">${statusText}</span></td>
        <td class="actions">${receiptButton}</td>
      `
        tableBody.appendChild(row)
      })

      // Añadir event listeners a los botones de comprobante
      document.querySelectorAll(".receipt-btn").forEach((btn) => {
        btn.addEventListener("click", function () {
          const transactionId = this.getAttribute("data-transaction")
          const paymentId = this.getAttribute("data-payment")
          console.log("Mostrando comprobante para transacción:", transactionId, "pago:", paymentId)

          if (!transactionId) {
            showMessage("Error: ID de transacción no disponible")
            return
          }

          showPaymentReceipt(transactionId)
        })
      })
    }
  }

  // Función para mostrar comprobante de pago
  async function showPaymentReceipt(transactionId) {
    if (!transactionId) {
      showMessage("Error: ID de transacción no proporcionado")
      return
    }

    showLoading(true)

    try {
      console.log("Obteniendo comprobante para transacción:", transactionId)
      const response = await fetch(`PHP/get_payment_receipt.php?transactionId=${transactionId}`)

      if (!response.ok) {
        throw new Error(`Error HTTP: ${response.status}`)
      }

      const data = await response.json()

      if (!data.success) {
        throw new Error(data.error || "Error al obtener el comprobante")
      }

      console.log("Datos del comprobante recibidos:", data)

      // Crear modal para el comprobante
      const receiptModal = document.createElement("div")
      receiptModal.className = "modal"
      receiptModal.id = "receiptModal"
      receiptModal.style.display = "flex"

      receiptModal.innerHTML = `
        <div class="modal-content">
          <div class="modal-header">
            <h2>Comprobante de Pago</h2>
            <span class="close-modal">&times;</span>
          </div>
          <div class="modal-body" id="receiptContent">
            <div class="receipt">
              <div class="receipt-header">
                <h3>COMPROBANTE DE PAGO</h3>
                <p>Fecha: ${formatDate(data.receipt.date)}</p>
                <p>Referencia: ${data.receipt.reference}</p>
              </div>
              
              <div class="receipt-body">
                <div class="receipt-row">
                  <span class="receipt-label">Cliente:</span>
                  <span>${data.receipt.clientName}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Préstamo ID:</span>
                  <span>${data.receipt.loanId}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Transacción ID:</span>
                  <span>${data.receipt.transactionId}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Cuota Número:</span>
                  <span>${data.receipt.paymentNumber}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Fecha de Vencimiento:</span>
                  <span>${formatDate(data.receipt.dueDate)}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Monto Original:</span>
                  <span>${formatCurrency(data.receipt.originalAmount)}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Monto Pagado:</span>
                  <span>${formatCurrency(data.receipt.amount)}</span>
                </div>
                <div class="receipt-row">
                  <span class="receipt-label">Estado:</span>
                  <span>Completado</span>
                </div>
              </div>
              
              <div class="receipt-footer">
                <p>Gracias por su pago</p>
                <p>Este comprobante es válido como constancia de pago</p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="printReceiptBtn" class="btn btn-primary">
              <i class="fas fa-print"></i> Imprimir
            </button>
            <button id="closeReceiptBtn" class="btn btn-secondary">Cerrar</button>
          </div>
        </div>
      `

      // Añadir modal al documento
      document.body.appendChild(receiptModal)

      // Configurar event listeners
      document.querySelector(".close-modal").addEventListener("click", () => {
        receiptModal.remove()
      })

      document.getElementById("closeReceiptBtn").addEventListener("click", () => {
        receiptModal.remove()
      })

      document.getElementById("printReceiptBtn").addEventListener("click", () => {
        printReceipt(data.receipt)
      })

      // Cerrar modal al hacer clic fuera
      receiptModal.addEventListener("click", (e) => {
        if (e.target === receiptModal) {
          receiptModal.remove()
        }
      })
    } catch (error) {
      console.error("Error al obtener comprobante:", error)
      showMessage("Error al obtener el comprobante: " + error.message)
    } finally {
      showLoading(false)
    }
  }

  // Imprimir comprobante
  function printReceipt(receiptData) {
    const printWindow = window.open("", "_blank")
    printWindow.document.write(`
      <html>
        <head>
          <title>Comprobante de Pago - ${receiptData.reference}</title>
          <style>
            body { 
              font-family: Arial, sans-serif; 
              margin: 20px; 
              color: #333;
            }
            .receipt { 
              max-width: 800px; 
              margin: 0 auto; 
              border: 1px solid #ddd;
              padding: 20px;
            }
            .receipt-header { 
              text-align: center; 
              margin-bottom: 20px; 
              padding-bottom: 10px; 
              border-bottom: 1px dashed #ccc; 
            }
            .receipt-header h3 { 
              margin: 5px 0; 
              font-size: 24px;
            }
            .receipt-header p { 
              margin: 5px 0; 
              color: #666; 
            }
            .receipt-body { 
              margin-bottom: 20px; 
            }
            .receipt-row { 
              display: flex; 
              justify-content: space-between; 
              margin-bottom: 8px; 
              padding: 5px 0;
            }
            .receipt-label { 
              font-weight: bold; 
              width: 40%;
            }
            .receipt-value {
              width: 60%;
              text-align: right;
            }
            .receipt-footer { 
              text-align: center; 
              margin-top: 20px; 
              padding-top: 10px; 
              border-top: 1px dashed #ccc; 
              font-size: 12px; 
              color: #666; 
            }
            @media print {
              body { margin: 0; }
              .receipt { border: none; }
            }
          </style>
        </head>
        <body>
          <div class="receipt">
            <div class="receipt-header">
              <h3>COMPROBANTE DE PAGO</h3>
              <p>Fecha: ${formatDate(receiptData.date)}</p>
              <p>Referencia: ${receiptData.reference}</p>
            </div>
            
            <div class="receipt-body">
              <div class="receipt-row">
                <span class="receipt-label">Cliente:</span>
                <span class="receipt-value">${receiptData.clientName}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Préstamo ID:</span>
                <span class="receipt-value">${receiptData.loanId}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Transacción ID:</span>
                <span class="receipt-value">${receiptData.transactionId}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Cuota Número:</span>
                <span class="receipt-value">${receiptData.paymentNumber}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Fecha de Vencimiento:</span>
                <span class="receipt-value">${formatDate(receiptData.dueDate)}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Monto Original:</span>
                <span class="receipt-value">${formatCurrency(receiptData.originalAmount)}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Monto Pagado:</span>
                <span class="receipt-value">${formatCurrency(receiptData.amount)}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">Estado:</span>
                <span class="receipt-value">Completado</span>
              </div>
            </div>
            
            <div class="receipt-footer">
              <p>Gracias por su pago</p>
              <p>Este comprobante es válido como constancia de pago</p>
              <p>Generado el: ${formatDate(new Date())}</p>
            </div>
          </div>
          <script>
            window.onload = function() {
              setTimeout(function() {
                window.print();
                window.close();
              }, 500);
            };
          </script>
        </body>
      </html>
    `)
    printWindow.document.close()
  }

  // === CONFIGURACIÓN DE EVENTOS ===

  // Mostrar/ocultar el menú desplegable al hacer clic en el avatar
  if (userDropdown) {
    userDropdown.addEventListener("click", (e) => {
      e.preventDefault()
      e.stopPropagation() // Evitar que el clic se propague
      console.log("Click en userDropdown")

      // Verificar si el menú está visible
      const isVisible = dropdownContent.classList.contains("show")
      console.log("Menú visible antes:", isVisible)

      // Alternar la clase "show"
      if (isVisible) {
        dropdownContent.classList.remove("show")
        console.log("Menú ocultado")
      } else {
        dropdownContent.classList.add("show")
        console.log("Menú mostrado")
      }
    })
  }

  // Cerrar el menú al hacer clic en cualquier parte del documento
  document.addEventListener("click", (e) => {
    console.log("Click en documento")
    if (dropdownContent && dropdownContent.classList.contains("show")) {
      dropdownContent.classList.remove("show")
      console.log("Menú ocultado por clic en documento")
    }
  })

  // Evitar que el clic en el menú desplegable lo cierre
  if (dropdownContent) {
    dropdownContent.addEventListener("click", (e) => {
      e.stopPropagation() // Evitar que el clic se propague al documento
      console.log("Click en dropdownContent (propagación detenida)")
    })
  }

  // Evento para el botón de cerrar sesión
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault()
      e.stopPropagation() // Evitar que el clic se propague
      console.log("Click en logoutBtn - Redirigiendo a Index.html")

      // Redirección a Index.html
      window.location.href = "Index.html"
    })
  }

  // Evento para el botón de agregar cuenta
  if (addCardBtn) {
    addCardBtn.addEventListener("click", (e) => {
      e.preventDefault()
      console.log("Click en addCardBtn - Redirigiendo a FormularioCuenta.html")

      // Redirección a la página del formulario
      window.location.href = "FormularioCuenta.html"
    })
  }

  // Eventos para los elementos del menú lateral
  menuItems.forEach((item) => {
    item.addEventListener("click", function () {
      // Remover clase activa de todos los elementos
      menuItems.forEach((mi) => mi.classList.remove("active"))

      // Agregar clase activa al elemento seleccionado
      this.classList.add("active")

      // Obtener la sección seleccionada
      const section = this.getAttribute("data-section")
      console.log("Sección seleccionada:", section)

      // Manejar redirecciones
      if (section === "payments") {
        console.log("Redirigiendo a SolicitarPrestamo.html")
        window.location.href = "SolicitarPrestamo.html"
      } else if (section === "profile") {
        console.log("Redirigiendo a Perfil.html")
        window.location.href = "Perfil.html"
      } else if (section === "history") {
        console.log("Redirigiendo a Dudas.html")
        window.location.href = "Dudas.html"
      }
      // Aquí puedes agregar más redirecciones para otras secciones si es necesario
    })
  })

  // Eventos para los botones de filtro del historial
  filterBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      // Remover clase activa de todos los botones
      filterBtns.forEach((fb) => fb.classList.remove("active"))

      // Agregar clase activa al botón seleccionado
      this.classList.add("active")

      // Cargar historial según el filtro
      const filter = this.getAttribute("data-filter")
      loadHistoryWithReceipts(filter, userData?.payments || [])
    })
  })

  // Función para añadir estilos CSS para los comprobantes
  function addReceiptStyles() {
    // Verificar si ya existen los estilos
    if (document.getElementById("receipt-styles")) return

    const styleElement = document.createElement("style")
    styleElement.id = "receipt-styles"
    styleElement.textContent = `
      /* Estilos para botones de comprobante */
      .actions {
        text-align: center;
        padding: 8px;
      }
      
      .receipt-btn {
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 6px 12px;
        cursor: pointer;
        transition: background-color 0.3s;
        font-size: 14px;
      }
      
      .receipt-btn:hover {
        background-color: #45a049;
      }
      
      .receipt-btn i {
        margin-right: 4px;
      }
      
      .no-receipt {
        color: #999;
        font-style: italic;
      }
      
      /* Estilos para el modal */
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
      }
      
      .modal-content {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalFadeIn 0.3s;
      }
      
      @keyframes modalFadeIn {
        from {
          opacity: 0;
          transform: translateY(-20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        background-color: #f8f9fa;
      }
      
      .modal-header h2 {
        margin: 0;
        font-size: 20px;
        color: #333;
      }
      
      .close-modal {
        font-size: 24px;
        cursor: pointer;
        color: #aaa;
        transition: color 0.3s;
      }
      
      .close-modal:hover {
        color: #333;
      }
      
      .modal-body {
        padding: 20px;
      }
      
      .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background-color: #f8f9fa;
      }
      
      /* Estilos para el comprobante */
      .receipt {
        font-family: 'Courier New', Courier, monospace;
        padding: 20px;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
      }
      
      .receipt-header {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px dashed #ccc;
      }
      
      .receipt-header h3 {
        margin: 5px 0;
        font-size: 22px;
        color: #333;
      }
      
      .receipt-header p {
        margin: 5px 0;
        color: #666;
        font-size: 14px;
      }
      
      .receipt-body {
        margin-bottom: 20px;
      }
      
      .receipt-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding: 3px 0;
      }
      
      .receipt-label {
        font-weight: bold;
        color: #333;
      }
      
      .receipt-footer {
        text-align: center;
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
        font-size: 12px;
        color: #666;
      }
      
      /* Estilos para botones */
      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
      }
      
      .btn-primary {
        background-color: #007bff;
        color: white;
      }
      
      .btn-primary:hover {
        background-color: #0056b3;
      }
      
      .btn-secondary {
        background-color: #6c757d;
        color: white;
      }
      
      .btn-secondary:hover {
        background-color: #5a6268;
      }
      
      /* Estilos para estados */
      .status {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
      }
      
      .status.pending {
        background-color: #fff3cd;
        color: #856404;
      }
      
      .status.completed {
        background-color: #d4edda;
        color: #155724;
      }
      
      .status.overdue {
        background-color: #f8d7da;
        color: #721c24;
      }
      
      .status.cancelled {
        background-color: #e2e3e5;
        color: #383d41;
      }
      
      .status.unknown {
        background-color: #f8f9fa;
        color: #6c757d;
      }
      
      /* Estilos para botón de pago deshabilitado */
      .payment-btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
      
      /* Estilos para tabla */
      .no-data {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 20px;
      }
      
      /* Estilos para filtros activos */
      .filter-btn.active {
        background-color: #007bff;
        color: white;
      }
      
      /* Responsive */
      @media (max-width: 768px) {
        .modal-content {
          width: 95%;
          margin: 10px;
        }
        
        .receipt-row {
          flex-direction: column;
          gap: 2px;
        }
        
        .receipt-label {
          font-size: 12px;
        }
        
        .modal-footer {
          flex-direction: column;
        }
        
        .btn {
          width: 100%;
          justify-content: center;
        }
      }
      
      /* Estilos para impresión */
      @media print {
        body * {
          visibility: hidden;
        }
        
        #receiptContent,
        #receiptContent * {
          visibility: visible;
        }
        
        #receiptContent {
          position: absolute;
          left: 0;
          top: 0;
          width: 100%;
        }
        
        .modal-header,
        .modal-footer {
          display: none !important;
        }
      }
    `

    document.head.appendChild(styleElement)
  }

  // === INICIALIZACIÓN ===

  // Función para inicializar la aplicación
  async function init() {
    console.log("Inicializando aplicación...")

    // Añadir estilos CSS para los comprobantes
    addReceiptStyles()

    // Verificar autenticación primero
    const isAuthenticated = await checkAuth()
    if (isAuthenticated) {
      console.log("Usuario autenticado, cargando datos...")
      // Cargar datos del usuario
      await loadUserData()
    }
  }

  // Iniciar la aplicación
  init()

  console.log("=== SCRIPT COMPLETAMENTE CARGADO ===")
})
