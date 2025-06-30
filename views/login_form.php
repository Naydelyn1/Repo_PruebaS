<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GRUPO SEAL</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS exclusivo para login -->
    <link rel="stylesheet" href="../assets/css/login-styles.css">
    
    <!-- Meta tags adicionales para mejor SEO y rendimiento -->
    <meta name="description" content="Iniciar sesión en el sistema de gestión de inventario GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body>
    <div class="login-container">
        <!-- Sección Izquierda: Logo y Nombre -->
        <div class="left-section">
            <img src="../assets/img/logo.png" alt="Logo de GRUPO SEAL - Sistema de Gestión de Inventario" class="logo">
        </div>

        <!-- Línea Divisoria -->
        <div class="divider"></div>

        <!-- Sección Derecha: Formulario de Login -->
        <div class="right-section">
            <h2>Login</h2>
            <form action="../auth/login.php" method="POST" id="loginForm">
                <div class="input-container">
                    <label for="correo">Correo Electrónico:</label>
                    <input 
                        type="email" 
                        id="correo" 
                        name="correo" 
                        required 
                        autocomplete="email"
                        placeholder="ejemplo@gruposeal.com"
                        aria-describedby="correo-help"
                    >
                </div>

                <div class="input-container">
                    <label for="password">Contraseña:</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="Ingresa tu contraseña"
                            minlength="6"
                            aria-describedby="password-help"
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="togglePassword"
                            aria-label="Mostrar contraseña"
                            title="Mostrar/Ocultar contraseña"
                        >
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>

    <!-- JavaScript para mejorar la experiencia del usuario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginBtn');
            const inputs = form.querySelectorAll('input');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');

            // Funcionalidad mostrar/ocultar contraseña
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Cambiar el icono
                if (type === 'text') {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                    this.setAttribute('aria-label', 'Ocultar contraseña');
                    this.setAttribute('title', 'Ocultar contraseña');
                } else {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                    this.setAttribute('aria-label', 'Mostrar contraseña');
                    this.setAttribute('title', 'Mostrar contraseña');
                }
                
                // Enfocar nuevamente el campo de contraseña
                passwordInput.focus();
            });

            // Agregar efecto de carga al enviar formulario
            form.addEventListener('submit', function(e) {
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...';
                
                // Simular validación antes de enviar
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        isValid = false;
                        input.focus();
                        return false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    submitBtn.classList.remove('loading');
                    submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
                    mostrarError('Por favor, completa todos los campos correctamente.');
                }
            });

            // Mejorar la experiencia con el teclado
            inputs.forEach((input, index) => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const nextInput = inputs[index + 1];
                        if (nextInput) {
                            nextInput.focus();
                        } else {
                            form.submit();
                        }
                    }
                });

                // Agregar validación en tiempo real
                input.addEventListener('blur', function() {
                    if (this.value && !this.checkValidity()) {
                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                    } else if (this.value && this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                    }
                });

                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                    } else if (this.value) {
                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                    } else {
                        this.style.borderColor = '#c8c9ca';
                        this.style.boxShadow = 'none';
                    }
                });

                // Limpiar estilos de validación al hacer focus
                input.addEventListener('focus', function() {
                    if (!this.value) {
                        this.style.borderColor = '#0a253c';
                        this.style.boxShadow = '0 0 0 3px rgba(10, 37, 60, 0.1), 0 8px 25px rgba(10, 37, 60, 0.1)';
                    }
                });
            });

            // Prevenir envío múltiple
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                // Reset después de 5 segundos en caso de error
                setTimeout(() => {
                    isSubmitting = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
                }, 5000);
            });

            // Función para mostrar errores
            function mostrarError(mensaje) {
                // Remover error anterior si existe
                const errorAnterior = document.querySelector('.login-error');
                if (errorAnterior) {
                    errorAnterior.remove();
                }

                // Crear nuevo elemento de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'login-error';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${mensaje}</span>
                `;
                
                // Insertar antes del formulario
                form.parentNode.insertBefore(errorDiv, form);
                
                // Auto-remover después de 5 segundos
                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            }

            // Detectar si hay parámetros de error en la URL
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            if (error) {
                let mensaje = 'Error desconocido';
                switch(error) {
                    case 'invalid_credentials':
                        mensaje = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
                        break;
                    case 'empty_fields':
                        mensaje = 'Por favor, completa todos los campos.';
                        break;
                    case 'user_not_found':
                        mensaje = 'Usuario no encontrado en el sistema.';
                        break;
                    case 'account_disabled':
                        mensaje = 'Tu cuenta está deshabilitada. Contacta al administrador.';
                        break;
                    default:
                        mensaje = 'Error al iniciar sesión. Inténtalo nuevamente.';
                }
                mostrarError(mensaje);
            }
        });

        // Manejar errores de carga de imagen
        document.querySelector('.logo').addEventListener('error', function() {
            this.style.display = 'none';
            console.warn('Logo no pudo cargar, verificar ruta de imagen');
        });

        // Accessibility: Manejar navegación con teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });

        // Prevenir ataques de fuerza bruta básicos
        let intentosFallidos = parseInt(localStorage.getItem('intentosFallidos') || '0');
        const maxIntentos = 5;
        const tiempoBloqueo = 15 * 60 * 1000; // 15 minutos

        if (intentosFallidos >= maxIntentos) {
            const tiempoBloqueoInicio = parseInt(localStorage.getItem('tiempoBloqueoInicio') || '0');
            const tiempoRestante = tiempoBloqueoInicio + tiempoBloqueo - Date.now();
            
            if (tiempoRestante > 0) {
                const form = document.getElementById('loginForm');
                const submitBtn = document.getElementById('loginBtn');
                
                form.style.opacity = '0.5';
                form.style.pointerEvents = 'none';
                submitBtn.disabled = true;
                
                const minutos = Math.ceil(tiempoRestante / (60 * 1000));
                mostrarError(`Demasiados intentos fallidos. Inténtalo nuevamente en ${minutos} minutos.`);
                
                setTimeout(() => {
                    localStorage.removeItem('intentosFallidos');
                    localStorage.removeItem('tiempoBloqueoInicio');
                    location.reload();
                }, tiempoRestante);
            } else {
                localStorage.removeItem('intentosFallidos');
                localStorage.removeItem('tiempoBloqueoInicio');
            }
        }

        // Registrar intento fallido si hay error en la URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error')) {
            intentosFallidos++;
            localStorage.setItem('intentosFallidos', intentosFallidos.toString());
            
            if (intentosFallidos >= maxIntentos) {
                localStorage.setItem('tiempoBloqueoInicio', Date.now().toString());
            }
        }
    </script>

    <!-- Estilos adicionales para el componente de error -->
    <style>
        .login-error {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            margin-bottom: 20px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.1);
            animation: slideInDown 0.3s ease;
        }

        .login-error i {
            font-size: 18px;
            flex-shrink: 0;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estilos para navegación por teclado */
        .keyboard-navigation input:focus,
        .keyboard-navigation button:focus,
        .keyboard-navigation .password-toggle:focus {
            outline: 3px solid #0a253c !important;
            outline-offset: 2px !important;
        }
    </style>
</body>
</html>