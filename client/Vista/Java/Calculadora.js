document.addEventListener("DOMContentLoaded", () => {
  console.log("=== CALCULADORA DE PRÉSTAMOS INICIADA ===")

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
  const errorMessage = document.getElementById("error-message")

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
    console.log("Inicializando calculadora...")
    await cargarPlanes()
    setupEventListeners()
    actualizarCalculadora()
  }

  // Cargar planes desde la base de datos (sidebar independiente)
  async function cargarPlanes() {
    showLoading(true)

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

      showLoading(false)
    } catch (error) {
      console.error("Error al cargar planes:", error)
      showLoading(false)
      showMessage("Error al cargar los planes: " + error.message)

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

  // Renderizar planes en la sidebar (independientes de la calculadora)
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

  // Actualizar cálculos de la calculadora (independiente de los planes)
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
  function showMessage(message, isError = true) {
    if (errorMessage) {
      errorMessage.textContent = message
      errorMessage.style.display = "block"
      errorMessage.style.backgroundColor = isError ? "#e74c3c" : "#27ae60"

      setTimeout(() => {
        errorMessage.style.display = "none"
      }, 5000)
    }
  }

  // Funciones globales
  window.solicitarPlan = (planId, planName) => {
    console.log(`Solicitando plan: ${planName} (ID: ${planId})`)
    alert(`Serás redirigido al login para solicitar el ${planName}`)
    window.location.href = "Login.html"
  }

  window.solicitarPrestamoPersonalizado = () => {
    const monto = Number.parseFloat(montoInput.value)
    const tipo = tipoPrestamoSelect.value
    const meses = Number.parseInt(periodoInput.value)

    console.log(`Solicitando préstamo personalizado: $${monto}, ${tipo}, ${meses} meses`)
    alert("Serás redirigido al login para solicitar tu préstamo personalizado")
    window.location.href = "Login.html"
  }

  window.resetCalculator = () => {
    montoInput.value = 10000
    periodoInput.value = 12
    tipoPrestamoSelect.value = "personal"

    actualizarModalidadInfo()
    actualizarCalculadora()
  }

  window.redirectToLogin = () => {
    window.location.href = "Login.html"
  }

  // Inicializar información de modalidad
  actualizarModalidadInfo()

  console.log("=== CALCULADORA COMPLETAMENTE CARGADA ===")
})
