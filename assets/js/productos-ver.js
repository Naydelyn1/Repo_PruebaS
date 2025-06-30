/* ============================================
   PRODUCTOS VER - JAVASCRIPT CORREGIDO
   ============================================ */

// ===== VARIABLES GLOBALES =====
let productosVer = null;

// ===== CLASE PRINCIPAL =====
class ProductosVer {
    constructor() {
        this.modal = null;
        this.form = null;
        this.maxStock = 0;
        this.inicializar();
    }

    inicializar() {
        console.log('🚀 Inicializando ProductosVer...');
        
        // Configurar elementos principales
        this.modal = document.getElementById('modalTransferencia');
        this.form = document.getElementById('formTransferencia');
        
        // Configurar componentes
        this.configurarSidebar();
        this.configurarControlesStock();
        this.configurarModal();
        this.configurarAlertas();
        this.configurarEventListeners();
        
        console.log('✅ ProductosVer inicializado correctamente');
        console.log('Modal encontrado:', !!this.modal);
        console.log('Form encontrado:', !!this.form);
    }

    configurarEventListeners() {
        // ⭐ INTERCEPTAR FORMULARIO - ESTO ES LO MÁS IMPORTANTE
        if (this.form) {
            // Remover cualquier listener previo
            this.form.removeEventListener('submit', this.handleFormSubmit);
            
            // Agregar nuevo listener
            this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
            console.log('✅ Event listener agregado al formulario');
        } else {
            console.error('❌ No se encontró el formulario formTransferencia');
        }

        // Eventos globales
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'flex') {
                this.cerrarModal();
            }
        });

        // Cerrar modal al hacer clic fuera
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.cerrarModal();
                }
            });
        }
    }

    // ⭐ FUNCIÓN PRINCIPAL PARA MANEJAR EL ENVÍO DEL FORMULARIO
    async handleFormSubmit(e) {
        e.preventDefault(); // ⭐ PREVENIR ENVÍO NORMAL
        e.stopPropagation();
        
        console.log('🎯 Formulario interceptado correctamente');
        
        // Validar datos
        const cantidad = parseInt(document.getElementById('cantidad_modal').value);
        const almacenDestino = document.getElementById('almacen_destino_modal').value;
        const productoNombre = document.getElementById('producto_nombre_modal').textContent;
        
        if (!almacenDestino) {
            this.mostrarNotificacion('Debe seleccionar un almacén de destino', 'error');
            return false;
        }
        
        if (cantidad < 1 || cantidad > this.maxStock) {
            this.mostrarNotificacion('La cantidad no es válida', 'error');
            return false;
        }

        // Confirmar acción
        const confirmado = await this.confirmarAccion(
            `¿Confirma la transferencia de ${cantidad} unidades de "${productoNombre}" al almacén seleccionado?`,
            'Confirmar Transferencia',
            'warning'
        );
        
        if (!confirmado) {
            return false;
        }

        // Procesar transferencia
        await this.procesarTransferencia();
        return false; // ⭐ IMPORTANTE: Siempre retornar false
    }

    async procesarTransferencia() {
        const submitButton = this.form.querySelector('.btn-confirm');
        const originalText = submitButton.innerHTML;
        
        // Mostrar estado de carga
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...';
        submitButton.disabled = true;
        
        this.mostrarNotificacion('Procesando transferencia...', 'info', 2000);

        try {
            const formData = new FormData(this.form);
            
            console.log('📤 Enviando datos por AJAX...');
            
            const response = await fetch('procesar_formulario.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Identificar como AJAX
                }
            });

            console.log('📥 Respuesta recibida:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Datos procesados:', data);

            if (data.success) {
                // ✅ ÉXITO
                this.mostrarNotificacion('¡Transferencia solicitada exitosamente!', 'exito');
                this.cerrarModal();
                
                // Recargar página después de 2 segundos
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                // ❌ ERROR DEL SERVIDOR
                this.mostrarNotificacion(data.message || 'Error al procesar la transferencia', 'error');
            }
        } catch (error) {
            // ❌ ERROR DE CONEXIÓN
            console.error('Error en la transferencia:', error);
            this.mostrarNotificacion('Error de conexión. Por favor, inténtelo de nuevo.', 'error');
        } finally {
            // Restaurar botón
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    configurarSidebar() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const submenuContainers = document.querySelectorAll('.submenu-container');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (mainContent) {
                    mainContent.classList.toggle('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        }

        // Submenús
        submenuContainers.forEach(container => {
            const link = container.querySelector('a');
            const submenu = container.querySelector('.submenu');
            const chevron = link?.querySelector('.fa-chevron-down');
            
            if (link && submenu) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    submenuContainers.forEach(otherContainer => {
                        if (otherContainer !== container) {
                            const otherSubmenu = otherContainer.querySelector('.submenu');
                            const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                            
                            if (otherSubmenu?.classList.contains('activo')) {
                                otherSubmenu.classList.remove('activo');
                                if (otherChevron) {
                                    otherChevron.style.transform = 'rotate(0deg)';
                                }
                            }
                        }
                    });
                    
                    submenu.classList.toggle('activo');
                    const isExpanded = submenu.classList.contains('activo');
                    
                    if (chevron) {
                        chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                });
            }
        });

        // Cerrar menú móvil
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    if (mainContent) {
                        mainContent.classList.remove('with-sidebar');
                    }
                    
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }

    configurarControlesStock() {
        const stockButtons = document.querySelectorAll('.stock-btn');
        console.log('🔧 Configurando controles de stock:', stockButtons.length);
        
        stockButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = button.dataset.id;
                const accion = button.dataset.accion;
                
                if (productId && accion) {
                    await this.actualizarStock(productId, accion, button);
                }
            });
        });
    }

    async actualizarStock(productId, accion, button) {
        const stockElement = document.getElementById('cantidad-actual');
        if (!stockElement) return;
        
        const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
        
        if (accion === 'restar' && currentStock <= 0) {
            this.mostrarNotificacion('No se puede reducir más el stock', 'error');
            return;
        }

        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const formData = new FormData();
            formData.append('producto_id', productId);
            formData.append('accion', accion);

            const response = await fetch('actualizar_cantidad.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                stockElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
                this.actualizarClasesStock(stockElement, data.nueva_cantidad);
                this.actualizarBotonTransferencia(data.nueva_cantidad);
                this.mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
                
                // Animación
                stockElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    stockElement.style.transform = 'scale(1)';
                }, 200);
            } else {
                this.mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
            
            if (accion === 'restar') {
                const newStock = parseInt(stockElement.textContent.replace(/,/g, ''));
                const decreaseBtn = document.querySelector('.stock-btn[data-accion="restar"]');
                if (decreaseBtn) {
                    decreaseBtn.disabled = newStock <= 0;
                }
            }
        }
    }

    actualizarClasesStock(element, cantidad) {
        const stockValue = element.closest('.stock-value');
        if (stockValue) {
            stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good');
            
            if (cantidad < 5) {
                stockValue.classList.add('stock-critical');
            } else if (cantidad < 10) {
                stockValue.classList.add('stock-warning');
            } else {
                stockValue.classList.add('stock-good');
            }
        }
    }

    actualizarBotonTransferencia(cantidad) {
        const transferButton = document.querySelector('.btn-transfer');
        if (!transferButton) return;

        if (cantidad > 0) {
            transferButton.disabled = false;
            transferButton.style.opacity = '1';
            transferButton.dataset.cantidad = cantidad;
        } else {
            transferButton.disabled = true;
            transferButton.style.opacity = '0.6';
        }
    }

    configurarModal() {
        if (!this.modal) return;

        // Configurar botones de cerrar
        const closeButtons = this.modal.querySelectorAll('.modal-close, .btn-cancel');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => this.cerrarModal());
        });

        // Configurar controles de cantidad
        this.configurarControlesCantidad();
    }

    configurarControlesCantidad() {
        const minusBtn = this.modal?.querySelector('.qty-btn.minus');
        const plusBtn = this.modal?.querySelector('.qty-btn.plus');
        const quantityInput = this.modal?.querySelector('.qty-input');

        if (minusBtn && plusBtn && quantityInput) {
            minusBtn.addEventListener('click', () => this.adjustQuantity(-1));
            plusBtn.addEventListener('click', () => this.adjustQuantity(1));
            
            quantityInput.addEventListener('change', () => this.validarCantidad());
            quantityInput.addEventListener('input', () => this.validarCantidad());
        }
    }

    configurarAlertas() {
        const alertas = document.querySelectorAll('.alert');
        alertas.forEach(alerta => {
            setTimeout(() => {
                alerta.style.animation = 'slideOutUp 0.5s ease-in-out';
                setTimeout(() => alerta.remove(), 500);
            }, 5000);
        });
    }

    // ⭐ FUNCIÓN PARA ABRIR MODAL
    abrirModal(datos) {
        if (!this.modal) {
            console.error('❌ Modal no encontrado');
            return;
        }

        console.log('📱 Abriendo modal con datos:', datos);

        // Llenar datos del modal
        const elements = {
            'producto_id_modal': datos.id,
            'almacen_origen_modal': datos.almacen,
            'producto_nombre_modal': datos.nombre,
            'stock_disponible_modal': `${datos.cantidad} unidades`
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                if (element.tagName === 'INPUT') {
                    element.value = value;
                } else {
                    element.textContent = value;
                }
            }
        });
        
        const quantityInput = document.getElementById('cantidad_modal');
        if (quantityInput) {
            quantityInput.value = 1;
            quantityInput.max = datos.cantidad;
        }
        
        const almacenSelect = document.getElementById('almacen_destino_modal');
        if (almacenSelect) {
            almacenSelect.value = '';
        }
        
        this.maxStock = parseInt(datos.cantidad);
        
        // Mostrar modal
        this.modal.style.display = 'flex';
        this.modal.setAttribute('aria-hidden', 'false');
        
        // Focus en input
        setTimeout(() => {
            if (quantityInput) quantityInput.focus();
        }, 100);
        
        document.body.style.overflow = 'hidden';
    }

    cerrarModal() {
        if (!this.modal) return;

        this.modal.style.display = 'none';
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        
        if (this.form) {
            this.form.reset();
        }
        
        console.log('📱 Modal cerrado');
    }

    adjustQuantity(increment) {
        const quantityInput = document.getElementById('cantidad_modal');
        if (!quantityInput) return;

        let currentValue = parseInt(quantityInput.value) || 1;
        let newValue = currentValue + increment;
        
        newValue = Math.max(1, Math.min(newValue, this.maxStock));
        
        quantityInput.value = newValue;
        this.validarCantidad();
    }

    validarCantidad() {
        const quantityInput = document.getElementById('cantidad_modal');
        if (!quantityInput) return;

        const value = parseInt(quantityInput.value);
        const submitButton = this.form?.querySelector('.btn-confirm');
        
        if (value < 1 || value > this.maxStock || isNaN(value)) {
            quantityInput.style.borderColor = '#dc3545';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.style.opacity = '0.6';
            }
            
            if (value > this.maxStock) {
                this.mostrarNotificacion(`La cantidad no puede ser mayor a ${this.maxStock}`, 'error');
            }
        } else {
            quantityInput.style.borderColor = '#28a745';
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
            }
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        let container = document.getElementById('notificaciones-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificaciones-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10001;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        const iconos = {
            exito: 'fa-check-circle',
            error: 'fa-exclamation-triangle', 
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        const colores = {
            exito: '#28a745',
            error: '#dc3545',
            warning: '#ffc107', 
            info: '#0a253c'
        };

        const notificacion = document.createElement('div');
        notificacion.style.cssText = `
            background: white;
            border-left: 5px solid ${colores[tipo] || colores.info};
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 4px 12px rgba(10, 37, 60, 0.15);
            animation: slideInRight 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        `;

        notificacion.innerHTML = `
            <i class="fas ${iconos[tipo] || iconos.info}" style="font-size: 20px; color: ${colores[tipo] || colores.info};"></i>
            <span style="flex: 1; color: #0a253c; font-weight: 500;">${mensaje}</span>
            <button class="cerrar" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666;">&times;</button>
        `;

        container.appendChild(notificacion);

        const cerrarBtn = notificacion.querySelector('.cerrar');
        cerrarBtn.addEventListener('click', () => {
            notificacion.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notificacion.remove(), 300);
        });

        if (duracion > 0) {
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notificacion.remove(), 300);
                }
            }, duracion);
        }
    }

    async confirmarAccion(mensaje, titulo = 'Confirmar', tipo = 'info') {
        return new Promise((resolve) => {
            if (confirm(`${titulo}\n\n${mensaje}`)) {
                resolve(true);
            } else {
                resolve(false);
            }
        });
    }

    async manejarCerrarSesion(event) {
        event.preventDefault();
        
        const confirmado = await this.confirmarAccion(
            '¿Estás seguro que deseas cerrar sesión?',
            'Cerrar Sesión'
        );
        
        if (confirmado) {
            this.mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    }
}

// ===== FUNCIONES GLOBALES =====
function abrirModalTransferencia(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    if (productosVer) {
        productosVer.abrirModal(datos);
    } else {
        console.error('❌ productosVer no está inicializado');
    }
}

function cerrarModal() {
    if (productosVer) {
        productosVer.cerrarModal();
    }
}

function adjustQuantity(increment) {
    if (productosVer) {
        productosVer.adjustQuantity(increment);
    }
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    if (!productosVer) return;
    
    const confirmado = await productosVer.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto'
    );
    
    if (confirmado) {
        productosVer.mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                productosVer.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                setTimeout(() => window.location.href = 'listar.php', 2000);
            } else {
                productosVer.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            productosVer.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    if (productosVer) {
        productosVer.manejarCerrarSesion(event);
    }
}

// ===== ESTILOS CSS =====
if (!document.getElementById('productos-ver-animations')) {
    const styles = document.createElement('style');
    styles.id = 'productos-ver-animations';
    styles.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(30px); }
        }
        @keyframes slideOutUp {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
        .stock-value {
            transition: transform 0.2s ease;
        }
    `;
    document.head.appendChild(styles);
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Iniciando ProductosVer...');
    productosVer = new ProductosVer();
    
    // Hacer disponible globalmente
    window.productosVer = productosVer;
    
    console.log('✅ ProductosVer disponible globalmente');
});

// ===== DEBUG =====
window.debugProductosVer = function() {
    console.log('🔍 Estado de ProductosVer:', {
        instancia: !!productosVer,
        modal: !!productosVer?.modal,
        form: !!productosVer?.form,
        maxStock: productosVer?.maxStock
    });
};