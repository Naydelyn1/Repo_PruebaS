/* ============================================
   PRODUCTOS CATEGORÍAS - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosCategorias {
    constructor() {
        this.inicializar();
        this.configurarEventListeners();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Animar tarjetas de categorías
        this.animarTarjetas();
        
        // Configurar efectos hover
        this.configurarEfectosHover();
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

    animarTarjetas() {
        const tarjetas = document.querySelectorAll('.category-card');
        tarjetas.forEach((tarjeta, index) => {
            tarjeta.style.animationDelay = `${index * 0.1}s`;
            tarjeta.classList.add('card-enter');
        });
    }

    configurarEfectosHover() {
        const tarjetas = document.querySelectorAll('.category-card');
        tarjetas.forEach(tarjeta => {
            tarjeta.addEventListener('mouseenter', () => {
                tarjeta.style.transform = 'translateY(-8px) scale(1.02)';
                tarjeta.style.boxShadow = '0 12px 35px rgba(10, 37, 60, 0.15)';
            });
            
            tarjeta.addEventListener('mouseleave', () => {
                tarjeta.style.transform = 'translateY(0) scale(1)';
                tarjeta.style.boxShadow = '0 4px 15px rgba(10, 37, 60, 0.1)';
            });
        });
    }

    configurarEventListeners() {
        // Configurar eventos globales
        document.addEventListener('keydown', (e) => {
            // Buscar categorías con Ctrl + F
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                this.activarBusquedaRapida();
            }
        });

        // Manejar cerrar sesión
        const logoutLinks = document.querySelectorAll('a[onclick*="manejarCerrarSesion"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => this.manejarCerrarSesion(e));
        });

        // Configurar botones de acción
        this.configurarBotonesAccion();
    }

    configurarBotonesAccion() {
        // Botones para ver productos de una categoría
        const botonesVer = document.querySelectorAll('.btn-view');
        botonesVer.forEach(boton => {
            boton.addEventListener('click', (e) => {
                e.preventDefault();
                const href = boton.getAttribute('href');
                this.navegarConTransicion(href);
            });
        });

        // Botones para agregar producto a una categoría
        const botonesAgregar = document.querySelectorAll('.btn-add');
        botonesAgregar.forEach(boton => {
            boton.addEventListener('click', (e) => {
                e.preventDefault();
                const href = boton.getAttribute('href');
                this.navegarConTransicion(href);
            });
        });
    }

    activarBusquedaRapida() {
        // Crear input de búsqueda dinámico
        let searchInput = document.getElementById('categoria-search');
        
        if (!searchInput) {
            searchInput = document.createElement('input');
            searchInput.id = 'categoria-search';
            searchInput.type = 'text';
            searchInput.placeholder = 'Buscar categorías... (Escape para cancelar)';
            searchInput.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 2000;
                padding: 15px 20px;
                border: 2px solid var(--accent-color);
                border-radius: 25px;
                background: white;
                font-size: 16px;
                width: 350px;
                box-shadow: 0 8px 25px rgba(10, 37, 60, 0.2);
                animation: slideDown 0.3s ease;
            `;
            
            document.body.appendChild(searchInput);
            searchInput.focus();
        }

        searchInput.addEventListener('input', (e) => {
            this.filtrarCategorias(e.target.value);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                searchInput.remove();
                this.mostrarTodasCategorias();
            }
        });

        // Remover al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (e.target !== searchInput) {
                searchInput.remove();
                this.mostrarTodasCategorias();
            }
        }, { once: true });
    }

    filtrarCategorias(termino) {
        const tarjetas = document.querySelectorAll('.category-card');
        const terminoLower = termino.toLowerCase();
        
        tarjetas.forEach(tarjeta => {
            const nombreCategoria = tarjeta.querySelector('h3').textContent.toLowerCase();
            const descripcion = tarjeta.querySelector('.category-description')?.textContent.toLowerCase() || '';
            
            if (nombreCategoria.includes(terminoLower) || descripcion.includes(terminoLower)) {
                tarjeta.style.display = 'block';
                tarjeta.style.animation = 'fadeIn 0.3s ease';
            } else {
                tarjeta.style.display = 'none';
            }
        });
        
        // Mostrar mensaje si no hay resultados
        this.verificarResultados();
    }

    mostrarTodasCategorias() {
        const tarjetas = document.querySelectorAll('.category-card');
        tarjetas.forEach(tarjeta => {
            tarjeta.style.display = 'block';
        });
        
        // Remover mensaje de sin resultados
        const mensajeSinResultados = document.getElementById('sin-resultados');
        if (mensajeSinResultados) {
            mensajeSinResultados.remove();
        }
    }

    verificarResultados() {
        const tarjetasVisibles = document.querySelectorAll('.category-card[style*="display: block"]');
        const contenedorGrid = document.querySelector('.categories-grid');
        
        let mensajeSinResultados = document.getElementById('sin-resultados');
        
        if (tarjetasVisibles.length === 0) {
            if (!mensajeSinResultados) {
                mensajeSinResultados = document.createElement('div');
                mensajeSinResultados.id = 'sin-resultados';
                mensajeSinResultados.className = 'empty-state';
                mensajeSinResultados.innerHTML = `
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No se encontraron categorías</h3>
                    <p>No hay categorías que coincidan con tu búsqueda.</p>
                `;
                contenedorGrid.parentNode.appendChild(mensajeSinResultados);
            }
        } else if (mensajeSinResultados) {
            mensajeSinResultados.remove();
        }
    }

    navegarConTransicion(url) {
        // Añadir clase de transición a las tarjetas
        const tarjetas = document.querySelectorAll('.category-card');
        tarjetas.forEach((tarjeta, index) => {
            setTimeout(() => {
                tarjeta.style.animation = 'slideOutDown 0.3s ease';
            }, index * 50);
        });
        
        // Navegar después de la animación
        setTimeout(() => {
            window.location.href = url;
        }, 500);
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

    async manejarCerrarSesion(event) {
        event.preventDefault();
        
        const confirmado = await this.confirmarAccion(
            '¿Estás seguro que deseas cerrar sesión?',
            'Cerrar Sesión',
            'warning'
        );
        
        if (confirmado) {
            this.mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    }

    async confirmarAccion(mensaje, titulo = 'Confirmar', tipo = 'info') {
        return new Promise((resolve) => {
            // Usar el sistema de confirmaciones universal
            if (typeof confirmarAccion === 'function') {
                resolve(confirmarAccion(mensaje, titulo, tipo));
            } else {
                // Fallback a confirm nativo
                resolve(confirm(mensaje));
            }
        });
    }
}

// Funciones globales para compatibilidad
function manejarCerrarSesion(event) {
    window.productosCategorias.manejarCerrarSesion(event);
}

// Agregar estilos de animación adicionales
const additionalStyles = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes slideOutDown {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(30px);
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    @keyframes slideOutUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    .card-enter {
        animation: slideInUp 0.6s ease-out both;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.productosCategorias = new ProductosCategorias();
    console.log('Productos Categorías inicializado correctamente');
});