document.addEventListener("DOMContentLoaded", () => {
  console.log("=== DUDAS Y NOTIFICACIONES INICIADO ===")

  // Referencias a elementos del DOM
  const loadingSpinner = document.getElementById("loading-spinner")
  const message = document.getElementById("message")
  const notificationsContainer = document.getElementById("notificationsContainer")
  const chatMessages = document.getElementById("chatMessages")
  const chatForm = document.getElementById("chatForm")
  const messageInput = document.getElementById("messageInput")
  const charCounter = document.getElementById("charCounter")
  const sendBtn = document.getElementById("sendBtn")
  const lenderName = document.getElementById("lenderName")
  const refreshNotifications = document.getElementById("refreshNotifications")
  const markAllRead = document.getElementById("markAllRead")
  const refreshChat = document.getElementById("refreshChat")

  // Variables globales
  let currentLender = null
  let chatMessages_data = []
  let notifications_data = []
  let refreshInterval = null

  // Inicialización
  init()

  async function init() {
    console.log("Inicializando dudas y notificaciones...")

    // Verificar autenticación
    const isAuthenticated = await checkAuth()
    if (isAuthenticated) {
      await loadLenderInfo()
      await loadNotifications()
      await loadChatMessages()
      setupEventListeners()
      startAutoRefresh()
    }
  }

  // Verificar autenticación
  async function checkAuth() {
    try {
      const response = await fetch("PHP/check_auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        showMessage("Debes iniciar sesión para acceder a las dudas", "error")
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

  // Cargar información del prestamista asignado
  async function loadLenderInfo() {
    try {
      const response = await fetch("PHP/get_assigned_lender.php")
      const data = await response.json()

      if (data.success) {
        currentLender = data.lender
        lenderName.textContent = data.lender.nombreEmpleado
        console.log("Prestamista asignado:", currentLender)
      } else {
        throw new Error(data.message || "Error al obtener prestamista")
      }
    } catch (error) {
      console.error("Error al cargar prestamista:", error)
      showMessage("Error al cargar información del prestamista", "error")
      lenderName.textContent = "Prestamista no disponible"
    }
  }

  // Cargar notificaciones (excluyendo dudas y respuestas)
  async function loadNotifications() {
    try {
      const response = await fetch("PHP/get_notifications.php")
      const data = await response.json()

      if (data.success) {
        notifications_data = data.notifications
        renderNotifications(data.notifications)
      } else {
        throw new Error(data.message || "Error al cargar notificaciones")
      }
    } catch (error) {
      console.error("Error al cargar notificaciones:", error)
      showMessage("Error al cargar notificaciones", "error")
      renderNotifications([])
    }
  }

  // Renderizar notificaciones
  function renderNotifications(notifications) {
    if (notifications.length === 0) {
      notificationsContainer.innerHTML = `
        <div class="empty-notifications">
          <i class="fas fa-bell-slash"></i>
          <p>No tienes notificaciones</p>
        </div>
      `
      return
    }

    notificationsContainer.innerHTML = notifications
      .map(
        (notification) => `
      <div class="notification-item ${notification.estado === 0 ? "unread" : "read"}" 
           data-id="${notification.idNotificacion}">
        <div class="notification-header">
          <span class="notification-type ${getNotificationTypeClass(notification.idTipoNotificacion)}">
            ${getNotificationTypeText(notification.idTipoNotificacion)}
          </span>
          <span class="notification-date">${formatDate(notification.fechaEnvio)}</span>
        </div>
        <div class="notification-message">${notification.mensaje}</div>
      </div>
    `,
      )
      .join("")

    // Añadir event listeners a las notificaciones
    document.querySelectorAll(".notification-item").forEach((item) => {
      item.addEventListener("click", function () {
        const notificationId = this.getAttribute("data-id")
        markNotificationAsRead(notificationId)
      })
    })
  }

  // Cargar mensajes del chat
  async function loadChatMessages() {
    try {
      const response = await fetch("PHP/get_chat_messages.php")
      const data = await response.json()

      if (data.success) {
        chatMessages_data = data.messages
        renderChatMessages(data.messages)
      } else {
        throw new Error(data.message || "Error al cargar mensajes")
      }
    } catch (error) {
      console.error("Error al cargar mensajes:", error)
      showMessage("Error al cargar la conversación", "error")
      renderChatMessages([])
    }
  }

  // Renderizar mensajes del chat
  function renderChatMessages(messages) {
    if (messages.length === 0) {
      chatMessages.innerHTML = `
        <div class="empty-chat">
          <i class="fas fa-comments"></i>
          <h3>¡Inicia la conversación!</h3>
          <p>Envía tu primera duda a tu prestamista</p>
        </div>
      `
      return
    }

    chatMessages.innerHTML = messages
      .map(
        (msg) => `
      <div class="message-item ${msg.idTipoNotificacion === 7 ? "user" : "lender"}">
        <div class="message-content">
          <div class="message-text">${msg.mensaje}</div>
          <div class="message-time">${formatDateTime(msg.fechaEnvio)}</div>
        </div>
      </div>
    `,
      )
      .join("")

    // Scroll al final
    scrollToBottom()
  }

  // Configurar event listeners
  function setupEventListeners() {
    // Formulario de chat
    chatForm.addEventListener("submit", handleSendMessage)

    // Contador de caracteres
    messageInput.addEventListener("input", updateCharCounter)

    // Botones de refresh
    refreshNotifications.addEventListener("click", loadNotifications)
    refreshChat.addEventListener("click", loadChatMessages)

    // Marcar todas como leídas
    markAllRead.addEventListener("click", markAllNotificationsAsRead)

    // Auto-resize del textarea
    messageInput.addEventListener("input", autoResizeTextarea)
  }

  // Manejar envío de mensaje
  async function handleSendMessage(e) {
    e.preventDefault()

    const messageText = messageInput.value.trim()
    if (!messageText) return

    if (!currentLender) {
      showMessage("No hay prestamista asignado", "error")
      return
    }

    // Deshabilitar botón de envío
    sendBtn.disabled = true
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'

    try {
      const response = await fetch("PHP/send_message.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          message: messageText,
          lenderId: currentLender.idEmpleado,
        }),
      })

      const data = await response.json()

      if (data.success) {
        // Limpiar input
        messageInput.value = ""
        updateCharCounter()

        // Recargar mensajes
        await loadChatMessages()

        showMessage("Mensaje enviado correctamente", "success")
      } else {
        throw new Error(data.message || "Error al enviar mensaje")
      }
    } catch (error) {
      console.error("Error al enviar mensaje:", error)
      showMessage("Error al enviar el mensaje", "error")
    } finally {
      // Rehabilitar botón
      sendBtn.disabled = false
      sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>'
    }
  }

  // Actualizar contador de caracteres
  function updateCharCounter() {
    const length = messageInput.value.length
    charCounter.textContent = `${length}/200`

    // Cambiar color según la longitud
    charCounter.className = "char-counter"
    if (length > 180) {
      charCounter.classList.add("danger")
    } else if (length > 150) {
      charCounter.classList.add("warning")
    }

    // Deshabilitar botón si está vacío o excede el límite
    sendBtn.disabled = length === 0 || length > 200
  }

  // Auto-resize del textarea
  function autoResizeTextarea() {
    messageInput.style.height = "auto"
    messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + "px"
  }

  // Marcar notificación como leída
  async function markNotificationAsRead(notificationId) {
    try {
      const response = await fetch("PHP/mark_notification_read.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          notificationId: notificationId,
        }),
      })

      const data = await response.json()

      if (data.success) {
        // Actualizar visualmente
        const notificationElement = document.querySelector(`[data-id="${notificationId}"]`)
        if (notificationElement) {
          notificationElement.classList.remove("unread")
          notificationElement.classList.add("read")
        }
      }
    } catch (error) {
      console.error("Error al marcar notificación como leída:", error)
    }
  }

  // Marcar todas las notificaciones como leídas
  async function markAllNotificationsAsRead() {
    try {
      const response = await fetch("PHP/mark_all_notifications_read.php", {
        method: "POST",
        credentials: "same-origin",
      })

      const data = await response.json()

      if (data.success) {
        // Recargar notificaciones
        await loadNotifications()
        showMessage("Todas las notificaciones marcadas como leídas", "success")
      } else {
        throw new Error(data.message || "Error al marcar notificaciones")
      }
    } catch (error) {
      console.error("Error al marcar todas como leídas:", error)
      showMessage("Error al marcar notificaciones como leídas", "error")
    }
  }

  // Iniciar auto-refresh
  function startAutoRefresh() {
    // Refrescar cada 30 segundos
    refreshInterval = setInterval(async () => {
      await loadChatMessages()
      await loadNotifications()
    }, 30000)
  }

  // Detener auto-refresh
  function stopAutoRefresh() {
    if (refreshInterval) {
      clearInterval(refreshInterval)
      refreshInterval = null
    }
  }

  // Scroll al final del chat
  function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight
  }

  // Obtener clase CSS para tipo de notificación
  function getNotificationTypeClass(typeId) {
    switch (Number(typeId)) {
      case 1:
        return "approval"
      case 2:
        return "rejection"
      case 3:
        return "reminder"
      case 4:
        return "payment"
      case 5:
        return "payment"
      case 6:
        return "status"
      default:
        return "status"
    }
  }

  // Obtener texto para tipo de notificación
  function getNotificationTypeText(typeId) {
    switch (Number(typeId)) {
      case 1:
        return "Aprobación"
      case 2:
        return "Rechazo"
      case 3:
        return "Recordatorio"
      case 4:
        return "Pago Atrasado"
      case 5:
        return "Pago Recibido"
      case 6:
        return "Cambio de Estado"
      default:
        return "Notificación"
    }
  }

  // Formatear fecha
  function formatDate(dateString) {
    if (!dateString) return ""

    const date = new Date(dateString)
    if (isNaN(date.getTime())) return dateString

    return date.toLocaleDateString("es-MX", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    })
  }

  // Formatear fecha y hora
  function formatDateTime(dateString) {
    if (!dateString) return ""

    const date = new Date(dateString)
    if (isNaN(date.getTime())) return dateString

    return date.toLocaleString("es-MX", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
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
    stopAutoRefresh()
    window.location.href = "PanelUsuario.html"
  }

  // Limpiar interval al salir de la página
  window.addEventListener("beforeunload", () => {
    stopAutoRefresh()
  })

  console.log("=== DUDAS COMPLETAMENTE CARGADO ===")
})
