document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar contraseña
    const toggleNewPassword = document.getElementById('toggleNewPassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    toggleNewPassword.addEventListener('click', function() {
        const type = newPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        newPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    // Envío del formulario
    const passwordForm = document.getElementById('passwordForm');
    const errorMessage = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');
    
    passwordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validar que las contraseñas coincidan
        if (newPassword.value !== confirmPassword.value) {
            showError('Las contraseñas no coinciden');
            return;
        }
        
        // Validar fortaleza de la contraseña
        if (newPassword.value.length < 8) {
            showError('La contraseña debe tener al menos 8 caracteres');
            return;
        }
        
        // Mostrar estado de carga
        const submitBtn = passwordForm.querySelector('.login-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Procesando...';
        
        // Enviar datos al servidor
        fetch('../Controlador/cambioContraseña.php', {
            method: 'POST',
            body: new FormData(passwordForm)
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la red');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess(data.message);
                setTimeout(() => {
                    window.location.href = 'Login.html';
                }, 2000);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error al conectar con el servidor');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Cambiar Contraseña';
        });
    });
    
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
        successMessage.style.display = 'none';
    }
    
    function showSuccess(message) {
        successMessage.textContent = message;
        successMessage.style.display = 'block';
        errorMessage.style.display = 'none';
    }
    
    // Ocultar mensajes al escribir
    const inputs = passwordForm.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (errorMessage.style.display === 'block' || successMessage.style.display === 'block') {
                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';
            }
        });
    });
});
