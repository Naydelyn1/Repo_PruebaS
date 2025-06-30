/* ============================================
   PRODUCTOS LISTAR - JAVASCRIPT COMPLETO MEJORADO
   Con carrito persistente, esquina derecha y UX optimizada
   ============================================ */

// ===== VARIABLES GLOBALES =====
let modoSeleccion = false;
let productosSeleccionados = new Set();
let carritoEntrega = [];
let carritoMinimizado = false;

// ===== CLAVES PARA LOCALSTORAGE =====
const CARRITO_STORAGE_KEY = 'productos_entrega_carrito';
const MODO_STORAGE_KEY = 'productos_entrega_modo';

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    inicializarComponentes();
    configurarEventListeners();
    inicializarSidebar();
    configurarTeclasRapidas();
    
    // ⭐ RESTAURAR CARRITO GUARDADO
    restaurarCarritoGuardado();
    
    // ⭐ CREAR ELEMENTOS ADICIONALES
    crearIndicadorCarrito();
    
    // ⭐ AJUSTAR TAMAÑO INICIAL
    ajustarTamañoCarrito();
    
    // ⭐ CONFIGURAR CONTROLES DE STOCK CORREGIDOS
    setTimeout(() => {
        configurarControlesStock();
    }, 100);
});

// ===== PERSISTENCIA DEL CARRITO =====
function guardarCarritoEnStorage() {
    try {
        const carritoData = {
            productos: carritoEntrega,
            seleccionados: Array.from(productosSeleccionados),
            modoActivo: modoSeleccion,
            timestamp: Date.now(),
            url: window.location.href
        };
        localStorage.setItem(CARRITO_STORAGE_KEY, JSON.stringify(carritoData));
        console.log('💾 Carrito guardado en localStorage');
    } catch (error) {
        console.error('Error al guardar carrito:', error);
    }
}

function restaurarCarritoGuardado() {
    try {
        const carritoGuardado = localStorage.getItem(CARRITO_STORAGE_KEY);
        if (!carritoGuardado) return;
        
        const carritoData = JSON.parse(carritoGuardado);
        
        // Verificar que no sea muy antiguo (máximo 2 horas)
        const dosHoras = 2 * 60 * 60 * 1000;
        if (Date.now() - carritoData.timestamp > dosHoras) {
            localStorage.removeItem(CARRITO_STORAGE_KEY);
            return;
        }
        
        // Verificar que estemos en la misma sección (productos)
        if (!carritoData.url || !carritoData.url.includes('/productos/')) {
            return;
        }
        
        // Restaurar datos
        carritoEntrega = carritoData.productos || [];
        productosSeleccionados = new Set(carritoData.seleccionados || []);
        
        // Si había productos en el carrito, restaurar modo selección
        if (carritoEntrega.length > 0) {
            console.log('🔄 Restaurando carrito con', carritoEntrega.length, 'productos');
            
            // Activar modo selección sin mostrar notificación
            modoSeleccion = true;
            activarModoSeleccionVisual();
            
            // Marcar checkboxes correspondientes (solo los que existen en esta página)
            productosSeleccionados.forEach(productoId => {
                const checkbox = document.querySelector(`[data-id="${productoId}"]`);
                if (checkbox) {
                    checkbox.classList.add('checked');
                }
            });
            
            // Mostrar carrito y actualizar
            const carritoElement = document.getElementById('carritoEntrega');
            carritoElement.classList.add('show');
            actualizarCarrito();
            
            // Mostrar notificación de restauración
            mostrarNotificacion(`Carrito restaurado con ${carritoEntrega.length} productos`, 'info', 3000);
        }
        
    } catch (error) {
        console.error('Error al restaurar carrito:', error);
        localStorage.removeItem(CARRITO_STORAGE_KEY);
    }
}

function limpiarCarritoStorage() {
    localStorage.removeItem(CARRITO_STORAGE_KEY);
    console.log('🗑️ Carrito eliminado del localStorage');
}

// ===== CREAR INDICADOR DEL CARRITO =====
function crearIndicadorCarrito() {
    const indicador = document.createElement('div');
    indicador.id = 'carritoIndicator';
    indicador.className = 'carrito-indicator';
    indicador.innerHTML = `
        <i class="fas fa-shopping-cart"></i>
        <span class="indicator-count">0</span>
    `;
    
    indicador.addEventListener('click', () => {
        if (carritoMinimizado) {
            expandirCarrito();
        } else {
            const carrito = document.getElementById('carritoEntrega');
            if (!carrito.classList.contains('show')) {
                toggleModoSeleccion();
            }
        }
    });
    
    document.body.appendChild(indicador);
    console.log('📍 Indicador del carrito creado en la esquina inferior derecha');
}

// ===== AJUSTAR TAMAÑO SEGÚN PANTALLA =====
function ajustarTamañoCarrito() {
    const carrito = document.getElementById('carritoEntrega');
    if (!carrito) return;
    
    const width = window.innerWidth;
    
    // Remover todas las clases de tamaño
    carrito.classList.remove('compact', 'mini');
    
    // Aplicar clase según el tamaño de pantalla
    if (width <= 350) {
        carrito.classList.add('compact', 'mini');
    } else if (width <= 768) {
        carrito.classList.add('compact');
    }
    
    console.log(`📐 Carrito ajustado para pantalla de ${width}px`);
}

// ===== INICIALIZACIÓN DE COMPONENTES =====
function inicializarComponentes() {
    // Precargar página siguiente si es posible
    precargarPaginaSiguiente();
    
    // Configurar tooltips si los hay
    configurarTooltips();
    
    // Inicializar efectos visuales
    inicializarEfectosVisuales();
    
    console.log('✅ Componentes inicializados correctamente');
}

// ===== CONFIGURACIÓN DE EVENT LISTENERS =====
function configurarEventListeners() {
    // Botón de entrega a personal
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    if (btnEntregarPersonal) {
        btnEntregarPersonal.addEventListener('click', toggleModoSeleccion);
    }
    
    // Checkboxes de selección
    document.addEventListener('click', function(e) {
        if (e.target.closest('.selection-checkbox')) {
            manejarSeleccionProducto(e.target.closest('.selection-checkbox'));
        }
    });
    
    // Formulario de búsqueda
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            mostrarIndicadorCarga();
        });
    }
    
    // Enlaces de paginación
    document.querySelectorAll('.pagination-btn:not(.current)').forEach(btn => {
        btn.addEventListener('click', function() {
            mostrarIndicadorCarga();
        });
    });
    
    // Validación en tiempo real del DNI
    const dniInput = document.getElementById('dniDestinatario');
    if (dniInput) {
        dniInput.addEventListener('input', validarDNI);
    }
    
    // Validación del nombre
    const nombreInput = document.getElementById('nombreDestinatario');
    if (nombreInput) {
        nombreInput.addEventListener('input', validarFormularioEntrega);
    }
    
    // Escuchar cambios de tamaño de ventana
    window.addEventListener('resize', ajustarTamañoCarrito);
}

// ===== SIDEBAR =====
function inicializarSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            // Cambiar icono
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
    
    // Submenús
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link?.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
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
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                const isActive = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isActive ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        }
    });
    
    // Cerrar menú móvil al hacer clic fuera
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

// ===== MODO SELECCIÓN MÚLTIPLE MEJORADO =====
function toggleModoSeleccion() {
    modoSeleccion = !modoSeleccion;
    
    if (modoSeleccion) {
        activarModoSeleccion();
    } else {
        desactivarModoSeleccion();
    }
    
    // Guardar estado
    guardarCarritoEnStorage();
}

function activarModoSeleccion() {
    modoSeleccion = true;
    activarModoSeleccionVisual();
    
    const carritoEntrega = document.getElementById('carritoEntrega');
    carritoEntrega.classList.add('show');
    
    // Ajustar tamaño al activar
    setTimeout(ajustarTamañoCarrito, 100);
    
    mostrarNotificacion('Modo de selección activado. Selecciona productos para entregar.', 'info');
}

function activarModoSeleccionVisual() {
    const tabla = document.getElementById('productosTabla');
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    
    // Activar clases visuales
    tabla.classList.add('modo-seleccion');
    btnEntregarPersonal.classList.add('active');
    btnEntregarPersonal.innerHTML = '<i class="fas fa-times"></i><span>Cancelar Selección</span>';
    
    // Mostrar columnas de selección
    document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
        el.style.display = 'table-cell';
    });
    
    // Agregar toggle al header del carrito
    agregarToggleCarrito();
}

function desactivarModoSeleccion() {
    modoSeleccion = false;
    const tabla = document.getElementById('productosTabla');
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    const carritoElement = document.getElementById('carritoEntrega');
    const indicador = document.getElementById('carritoIndicator');
    
    // Desactivar clases visuales
    tabla.classList.remove('modo-seleccion');
    btnEntregarPersonal.classList.remove('active');
    btnEntregarPersonal.innerHTML = '<i class="fas fa-hand-holding"></i><span>Entregar a Personal</span>';
    carritoElement.classList.remove('show');
    indicador.classList.remove('show');
    
    // Ocultar columnas de selección
    document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
        el.style.display = 'none';
    });
    
    // Limpiar carrito
    limpiarCarrito();
}

// ===== FUNCIONES DE MINIMIZAR/EXPANDIR =====
function agregarToggleCarrito() {
    const carritoHeader = document.querySelector('.carrito-header');
    if (!carritoHeader.querySelector('.carrito-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'carrito-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-minus"></i>';
        toggleBtn.title = 'Minimizar carrito';
        
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleCarrito();
        });
        
        carritoHeader.appendChild(toggleBtn);
    }
    
    // Hacer el header clickeable para toggle
    carritoHeader.style.cursor = 'pointer';
    carritoHeader.addEventListener('click', toggleCarrito);
}

function toggleCarrito() {
    if (carritoMinimizado) {
        expandirCarrito();
    } else {
        minimizarCarrito();
    }
}

function minimizarCarrito() {
    const carrito = document.getElementById('carritoEntrega');
    const indicador = document.getElementById('carritoIndicator');
    const toggleBtn = document.querySelector('.carrito-toggle i');
    
    carrito.classList.add('minimized');
    indicador.classList.add('show');
    
    if (toggleBtn) {
        toggleBtn.className = 'fas fa-plus';
        toggleBtn.parentElement.title = 'Expandir carrito';
    }
    
    carritoMinimizado = true;
    
    // Actualizar contador en indicador
    const count = document.querySelector('.indicator-count');
    count.textContent = carritoEntrega.length;
    
    console.log('📱 Carrito minimizado');
}

function expandirCarrito() {
    const carrito = document.getElementById('carritoEntrega');
    const indicador = document.getElementById('carritoIndicator');
    const toggleBtn = document.querySelector('.carrito-toggle i');
    
    carrito.classList.remove('minimized');
    indicador.classList.remove('show');
    
    if (toggleBtn) {
        toggleBtn.className = 'fas fa-minus';
        toggleBtn.parentElement.title = 'Minimizar carrito';
    }
    
    carritoMinimizado = false;
    
    console.log('📱 Carrito expandido');
}

// ===== GESTIÓN DE PRODUCTOS MEJORADA =====
function manejarSeleccionProducto(checkbox) {
    const productoId = checkbox.dataset.id;
    const isChecked = checkbox.classList.contains('checked');
    
    if (isChecked) {
        // Deseleccionar
        checkbox.classList.remove('checked');
        productosSeleccionados.delete(productoId);
        eliminarDelCarrito(productoId);
    } else {
        // Seleccionar
        checkbox.classList.add('checked');
        productosSeleccionados.add(productoId);
        agregarAlCarrito(productoId);
    }
    
    actualizarCarrito();
    guardarCarritoEnStorage(); // ⭐ Guardar después de cada cambio
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
        console.log('➕ Producto agregado al carrito:', itemCarrito.nombre);
        
    } catch (error) {
        console.error('Error al agregar producto al carrito:', error);
        mostrarNotificacion('Error al agregar el producto al carrito', 'error');
    }
}

function eliminarDelCarrito(productoId) {
    const initialLength = carritoEntrega.length;
    carritoEntrega = carritoEntrega.filter(item => item.id != productoId);
    
    if (carritoEntrega.length < initialLength) {
        console.log('➖ Producto eliminado del carrito:', productoId);
    }
}

// ===== FUNCIÓN MEJORADA PARA ACTUALIZAR CARRITO =====
function actualizarCarrito() {
    const carritoLista = document.getElementById('carritoLista');
    const carritoContador = document.querySelector('.carrito-contador');
    const totalUnidades = document.getElementById('totalUnidades');
    const btnProceder = document.querySelector('.btn-proceder');
    const indicadorCount = document.querySelector('.indicator-count');
    
    // Guardar valor anterior para animación
    const valorAnterior = parseInt(carritoContador.textContent) || 0;
    const valorNuevo = carritoEntrega.length;
    
    // Actualizar contador en indicador
    if (indicadorCount) {
        indicadorCount.textContent = valorNuevo;
    }
    
    // Animar contador si cambió
    if (valorAnterior !== valorNuevo) {
        carritoContador.classList.add('updated');
        setTimeout(() => {
            carritoContador.classList.remove('updated');
        }, 500);
    }
    
    if (carritoEntrega.length === 0) {
        carritoLista.innerHTML = `
            <div class="carrito-vacio">
                <i class="fas fa-hand-holding"></i>
                <p>Selecciona productos para entregar</p>
            </div>
        `;
        carritoContador.textContent = '0';
        totalUnidades.textContent = '0';
        btnProceder.disabled = true;
        
        // Remover clase compact si no hay productos
        document.getElementById('carritoEntrega').classList.remove('compact');
        return;
    }
    
    // Agregar clase compact si hay muchos productos (para ahorrar espacio)
    const carritoElement = document.getElementById('carritoEntrega');
    if (carritoEntrega.length >= 3) {
        carritoElement.classList.add('compact');
    } else {
        carritoElement.classList.remove('compact');
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
    totalUnidades.textContent = totalUnidadesCount;
    btnProceder.disabled = false;
    
    // Ajustar notificaciones para no solaparse
    ajustarPosicionNotificaciones();
}

function ajustarCantidadCarrito(productoId, cambio) {
    const item = carritoEntrega.find(item => item.id == productoId);
    if (item) {
        const nuevaCantidad = item.cantidad + cambio;
        if (nuevaCantidad >= 1 && nuevaCantidad <= item.maxCantidad) {
            item.cantidad = nuevaCantidad;
            actualizarCarrito();
            guardarCarritoEnStorage(); // ⭐ Guardar cambios
        }
    }
}

function removerDelCarrito(productoId) {
    // Deseleccionar checkbox
    const checkbox = document.querySelector(`[data-id="${productoId}"]`);
    if (checkbox) {
        checkbox.classList.remove('checked');
    }
    
    productosSeleccionados.delete(productoId.toString());
    eliminarDelCarrito(productoId);
    actualizarCarrito();
    guardarCarritoEnStorage(); // ⭐ Guardar cambios
}

function limpiarCarrito() {
    carritoEntrega = [];
    productosSeleccionados.clear();
    
    // Deseleccionar todos los checkboxes
    document.querySelectorAll('.selection-checkbox.checked').forEach(checkbox => {
        checkbox.classList.remove('checked');
    });
    
    actualizarCarrito();
    limpiarCarritoStorage(); // ⭐ Limpiar storage
}

function procederEntrega() {
    if (carritoEntrega.length === 0) {
        mostrarNotificacion('No hay productos seleccionados para entregar.', 'warning');
        return;
    }
    
    mostrarModalEntrega();
}

// ===== MODAL DE ENTREGA =====
function mostrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    const productosResumen = document.getElementById('productosResumen');
    const totalUnidadesModal = document.getElementById('totalUnidadesModal');
    const totalTiposModal = document.getElementById('totalTiposModal');
    
    // Generar resumen
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
    
    productosResumen.innerHTML = html;
    totalUnidadesModal.textContent = totalUnidades;
    totalTiposModal.textContent = carritoEntrega.length;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
    
    // Focus en el primer input
    setTimeout(() => {
        document.getElementById('nombreDestinatario').focus();
    }, 300);
}

function cerrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    // Limpiar formulario
    document.getElementById('formEntregaPersonal').reset();
    const btnConfirmar = document.querySelector('.btn-confirm');
    if (btnConfirmar) {
        btnConfirmar.disabled = true;
    }
}

function validarFormularioEntrega() {
    const nombre = document.getElementById('nombreDestinatario').value.trim();
    const dni = document.getElementById('dniDestinatario').value.trim();
    const btnConfirmar = document.querySelector('.btn-confirm');
    
    const nombreValido = nombre.length >= 3;
    const dniValido = /^[0-9]{8}$/.test(dni);
    
    if (btnConfirmar) {
        btnConfirmar.disabled = !(nombreValido && dniValido);
    }
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

// ===== FUNCIÓN MEJORADA PARA ENVIAR ENTREGA =====
async function confirmarEntrega() {
    const nombre = document.getElementById('nombreDestinatario').value.trim();
    const dni = document.getElementById('dniDestinatario').value.trim();
    
    if (!nombre || !dni || dni.length !== 8) {
        mostrarNotificacion('Por favor, complete todos los campos correctamente.', 'error');
        return;
    }
    
    // Preparar datos para envío
    const datosEntrega = {
        nombre_destinatario: nombre,
        dni_destinatario: dni,
        productos: JSON.stringify(carritoEntrega)
    };
    
    // Mostrar indicador de carga
    const btnConfirmar = document.querySelector('.btn-confirm');
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btnConfirmar.disabled = true;
    
    try {
        // LLAMADA REAL AL SERVIDOR
        const response = await fetch('../entregas/Procesar_entrega.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(datosEntrega)
        });

        const data = await response.json();

        if (data.success) {
            mostrarNotificacion('¡Entrega registrada exitosamente!', 'success');
            
            // Cerrar modal y limpiar
            cerrarModalEntrega();
            desactivarModoSeleccion(); // Salir del modo selección completamente
            
            // Recargar página para actualizar stock
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            mostrarNotificacion(data.message || 'Error al registrar la entrega', 'error');
            
            // Restaurar botón
            btnConfirmar.innerHTML = textoOriginal;
            btnConfirmar.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión al registrar la entrega', 'error');
        
        // Restaurar botón
        btnConfirmar.innerHTML = textoOriginal;
        btnConfirmar.disabled = false;
    }
}

// ===== MODAL DE TRANSFERENCIA =====
function abrirModalEnvio(button) {
    const modal = document.getElementById('modalTransferencia');
    const productoId = button.dataset.id;
    const productoNombre = button.dataset.nombre;
    const almacenOrigen = button.dataset.almacen;
    const stockDisponible = button.dataset.cantidad;
    
    // Llenar datos del modal
    document.getElementById('producto_id').value = productoId;
    document.getElementById('almacen_origen').value = almacenOrigen;
    document.getElementById('producto_nombre').textContent = productoNombre;
    document.getElementById('stock_disponible').textContent = stockDisponible;
    document.getElementById('cantidad').max = stockDisponible;
    document.getElementById('cantidad').value = 1;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
}

function cerrarModal() {
    const modal = document.getElementById('modalTransferencia');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function adjustQuantity(change) {
    const cantidadInput = document.getElementById('cantidad');
    const currentValue = parseInt(cantidadInput.value) || 1;
    const maxValue = parseInt(cantidadInput.max);
    const newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= maxValue) {
        cantidadInput.value = newValue;
    }
}

// ===== MANEJO DE STOCK CORREGIDO =====
async function manejarCambioStock(button) {
    const productoId = button.dataset.id;
    const accion = button.dataset.accion;
    const stockElement = document.getElementById(`cantidad-${productoId}`);
    
    if (!stockElement) {
        console.error('No se encontró el elemento de stock para el producto:', productoId);
        mostrarNotificacion('Error: No se encontró el elemento de stock', 'error');
        return;
    }
    
    const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
    
    // Validaciones previas
    if (accion === 'restar' && currentStock <= 0) {
        mostrarNotificacion('No se puede reducir más el stock. Ya está en 0.', 'warning');
        return;
    }
    
    // Deshabilitar botón y mostrar loading
    button.disabled = true;
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    console.log(`🔄 Actualizando stock del producto ${productoId}: ${accion}`);
    
    try {
        // ⭐ LLAMADA REAL AL SERVIDOR
        const formData = new FormData();
        formData.append('producto_id', productoId);
        formData.append('accion', accion);
        
        console.log('📤 Enviando petición al servidor...');
        
        const response = await fetch('actualizar_cantidad.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        console.log('📥 Respuesta del servidor:', response.status);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('📊 Datos recibidos:', data);
        
        if (data.success) {
            // ✅ ACTUALIZACIÓN EXITOSA
            const nuevaCantidad = parseInt(data.nueva_cantidad);
            
            // Actualizar el stock visualmente
            stockElement.textContent = nuevaCantidad.toLocaleString();
            
            // Actualizar clases de stock (crítico, warning, bueno)
            actualizarClaseStock(stockElement, nuevaCantidad);
            
            // Animación visual de éxito
            stockElement.style.transform = 'scale(1.15)';
            stockElement.style.color = '#28a745';
            stockElement.style.fontWeight = 'bold';
            
            setTimeout(() => {
                stockElement.style.transform = 'scale(1)';
                stockElement.style.color = '';
                stockElement.style.fontWeight = '';
            }, 400);
            
            // Actualizar estado de botones
            const allDecreaseButtons = document.querySelectorAll(`.stock-btn[data-id="${productoId}"][data-accion="restar"]`);
            const allIncreaseButtons = document.querySelectorAll(`.stock-btn[data-id="${productoId}"][data-accion="sumar"]`);
            
            // Deshabilitar botón de restar si llegó a 0
            allDecreaseButtons.forEach(btn => {
                btn.disabled = nuevaCantidad <= 0;
            });
            
            // Habilitar botón de sumar si estaba deshabilitado
            allIncreaseButtons.forEach(btn => {
                btn.disabled = false;
            });
            
            // Actualizar botón de transferencia si existe
            const transferButton = document.querySelector(`.btn-transfer[data-id="${productoId}"]`);
            if (transferButton) {
                if (nuevaCantidad > 0) {
                    transferButton.disabled = false;
                    transferButton.classList.remove('disabled');
                    transferButton.dataset.cantidad = nuevaCantidad;
                    transferButton.title = 'Transferir producto';
                    transferButton.querySelector('i').className = 'fas fa-paper-plane';
                } else {
                    transferButton.disabled = true;
                    transferButton.classList.add('disabled');
                    transferButton.title = 'Sin stock disponible';
                    transferButton.querySelector('i').className = 'fas fa-times';
                }
            }
            
            // Mostrar notificación de éxito
            const accionTexto = accion === 'sumar' ? 'aumentado' : 'reducido';
            mostrarNotificacion(
                `✅ Stock ${accionTexto} correctamente. Nuevo stock: ${nuevaCantidad.toLocaleString()} unidades`, 
                'exito',
                3000
            );
            
            console.log(`✅ Stock actualizado exitosamente: ${currentStock} → ${nuevaCantidad}`);
            
        } else {
            // ❌ ERROR REPORTADO POR EL SERVIDOR
            console.error('❌ Error del servidor:', data.message);
            mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
        }
        
    } catch (error) {
        // ❌ ERROR DE CONEXIÓN O PROCESAMIENTO
        console.error('❌ Error en la petición:', error);
        
        let mensajeError = 'Error de conexión. No se pudo actualizar el stock.';
        
        if (error.message.includes('HTTP')) {
            mensajeError = 'Error del servidor. Inténtelo más tarde.';
        } else if (error.message.includes('JSON')) {
            mensajeError = 'Error en la respuesta del servidor.';
        } else if (error.message.includes('Network')) {
            mensajeError = 'Error de red. Verifique su conexión.';
        }
        
        mostrarNotificacion(mensajeError, 'error');
        
    } finally {
        // Restaurar botón siempre
        button.disabled = false;
        button.innerHTML = originalHtml;
        
        console.log('🔄 Proceso de actualización finalizado');
    }
}

// ===== FUNCIÓN AUXILIAR PARA ACTUALIZAR CLASES DE STOCK =====
function actualizarClaseStock(element, cantidad) {
    // Buscar el contenedor de valor de stock
    const stockValue = element.closest('.stock-value') || element;
    
    // Remover todas las clases de estado
    stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good', 'stock-empty');
    
    // Aplicar nueva clase según la cantidad
    if (cantidad === 0) {
        stockValue.classList.add('stock-empty');
    } else if (cantidad < 5) {
        stockValue.classList.add('stock-critical');
    } else if (cantidad < 10) {
        stockValue.classList.add('stock-warning');
    } else {
        stockValue.classList.add('stock-good');
    }
    
    console.log(`🎨 Clase de stock actualizada: ${cantidad} unidades`);
}

// ===== CONFIGURACIÓN DE CONTROLES DE STOCK MEJORADA =====
function configurarControlesStock() {
    const stockButtons = document.querySelectorAll('.stock-btn');
    console.log('🔧 Configurando controles de stock:', stockButtons.length, 'botones encontrados');
    
    if (stockButtons.length === 0) {
        console.warn('⚠️ No se encontraron botones de stock en la página');
        return;
    }
    
    stockButtons.forEach((button, index) => {
        const productId = button.dataset.id;
        const accion = button.dataset.accion;
        
        console.log(`🔘 Configurando botón ${index + 1}: Producto ${productId}, Acción: ${accion}`);
        
        // Remover listeners anteriores para evitar duplicados
        button.removeEventListener('click', handleStockClick);
        
        // Agregar nuevo listener
        button.addEventListener('click', handleStockClick);
    });
    
    console.log('✅ Controles de stock configurados correctamente');
}

// Función manejadora separada para mejor control
async function handleStockClick(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const button = e.currentTarget;
    const productId = button.dataset.id;
    const accion = button.dataset.accion;
    
    console.log(`🖱️ Click en botón de stock: Producto ${productId}, Acción: ${accion}`);
    
    if (productId && accion) {
        await manejarCambioStock(button);
    } else {
        console.error('❌ Datos de botón incompletos:', { productId, accion });
        mostrarNotificacion('Error: Datos del botón incompletos', 'error');
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
        mostrarNotificacion('Función eliminar producto en desarrollo.', 'info');
        console.log('Eliminar producto ID:', id);
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

function mostrarIndicadorCarga() {
    const indicator = document.getElementById('loading-indicator');
    if (indicator) {
        indicator.style.display = 'flex';
    }
}

function precargarPaginaSiguiente() {
    const currentPage = parseInt(document.body.dataset.page);
    const totalPages = parseInt(document.body.dataset.totalPages);
    
    if (currentPage < totalPages) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        // Aquí construirías la URL de la siguiente página
        document.head.appendChild(link);
    }
}

function configurarTooltips() {
    // Configuración básica de tooltips si es necesaria
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            // Lógica para mostrar tooltip personalizado si se desea
        });
    });
}

function inicializarEfectosVisuales() {
    // Efectos de entrada para las filas de la tabla
    const rows = document.querySelectorAll('.product-row');
    
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
}

function configurarTeclasRapidas() {
    document.addEventListener('keydown', function(e) {
        // Solo actuar si no estamos en un input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        switch(e.key) {
            case 'Escape':
                // Cerrar modales o salir del modo selección
                if (modoSeleccion) {
                    toggleModoSeleccion();
                } else {
                    cerrarModal();
                    cerrarModalEntrega();
                }
                break;
                
            case 'e':
            case 'E':
                if (!modoSeleccion) {
                    toggleModoSeleccion();
                }
                break;
                
            case 'm':
            case 'M':
                if (modoSeleccion && carritoEntrega.length > 0) {
                    toggleCarrito();
                }
                break;
        }
        
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case 'f':
                case 'F':
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="busqueda"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                    break;
            }
        }
    });
}

// ===== FUNCIÓN PARA AJUSTAR POSICIÓN SEGÚN CARRITO =====
function ajustarPosicionNotificaciones() {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const carritoElement = document.getElementById('carritoEntrega');
    const carritoVisible = carritoElement && carritoElement.classList.contains('show');
    const carritoMinimizado = carritoElement && carritoElement.classList.contains('minimized');
    
    if (carritoVisible && !carritoMinimizado) {
        // Si el carrito está visible y no minimizado, mover notificaciones más a la izquierda
        const carritoWidth = window.innerWidth <= 768 ? 280 : 340; // Ancho responsivo del carrito
        container.style.right = `${carritoWidth + 20}px`;
        console.log('📍 Notificaciones reposicionadas para evitar carrito');
    } else {
        // Posición normal
        container.style.right = '20px';
    }
    
    // Ajustar según tamaño de pantalla
    if (window.innerWidth <= 480) {
        container.style.right = '10px';
        container.style.left = '10px';
        container.style.maxWidth = 'calc(100% - 20px)';
    } else {
        container.style.left = 'auto';
        container.style.maxWidth = '380px';
    }
}

// ===== SISTEMA DE NOTIFICACIONES MEJORADO - ARRIBA DERECHA =====
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    let container = document.getElementById('notificaciones-container');
    
    // Crear container si no existe
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
        console.log('📍 Container de notificaciones creado arriba-derecha');
    }
    
    // Ajustar posición para no solaparse con carrito (solo si está visible)
    ajustarPosicionNotificaciones();
    
    const iconos = {
        success: 'fa-check-circle',
        exito: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colores = {
        success: '#28a745',
        exito: '#28a745',
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
        <button class="notificacion-close" onclick="this.parentElement.remove()" aria-label="Cerrar notificación">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Estilos mejorados para notificaciones arriba-derecha
    notificacion.style.cssText = `
        background: white;
        padding: 16px 20px;
        margin-bottom: 12px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
        border-left: 5px solid ${colores[tipo] || colores.info};
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        max-width: 380px;
        position: relative;
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: all;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    `;
    
    // Agregar al container
    container.appendChild(notificacion);
    
    // Animar entrada
    setTimeout(() => {
        notificacion.style.transform = 'translateX(0)';
        notificacion.style.opacity = '1';
    }, 50);
    
    // Configurar botón de cerrar
    const cerrarBtn = notificacion.querySelector('.notificacion-close');
    cerrarBtn.style.cssText = `
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 14px;
        padding: 4px;
        border-radius: 50%;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
    `;
    
    cerrarBtn.addEventListener('mouseenter', () => {
        cerrarBtn.style.background = 'rgba(0, 0, 0, 0.1)';
        cerrarBtn.style.color = '#333';
    });
    
    cerrarBtn.addEventListener('mouseleave', () => {
        cerrarBtn.style.background = 'none';
        cerrarBtn.style.color = '#666';
    });
    
    cerrarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        removerNotificacion(notificacion);
    });
    
    // Estilizar icono
    const iconElement = notificacion.querySelector('.notificacion-icon i');
    iconElement.style.cssText = `
        font-size: 20px;
        color: ${colores[tipo] || colores.info};
        flex-shrink: 0;
    `;
    
    // Estilizar contenido
    const contentElement = notificacion.querySelector('.notificacion-content');
    contentElement.style.cssText = `
        flex: 1;
        min-width: 0;
    `;
    
    const textElement = notificacion.querySelector('.notificacion-text');
    textElement.style.cssText = `
        color: #2c3e50;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.4;
        word-wrap: break-word;
        margin: 0;
    `;
    
    // Auto-remover después de la duración especificada
    if (duracion > 0) {
        setTimeout(() => {
            removerNotificacion(notificacion);
        }, duracion);
    }
    
    // Limpiar notificaciones antiguas
    limpiarNotificacionesAntiguas();
    
    console.log(`📬 Notificación ${tipo} mostrada: ${mensaje.substring(0, 50)}...`);
}

// ===== FUNCIÓN PARA REMOVER NOTIFICACIONES CON ANIMACIÓN =====
function removerNotificacion(notificacion) {
    if (!notificacion || !notificacion.parentElement) return;
    
    // Animar salida
    notificacion.style.transform = 'translateX(400px)';
    notificacion.style.opacity = '0';
    
    setTimeout(() => {
        if (notificacion.parentElement) {
            notificacion.remove();
        }
    }, 400);
}

// ===== FUNCIÓN PARA LIMPIAR NOTIFICACIONES ANTIGUAS =====
function limpiarNotificacionesAntiguas() {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificaciones = container.querySelectorAll('.notificacion');
    if (notificaciones.length > 5) { // Máximo 5 notificaciones visible
        // Remover las más antiguas
        for (let i = 0; i < notificaciones.length - 5; i++) {
            removerNotificacion(notificaciones[i]);
        }
    }
}

// ===== ESTILOS CSS MEJORADOS PARA NOTIFICACIONES =====
const estilosNotificacionesMejorados = `
    /* Animaciones para notificaciones */
    @keyframes slideInFromRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutToRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Contenedor de notificaciones */
    #notificaciones-container {
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Efectos hover para notificaciones */
    .notificacion {
        cursor: default;
        user-select: none;
    }
    
    .notificacion:hover {
        transform: translateX(0) scale(1.02) !important;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.16), 0 4px 12px rgba(0, 0, 0, 0.12) !important;
    }
    
    /* Responsive para móviles */
    @media (max-width: 480px) {
        .notificacion {
            min-width: auto !important;
            width: 100% !important;
            font-size: 13px !important;
        }
        
        .notificacion-text {
            font-size: 13px !important;
        }
        
        .notificacion-icon i {
            font-size: 18px !important;
        }
    }
    
    /* Ajustes para diferentes tipos */
    .notificacion-exito .notificacion-icon i,
    .notificacion-success .notificacion-icon i {
        color: #28a745 !important;
    }
    
    .notificacion-error .notificacion-icon i {
        color: #dc3545 !important;
    }
    
    .notificacion-warning .notificacion-icon i {
        color: #ffc107 !important;
    }
    
    .notificacion-info .notificacion-icon i {
        color: #0a253c !important;
    }
`;

// Inyectar estilos mejorados
if (!document.getElementById('notificaciones-mejoradas-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'notificaciones-mejoradas-styles';
    styleSheet.textContent = estilosNotificacionesMejorados;
    document.head.appendChild(styleSheet);
}

// ===== ESCUCHAR CAMBIOS DE TAMAÑO PARA REPOSICIONAR =====
window.addEventListener('resize', () => {
    setTimeout(ajustarPosicionNotificaciones, 100);
});

// ===== FUNCIÓN DE UTILIDAD PARA MOSTRAR NOTIFICACIONES ESPECÍFICAS =====
window.notificarExito = function(mensaje, duracion = 4000) {
    mostrarNotificacion(mensaje, 'exito', duracion);
};

window.notificarError = function(mensaje, duracion = 6000) {
    mostrarNotificacion(mensaje, 'error', duracion);
};

window.notificarInfo = function(mensaje, duracion = 4000) {
    mostrarNotificacion(mensaje, 'info', duracion);
};

window.notificarWarning = function(mensaje, duracion = 5000) {
    mostrarNotificacion(mensaje, 'warning', duracion);
};

// ===== FUNCIÓN DE DEBUG PARA TESTEAR =====
window.debugStock = function(productoId) {
    console.log('🔍 Debug de stock para producto:', productoId);
    
    const stockElement = document.getElementById(`cantidad-${productoId}`);
    const buttons = document.querySelectorAll(`.stock-btn[data-id="${productoId}"]`);
    
    console.log('Stock element:', stockElement);
    console.log('Stock buttons:', buttons);
    console.log('Cantidad actual:', stockElement ? stockElement.textContent : 'No encontrado');
    
    return {
        element: stockElement,
        buttons: Array.from(buttons),
        currentStock: stockElement ? parseInt(stockElement.textContent.replace(/,/g, '')) : null
    };
};

// ===== FUNCIONES EXPUESTAS GLOBALMENTE =====
window.toggleModoSeleccion = toggleModoSeleccion;
window.limpiarCarrito = limpiarCarrito;
window.procederEntrega = procederEntrega;
window.cerrarModalEntrega = cerrarModalEntrega;
window.confirmarEntrega = confirmarEntrega;
window.abrirModalEnvio = abrirModalEnvio;
window.cerrarModal = cerrarModal;
window.adjustQuantity = adjustQuantity;
window.verProducto = verProducto;
window.editarProducto = editarProducto;
window.eliminarProducto = eliminarProducto;
window.manejarCerrarSesion = manejarCerrarSesion;
window.ajustarCantidadCarrito = ajustarCantidadCarrito;
window.removerDelCarrito = removerDelCarrito;
window.toggleCarrito = toggleCarrito;
window.minimizarCarrito = minimizarCarrito;
window.expandirCarrito = expandirCarrito;
window.ajustarTamañoCarrito = ajustarTamañoCarrito;

console.log('🚀 Sistema de carrito de entrega completamente inicializado con notificaciones arriba-derecha');