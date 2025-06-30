/**
 * GRUPO SEAL - Sistema de Login Mejorado
 * Funciones principales del formulario de login
 */

document.addEventListener('DOMContentLoaded', function() {
    // ========================================
    // CONFIGURACIÓN DEL SISTEMA
    // ========================================
    const CONFIG = {
        maxIntentos: 8,                    // CORREGIDO: 8 intentos en lugar de 5
        tiempoBloqueo: 10 * 60 * 1000,    // 10 minutos de bloqueo (en lugar de 15)
        advertenciaEn: 5,                  // Mostrar advertencia desde el 5º intento
        timeoutSubmit: 8000                // Timeout para reset del botón submit
    };

    // ========================================
    // ELEMENTOS DEL DOM
    // ========================================
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('loginBtn');
    const inputs = form.querySelectorAll('input');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    const messagesArea = document.getElementById('messages-area');
    const helpBtn = document.getElementById('helpBtn');
    const helpModal = document.getElementById('helpModal');
    const closeModal = document.getElementById('closeModal');

    // ========================================
    // CLASE: SISTEMA DE PROTECCIÓN CONTRA FUERZA BRUTA
    // ========================================
    class BruteForceProtection {
        constructor() {
            this.storageKey = 'login_attempts';
            this.blockTimeKey = 'block_start_time';
        }

        getAttempts() {
            return parseInt(localStorage.getItem(this.storageKey) || '0');
        }

        setAttempts(count) {
            localStorage.setItem(this.storageKey, count.toString());
        }

        getBlockStartTime() {
            return parseInt(localStorage.getItem(this.blockTimeKey) || '0');
        }

        setBlockStartTime(time) {
            localStorage.setItem(this.blockTimeKey, time.toString());
        }

        clearAttempts() {
            localStorage.removeItem(this.storageKey);
            localStorage.removeItem(this.blockTimeKey);
        }

        incrementAttempts() {
            const current = this.getAttempts() + 1;
            this.setAttempts(current);
            
            if (current >= CONFIG.maxIntentos) {
                this.setBlockStartTime(Date.now());
            }
            
            return current;
        }

        isBlocked() {
            const attempts = this.getAttempts();
            
            if (attempts >= CONFIG.maxIntentos) {
                const blockStartTime = this.getBlockStartTime();
                const timeRemaining = blockStartTime + CONFIG.tiempoBloqueo - Date.now();
                
                if (timeRemaining > 0) {
                    return { blocked: true, timeRemaining };
                } else {
                    this.clearAttempts();
                    return { blocked: false };
                }
            }
            
            return { blocked: false };
        }

        shouldShowWarning() {
            const attempts = this.getAttempts();
            return attempts >= CONFIG.advertenciaEn && attempts < CONFIG.maxIntentos;
        }

        getRemainingAttempts() {
            return CONFIG.maxIntentos - this.getAttempts();
        }
    }

    const bruteForceProtection = new BruteForceProtection();

    // ========================================
    // CLASE: SISTEMA DE MENSAJES
    // ========================================
    class MessageSystem {
        showMessage(message, type = 'error', autoHide = true) {
            this.clearMessages();

            const messageDiv = document.createElement('div');
            messageDiv.className = `login-message login-${type}`;
            
            let icon = 'fas fa-exclamation-triangle';
            if (type === 'success') icon = 'fas fa-check-circle';
            if (type === 'warning') icon = 'fas fa-exclamation-circle';
            if (type === 'info') icon = 'fas fa-info-circle';

            messageDiv.innerHTML = `
                <i class="${icon}"></i>
                <span>${message}</span>
            `;
            
            messagesArea.appendChild(messageDiv);
            
            if (autoHide) {
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, type === 'error' ? 8000 : 5000);
            }

            return messageDiv;
        }

        clearMessages() {
            messagesArea.innerHTML = '';
        }

        showError(message) {
            return this.showMessage(message, 'error');
        }

        showWarning(message) {
            return this.showMessage(message, 'warning');
        }

        showSuccess(message) {
            return this.showMessage(message, 'success');
        }

        showInfo(message) {
            return this.showMessage(message, 'info');
        }
    }

    const messageSystem = new MessageSystem();

    // ========================================
    // GESTIÓN DE ERRORES DESDE URL
    // ========================================
    function handleUrlError() {
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        const logout = urlParams.get('logout');
        
        // Manejar mensaje de logout exitoso
        if (logout === 'success') {
            messageSystem.showSuccess('Sesión cerrada correctamente.');
            // Limpiar URL
            const url = new URL(window.location);
            url.searchParams.delete('logout');
            window.history.replaceState({}, '', url);
            return;
        }
        
        if (error) {
            const attempts = bruteForceProtection.incrementAttempts();
            let mensaje = '';
            
            switch(error) {
                case 'invalid_credentials':
                    mensaje = 'Correo o contraseña incorrectos.';
                    break;
                case 'empty_fields':
                    mensaje = 'Por favor, completa todos los campos.';
                    break;
                case 'invalid_email':
                    mensaje = 'El formato del correo electrónico no es válido.';
                    break;
                case 'user_not_found':
                    mensaje = 'Usuario no encontrado en el sistema.';
                    break;
                case 'account_disabled':
                    mensaje = 'Tu cuenta está deshabilitada. Contacta al administrador.';
                    break;
                case 'system_error':
                    mensaje = 'Error del sistema. Inténtalo más tarde.';
                    break;
                default:
                    mensaje = 'Error al iniciar sesión. Inténtalo nuevamente.';
            }
            
            messageSystem.showError(mensaje);
            
            // Mostrar advertencia si está cerca del límite
            if (bruteForceProtection.shouldShowWarning()) {
                const remaining = bruteForceProtection.getRemainingAttempts();
                setTimeout(() => {
                    messageSystem.showWarning(
                        `Atención: Te quedan ${remaining} intentos antes del bloqueo temporal.`
                    );
                }, 3000);
            }

            // Si se alcanzó el límite, recargar para aplicar bloqueo
            if (attempts >= CONFIG.maxIntentos) {
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }

            // Limpiar URL sin recargar la página
            const url = new URL(window.location);
            url.searchParams.delete('error');
            window.history.replaceState({}, '', url);
        }
    }

    // ========================================
    // FUNCIONALIDAD MOSTRAR/OCULTAR CONTRASEÑA
    // ========================================
    function setupPasswordToggle() {
        if (!togglePassword || !passwordInput || !eyeIcon) return;

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
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
            
            passwordInput.focus();
        });
    }

    // ========================================
    // VALIDACIÓN Y MANEJO DEL FORMULARIO
    // ========================================
    function setupFormHandling() {
        let isSubmitting = false;

        // Manejo del envío del formulario
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Verificar si el formulario está bloqueado
            const blockStatus = bruteForceProtection.isBlocked();
            if (blockStatus.blocked) {
                e.preventDefault();
                const minutes = Math.ceil(blockStatus.timeRemaining / (60 * 1000));
                messageSystem.showError(`Formulario bloqueado. Inténtalo en ${minutes} minutos.`);
                return false;
            }

            // Validar campos
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
                messageSystem.showError('Por favor, completa todos los campos correctamente.');
                return false;
            }

            // Efecto de carga
            isSubmitting = true;
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...';
            
            // Reset después del timeout
            setTimeout(() => {
                isSubmitting = false;
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
            }, CONFIG.timeoutSubmit);
        });

        // Navegación con teclado
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

            // Validación visual en tiempo real
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

            input.addEventListener('focus', function() {
                if (!this.value) {
                    this.style.borderColor = '#0a253c';
                    this.style.boxShadow = '0 0 0 3px rgba(10, 37, 60, 0.1), 0 8px 25px rgba(10, 37, 60, 0.1)';
                }
            });
        });
    }

    // ========================================
    // SISTEMA DE BLOQUEO
    // ========================================
    function setupBlockSystem() {
        const blockStatus = bruteForceProtection.isBlocked();
        
        if (blockStatus.blocked) {
            blockForm(blockStatus.timeRemaining);
        } else if (bruteForceProtection.shouldShowWarning()) {
            const remaining = bruteForceProtection.getRemainingAttempts();
            messageSystem.showWarning(
                `Advertencia: Te quedan ${remaining} intentos antes del bloqueo temporal.`
            );
        }
    }

    function blockForm(timeRemaining) {
        form.style.opacity = '0.6';
        form.style.pointerEvents = 'none';
        submitBtn.disabled = true;
        inputs.forEach(input => input.disabled = true);
        
        const minutes = Math.ceil(timeRemaining / (60 * 1000));
        const errorElement = messageSystem.showError(
            `Demasiados intentos fallidos. Formulario bloqueado por ${minutes} minutos.`,
            'error',
            false
        );
        
        // Agregar botón de ayuda al mensaje de error
        const helpButton = document.createElement('button');
        helpButton.type = 'button';
        helpButton.className = 'btn-help-inline';
        helpButton.innerHTML = '<i class="fas fa-question-circle"></i> ¿Necesitas ayuda?';
        helpButton.onclick = () => helpModal.style.display = 'block';
        errorElement.appendChild(helpButton);
        
        // Countdown en tiempo real
        const intervalId = setInterval(() => {
            const currentTimeRemaining = bruteForceProtection.getBlockStartTime() + CONFIG.tiempoBloqueo - Date.now();
            
            if (currentTimeRemaining <= 0) {
                clearInterval(intervalId);
                unblockForm();
            } else {
                const minutesRemaining = Math.ceil(currentTimeRemaining / (60 * 1000));
                const errorText = errorElement.querySelector('span');
                if (errorText) {
                    errorText.textContent = `Demasiados intentos fallidos. Formulario bloqueado por ${minutesRemaining} minutos.`;
                }
            }
        }, 30000);
    }

    function unblockForm() {
        form.style.opacity = '1';
        form.style.pointerEvents = 'auto';
        submitBtn.disabled = false;
        inputs.forEach(input => input.disabled = false);
        
        bruteForceProtection.clearAttempts();
        messageSystem.clearMessages();
        messageSystem.showSuccess('Formulario desbloqueado. Puedes intentar nuevamente.');
    }

    // ========================================
    // MODAL DE AYUDA
    // ========================================
    function setupHelpModal() {
        if (!helpBtn || !helpModal || !closeModal) return;

        helpBtn.addEventListener('click', () => {
            helpModal.style.display = 'block';
        });

        closeModal.addEventListener('click', () => {
            helpModal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === helpModal) {
                helpModal.style.display = 'none';
            }
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && helpModal.style.display === 'block') {
                helpModal.style.display = 'none';
            }
        });
    }

    // ========================================
    // FUNCIONES GLOBALES PARA USUARIOS
    // ========================================
    window.limpiarIntentosFallidos = function() {
        bruteForceProtection.clearAttempts();
        messageSystem.showSuccess('Intentos fallidos limpiados correctamente.');
        setTimeout(() => {
            location.reload();
        }, 1500);
    };

    window.mostrarEstadoSistema = function() {
        const attempts = bruteForceProtection.getAttempts();
        const blockStatus = bruteForceProtection.isBlocked();
        
        console.log('=== ESTADO DEL SISTEMA ===');
        console.log('Intentos fallidos:', attempts);
        console.log('Máximo permitido:', CONFIG.maxIntentos);
        console.log('¿Bloqueado?:', blockStatus.blocked);
        
        if (blockStatus.blocked) {
            const minutes = Math.ceil(blockStatus.timeRemaining / (60 * 1000));
            console.log('Tiempo restante:', minutes, 'minutos');
        }
        
        return {
            attempts,
            maxAttempts: CONFIG.maxIntentos,
            isBlocked: blockStatus.blocked,
            timeRemaining: blockStatus.timeRemaining || 0
        };
    };

    // ========================================
    // MANEJO DE ERRORES Y ACCESIBILIDAD
    // ========================================
    function setupAccessibility() {
        // Navegación por teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });

        // Manejar errores de carga de imagen
        const logo = document.querySelector('.logo');
        if (logo) {
            logo.addEventListener('error', function() {
                this.style.display = 'none';
                console.warn('Logo no pudo cargar, verificar ruta de imagen');
            });
        }
    }

    // ========================================
    // INICIALIZACIÓN PRINCIPAL
    // ========================================
    function init() {
        try {
            setupPasswordToggle();
            setupFormHandling();
            setupBlockSystem();
            setupHelpModal();
            setupAccessibility();
            handleUrlError();
            
            console.log('✅ Sistema de login inicializado correctamente');
            console.log(`📊 Configuración: ${CONFIG.maxIntentos} intentos max, ${CONFIG.tiempoBloqueo/60000} min bloqueo`);
            
        } catch (error) {
            console.error('❌ Error al inicializar sistema de login:', error);
            messageSystem.showError('Error al inicializar el sistema. Recarga la página.');
        }
    }

    // Ejecutar inicialización
    init();
});

// ========================================
// UTILIDADES DE DESARROLLO (Consola)
// ========================================
console.log(`
🔐 GRUPO SEAL - Sistema de Login Cargado
📝 Comandos disponibles:
   • mostrarEstadoSistema() - Ver estado actual
   • limpiarIntentosFallidos() - Limpiar intentos
   • Para más comandos: adminUtils.showHelp()
`);

// Detectar entorno de desarrollo
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('🏠 Entorno de desarrollo detectado');
}