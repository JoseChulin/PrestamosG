document.addEventListener("DOMContentLoaded", () => {
  // Referencias a elementos del DOM
  const bankSelect = document.getElementById("bankName")
  const accountInput = document.getElementById("accountNumber")
  const holderInput = document.getElementById("accountHolder")
  const previewNumber = document.getElementById("previewNumber")
  const previewName = document.getElementById("previewName")
  const previewBank = document.getElementById("previewBank")
  const accountForm = document.getElementById("accountForm")
  const errorMessage = document.getElementById("errorMessage")
  const successMessage = document.getElementById("successMessage")
  const loading = document.getElementById("loading")

  // Inicialización
  console.log("Formulario de cuenta bancaria iniciado")
  checkAuth()
  loadUserData()
  setupEventListeners()

  // Configurar event listeners
  function setupEventListeners() {
    // Actualizar número de cuenta en la vista previa
    accountInput.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "")

      // Limitar a 20 dígitos
      if (value.length > 20) {
        value = value.substring(0, 20)
        this.value = value
      }

      // Formatear para mostrar
      let formatted = ""
      for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) {
          formatted += " "
        }
        formatted += value[i]
      }

      // Completar con asteriscos
      while (formatted.length < 19) {
        if (formatted.length > 0 && formatted.length % 5 === 0) {
          formatted += " "
        }
        formatted += "*"
      }

      previewNumber.textContent = formatted
    })

    // Actualizar nombre del titular en la vista previa
    holderInput.addEventListener("input", function () {
      previewName.textContent = this.value.toUpperCase() || "NOMBRE DEL TITULAR"
    })

    // Actualizar banco en la vista previa
    bankSelect.addEventListener("change", function () {
      previewBank.textContent = this.value || "BANCO"
    })

    // Manejar envío del formulario
    accountForm.addEventListener("submit", (e) => {
      e.preventDefault()

      const accountNumber = accountInput.value.replace(/\D/g, "")

      // Validaciones del lado del cliente
      if (!bankSelect.value) {
        showMessage(errorMessage, "Por favor seleccione un banco")
        return
      }

      if (accountNumber.length < 16 || accountNumber.length > 20) {
        showMessage(errorMessage, "El número de cuenta debe tener entre 16 y 20 dígitos")
        return
      }

      if (!holderInput.value.trim()) {
        showMessage(errorMessage, "Por favor ingrese el nombre del titular")
        return
      }

      saveAccount()
    })
  }

  // Función para verificar autenticación
  async function checkAuth() {
    try {
      const response = await fetch("PHP/check_auth.php")
      const data = await response.json()

      console.log("Respuesta de autenticación:", data)

      if (!data.authenticated) {
        console.log("Usuario no autenticado, redirigiendo...")
        alert("Sesión expirada. Será redirigido al login.")
        window.location.href = "Login.html"
        return false
      }

      return true
    } catch (error) {
      console.error("Error verificando autenticación:", error)
      alert("Error de conexión. Será redirigido al login.")
      window.location.href = "Login.html"
      return false
    }
  }

  // Función para cargar datos del usuario
  async function loadUserData() {
    showLoading(true)

    try {
      const response = await fetch("PHP/user_data.php")

      if (!response.ok) {
        throw new Error(`Error HTTP: ${response.status}`)
      }

      const data = await response.json()

      console.log("Datos del usuario:", data)

      if (data.error) {
        throw new Error(data.error)
      }

      // Prellenar el formulario con los datos existentes
      if (data.user) {
        if (data.user.name) {
          holderInput.value = data.user.name
          previewName.textContent = data.user.name.toUpperCase()
        }

        if (data.user.account) {
          if (data.user.account.bank) {
            bankSelect.value = data.user.account.bank
            previewBank.textContent = data.user.account.bank
          }

          if (data.user.account.number) {
            accountInput.value = data.user.account.number

            // Actualizar vista previa
            let formatted = ""
            const value = data.user.account.number
            for (let i = 0; i < value.length; i++) {
              if (i > 0 && i % 4 === 0) {
                formatted += " "
              }
              formatted += value[i]
            }
            previewNumber.textContent = formatted
          }
        }
      }

      showLoading(false)
    } catch (error) {
      console.error("Error al cargar datos:", error)
      showLoading(false)
      showMessage(errorMessage, "Error al cargar los datos del usuario: " + error.message)
    }
  }

  // Función para guardar la cuenta bancaria
  async function saveAccount() {
    showLoading(true)

    const data = {
      bank: bankSelect.value,
      accountNumber: accountInput.value.replace(/\D/g, ""),
      accountHolder: holderInput.value.trim(),
    }

    console.log("Enviando datos:", data)

    try {
      const response = await fetch("PHP/save_account.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      })

      console.log("Respuesta del servidor:", response.status)

      if (!response.ok) {
        const errorText = await response.text()
        console.error("Error del servidor:", errorText)
        throw new Error(`Error del servidor: ${response.status}`)
      }

      const result = await response.json()
      console.log("Resultado:", result)

      if (result.success) {
        showMessage(successMessage, result.message || "Cuenta bancaria guardada correctamente", false)

        // Redireccionar después de 2 segundos
        setTimeout(() => {
          window.location.href = "PanelUsuario.html"
        }, 2000)
      } else {
        throw new Error(result.message || "Error al guardar la cuenta bancaria")
      }
    } catch (error) {
      console.error("Error:", error)
      showMessage(errorMessage, error.message || "Error al guardar la cuenta bancaria")
    } finally {
      showLoading(false)
    }
  }

  // Función para mostrar mensajes
  function showMessage(element, message, isError = true) {
    // Limpiar mensajes anteriores
    errorMessage.style.display = "none"
    successMessage.style.display = "none"

    element.textContent = message
    element.style.display = "block"

    // Ocultar después de 5 segundos
    setTimeout(() => {
      element.style.display = "none"
    }, 5000)
  }

  // Función para mostrar/ocultar el spinner de carga
  function showLoading(show) {
    if (loading) {
      loading.style.display = show ? "flex" : "none"
    }
  }
})
