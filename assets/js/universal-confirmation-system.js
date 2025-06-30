/**
 * ===================================================================
 * SISTEMA DE CONFIRMACIONES UNIVERSAL PARA COMSEPROA
 * ===================================================================
 */

class ConfirmationSystem {
    constructor() {
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        // Verificar si ya existe el modal
        if (document.getElementById('confirmation-modal')) {
            return;
        }

        const modalHTML = `
            <div id="confirmation-modal" class="confirmation-modal" role="dialog" aria-modal="true">
                <div class="confirmation-overlay"></div>
                <div class="confirmation-container">
                    <div class="confirmation-header">
                        <div class="confirmation-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3 class="confirmation-title">Confirmar Acción</h3>
                    </div>
                    <div class="confirmation-body">
                        <p class="confirmation-message">¿Estás seguro de que deseas realizar esta acción?</p>
                        <div class="confirmation-details" style="display: none;"></div>
                    </div>
                    <div class="confirmation-footer">
                        <button type="button" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="button" class="btn-confirm">
                            <i class="fas fa-check"></i> Confirmar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById('confirmation-styles')) {
            return;
        }

        const styles = `
            <style id="confirmation-styles">
            .confirmation-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                display: none;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .confirmation-modal.show {
                display: flex;
                opacity: 1;
            }

            .confirmation-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(10, 37, 60, 0.8);
                backdrop-filter: blur(5px);
            }

            .confirmation-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(10, 37, 60, 0.3);
                max-width: 500px;
                width: 90%;
                overflow: hidden;
                position: relative;
                transform: scale(0.8);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .confirmation-modal.show .confirmation-container {
                transform: scale(1);
            }

            .confirmation-header {
                background: linear-gradient(135deg, #0a253c 0%, #164463 100%);
                color: white;
                padding: 25px;
                text-align: center;
            }

            .confirmation-icon {
                font-size: 48px;
                margin-bottom: 15px;
                opacity: 0.9;
            }

            .confirmation-icon.warning { color: #ffc107; }
            .confirmation-icon.danger { color: #dc3545; }
            .confirmation-icon.success { color: #28a745; }
            .confirmation-icon.info { color: #17a2b8; }

            .confirmation-title {
                font-size: 22px;
                font-weight: 700;
                margin: 0;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }

            .confirmation-body {
                padding: 30px;
                text-align: center;
            }

            .confirmation-message {
                font-size: 16px;
                color: #0a253c;
                margin: 0 0 15px 0;
                line-height: 1.6;
                font-weight: 500;
            }

            .confirmation-details {
                background: #f8f9fa;
                border-left: 4px solid #0a253c;
                padding: 15px;
                margin: 15px 0;
                border-radius: 0 8px 8px 0;
                text-align: left;
            }

            .confirmation-details h4 {
                margin: 0 0 10px 0;
                color: #0a253c;
                font-size: 14px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .confirmation-details p {
                margin: 5px 0;
                font-size: 14px;
                color: #666;
            }

            .confirmation-footer {
                padding: 20px 30px 30px;
                display: flex;
                gap: 15px;
                justify-content: center;
            }

            .confirmation-footer button {
                padding: 12px 25px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 120px;
                justify-content: center;
            }

            .btn-cancel {
                background: #6c757d;
                color: white;
            }

            .btn-cancel:hover {
                background: #5a6268;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            }

            .btn-confirm {
                background: linear-gradient(135deg, #0a253c 0%, #164463 100%);
                color: white;
            }

            .btn-confirm:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(10, 37, 60, 0.3);
            }

            .btn-confirm.warning {
                background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                color: #0a253c;
            }

            .btn-confirm.danger {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
            }

            .btn-confirm.success {
                background: linear-gradient(135deg, #28a745 0%, #218838 100%);
                color: white;
            }

            @media (max-width: 768px) {
                .confirmation-container {
                    width: 95%;
                    margin: 20px;
                }
                .confirmation-footer {
                    flex-direction: column;
                }
                .confirmation-footer button {
                    width: 100%;
                }
            }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', styles);
    }

    bindEvents() {
        const modal = document.getElementById('confirmation-modal');
        const cancelBtn = modal.querySelector('.btn-cancel');
        const overlay = modal.querySelector('.confirmation-overlay');

        cancelBtn.addEventListener('click', () => this.hide());
        overlay.addEventListener('click', () => this.hide());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                this.hide();
            }
        });
    }

    show(options = {}) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmation-modal');
            const title = modal.querySelector('.confirmation-title');
            const message = modal.querySelector('.confirmation-message');
            const details = modal.querySelector('.confirmation-details');
            const confirmBtn = modal.querySelector('.btn-confirm');
            const icon = modal.querySelector('.confirmation-icon i');
            const iconContainer = modal.querySelector('.confirmation-icon');

            title.textContent = options.title || 'Confirmar Acción';
            message.textContent = options.message || '¿Estás seguro de que deseas realizar esta acción?';

            if (options.details) {
                details.innerHTML = options.details;
                details.style.display = 'block';
            } else {
                details.style.display = 'none';
            }

            const type = options.type || 'info';
            iconContainer.className = `confirmation-icon ${type}`;

            const icons = {
                danger: 'fa-exclamation-triangle',
                warning: 'fa-exclamation-circle',
                success: 'fa-check-circle',
                info: 'fa-question-circle'
            };
            icon.className = `fas ${icons[type] || icons.info}`;

            confirmBtn.className = `btn-confirm ${type !== 'info' ? type : ''}`;
            confirmBtn.innerHTML = `<i class="fas fa-check"></i> ${options.confirmText || 'Confirmar'}`;

            modal.classList.add('show');
            confirmBtn.focus();

            const handleConfirm = () => {
                confirmBtn.removeEventListener('click', handleConfirm);
                this.hide();
                resolve(true);
            };

            const handleCancel = () => {
                modal.removeEventListener('hidden', handleCancel);
                resolve(false);
            };

            confirmBtn.addEventListener('click', handleConfirm);
            modal.addEventListener('hidden', handleCancel, { once: true });
        });
    }

    hide() {
        const modal = document.getElementById('confirmation-modal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.dispatchEvent(new CustomEvent('hidden'));
        }, 300);
    }
}

// Instancia global del sistema de confirmaciones
let confirmationSystem = null;

// Inicializar cuando el DOM esté listo
function initConfirmationSystem() {
    if (!confirmationSystem) {
        confirmationSystem = new ConfirmationSystem();
    }
}

// ===== FUNCIONES DE CONFIRMACIÓN ESPECÍFICAS =====

// 👤 Para usuarios
async function confirmarRegistroUsuario(nombreCompleto, rol) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = nombreCompleto ? `
        <h4>Usuario a registrar:</h4>
        <p><strong>Nombre:</strong> ${nombreCompleto}</p>
        <p><strong>Rol:</strong> ${rol}</p>
        <p><small>Se enviará un correo de confirmación al usuario.</small></p>
    ` : '';

    return await confirmationSystem.show({
        title: 'Registrar Usuario',
        message: '¿Confirmas el registro de este nuevo usuario?',
        details: details,
        type: 'success',
        confirmText: 'Registrar Usuario'
    });
}

async function confirmarEdicionUsuario(nombreUsuario) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = nombreUsuario ? `
        <h4>Usuario a modificar:</h4>
        <p><strong>${nombreUsuario}</strong></p>
        <p><small>Los cambios se aplicarán inmediatamente.</small></p>
    ` : '';

    return await confirmationSystem.show({
        title: 'Guardar Cambios',
        message: '¿Estás seguro de que deseas guardar los cambios?',
        details: details,
        type: 'warning',
        confirmText: 'Guardar Cambios'
    });
}

// 🏢 Para almacenes
async function confirmarRegistroAlmacen(nombreAlmacen, ubicacion) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = `
        <h4>Almacén a registrar:</h4>
        <p><strong>Nombre:</strong> ${nombreAlmacen}</p>
        <p><strong>Ubicación:</strong> ${ubicacion}</p>
    `;

    return await confirmationSystem.show({
        title: 'Registrar Almacén',
        message: '¿Confirmas el registro de este almacén?',
        details: details,
        type: 'success',
        confirmText: 'Registrar Almacén'
    });
}

// 📦 Para productos
async function confirmarRegistroProducto(nombreProducto, categoria, cantidad) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = `
        <h4>Producto a registrar:</h4>
        <p><strong>Nombre:</strong> ${nombreProducto}</p>
        <p><strong>Categoría:</strong> ${categoria}</p>
        <p><strong>Cantidad:</strong> ${cantidad}</p>
    `;

    return await confirmationSystem.show({
        title: 'Registrar Producto',
        message: '¿Confirmas el registro de este producto?',
        details: details,
        type: 'success',
        confirmText: 'Registrar Producto'
    });
}

async function confirmarEnvioProducto(nombreProducto, almacenDestino, cantidad) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = `
        <h4>Transferencia a realizar:</h4>
        <p><strong>Producto:</strong> ${nombreProducto}</p>
        <p><strong>Destino:</strong> ${almacenDestino}</p>
        <p><strong>Cantidad:</strong> ${cantidad}</p>
        <p><small>Esta acción reducirá el stock del almacén actual.</small></p>
    `;

    return await confirmationSystem.show({
        title: 'Enviar Producto',
        message: '¿Confirmas el envío de este producto?',
        details: details,
        type: 'warning',
        confirmText: 'Enviar Producto'
    });
}

// 🗑️ Para eliminaciones
async function confirmarEliminacion(elemento, nombre) {
    if (!confirmationSystem) initConfirmationSystem();
    
    const details = `
        <h4>${elemento} a eliminar:</h4>
        <p><strong>${nombre}</strong></p>
        <p><small style="color: #dc3545;"><strong>⚠️ Esta acción no se puede deshacer</strong></small></p>
    `;

    return await confirmationSystem.show({
        title: `Eliminar ${elemento}`,
        message: `¿Estás seguro de que deseas eliminar este ${elemento.toLowerCase()}?`,
        details: details,
        type: 'danger',
        confirmText: 'Eliminar'
    });
}

// ✅ Para aprobaciones
async function confirmarAprobacionSolicitud(tipoSolicitud, detalles) {
    if (!confirmationSystem) initConfirmationSystem();
    
    return await confirmationSystem.show({
        title: `Aprobar ${tipoSolicitud}`,
        message: `¿Confirmas la aprobación de esta ${tipoSolicitud.toLowerCase()}?`,
        details: detalles,
        type: 'success',
        confirmText: 'Aprobar'
    });
}

async function confirmarRechazoSolicitud(tipoSolicitud, detalles) {
    if (!confirmationSystem) initConfirmationSystem();
    
    return await confirmationSystem.show({
        title: `Rechazar ${tipoSolicitud}`,
        message: `¿Confirmas el rechazo de esta ${tipoSolicitud.toLowerCase()}?`,
        details: detalles,
        type: 'danger',
        confirmText: 'Rechazar'
    });
}

// 🔄 Para cambios de estado
async function confirmarCambioEstado(elemento, nuevoEstado) {
    if (!confirmationSystem) initConfirmationSystem();
    
    return await confirmationSystem.show({
        title: 'Cambiar Estado',
        message: `¿Confirmas cambiar el estado a "${nuevoEstado}"?`,
        type: 'warning',
        confirmText: 'Cambiar Estado'
    });
}

// 🚪 Para sesión
async function confirmarCerrarSesion() {
    if (!confirmationSystem) initConfirmationSystem();
    
    return await confirmationSystem.show({
        title: 'Cerrar Sesión',
        message: '¿Estás seguro de que deseas cerrar tu sesión?',
        details: '<p><small>Tendrás que iniciar sesión nuevamente para acceder al sistema.</small></p>',
        type: 'info',
        confirmText: 'Cerrar Sesión'
    });
}

// 🎯 Genérica
async function confirmarAccion(mensaje, titulo = 'Confirmar Acción', tipo = 'info') {
    if (!confirmationSystem) initConfirmationSystem();
    
    return await confirmationSystem.show({
        title: titulo,
        message: mensaje,
        type: tipo
    });
}

// ===== SISTEMA DE NOTIFICACIONES =====

function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    let container = document.getElementById('notificaciones-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificaciones-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
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
    notificacion.className = `notificacion ${tipo}`;
    notificacion.style.cssText = `
        background: white;
        border-left: 5px solid ${colores[tipo] || colores.info};
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 4px 12px rgba(10, 37, 60, 0.15);
        position: relative;
        animation: slideInRight 0.4s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    `;

    notificacion.innerHTML = `
        <i class="fas ${iconos[tipo] || iconos.info}" style="font-size: 20px; color: ${colores[tipo] || colores.info};"></i>
        <span style="flex: 1; color: #0a253c; font-weight: 500;">${mensaje}</span>
        <button class="cerrar" aria-label="Cerrar notificación" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 0;">&times;</button>
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

    // Agregar animaciones CSS si no existen
    if (!document.getElementById('notification-animations')) {
        const animationStyles = document.createElement('style');
        animationStyles.id = 'notification-animations';
        animationStyles.textContent = `
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(30px); }
                to { opacity: 1; transform: translateX(0); }
            }
            @keyframes slideOutRight {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(30px); }
            }
        `;
        document.head.appendChild(animationStyles);
    }
}

// Inicializar automáticamente cuando se carga el script
document.addEventListener('DOMContentLoaded', function() {
    initConfirmationSystem();
});