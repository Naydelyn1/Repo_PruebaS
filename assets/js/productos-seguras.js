/**
 * Funciones seguras para manejo de URLs limpias en productos
 * GRUPO SEAL - Sistema de Gestión de Inventario
 */

// Variables globales
window.ProductosSeguras = {
    // ⭐ FUNCIÓN SEGURA PARA VER PRODUCTO
    verProducto: function(productoId, contextParams = '') {
        // Obtener contexto desde el DOM si no se proporciona
        if (!contextParams && document.body.dataset.context) {
            contextParams = document.body.dataset.context;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'ver_redirect.php';
        form.style.display = 'none';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'producto_id';
        inputId.value = productoId;
        form.appendChild(inputId);
        
        if (contextParams) {
            const inputContext = document.createElement('input');
            inputContext.type = 'hidden';
            inputContext.name = 'context_params';
            inputContext.value = contextParams;
            form.appendChild(inputContext);
        }
        
        document.body.appendChild(form);
        form.submit();
    },

    // ⭐ FUNCIÓN SEGURA PARA EDITAR PRODUCTO
    editarProducto: function(productoId, contextParams = '') {
        // Obtener contexto desde el DOM si no se proporciona
        if (!contextParams && document.body.dataset.context) {
            contextParams = document.body.dataset.context;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'editar_redirect.php';
        form.style.display = 'none';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'producto_id';
        inputId.value = productoId;
        form.appendChild(inputId);
        
        if (contextParams) {
            const inputContext = document.createElement('input');
            inputContext.type = 'hidden';
            inputContext.name = 'context_params';
            inputContext.value = contextParams;
            form.appendChild(inputContext);
        }
        
        document.body.appendChild(form);
        form.submit();
    },

    // ⭐ FUNCIÓN PARA ELIMINAR PRODUCTO CON REDIRECCIÓN CONTEXTUAL
    eliminarProducto: async function(productoId, nombreProducto, returnUrl = null) {
        if (!confirm(`¿Está seguro de que desea eliminar el producto "${nombreProducto}"?\n\nEsta acción no se puede deshacer.`)) {
            return false;
        }

        // Mostrar notificación de carga
        if (window.mostrarNotificacion) {
            window.mostrarNotificacion('Eliminando producto...', 'info');
        }
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: productoId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                if (window.mostrarNotificacion) {
                    window.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                }
                
                // Redirigir después de 2 segundos
                setTimeout(() => {
                    if (returnUrl) {
                        window.location.href = returnUrl;
                    } else if (document.body.dataset.context) {
                        // Usar el contexto para construir la URL de retorno
                        window.location.href = 'listar.php?' + document.body.dataset.context;
                    } else {
                        window.location.href = 'listar.php';
                    }
                }, 2000);
                
                return true;
            } else {
                if (window.mostrarNotificacion) {
                    window.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
                }
                return false;
            }
        } catch (error) {
            console.error('Error:', error);
            if (window.mostrarNotificacion) {
                window.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
            }
            return false;
        }
    },

    // ⭐ FUNCIÓN PARA ACTUALIZAR CANTIDAD CON NOTIFICACIONES
    actualizarCantidad: async function(productoId, accion) {
        try {
            const formData = new FormData();
            formData.append('producto_id', productoId);
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
                // Actualizar la cantidad en la interfaz
                const cantidadElement = document.getElementById(`cantidad-${productoId}`);
                if (cantidadElement) {
                    cantidadElement.textContent = data.nueva_cantidad.toLocaleString();
                    
                    // Actualizar clases de estado del stock
                    cantidadElement.className = 'stock-value';
                    if (data.estado_stock === 'critico') {
                        cantidadElement.classList.add('stock-critical');
                    } else if (data.estado_stock === 'bajo') {
                        cantidadElement.classList.add('stock-warning');
                    } else {
                        cantidadElement.classList.add('stock-good');
                    }
                }

                // Actualizar botones de stock
                const decreaseBtn = document.querySelector(`[data-id="${productoId}"][data-accion="restar"]`);
                if (decreaseBtn) {
                    decreaseBtn.disabled = !data.puede_restar;
                }

                if (window.mostrarNotificacion) {
                    window.mostrarNotificacion(data.message, 'exito', 3000);
                }
                
                return data;
            } else {
                if (window.mostrarNotificacion) {
                    window.mostrarNotificacion(data.message || 'Error al actualizar la cantidad', 'error');
                }
                return null;
            }
        } catch (error) {
            console.error('Error:', error);
            if (window.mostrarNotificacion) {
                window.mostrarNotificacion('Error de conexión al actualizar cantidad', 'error');
            }
            return null;
        }
    },

    // ⭐ FUNCIÓN PARA INICIALIZAR EVENTOS DE STOCK
    inicializarControlesStock: function() {
        document.addEventListener('click', function(e) {
            if (e.target.matches('.stock-btn[data-accion]') || e.target.closest('.stock-btn[data-accion]')) {
                e.preventDefault();
                
                const button = e.target.matches('.stock-btn[data-accion]') ? 
                              e.target : e.target.closest('.stock-btn[data-accion]');
                
                if (button.disabled) return;
                
                const productoId = button.dataset.id;
                const accion = button.dataset.accion;
                
                if (productoId && accion) {
                    // Deshabilitar botón temporalmente
                    button.disabled = true;
                    
                    window.ProductosSeguras.actualizarCantidad(productoId, accion)
                        .finally(() => {
                            // Rehabilitar botón después de la respuesta
                            setTimeout(() => {
                                button.disabled = false;
                            }, 500);
                        });
                }
            }
        });
    }
};

// ⭐ FUNCIONES GLOBALES DE COMPATIBILIDAD
window.verProductoSeguro = window.ProductosSeguras.verProducto;
window.editarProductoSeguro = window.ProductosSeguras.editarProducto;
window.eliminarProductoSeguro = window.ProductosSeguras.eliminarProducto;

// Funciones de compatibilidad con el código existente
window.verProductoConContexto = window.ProductosSeguras.verProducto;
window.editarProductoConContexto = window.ProductosSeguras.editarProducto;
window.verProducto = window.ProductosSeguras.verProducto;
window.editarProducto = window.ProductosSeguras.editarProducto;
window.eliminarProducto = window.ProductosSeguras.eliminarProducto;

// ⭐ INICIALIZACIÓN AUTOMÁTICA
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar controles de stock
    window.ProductosSeguras.inicializarControlesStock();
    
    // Debugging en desarrollo
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ ProductosSeguras inicializado correctamente');
        console.log('📊 Contexto actual:', document.body.dataset.context || 'ninguno');
    }
});

// ⭐ EXPORTAR PARA USO EN MÓDULOS (OPCIONAL)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.ProductosSeguras;
}