document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar contraseña
    const togglePassword = document.querySelector('#togglePassword');
    const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
    const password = document.querySelector('#password');
    const confirmPassword = document.querySelector('#confirm_password');
    
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    // Envío del formulario
    const signupForm = document.getElementById('signupForm');
    const errorMessage = document.getElementById('error-message');
    
    signupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validar que las contraseñas coincidan
        if (password.value !== confirmPassword.value) {
            errorMessage.textContent = 'Las contraseñas no coinciden';
            errorMessage.style.display = 'block';
            return;
        }
        
        // Validar fortaleza de la contraseña 
        if (password.value.length < 8) {
            errorMessage.textContent = 'La contraseña debe tener al menos 8 caracteres';
            errorMessage.style.display = 'block';
            return;
        }
        
        // Validar formato de teléfono 
        const phone = document.querySelector('input[name="phone"]').value;
        if (!/^[0-9]{10}$/.test(phone)) {
            errorMessage.textContent = 'Ingrese un número telefónico válido (10 dígitos)';
            errorMessage.style.display = 'block';
            return;
        }
        
        // Mostrar estado de carga
        const submitBtn = signupForm.querySelector('.login-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Registrando...';
        
        // Obtener datos del formulario
        const formData = new FormData(signupForm);
        
        // Enviar datos al servidor
        fetch('../Controlador/signup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir al login con mensaje de éxito
                window.location.href = 'Login.html?registered=true';
            } else {
                // Mostrar mensaje de error
                errorMessage.textContent = data.message;
                errorMessage.style.display = 'block';
                
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.textContent = 'Registrarse';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorMessage.textContent = 'Error al conectar con el servidor';
            errorMessage.style.display = 'block';
            
            // Restaurar botón
            submitBtn.disabled = false;
            submitBtn.textContent = 'Registrarse';
        });
    });
    
    // Ocultar mensaje de error al escribir
    const inputs = signupForm.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (errorMessage.style.display === 'block') {
                errorMessage.style.display = 'none';
            }
        });
    });
});
