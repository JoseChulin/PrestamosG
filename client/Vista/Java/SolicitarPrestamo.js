document.addEventListener("DOMContentLoaded", () => {
  console.log("=== SOLICITAR PRÉSTAMO INICIADO ===")

  // Referencias a elementos del DOM
  const montoInput = document.getElementById("monto")
  const periodoInput = document.getElementById("periodo")
  const tipoPrestamoSelect = document.getElementById("tipo-prestamo")
  const montoValor = document.getElementById("monto-valor")
  const periodoValor = document.getElementById("periodo-valor")
  const pagoMensual = document.getElementById("pago-mensual")
  const prestado = document.getElementById("prestado")
  const totalPagar = document.getElementById("total-pagar")
  const tasaInteres = document.getElementById("tasa-interes")
  const modalidadInfo = document.getElementById("modalidadInfo")
  const plansContainer = document.getElementById("plansContainer")
  const loadingSpinner = document.getElementById("loading-spinner")
  const message = document.getElementById("message")
  const solicitarPersonalizadoBtn = document.getElementById("solicitarPersonalizadoBtn")

  // Información de modalidades
  const modalidades = {
    personal: {
      tasa: 12,
      descripcion: "Ideal para gastos personales, vacaciones, emergencias médicas, consolidación de deudas, etc.",
    },
    automovil: {
      tasa: 8,
      descripcion: "Perfecto para la compra de vehículos nuevos o usados, con tasas preferenciales.",
    },
    hipotecario: {
      tasa: 6,
      descripcion: "Diseñado para la compra de vivienda, con las mejores tasas del mercado.",
    },
  }

  // Inicialización
  init()

  async function init() {
    console.log("Inicializando solicitud de préstamo...")

    // Verificar autenticación
    const isAuthenticated = await checkAuth()
    if (!isAuthenticated) {
      return
    }

    // Verificar préstamos existentes
    const canRequest = await checkExistingLoan()
    if (!canRequest) {
      return
    }

    await cargarPlanes()
    setupEventListeners()
    actualizarCalculadora()
  }

  // Verificar autenticación
  async function checkAuth() {
    try {
      const response = await fetch("PHP/check_auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        showMessage("Debes iniciar sesión para solicitar un préstamo", true)
        setTimeout(() => {
          window.location.href = "Login.html"
        }, 2000)
        return false
      }
      return true
    } catch (error) {
      console.error("Error verificando autenticación:", error)
      showMessage("Error de conexión. Redirigiendo al login...", true)
      setTimeout(() => {
        window.location.href = "Login.html"
      }, 2000)
      return false
    }
  }

  // Verificar préstamos existentes
  async function checkExistingLoan() {
    showLoading(true)

    try {
      const response = await fetch("PHP/check_existing_loan.php")
      const data = await response.json()

      if (!data.success && data.hasActiveLoan) {
        showMessage(data.message, true)

        // Deshabilitar botones
        disableAllButtons()

        showLoading(false)
        return false
      }

      showLoading(false)
      return true
    } catch (error) {
      console.error("Error verificando préstamos existentes:", error)
      showMessage("Error al verificar préstamos existentes", true)
      showLoading(false)
      return false
    }
  }

  // Deshabilitar todos los botones
  function disableAllButtons() {
    if (solicitarPersonalizadoBtn) {
      solicitarPersonalizadoBtn.disabled = true
      solicitarPersonalizadoBtn.classList.add("disabled")
      solicitarPersonalizadoBtn.innerHTML = '<i class="fas fa-ban"></i> No disponible'
    }

    // Deshabilitar botones de planes cuando se carguen
    setTimeout(() => {
      document.querySelectorAll(".plan-btn").forEach((btn) => {
        btn.disabled = true
        btn.classList.add("disabled")
        btn.innerHTML = '<i class="fas fa-ban"></i> No disponible'
      })
    }, 1000)
  }

  // Cargar planes desde la base de datos
  async function cargarPlanes() {
    try {
      const response = await fetch("PHP/get_loan_plans.php")

      if (!response.ok) {
        throw new Error(`Error HTTP: ${response.status}`)
      }

      const data = await response.json()
      console.log("Planes recibidos:", data)

      if (data.error) {
        throw new Error(data.error)
      }

      const planes = data.plans || []
      renderPlanes(planes)
    } catch (error) {
      console.error("Error al cargar planes:", error)
      showMessage("Error al cargar los planes: " + error.message, true)

      // Fallback con planes por defecto
      const planesFallback = [
        {
          idPlan: 1,
          nombrePlan: "Plan Básico",
          tasaInteres: 10,
          duracion: 12,
          monto: 10000,
          descripcion: "Préstamo básico a 12 meses con tasa fija",
        },
        {
          idPlan: 2,
          nombrePlan: "Plan Intermedio",
          tasaInteres: 8,
          duracion: 24,
          monto: 50000,
          descripcion: "Préstamo intermedio a 24 meses con tasa fija",
        },
        {
          idPlan: 3,
          nombrePlan: "Plan Avanzado",
          tasaInteres: 6,
          duracion: 36,
          monto: 100000,
          descripcion: "Préstamo avanzado a 36 meses con tasa fija",
        },
        {
          idPlan: 4,
          nombrePlan: "Plan Emergencia",
          tasaInteres: 15,
          duracion: 6,
          monto: 5000,
          descripcion: "Préstamo rápido para emergencias",
        },
      ]
      renderPlanes(planesFallback)
    }
  }

  // Renderizar planes en la sidebar
  function renderPlanes(planes) {
    if (planes.length === 0) {
      plansContainer.innerHTML = `
        <div class="loading-plans">
          <i class="fas fa-exclamation-triangle"></i>
          <p>No hay planes disponibles</p>
        </div>
      `
      return
    }

    plansContainer.innerHTML = planes
      .map(
        (plan) => `
      <div class="plan-card">
        <div class="plan-header">
          <div class="plan-name">${plan.nombrePlan}</div>
          <div class="plan-badge">${plan.tasaInteres}%</div>
        </div>
        <div class="plan-details">
          <div class="plan-detail">
            <span class="plan-detail-label">Monto máximo:</span>
            <span class="plan-detail-value">$${formatCurrency(plan.monto)}</span>
          </div>
          <div class="plan-detail">
            <span class="plan-detail-label">Plazo:</span>
            <span class="plan-detail-value">${plan.duracion} meses</span>
          </div>
          <div class="plan-detail">
            <span class="plan-detail-label">Tasa anual:</span>
            <span class="plan-detail-value">${plan.tasaInteres}%</span>
          </div>
        </div>
        <div class="plan-description">${plan.descripcion}</div>
        <button class="plan-btn" onclick="solicitarPlan(${plan.idPlan}, '${plan.nombrePlan}')">
          <i class="fas fa-paper-plane"></i>
          Solicitar Plan
        </button>
      </div>
    `,
      )
      .join("")
  }

  // Configurar event listeners para la calculadora
  function setupEventListeners() {
    montoInput.addEventListener("input", actualizarCalculadora)
    periodoInput.addEventListener("input", actualizarCalculadora)
    tipoPrestamoSelect.addEventListener("change", () => {
      actualizarModalidadInfo()
      actualizarCalculadora()
    })

    // Event listener para solicitar préstamo personalizado
    if (solicitarPersonalizadoBtn) {
      solicitarPersonalizadoBtn.addEventListener("click", solicitarPrestamoPersonalizado)
    }
  }

  // Actualizar información de la modalidad seleccionada
  function actualizarModalidadInfo() {
    const tipo = tipoPrestamoSelect.value
    const modalidad = modalidades[tipo]

    if (modalidad) {
      modalidadInfo.innerHTML = `
        <p><strong>${tipo.charAt(0).toUpperCase() + tipo.slice(1)}:</strong> ${modalidad.descripcion}</p>
      `
    }
  }

  // Actualizar cálculos de la calculadora
  function actualizarCalculadora() {
    const monto = Number.parseFloat(montoInput.value)
    const meses = Number.parseInt(periodoInput.value)
    const tipo = tipoPrestamoSelect.value
    const modalidad = modalidades[tipo]

    // Actualizar displays
    montoValor.textContent = `$${formatCurrency(monto)}`
    periodoValor.textContent = `${meses} ${meses === 1 ? "mes" : "meses"}`
    prestado.textContent = `$${formatCurrency(monto)}`
    tasaInteres.textContent = `${modalidad.tasa}%`

    // Calcular préstamo con interés simple
    const tasaAnual = modalidad.tasa / 100
    const totalConInteres = monto * (1 + tasaAnual * (meses / 12))
    const pagoMensualCalculado = totalConInteres / meses

    // Actualizar resultados
    pagoMensual.textContent = `$${formatCurrency(pagoMensualCalculado)}`
    totalPagar.textContent = `$${formatCurrency(totalConInteres)}`
  }

  // Solicitar plan fijo
  async function solicitarPlan(planId, planName) {
    console.log(`Solicitando plan: ${planName} (ID: ${planId})`)

    if (!confirm(`¿Estás seguro de que deseas solicitar el ${planName}?`)) {
      return
    }

    showLoading(true)

    try {
      const response = await fetch("PHP/request_fixed_plan.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          planId: planId,
        }),
      })

      const data = await response.json()

      if (data.success) {
        showMessage(
          `¡Solicitud enviada exitosamente! Tu ${planName} por $${formatCurrency(data.amount)} ha sido procesado.`,
          false,
        )

        // Redirigir al panel después de 3 segundos
        setTimeout(() => {
          window.location.href = "PanelUsuario.html"
        }, 3000)
      } else {
        showMessage(data.message || "Error al procesar la solicitud", true)
      }
    } catch (error) {
      console.error("Error al solicitar plan:", error)
      showMessage("Error de conexión al procesar la solicitud", true)
    } finally {
      showLoading(false)
    }
  }

  // Solicitar préstamo personalizado
  async function solicitarPrestamoPersonalizado() {
    const monto = Number.parseFloat(montoInput.value)
    const tipo = tipoPrestamoSelect.value
    const meses = Number.parseInt(periodoInput.value)

    console.log(`Solicitando préstamo personalizado: $${monto}, ${tipo}, ${meses} meses`)

    if (
      !confirm(
        `¿Estás seguro de que deseas solicitar un préstamo ${tipo} por $${formatCurrency(monto)} a ${meses} meses?`,
      )
    ) {
      return
    }

    showLoading(true)

    try {
      const response = await fetch("PHP/request_custom_loan.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          monto: monto,
          tipo: tipo,
          meses: meses,
        }),
      })

      const data = await response.json()

      if (data.success) {
        showMessage(
          `¡Solicitud enviada exitosamente! Tu préstamo personalizado por $${formatCurrency(data.amount)} ha sido procesado.`,
          false,
        )

        // Redirigir al panel después de 3 segundos
        setTimeout(() => {
          window.location.href = "PanelUsuario.html"
        }, 3000)
      } else {
        showMessage(data.message || "Error al procesar la solicitud", true)
      }
    } catch (error) {
      console.error("Error al solicitar préstamo personalizado:", error)
      showMessage("Error de conexión al procesar la solicitud", true)
    } finally {
      showLoading(false)
    }
  }

  // Formatear moneda
  function formatCurrency(amount) {
    return new Intl.NumberFormat("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount)
  }

  // Mostrar/ocultar spinner de carga
  function showLoading(show) {
    if (loadingSpinner) {
      loadingSpinner.style.display = show ? "flex" : "none"
    }
  }

  // Mostrar mensajes
  function showMessage(text, isError = true) {
    if (message) {
      message.textContent = text
      message.className = `message ${isError ? "error" : "success"}`
      message.style.display = "block"

      setTimeout(() => {
        message.style.display = "none"
      }, 5000)
    }
  }

  // Funciones globales
  window.solicitarPlan = solicitarPlan

  window.resetCalculator = () => {
    montoInput.value = 10000
    periodoInput.value = 12
    tipoPrestamoSelect.value = "personal"

    actualizarModalidadInfo()
    actualizarCalculadora()
  }

  window.redirectToPanel = () => {
    window.location.href = "PanelUsuario.html"
  }

  // Inicializar información de modalidad
  actualizarModalidadInfo()

  console.log("=== SOLICITAR PRÉSTAMO COMPLETAMENTE CARGADO ===")
})
