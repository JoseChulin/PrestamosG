document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar contraseña
    const togglePassword = document.querySelector('#togglePassword');
    const loginForm = document.querySelector('.login-form');
    const errorMessage = document.getElementById('error-message');

    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Mostrar estado de carga
        const submitBtn = loginForm.querySelector('.login-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Iniciando sesión...';
        
        // Obtener los datos del formulario
        const formData = new FormData(loginForm);
        
        // Enviar la solicitud al servidor
        fetch('../Controlador/loginUsuario.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir al panel de usuario
                window.location.href = data.redirect;
            } else {
                // Mostrar mensaje de error
                errorMessage.textContent = data.message;
                errorMessage.style.display = 'block';
                
                // Restaurar el botón
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorMessage.textContent = 'Error al conectar con el servidor';
            errorMessage.style.display = 'block';
            
            // Restaurar el botón
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
        });
    });
    
    // Ocultar mensaje de error cuando el usuario empiece a escribir
    const inputs = loginForm.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (errorMessage.style.display === 'block') {
                errorMessage.style.display = 'none';
            }
        });
    });
    
});
