/**
 * ============================================
 * PRODUCTOS REGISTRAR - JAVASCRIPT
 * Sistema de gestión de inventario COMSEPROA
 * ============================================
 */

// Variables globales
let isFormChanged = false;

// ===== INICIALIZACIÓN ===== 
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
    setupEventListeners();
    setupKeyboardShortcuts();
    setupFormValidation();
});

// ===== CONFIGURACIÓN INICIAL =====
function initializeForm() {
    // Configurar el contador de caracteres para observaciones
    const observacionesTextarea = document.getElementById('observaciones');
    const charCount = document.getElementById('charCount');
    
    if (observacionesTextarea && charCount) {
        observacionesTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            // Cambiar color cuando se acerca al límite
            if (this.value.length > 450) {
                charCount.style.color = '#dc3545';
            } else if (this.value.length > 400) {
                charCount.style.color = '#ffc107';
            } else {
                charCount.style.color = '#9ca3af';
            }
        });
    }

    // Configurar sidebar
    setupSidebar();
    
    // Marcar campos como modificados
    trackFormChanges();
    
    console.log('Formulario de registro inicializado correctamente');
}

// ===== CONFIGURACIÓN DEL SIDEBAR =====
function setupSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
        });
    }

    // Configurar submenús
    const submenuContainers = document.querySelectorAll('.submenu-container');
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                container.classList.toggle('activo');
                submenu.classList.toggle('activo');
            });
        }
    });
}

// ===== EVENT LISTENERS =====
function setupEventListeners() {
    // Cerrar sidebar al hacer clic fuera
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        
        if (sidebar && menuToggle && 
            !sidebar.contains(e.target) && 
            !menuToggle.contains(e.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            const mainContent = document.getElementById('main-content');
            if (mainContent) {
                mainContent.classList.remove('with-sidebar');
            }
        }
    });

    // Prevenir envío del formulario con Enter en campos de texto
    const form = document.getElementById('formRegistrarProducto');
    if (form) {
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.type !== 'submit' && e.target.type !== 'textarea') {
                e.preventDefault();
            }
        });
        
        // Confirmar antes de abandonar si hay cambios
        window.addEventListener('beforeunload', function(e) {
            if (isFormChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
}

// ===== ATAJOS DE TECLADO =====
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + S para guardar
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const form = document.getElementById('formRegistrarProducto');
            if (form) {
                submitForm();
            }
        }
        
        // Ctrl + R para limpiar formulario
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            limpiarFormulario();
        }
        
        // Escape para cancelar
        if (e.key === 'Escape') {
            const confirmCancel = confirm('¿Está seguro que desea cancelar? Se perderán los datos ingresados.');
            if (confirmCancel) {
                window.location.href = 'listar.php';
            }
        }
    });
}

// ===== VALIDACIÓN DEL FORMULARIO =====
function setupFormValidation() {
    const requiredFields = document.querySelectorAll('input[required], select[required]');
    
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    
    // Validación básica de campos requeridos
    if (field.hasAttribute('required') && !value) {
        isValid = false;
    }
    
    // Validaciones específicas
    switch (field.type) {
        case 'number':
            if (value && (isNaN(value) || parseFloat(value) <= 0)) {
                isValid = false;
            }
            break;
        case 'text':
            if (field.id === 'nombre' && value.length < 3) {
                isValid = false;
            }
            break;
    }
    
    // Aplicar estilos de validación
    if (isValid) {
        field.classList.remove('error');
        field.classList.add('valid');
    } else {
        field.classList.remove('valid');
        field.classList.add('error');
    }
    
    return isValid;
}

// ===== SEGUIMIENTO DE CAMBIOS =====
function trackFormChanges() {
    const formFields = document.querySelectorAll('input, select, textarea');
    const formContainer = document.querySelector('.form-container');
    
    formFields.forEach(field => {
        field.addEventListener('input', function() {
            if (!isFormChanged) {
                isFormChanged = true;
                if (formContainer) {
                    formContainer.classList.add('form-changed');
                }
            }
        });
    });
}

// ===== CONTROL DE CANTIDAD =====
function adjustQuantity(change) {
    const quantityInput = document.getElementById('cantidad');
    if (quantityInput) {
        let currentValue = parseInt(quantityInput.value) || 1;
        let newValue = currentValue + change;
        
        // Asegurar que no sea menor a 1
        if (newValue < 1) {
            newValue = 1;
        }
        
        quantityInput.value = newValue;
        
        // Disparar evento de cambio
        quantityInput.dispatchEvent(new Event('input'));
        
        // Animación visual
        quantityInput.style.transform = 'scale(1.1)';
        setTimeout(() => {
            quantityInput.style.transform = 'scale(1)';
        }, 150);
    }
}

// ===== LIMPIAR FORMULARIO =====
function limpiarFormulario() {
    if (isFormChanged) {
        if (!confirm('¿Está seguro que desea limpiar todos los campos?')) {
            return;
        }
    }
    
    const form = document.getElementById('formRegistrarProducto');
    if (form) {
        form.reset();
        
        // Limpiar clases de validación
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('valid', 'error');
        });
        
        // Resetear contador de caracteres
        const charCount = document.getElementById('charCount');
        if (charCount) {
            charCount.textContent = '0';
            charCount.style.color = '#9ca3af';
        }
        
        // Resetear cantidad a 1
        const quantityInput = document.getElementById('cantidad');
        if (quantityInput) {
            quantityInput.value = '1';
        }
        
        // Remover clase de cambios
        const formContainer = document.querySelector('.form-container');
        if (formContainer) {
            formContainer.classList.remove('form-changed');
        }
        
        isFormChanged = false;
        
        mostrarNotificacion('Formulario limpiado correctamente', 'info');
        
        // Enfocar el primer campo
        const firstField = form.querySelector('input[type="text"]');
        if (firstField) {
            firstField.focus();
        }
    }
}

// ===== ENVÍO DEL FORMULARIO =====
function submitForm() {
    const form = document.getElementById('formRegistrarProducto');
    const submitBtn = document.getElementById('btnRegistrar');
    
    if (!form) return;
    
    // Validar todos los campos requeridos
    const requiredFields = form.querySelectorAll('input[required], select[required]');
    let allValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            allValid = false;
        }
    });
    
    if (!allValid) {
        mostrarNotificacion('Por favor complete todos los campos obligatorios correctamente', 'error');
        
        // Enfocar el primer campo con error
        const firstError = form.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    // Mostrar estado de carga
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    }
    
    // Enviar formulario
    setTimeout(() => {
        form.submit();
    }, 100);
}

// ===== NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo = 'info') {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        'success': 'fas fa-check-circle',
        'exito': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos.info}"></i>
        <span>${mensaje}</span>
    `;
    
    container.appendChild(notificacion);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentNode) {
            notificacion.style.opacity = '0';
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.parentNode.removeChild(notificacion);
                }
            }, 300);
        }
    }, 5000);
}

// ===== MANEJO DE SESIÓN =====
function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (isFormChanged) {
        const confirmExit = confirm('Tiene cambios sin guardar. ¿Está seguro que desea cerrar sesión?');
        if (!confirmExit) {
            return;
        }
    }
    
    if (confirm('¿Está seguro que desea cerrar sesión?')) {
        window.location.href = '../auth/logout.php';
    }
}

// ===== FUNCIONES DE UTILIDAD =====
function formatearNumero(numero) {
    return new Intl.NumberFormat('es-PE').format(numero);
}

function validarTexto(texto, minLength = 3) {
    return texto && texto.trim().length >= minLength;
}

function validarNumero(numero, min = 1) {
    return !isNaN(numero) && parseFloat(numero) >= min;
}

// ===== MANEJO DE ERRORES GLOBALES =====
window.addEventListener('error', function(e) {
    console.error('Error en productos-registrar.js:', e.error);
    mostrarNotificacion('Ha ocurrido un error inesperado', 'error');
});

// ===== EXPOSICIÓN DE FUNCIONES GLOBALES =====
window.adjustQuantity = adjustQuantity;
window.limpiarFormulario = limpiarFormulario;
window.submitForm = submitForm;
window.manejarCerrarSesion = manejarCerrarSesion;

console.log('productos-registrar.js cargado correctamente');