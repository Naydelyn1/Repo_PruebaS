/* ============================================
   PRODUCTOS LISTAR - JAVASCRIPT COMPLETO FUNCIONAL
   Versión: 2.0.1 - Sin errores de sintaxis
   ============================================ */

// ===== VARIABLES GLOBALES =====
let modoSeleccion = false;
let productosSeleccionados = new Set();
let carritoEntrega = [];
let carritoMinimizado = false;

// ===== CLAVES PARA LOCALSTORAGE =====
const CARRITO_STORAGE_KEY = 'productos_entrega_carrito';

// ===== VARIABLES PARA PRESERVAR CONTEXTO =====
let CONTEXTO_ACTUAL = {
    almacen_id: null,
    categoria_id: null,
    busqueda: '',
    pagina: 1
};

// ===== FUNCIONES DE CONTEXTO =====
function obtenerContextoActual() {
    const body = document.body;
    CONTEXTO_ACTUAL.almacen_id = body.dataset.almacenId && body.dataset.almacenId !== 'null' ? body.dataset.almacenId : null;
    CONTEXTO_ACTUAL.categoria_id = body.dataset.categoriaId && body.dataset.categoriaId !== 'null' ? body.dataset.categoriaId : null;
    
    const urlParams = new URLSearchParams(window.location.search);
    CONTEXTO_ACTUAL.busqueda = urlParams.get('busqueda') || '';
    CONTEXTO_ACTUAL.pagina = parseInt(urlParams.get('pagina')) || 1;
    
    console.log('📍 Contexto obtenido:', CONTEXTO_ACTUAL);
}

function construirUrlConContexto() {
    const params = new URLSearchParams();
    
    if (CONTEXTO_ACTUAL.almacen_id) {
        params.set('almacen_id', CONTEXTO_ACTUAL.almacen_id);
    }
    
    if (CONTEXTO_ACTUAL.categoria_id) {
        params.set('categoria_id', CONTEXTO_ACTUAL.categoria_id);
    }
    
    if (CONTEXTO_ACTUAL.busqueda) {
        params.set('busqueda', CONTEXTO_ACTUAL.busqueda);
    }
    
    const queryString = params.toString();
    return window.location.pathname + (queryString ? '?' + queryString : '');
}

function recargarConContexto() {
    const urlConContexto = construirUrlConContexto();
    console.log('🔄 Recargando con contexto:', urlConContexto);
    window.location.href = urlConContexto;
}

// ===== GESTIÓN DEL CARRITO =====
function guardarCarritoEnStorage() {
    try {
        const carritoData = {
            productos: carritoEntrega,
            seleccionados: Array.from(productosSeleccionados),
            modoActivo: modoSeleccion,
            timestamp: Date.now(),
            contexto: CONTEXTO_ACTUAL
        };
        localStorage.setItem(CARRITO_STORAGE_KEY, JSON.stringify(carritoData));
        console.log('💾 Carrito guardado');
    } catch (error) {
        console.error('Error al guardar carrito:', error);
    }
}

function restaurarCarritoGuardado() {
    try {
        const carritoGuardado = localStorage.getItem(CARRITO_STORAGE_KEY);
        if (!carritoGuardado) return;
        
        const carritoData = JSON.parse(carritoGuardado);
        
        // Verificar que no sea muy antiguo (2 horas)
        const dosHoras = 2 * 60 * 60 * 1000;
        if (Date.now() - carritoData.timestamp > dosHoras) {
            localStorage.removeItem(CARRITO_STORAGE_KEY);
            return;
        }
        
        // Verificar contexto
        if (carritoData.contexto) {
            const contextoCoincide = 
                carritoData.contexto.almacen_id === CONTEXTO_ACTUAL.almacen_id &&
                carritoData.contexto.categoria_id === CONTEXTO_ACTUAL.categoria_id;
            
            if (!contextoCoincide) {
                localStorage.removeItem(CARRITO_STORAGE_KEY);
                return;
            }
        }
        
        // Restaurar datos
        carritoEntrega = carritoData.productos || [];
        productosSeleccionados = new Set(carritoData.seleccionados || []);
        
        if (carritoEntrega.length > 0) {
            modoSeleccion = true;
            activarModoSeleccionVisual();
            
            productosSeleccionados.forEach(productoId => {
                const checkbox = document.querySelector(`[data-id="${productoId}"]`);
                if (checkbox) {
                    checkbox.classList.add('checked');
                }
            });
            
            const carritoElement = document.getElementById('carritoEntrega');
            if (carritoElement) {
                carritoElement.classList.add('show');
            }
            actualizarCarrito();
            
            mostrarNotificacion(`Carrito restaurado con ${carritoEntrega.length} productos`, 'info', 3000);
        }
        
    } catch (error) {
        console.error('Error al restaurar carrito:', error);
        localStorage.removeItem(CARRITO_STORAGE_KEY);
    }
}

function limpiarCarritoStorage() {
    localStorage.removeItem(CARRITO_STORAGE_KEY);
    console.log('🗑️ Carrito eliminado del storage');
}

// ===== MODO SELECCIÓN =====
function toggleModoSeleccion() {
    modoSeleccion = !modoSeleccion;
    
    if (modoSeleccion) {
        activarModoSeleccion();
    } else {
        desactivarModoSeleccion();
    }
    
    guardarCarritoEnStorage();
}

function activarModoSeleccion() {
    modoSeleccion = true;
    activarModoSeleccionVisual();
    
    const carritoElement = document.getElementById('carritoEntrega');
    if (carritoElement) {
        carritoElement.classList.add('show');
    }
    
    mostrarNotificacion('Modo de selección activado', 'info');
}

function activarModoSeleccionVisual() {
    const tabla = document.getElementById('productosTabla');
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    
    if (tabla) {
        tabla.classList.add('modo-seleccion');
    }
    
    if (btnEntregarPersonal) {
        btnEntregarPersonal.classList.add('active');
        btnEntregarPersonal.innerHTML = '<i class="fas fa-times"></i><span>Cancelar Selección</span>';
    }
    
    document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
        el.style.display = 'table-cell';
    });
}

function desactivarModoSeleccion() {
    modoSeleccion = false;
    const tabla = document.getElementById('productosTabla');
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    const carritoElement = document.getElementById('carritoEntrega');
    
    if (tabla) {
        tabla.classList.remove('modo-seleccion');
    }
    
    if (btnEntregarPersonal) {
        btnEntregarPersonal.classList.remove('active');
        btnEntregarPersonal.innerHTML = '<i class="fas fa-hand-holding"></i><span>Entregar a Personal</span>';
    }
    
    if (carritoElement) {
        carritoElement.classList.remove('show');
    }
    
    document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
        el.style.display = 'none';
    });
    
    limpiarCarrito();
}

// ===== GESTIÓN DE PRODUCTOS EN CARRITO =====
function manejarSeleccionProducto(checkbox) {
    const productoId = checkbox.dataset.id;
    const isChecked = checkbox.classList.contains('checked');
    
    if (isChecked) {
        checkbox.classList.remove('checked');
        productosSeleccionados.delete(productoId);
        eliminarDelCarrito(productoId);
    } else {
        checkbox.classList.add('checked');
        productosSeleccionados.add(productoId);
        agregarAlCarrito(productoId);
    }
    
    actualizarCarrito();
    guardarCarritoEnStorage();
}

function agregarAlCarrito(productoId) {
    const row = document.querySelector(`[data-producto-id="${productoId}"]`);
    if (!row) {
        console.warn('No se encontró la fila del producto:', productoId);
        return;
    }
    
    const productDataScript = row.querySelector('.product-data');
    if (!productDataScript) {
        console.warn('No se encontraron datos del producto:', productoId);
        return;
    }
    
    try {
        const productData = JSON.parse(productDataScript.textContent);
        
        const itemCarrito = {
            id: productData.id,
            nombre: productData.nombre,
            modelo: productData.modelo || '',
            color: productData.color || '',
            talla: productData.talla || '',
            cantidad: 1,
            maxCantidad: productData.cantidad,
            almacen: productData.almacen,
            almacenNombre: productData.almacen_nombre
        };
        
        carritoEntrega.push(itemCarrito);
        console.log('➕ Producto agregado:', itemCarrito.nombre);
        
    } catch (error) {
        console.error('Error al agregar producto:', error);
        mostrarNotificacion('Error al agregar el producto', 'error');
    }
}

function eliminarDelCarrito(productoId) {
    const initialLength = carritoEntrega.length;
    carritoEntrega = carritoEntrega.filter(item => item.id != productoId);
    
    if (carritoEntrega.length < initialLength) {
        console.log('➖ Producto eliminado del carrito:', productoId);
    }
}

function actualizarCarrito() {
    const carritoLista = document.getElementById('carritoLista');
    const carritoContador = document.querySelector('.carrito-contador');
    const totalUnidades = document.getElementById('totalUnidades');
    const btnProceder = document.querySelector('.btn-proceder');
    
    if (!carritoLista || !carritoContador) {
        console.warn('Elementos del carrito no encontrados');
        return;
    }
    
    const valorNuevo = carritoEntrega.length;
    
    if (carritoEntrega.length === 0) {
        carritoLista.innerHTML = `
            <div class="carrito-vacio">
                <i class="fas fa-hand-holding"></i>
                <p>Selecciona productos para entregar</p>
            </div>
        `;
        carritoContador.textContent = '0';
        if (totalUnidades) totalUnidades.textContent = '0';
        if (btnProceder) btnProceder.disabled = true;
        return;
    }
    
    let html = '';
    let totalUnidadesCount = 0;
    
    carritoEntrega.forEach(item => {
        totalUnidadesCount += item.cantidad;
        html += `
            <div class="carrito-item" data-id="${item.id}">
                <div class="item-info">
                    <div class="item-nombre">${item.nombre}</div>
                    <div class="item-detalles">
                        ${item.modelo ? `Modelo: ${item.modelo}` : ''}
                        ${item.color ? ` | Color: ${item.color}` : ''}
                        ${item.talla ? ` | Talla: ${item.talla}` : ''}
                    </div>
                </div>
                <div class="item-cantidad">
                    <button class="qty-btn-small minus" onclick="ajustarCantidadCarrito(${item.id}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="qty-display">${item.cantidad}</span>
                    <button class="qty-btn-small plus" onclick="ajustarCantidadCarrito(${item.id}, 1)" 
                            ${item.cantidad >= item.maxCantidad ? 'disabled' : ''}>
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <button class="item-remove" onclick="removerDelCarrito(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
    
    carritoLista.innerHTML = html;
    carritoContador.textContent = valorNuevo;
    if (totalUnidades) totalUnidades.textContent = totalUnidadesCount;
    if (btnProceder) btnProceder.disabled = false;
}

function ajustarCantidadCarrito(productoId, cambio) {
    const item = carritoEntrega.find(item => item.id == productoId);
    if (item) {
        const nuevaCantidad = item.cantidad + cambio;
        if (nuevaCantidad >= 1 && nuevaCantidad <= item.maxCantidad) {
            item.cantidad = nuevaCantidad;
            actualizarCarrito();
            guardarCarritoEnStorage();
        }
    }
}

function removerDelCarrito(productoId) {
    const checkbox = document.querySelector(`[data-id="${productoId}"]`);
    if (checkbox) {
        checkbox.classList.remove('checked');
    }
    
    productosSeleccionados.delete(productoId.toString());
    eliminarDelCarrito(productoId);
    actualizarCarrito();
    guardarCarritoEnStorage();
}

function limpiarCarrito() {
    carritoEntrega = [];
    productosSeleccionados.clear();
    
    document.querySelectorAll('.selection-checkbox.checked').forEach(checkbox => {
        checkbox.classList.remove('checked');
    });
    
    actualizarCarrito();
    limpiarCarritoStorage();
}

// ===== MODAL DE ENTREGA =====
function procederEntrega() {
    if (carritoEntrega.length === 0) {
        mostrarNotificacion('No hay productos seleccionados', 'warning');
        return;
    }
    
    mostrarModalEntrega();
}

function mostrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    const productosResumen = document.getElementById('productosResumen');
    const totalUnidadesModal = document.getElementById('totalUnidadesModal');
    const totalTiposModal = document.getElementById('totalTiposModal');
    
    if (!modal) {
        console.error('Modal de entrega no encontrado');
        return;
    }
    
    let html = '';
    let totalUnidades = 0;
    
    carritoEntrega.forEach(item => {
        totalUnidades += item.cantidad;
        html += `
            <div class="producto-resumen-item">
                <div class="producto-resumen-info">
                    <strong>${item.nombre}</strong>
                    <div class="producto-resumen-detalles">
                        ${item.modelo ? `Modelo: ${item.modelo}` : ''}
                        ${item.color ? ` | Color: ${item.color}` : ''}
                        ${item.talla ? ` | Talla: ${item.talla}` : ''}
                    </div>
                </div>
                <div class="producto-resumen-cantidad">
                    <span class="cantidad-badge">${item.cantidad}</span>
                </div>
            </div>
        `;
    });
    
    if (productosResumen) productosResumen.innerHTML = html;
    if (totalUnidadesModal) totalUnidadesModal.textContent = totalUnidades;
    if (totalTiposModal) totalTiposModal.textContent = carritoEntrega.length;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
    
    setTimeout(() => {
        const nombreInput = document.getElementById('nombreDestinatario');
        if (nombreInput) nombreInput.focus();
    }, 300);
}

function cerrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
    
    const form = document.getElementById('formEntregaPersonal');
    if (form) {
        form.reset();
    }
    
    const btnConfirmar = document.querySelector('.btn-confirm');
    if (btnConfirmar) {
        btnConfirmar.disabled = true;
    }
}

function validarFormularioEntrega() {
    const nombre = document.getElementById('nombreDestinatario');
    const dni = document.getElementById('dniDestinatario');
    const btnConfirmar = document.querySelector('.btn-confirm');
    
    if (!nombre || !dni || !btnConfirmar) return;
    
    const nombreValido = nombre.value.trim().length >= 3;
    const dniValido = /^[0-9]{8}$/.test(dni.value.trim());
    
    btnConfirmar.disabled = !(nombreValido && dniValido);
}

function validarDNI(e) {
    const input = e.target;
    let value = input.value.replace(/[^0-9]/g, '');
    
    if (value.length > 8) {
        value = value.substring(0, 8);
    }
    
    input.value = value;
    validarFormularioEntrega();
}

// ===== CONFIRMAR ENTREGA =====
async function confirmarEntrega() {
    const nombre = document.getElementById('nombreDestinatario');
    const dni = document.getElementById('dniDestinatario');
    
    if (!nombre || !dni) {
        mostrarNotificacion('Campos del formulario no encontrados', 'error');
        return;
    }
    
    const nombreValue = nombre.value.trim();
    const dniValue = dni.value.trim();
    
    if (!nombreValue || !dniValue || dniValue.length !== 8) {
        mostrarNotificacion('Complete todos los campos correctamente', 'error');
        return;
    }
    
    const datosEntrega = {
        tipo_operacion: 'entrega_personal',
        destinatario_nombre: nombreValue,
        destinatario_dni: dniValue,
        productos: carritoEntrega.map(item => ({
            id: item.id,
            cantidad: item.cantidad
        }))
    };
    
    const btnConfirmar = document.querySelector('.btn-confirm');
    if (btnConfirmar) {
        const textoOriginal = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        btnConfirmar.disabled = true;
        
        try {
            const response = await fetch('procesar_formulario.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(datosEntrega)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('📥 Respuesta del servidor:', data);

            if (data.success) {
                mostrarNotificacion('¡Entrega registrada exitosamente!', 'success');
                
                cerrarModalEntrega();
                desactivarModoSeleccion();
                
                setTimeout(() => {
                    recargarConContexto();
                }, 2000);
                
            } else {
                mostrarNotificacion(data.message || 'Error al registrar la entrega', 'error');
                btnConfirmar.innerHTML = textoOriginal;
                btnConfirmar.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
            btnConfirmar.innerHTML = textoOriginal;
            btnConfirmar.disabled = false;
        }
    }
}

// ===== MODAL DE TRANSFERENCIA =====
function abrirModalEnvio(button) {
    const modal = document.getElementById('modalTransferencia');
    if (!modal) return;
    
    const productoId = button.dataset.id;
    const productoNombre = button.dataset.nombre;
    const almacenOrigen = button.dataset.almacen;
    const stockDisponible = button.dataset.cantidad;
    
    const productoIdInput = document.getElementById('producto_id');
    const almacenOrigenInput = document.getElementById('almacen_origen');
    const productoNombreElement = document.getElementById('producto_nombre');
    const stockDisponibleElement = document.getElementById('stock_disponible');
    const cantidadInput = document.getElementById('cantidad');
    
    if (productoIdInput) productoIdInput.value = productoId;
    if (almacenOrigenInput) almacenOrigenInput.value = almacenOrigen;
    if (productoNombreElement) productoNombreElement.textContent = productoNombre;
    if (stockDisponibleElement) stockDisponibleElement.textContent = stockDisponible;
    if (cantidadInput) {
        cantidadInput.max = stockDisponible;
        cantidadInput.value = 1;
    }
    
    modal.classList.add('show');
    modal.style.display = 'flex';
}

function cerrarModal() {
    const modal = document.getElementById('modalTransferencia');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

function adjustQuantity(change) {
    const cantidadInput = document.getElementById('cantidad');
    if (!cantidadInput) return;
    
    const currentValue = parseInt(cantidadInput.value) || 1;
    const maxValue = parseInt(cantidadInput.max);
    const newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= maxValue) {
        cantidadInput.value = newValue;
    }
}

// ===== MANEJO DE STOCK =====
async function manejarCambioStock(button) {
    const productoId = button.dataset.id;
    const accion = button.dataset.accion;
    const stockElement = document.getElementById(`cantidad-${productoId}`);
    
    if (!stockElement) {
        console.error('Elemento de stock no encontrado:', productoId);
        mostrarNotificacion('Error: Elemento de stock no encontrado', 'error');
        return;
    }
    
    const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
    
    if (accion === 'restar' && currentStock <= 0) {
        mostrarNotificacion('No se puede reducir más el stock', 'warning');
        return;
    }
    
    button.disabled = true;
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const formData = new FormData();
        formData.append('producto_id', productoId);
        formData.append('accion', accion);
        
        const response = await fetch('actualizar_cantidad.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            const nuevaCantidad = parseInt(data.nueva_cantidad);
            stockElement.textContent = nuevaCantidad.toLocaleString();
            
            // Actualizar clases de stock
            actualizarClaseStock(stockElement, nuevaCantidad);
            
            // Animación visual
            stockElement.style.transform = 'scale(1.15)';
            stockElement.style.color = '#28a745';
            
            setTimeout(() => {
                stockElement.style.transform = 'scale(1)';
                stockElement.style.color = '';
            }, 400);
            
            const accionTexto = accion === 'sumar' ? 'aumentado' : 'reducido';
            mostrarNotificacion(
                `Stock ${accionTexto} correctamente. Nuevo stock: ${nuevaCantidad.toLocaleString()} unidades`, 
                'success',
                3000
            );
            
        } else {
            mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
        }
        
    } catch (error) {
        console.error('Error en la petición:', error);
        mostrarNotificacion('Error de conexión', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

function actualizarClaseStock(element, cantidad) {
    const stockValue = element.closest('.stock-value') || element;
    
    stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good', 'stock-empty');
    
    if (cantidad === 0) {
        stockValue.classList.add('stock-empty');
    } else if (cantidad < 5) {
        stockValue.classList.add('stock-critical');
    } else if (cantidad < 10) {
        stockValue.classList.add('stock-warning');
    } else {
        stockValue.classList.add('stock-good');
    }
}

function configurarControlesStock() {
    const stockButtons = document.querySelectorAll('.stock-btn');
    console.log('🔧 Configurando controles de stock:', stockButtons.length);
    
    stockButtons.forEach((button) => {
        button.removeEventListener('click', handleStockClick);
        button.addEventListener('click', handleStockClick);
    });
}

async function handleStockClick(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.id;
    const accion = button.dataset.accion;
    
    if (productId && accion) {
        await manejarCambioStock(button);
    }
}

// ===== SISTEMA DE NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    let container = document.getElementById('notificaciones-container');
    
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificaciones-container';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 99999;
            max-width: 380px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colores = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107', 
        info: '#0a253c'
    };
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    
    notificacion.innerHTML = `
        <div class="notificacion-icon">
            <i class="fas ${iconos[tipo]}"></i>
        </div>
        <div class="notificacion-content">
            <span class="notificacion-text">${mensaje}</span>
        </div>
        <button class="notificacion-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    notificacion.style.cssText = `
        background: white;
        padding: 16px 20px;
        margin-bottom: 12px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border-left: 5px solid ${colores[tipo]};
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        max-width: 380px;
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.4s ease;
        pointer-events: all;
    `;
    
    container.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.style.transform = 'translateX(0)';
        notificacion.style.opacity = '1';
    }, 50);
    
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.transform = 'translateX(400px)';
                notificacion.style.opacity = '0';
                setTimeout(() => notificacion.remove(), 400);
            }
        }, duracion);
    }
}

// ===== FUNCIONES AUXILIARES =====
function verProducto(id) {
    window.location.href = `ver-producto.php?id=${id}`;
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

function eliminarProducto(id, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar el producto "${nombre}"?`)) {
        mostrarNotificacion('Función eliminar en desarrollo', 'info');
    }
}

function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info');
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// ===== CONFIGURACIÓN DE EVENT LISTENERS =====
function configurarEventListeners() {
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    if (btnEntregarPersonal) {
        btnEntregarPersonal.addEventListener('click', toggleModoSeleccion);
    }
    
    document.addEventListener('click', function(e) {
        if (e.target.closest('.selection-checkbox')) {
            manejarSeleccionProducto(e.target.closest('.selection-checkbox'));
        }
    });
    
    const dniInput = document.getElementById('dniDestinatario');
    if (dniInput) {
        dniInput.addEventListener('input', validarDNI);
    }
    
    const nombreInput = document.getElementById('nombreDestinatario');
    if (nombreInput) {
        nombreInput.addEventListener('input', validarFormularioEntrega);
    }
}

// ===== SIDEBAR =====
function inicializarSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link?.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                        }
                    }
                });
                
                submenu.classList.toggle('activo');
                const isActive = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isActive ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        }
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
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

// ===== INICIALIZACIÓN PRINCIPAL =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando productos-listar-tabla.js');
    
    // Obtener contexto actual
    obtenerContextoActual();
    
    // Configurar componentes
    configurarEventListeners();
    inicializarSidebar();
    
    // Restaurar carrito si existe
    restaurarCarritoGuardado();
    
    // Configurar controles de stock después de un breve delay
    setTimeout(() => {
        configurarControlesStock();
    }, 100);
    
    console.log('✅ productos-listar-tabla.js cargado completamente');
});

// ===== FUNCIONES GLOBALES EXPUESTAS =====
window.toggleModoSeleccion = toggleModoSeleccion;
window.limpiarCarrito = limpiarCarrito;
window.procederEntrega = procederEntrega;
window.cerrarModalEntrega = cerrarModalEntrega;
window.confirmarEntrega = confirmarEntrega;
window.abrirModalEnvio = abrirModalEnvio;
window.cerrarModal = cerrarModal;
window.adjustQuantity = adjustQuantity;
window.ajustarCantidadCarrito = ajustarCantidadCarrito;
window.removerDelCarrito = removerDelCarrito;
window.verProducto = verProducto;
window.editarProducto = editarProducto;
window.eliminarProducto = eliminarProducto;
window.manejarCerrarSesion = manejarCerrarSesion;
window.mostrarNotificacion = mostrarNotificacion;
window.recargarConContexto = recargarConContexto;

// ===== MANEJO DE ERRORES GLOBALES =====
window.addEventListener('error', function(e) {
    console.error('Error JavaScript:', e.error);
    mostrarNotificacion('Error detectado. Recarga si persiste.', 'error');
});

// ===== DEBUG Y UTILIDADES =====
window.productosDebug = {
    obtenerEstado: function() {
        return {
            modoSeleccion,
            carritoEntrega: carritoEntrega.length,
            productosSeleccionados: productosSeleccionados.size,
            contexto: CONTEXTO_ACTUAL
        };
    },
    limpiarTodo: function() {
        desactivarModoSeleccion();
        limpiarCarritoStorage();
        console.log('🧹 Estado limpiado');
    }
};

console.log('📊 Para debug: window.productosDebug.obtenerEstado()');

// ===== FIN DEL ARCHIVO - SIN ERRORES DE SINTAXIS =====
