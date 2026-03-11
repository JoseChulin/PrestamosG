document.addEventListener("DOMContentLoaded", () => {
  console.log("=== PERFIL DE USUARIO INICIADO ===")

  // Referencias a elementos del DOM
  const loadingSpinner = document.getElementById("loading-spinner")
  const message = document.getElementById("message")
  const profileForm = document.getElementById("profileForm")
  const editBtn = document.getElementById("editBtn")
  const cancelBtn = document.getElementById("cancelBtn")
  const formActions = document.getElementById("formActions")
  const passwordSection = document.getElementById("passwordSection")

  // Referencias a campos del formulario
  const profileAvatar = document.getElementById("profileAvatar")
  const profileName = document.getElementById("profileName")
  const profileEmail = document.getElementById("profileEmail")

  // Campos del formulario
  const nombreCliente = document.getElementById("nombreCliente")
  const apellidoP = document.getElementById("apellidoP")
  const apellidoM = document.getElementById("apellidoM")
  const telefono = document.getElementById("telefono")
  const nombreUsuario = document.getElementById("nombreUsuario")
  const correo = document.getElementById("correo")
  const fechaRegistro = document.getElementById("fechaRegistro")

  // Campos de contraseña
  const currentPassword = document.getElementById("currentPassword")
  const newPassword = document.getElementById("newPassword")
  const confirmPassword = document.getElementById("confirmPassword")

  // Variables globales
  let isEditing = false
  let originalData = {}

  // Inicialización
  init()

  async function init() {
    console.log("Inicializando perfil de usuario...")

    // Verificar autenticación
    const isAuthenticated = await checkAuth()
    if (isAuthenticated) {
      await loadUserProfile()
      setupEventListeners()
    }
  }

  // Verificar autenticación
  async function checkAuth() {
    try {
      const response = await fetch("PHP/check_auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        showMessage("Debes iniciar sesión para ver tu perfil", "error")
        setTimeout(() => {
          window.location.href = "Login.html"
        }, 2000)
        return false
      }
      return true
    } catch (error) {
      console.error("Error verificando autenticación:", error)
      showMessage("Error de conexión. Redirigiendo al login...", "error")
      setTimeout(() => {
        window.location.href = "Login.html"
      }, 2000)
      return false
    }
  }

  // Cargar datos del perfil del usuario
  async function loadUserProfile() {
    showLoading(true)

    try {
      const response = await fetch("PHP/get_user_profile.php")

      if (!response.ok) {
        throw new Error(`Error HTTP: ${response.status}`)
      }

      const data = await response.json()
      console.log("Datos del perfil recibidos:", data)

      if (!data.success) {
        throw new Error(data.message || "Error al cargar el perfil")
      }

      // Guardar datos originales
      originalData = { ...data.profile }

      // Actualizar la interfaz
      updateProfileInterface(data.profile)

      showLoading(false)
    } catch (error) {
      console.error("Error al cargar perfil:", error)
      showMessage("Error al cargar el perfil: " + error.message, "error")
      showLoading(false)
    }
  }

  // Actualizar la interfaz con los datos del perfil
  function updateProfileInterface(profile) {
    // Actualizar header del perfil
    const fullName = `${profile.nombreCliente} ${profile.apellidoP} ${profile.apellidoM}`.trim()
    profileName.textContent = fullName
    profileEmail.textContent = profile.correo

    // Actualizar avatar con inicial del nombre
    profileAvatar.textContent = profile.nombreCliente.charAt(0).toUpperCase()

    // Llenar formulario
    nombreCliente.value = profile.nombreCliente || ""
    apellidoP.value = profile.apellidoP || ""
    apellidoM.value = profile.apellidoM || ""
    telefono.value = profile.telefono || ""
    nombreUsuario.value = profile.nombreUsuario || ""
    correo.value = profile.correo || ""
    fechaRegistro.value = formatDate(profile.fechaRegistro) || ""
  }

  // Configurar event listeners
  function setupEventListeners() {
    // Botón editar
    editBtn.addEventListener("click", toggleEditMode)

    // Botón cancelar
    cancelBtn.addEventListener("click", cancelEdit)

    // Formulario
    profileForm.addEventListener("submit", handleFormSubmit)

    // Botones para mostrar/ocultar contraseñas
    document.querySelectorAll(".toggle-password").forEach((btn) => {
      btn.addEventListener("click", togglePasswordVisibility)
    })

    // Validación en tiempo real
    newPassword.addEventListener("input", validatePassword)
    confirmPassword.addEventListener("input", validatePasswordConfirmation)
  }

  // Alternar modo de edición
  function toggleEditMode() {
    isEditing = !isEditing

    if (isEditing) {
      enableEditMode()
    } else {
      disableEditMode()
    }
  }

  // Habilitar modo de edición
  function enableEditMode() {
    console.log("Habilitando modo de edición")

    // Habilitar campos editables
    nombreCliente.removeAttribute("readonly")
    apellidoP.removeAttribute("readonly")
    apellidoM.removeAttribute("readonly")
    telefono.removeAttribute("readonly")
    nombreUsuario.removeAttribute("readonly")
    correo.removeAttribute("readonly")

    // Mostrar sección de contraseña y botones de acción
    passwordSection.style.display = "block"
    formActions.style.display = "flex"

    // Cambiar texto del botón
    editBtn.innerHTML = '<i class="fas fa-times"></i> Cancelar Edición'
    editBtn.classList.remove("btn-primary")
    editBtn.classList.add("btn-secondary")

    showMessage("Modo de edición activado. Puedes modificar tus datos.", "info")
  }

  // Deshabilitar modo de edición
  function disableEditMode() {
    console.log("Deshabilitando modo de edición")

    // Deshabilitar campos
    nombreCliente.setAttribute("readonly", true)
    apellidoP.setAttribute("readonly", true)
    apellidoM.setAttribute("readonly", true)
    telefono.setAttribute("readonly", true)
    nombreUsuario.setAttribute("readonly", true)
    correo.setAttribute("readonly", true)

    // Ocultar sección de contraseña y botones de acción
    passwordSection.style.display = "none"
    formActions.style.display = "none"

    // Limpiar campos de contraseña
    currentPassword.value = ""
    newPassword.value = ""
    confirmPassword.value = ""

    // Restaurar texto del botón
    editBtn.innerHTML = '<i class="fas fa-edit"></i> Editar Perfil'
    editBtn.classList.remove("btn-secondary")
    editBtn.classList.add("btn-primary")

    // Restaurar datos originales
    updateProfileInterface(originalData)
  }

  // Cancelar edición
  function cancelEdit() {
    if (confirm("¿Estás seguro de que deseas cancelar? Se perderán los cambios no guardados.")) {
      isEditing = false
      disableEditMode()
    }
  }

  // Manejar envío del formulario
  async function handleFormSubmit(e) {
    e.preventDefault()

    if (!isEditing) return

    // Validar formulario
    if (!validateForm()) {
      return
    }

    showLoading(true)

    try {
      const formData = {
        nombreCliente: nombreCliente.value.trim(),
        apellidoP: apellidoP.value.trim(),
        apellidoM: apellidoM.value.trim(),
        telefono: telefono.value.trim(),
        nombreUsuario: nombreUsuario.value.trim(),
        correo: correo.value.trim(),
        currentPassword: currentPassword.value,
        newPassword: newPassword.value,
      }

      console.log("Enviando datos del formulario:", formData)

      const response = await fetch("PHP/update_user_profile.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify(formData),
      })

      const data = await response.json()

      if (data.success) {
        showMessage("Perfil actualizado correctamente", "success")

        // Actualizar datos originales
        originalData = { ...formData }

        // Deshabilitar modo de edición
        isEditing = false
        disableEditMode()

        // Recargar datos del perfil
        setTimeout(() => {
          loadUserProfile()
        }, 1000)
      } else {
        showMessage(data.message || "Error al actualizar el perfil", "error")
      }
    } catch (error) {
      console.error("Error al actualizar perfil:", error)
      showMessage("Error de conexión al actualizar el perfil", "error")
    } finally {
      showLoading(false)
    }
  }

  // Validar formulario
  function validateForm() {
    let isValid = true

    // Validar campos requeridos
    if (!nombreCliente.value.trim()) {
      showFieldError(nombreCliente, "El nombre es requerido")
      isValid = false
    }

    if (!apellidoP.value.trim()) {
      showFieldError(apellidoP, "El apellido paterno es requerido")
      isValid = false
    }

    if (!nombreUsuario.value.trim()) {
      showFieldError(nombreUsuario, "El nombre de usuario es requerido")
      isValid = false
    }

    if (!correo.value.trim()) {
      showFieldError(correo, "El correo es requerido")
      isValid = false
    } else if (!isValidEmail(correo.value)) {
      showFieldError(correo, "El correo no tiene un formato válido")
      isValid = false
    }

    // Validar teléfono si se proporciona
    if (telefono.value.trim() && !isValidPhone(telefono.value)) {
      showFieldError(telefono, "El teléfono debe tener 10 dígitos")
      isValid = false
    }

    // Validar contraseña si se está cambiando
    if (newPassword.value) {
      if (!currentPassword.value) {
        showFieldError(currentPassword, "Debes ingresar tu contraseña actual")
        isValid = false
      }

      if (!isValidPassword(newPassword.value)) {
        showFieldError(newPassword, "La contraseña debe tener al menos 8 caracteres")
        isValid = false
      }

      if (newPassword.value !== confirmPassword.value) {
        showFieldError(confirmPassword, "Las contraseñas no coinciden")
        isValid = false
      }
    }

    return isValid
  }

  // Mostrar error en campo
  function showFieldError(field, message) {
    field.classList.add("invalid")
    field.classList.remove("valid")

    // Remover mensaje anterior
    const existingMessage = field.parentNode.querySelector(".validation-message")
    if (existingMessage) {
      existingMessage.remove()
    }

    // Agregar nuevo mensaje
    const messageElement = document.createElement("div")
    messageElement.className = "validation-message show"
    messageElement.textContent = message
    field.parentNode.appendChild(messageElement)

    // Remover mensaje después de 3 segundos
    setTimeout(() => {
      messageElement.classList.remove("show")
      setTimeout(() => {
        if (messageElement.parentNode) {
          messageElement.remove()
        }
      }, 300)
    }, 3000)
  }

  // Validar email
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  // Validar teléfono
  function isValidPhone(phone) {
    const phoneRegex = /^\d{10}$/
    return phoneRegex.test(phone.replace(/\D/g, ""))
  }

  // Validar contraseña
  function isValidPassword(password) {
    return password.length >= 8
  }

  // Validar contraseña en tiempo real
  function validatePassword() {
    const password = newPassword.value

    if (password) {
      if (isValidPassword(password)) {
        newPassword.classList.add("valid")
        newPassword.classList.remove("invalid")
      } else {
        newPassword.classList.add("invalid")
        newPassword.classList.remove("valid")
      }
    } else {
      newPassword.classList.remove("valid", "invalid")
    }
  }

  // Validar confirmación de contraseña
  function validatePasswordConfirmation() {
    const password = newPassword.value
    const confirmation = confirmPassword.value

    if (confirmation) {
      if (password === confirmation) {
        confirmPassword.classList.add("valid")
        confirmPassword.classList.remove("invalid")
      } else {
        confirmPassword.classList.add("invalid")
        confirmPassword.classList.remove("valid")
      }
    } else {
      confirmPassword.classList.remove("valid", "invalid")
    }
  }

  // Alternar visibilidad de contraseña
  function togglePasswordVisibility(e) {
    const targetId = e.target.closest(".toggle-password").getAttribute("data-target")
    const targetInput = document.getElementById(targetId)
    const icon = e.target.closest(".toggle-password").querySelector("i")

    if (targetInput.type === "password") {
      targetInput.type = "text"
      icon.classList.remove("fa-eye")
      icon.classList.add("fa-eye-slash")
    } else {
      targetInput.type = "password"
      icon.classList.remove("fa-eye-slash")
      icon.classList.add("fa-eye")
    }
  }

  // Formatear fecha
  function formatDate(dateString) {
    if (!dateString) return ""

    const date = new Date(dateString)
    if (isNaN(date.getTime())) return dateString

    return date.toLocaleDateString("es-MX", {
      year: "numeric",
      month: "long",
      day: "numeric",
    })
  }

  // Mostrar/ocultar spinner de carga
  function showLoading(show) {
    if (loadingSpinner) {
      loadingSpinner.style.display = show ? "flex" : "none"
    }
  }

  // Mostrar mensajes
  function showMessage(text, type = "info") {
    if (message) {
      message.textContent = text
      message.className = `message ${type}`
      message.style.display = "block"

      setTimeout(() => {
        message.style.display = "none"
      }, 5000)
    }
  }

  // Función global para redirección
  window.redirectToPanel = () => {
    window.location.href = "PanelUsuario.html"
  }

  console.log("=== PERFIL COMPLETAMENTE CARGADO ===")
})
