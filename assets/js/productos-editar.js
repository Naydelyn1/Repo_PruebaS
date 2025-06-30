/* ============================================
   PRODUCTOS EDITAR - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosEditar {
    constructor() {
        this.valoresOriginales = {};
        this.inicializar();
        this.configurarEventListeners();
        this.guardarValoresOriginales();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Configurar validaciones
        this.configurarValidaciones();
    }

    configurarSidebar() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const submenuContainers = document.querySelectorAll('.submenu-container');

        // Toggle del menú móvil
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (mainContent) {
                    mainContent.classList.toggle('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    menuToggle.setAttribute('aria-label', 'Cerrar menú de navegación');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
                }
            });
        }

        // Funcionalidad de submenús
        submenuContainers.forEach(container => {
            const link = container.querySelector('a');
            const submenu = container.querySelector('.submenu');
            const chevron = link?.querySelector('.fa-chevron-down');
            
            if (link && submenu) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Cerrar otros submenús
                    submenuContainers.forEach(otherContainer => {
                        if (otherContainer !== container) {
                            const otherSubmenu = otherContainer.querySelector('.submenu');
                            const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                            const otherLink = otherContainer.querySelector('a');
                            
                            if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                                otherSubmenu.classList.remove('activo');
                                if (otherChevron) {
                                    otherChevron.style.transform = 'rotate(0deg)';
                                }
                                if (otherLink) {
                                    otherLink.setAttribute('aria-expanded', 'false');
                                }
                            }
                        }
                    });
                    
                    // Toggle submenu actual
                    submenu.classList.toggle('activo');
                    const isExpanded = submenu.classList.contains('activo');
                    
                    if (chevron) {
                        chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                    
                    link.setAttribute('aria-expanded', isExpanded.toString());
                });
            }
        });

        // Mostrar submenú de productos activo por defecto
        const productosSubmenu = submenuContainers[2]?.querySelector('.submenu');
        const productosChevron = submenuContainers[2]?.querySelector('.fa-chevron-down');
        const productosLink = submenuContainers[2]?.querySelector('a');
        
        if (productosSubmenu) {
            productosSubmenu.classList.add('activo');
            if (productosChevron) {
                productosChevron.style.transform = 'rotate(180deg)';
            }
            if (productosLink) {
                productosLink.setAttribute('aria-expanded', 'true');
            }
        }
    }

    configurarAlertas() {
        const alertas = document.querySelectorAll('.alert');
        alertas.forEach(alerta => {
            setTimeout(() => {
                alerta.style.animation = 'slideOutUp 0.5s ease-in-out';
                setTimeout(() => {
                    alerta.remove();
                }, 500);
            }, 5000);
        });
    }

    guardarValoresOriginales() {
        const campos = ['nombre', 'modelo', 'color', 'talla_dimensiones', 'cantidad', 'unidad_medida', 'estado', 'observaciones', 'categoria_id', 'almacen_id'];
        
        campos.forEach(campo => {
            const element = document.getElementById(campo);
            if (element) {
                this.valoresOriginales[campo] = element.value;
            }
        });
    }

    configurarEventListeners() {
        const form = document.getElementById('formEditarProducto');
        
        if (form) {
            // Configurar envío del formulario
            form.addEventListener('submit', (e) => this.enviarFormulario(e));
            
            // Configurar detección de cambios
            this.configurarDeteccionCambios();
        }

        // Configurar eventos globales
        document.addEventListener('keydown', (e) => {
            // Ctrl + S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('btnGuardar')?.click();
            }
            
            // Esc para cancelar (con confirmación si hay cambios)
            if (e.key === 'Escape') {
                this.manejarCancelacion();
            }
        });

        // Advertencia al salir sin guardar
        window.addEventListener('beforeunload', (e) => {
            if (this.detectarCambios()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Manejar cerrar sesión
        const logoutLinks = document.querySelectorAll('a[onclick*="manejarCerrarSesion"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => this.manejarCerrarSesion(e));
        });
    }

    configurarDeteccionCambios() {
        const campos = ['nombre', 'modelo', 'color', 'talla_dimensiones', 'cantidad', 'unidad_medida', 'estado', 'observaciones', 'categoria_id', 'almacen_id'];
        
        campos.forEach(campo => {
            const element = document.getElementById(campo);
            if (element) {
                element.addEventListener('input', () => {
                    this.actualizarIndicadorCambios();
                });
                
                element.addEventListener('change', () => {
                    this.actualizarIndicadorCambios();
                });
            }
        });
    }

    configurarValidaciones() {
        const nombreInput = document.getElementById('nombre');
        const cantidadInput = document.getElementById('cantidad');
        const unidadMedidaInput = document.getElementById('unidad_medida');

        if (nombreInput) {
            nombreInput.addEventListener('input', () => {
                this.validarCampo(nombreInput, 2, 'El nombre debe tener al menos 2 caracteres');
            });
        }

        if (cantidadInput) {
            cantidadInput.addEventListener('input', () => {
                this.validarCantidad(cantidadInput);
            });
        }

        if (unidadMedidaInput) {
            unidadMedidaInput.addEventListener('input', () => {
                this.validarCampo(unidadMedidaInput, 1, 'La unidad de medida es requerida');
            });
        }
    }

    validarCampo(input, minLength, mensaje) {
        const value = input.value.trim();
        const isValid = value.length >= minLength;
        
        // Actualizar clases
        input.classList.toggle('invalid', !isValid && value.length > 0);
        input.classList.toggle('valid', isValid && value.length > 0);
        
        // Remover mensaje de error previo
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Mostrar mensaje de error si es necesario
        if (!isValid && value.length > 0) {
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
            input.parentNode.appendChild(errorElement);
        }
        
        return isValid;
    }

    validarCantidad(input) {
        const value = parseInt(input.value);
        const isValid = !isNaN(value) && value >= 0;
        
        input.classList.toggle('invalid', !isValid);
        input.classList.toggle('valid', isValid);
        
        // Remover mensaje de error previo
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        if (!isValid) {
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> La cantidad debe ser un número mayor o igual a 0';
            input.parentNode.appendChild(errorElement);
        }
        
        return isValid;
    }

    detectarCambios() {
        const campos = Object.keys(this.valoresOriginales);
        
        return campos.some(campo => {
            const element = document.getElementById(campo);
            if (element) {
                return element.value !== this.valoresOriginales[campo];
            }
            return false;
        });
    }

    actualizarIndicadorCambios() {
        const hasChanges = this.detectarCambios();
        const btnGuardar = document.getElementById('btnGuardar');
        
        if (hasChanges) {
            btnGuardar?.classList.add('has-changes');
            document.title = '* Editar Producto - COMSEPROA';
        } else {
            btnGuardar?.classList.remove('has-changes');
            document.title = 'Editar Producto - COMSEPROA';
        }
    }

    async enviarFormulario(e) {
        e.preventDefault();
        
        // Validaciones básicas
        const nombre = document.getElementById('nombre').value.trim();
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const unidadMedida = document.getElementById('unidad_medida').value.trim();
        const estado = document.getElementById('estado').value;
        const categoriaId = parseInt(document.getElementById('categoria_id').value);
        const almacenId = parseInt(document.getElementById('almacen_id').value);
        
        if (!nombre || nombre.length < 2) {
            this.mostrarNotificacion('El nombre del producto debe tener al menos 2 caracteres', 'error');
            return;
        }
        
        if (isNaN(cantidad) || cantidad < 0) {
            this.mostrarNotificacion('La cantidad debe ser un número mayor o igual a 0', 'error');
            return;
        }
        
        if (!unidadMedida) {
            this.mostrarNotificacion('La unidad de medida es requerida', 'error');
            return;
        }
        
        if (!estado) {
            this.mostrarNotificacion('Debe seleccionar un estado', 'error');
            return;
        }
        
        if (!categoriaId || categoriaId <= 0) {
            this.mostrarNotificacion('Debe seleccionar una categoría', 'error');
            return;
        }
        
        if (!almacenId || almacenId <= 0) {
            this.mostrarNotificacion('Debe seleccionar un almacén', 'error');
            return;
        }
        
        // Verificar si hay cambios
        if (!this.detectarCambios()) {
            this.mostrarNotificacion('No se han realizado cambios', 'warning');
            return;
        }
        
        // Confirmación antes de guardar
        const confirmado = await this.confirmarAccion(
            '¿Estás seguro que deseas guardar los cambios realizados en este producto?',
            'Confirmar Cambios',
            'info'
        );
        
        if (!confirmado) return;
        
        const btnSubmit = document.getElementById('btnGuardar');
        const originalText = btnSubmit.innerHTML;
        
        // Mostrar estado de carga
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnSubmit.disabled = true;
        
        try {
            // Enviar formulario
            e.target.submit();
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error al enviar el formulario', 'error');
            
            // Restaurar botón
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        }
    }

    async manejarCancelacion() {
        if (this.detectarCambios()) {
            const confirmado = await this.confirmarAccion(
                'Tienes cambios sin guardar. ¿Estás seguro que deseas salir sin guardar?',
                'Cambios Sin Guardar',
                'warning'
            );
            
            if (confirmado) {
                const urlParams = new URLSearchParams(window.location.search);
                const productId = urlParams.get('id') || new URL(window.location).pathname.split('/').pop().replace('.php', '');
                window.location.href = `ver-producto.php?id=${productId}`;
            }
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            const productId = urlParams.get('id') || new URL(window.location).pathname.split('/').pop().replace('.php', '');
            window.location.href = `ver-producto.php?id=${productId}`;
        }
    }

    async manejarCerrarSesion(event) {
        event.preventDefault();
        
        if (this.detectarCambios()) {
            const confirmado = await this.confirmarAccion(
                'Tienes cambios sin guardar. ¿Estás seguro que deseas cerrar sesión?',
                'Cambios Sin Guardar',
                'warning'
            );
            
            if (!confirmado) return;
        }
        
        const confirmado = await this.confirmarAccion(
            '¿Estás seguro que deseas cerrar sesión?',
            'Cerrar Sesión',
            'info'
        );
        
        if (confirmado) {
            this.mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        const container = document.getElementById('notificaciones-container');
        if (!container) return;

        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        
        let icono = 'fas fa-info-circle';
        if (tipo === 'exito') icono = 'fas fa-check-circle';
        if (tipo === 'error') icono = 'fas fa-exclamation-circle';
        if (tipo === 'warning') icono = 'fas fa-exclamation-triangle';
        
        notificacion.innerHTML = `
            <i class="${icono}"></i>
            <span>${mensaje}</span>
            <button class="cerrar" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(notificacion);

        // Auto-remover después del tiempo especificado
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.style.animation = 'slideOutRight 0.4s ease';
                setTimeout(() => {
                    notificacion.remove();
                }, 400);
            }
        }, duracion);
    }

    async confirmarAccion(mensaje, titulo = 'Confirmar', tipo = 'info') {
        return new Promise((resolve) => {
            // Crear modal de confirmación dinámico
            const modalHtml = `
                <div class="modal" style="display: block; z-index: 3000; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background: white; margin: 10% auto; width: 90%; max-width: 400px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                        <div class="modal-header" style="background: var(--primary-color); color: white; padding: 20px 25px;">
                            <h2 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-question-circle"></i>
                                ${titulo}
                            </h2>
                        </div>
                        <div class="modal-body" style="padding: 25px;">
                            <p style="margin: 0; text-align: center; font-size: 16px; line-height: 1.5;">${mensaje}</p>
                        </div>
                        <div class="modal-footer" style="background: #f8f9fa; padding: 20px 25px; display: flex; gap: 12px; justify-content: flex-end;">
                            <button class="btn-cancel" id="btnCancelar" style="background: #f8f9fa; color: #333; border: 1px solid #dee2e6; padding: 12px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button class="btn-confirm" id="btnConfirmar" style="background: var(--accent-color); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            const confirmModal = tempDiv.firstElementChild;
            
            document.body.appendChild(confirmModal);
            document.body.style.overflow = 'hidden';

            const btnConfirmar = confirmModal.querySelector('#btnConfirmar');
            const btnCancelar = confirmModal.querySelector('#btnCancelar');

            const cleanup = () => {
                document.body.removeChild(confirmModal);
                document.body.style.overflow = '';
            };

            btnConfirmar.addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            btnCancelar.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            // Cerrar con Escape
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    cleanup();
                    resolve(false);
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        });
    }
}

// Funciones globales para compatibilidad
async function eliminarProducto(id, nombre) {
    const confirmado = await window.productosEditar.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto',
        'danger'
    );
    
    if (confirmado) {
        window.productosEditar.mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                window.productosEditar.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                window.productosEditar.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.productosEditar.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    window.productosEditar.manejarCerrarSesion(event);
}

// Agregar estilos de animación adicionales
const additionalStyles = `
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    .cerrar {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 4px;
        transition: background 0.2s;
    }
    
    .cerrar:hover {
        background: rgba(0,0,0,0.1);
    }
`;

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.productosEditar = new ProductosEditar();
    console.log('Productos Editar inicializado correctamente');
});