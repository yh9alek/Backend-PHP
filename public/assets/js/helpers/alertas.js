/**
 * Crea y muestra una alerta personalizada utilizando SweetAlert2 con estilos de Bootstrap.
 *
 * @param {string} title El título de la alerta.
 * @param {string} text El mensaje o texto principal de la alerta.
 * @param {'success'|'error'|'warning'|'info'|'question'} icon El icono a mostrar en la alerta.
 * @param {object} [options={}] Un objeto de configuración opcional.
 * @param {string} [options.confirmButtonText='Aceptar'] El texto para el botón de confirmación.
 * @param {boolean} [options.showCancelButton=false] Si se debe mostrar o no el botón de cancelar.
 * @param {string} [options.cancelButtonText='Cancelar'] El texto para el botón de cancelar.
 * @param {Function} [options.onConfirm] Función callback a ejecutar cuando se presiona el botón de confirmación.
 * @param {Function} [options.onCancel] Función callback a ejecutar cuando se presiona el botón de cancelar.
 * @param {boolean} [options.allowOutsideClick=true] Permite cerrar la alerta haciendo clic fuera de ella.
 * @param {boolean} [options.allowEscapeKey=true] Permite cerrar la alerta con la tecla Escape.
 */
export function dispararMensaje(title, text, icon, options = {}) {

    // Definimos el mixin una sola vez para mantener la consistencia.
    const SwalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary me-2'
        },
        showClass: {
            popup: 'swal-popup-zoom-in'
        },
        hideClass: {
            popup: 'swal-popup-zoom-out'
        },
        buttonsStyling: false
    });

    // Construimos la configuración de la alerta
    const swalConfig = {

        title: title,
        text: text,
        icon: icon,

        confirmButtonText: options.confirmButtonText || 'Aceptar',
        showCancelButton:  options.showCancelButton  || false,
        cancelButtonText:  options.cancelButtonText  || 'Cancelar',

        allowOutsideClick: options.allowOutsideClick ?? true,
        allowEscapeKey: options.allowEscapeKey ?? true,
    };

    SwalWithBootstrapButtons.fire(swalConfig).then((result) => {

        // Si se confirma y hay una función de callback 'onConfirm'
        if (result.isConfirmed && options.onConfirm) {
            options.onConfirm();
        }
        // Si se cancela (usando el botón de cancelar) y hay un callback 'onCancel'
        else if (result.dismiss === Swal.DismissReason.cancel && options.onCancel) {
            options.onCancel();
        }
    });
}

/**
 * Muestra una alerta de tipo "loader" que el usuario no puede cerrar.
 * Utiliza el spinner incorporado de SweetAlert2.
 */
export function mostrarLoader() {
    Swal.fire({
        title: 'Cargando',
        allowOutsideClick: false, // No permitir cerrar haciendo clic fuera
        allowEscapeKey: false,    // No permitir cerrar con la tecla Esc
        showConfirmButton: false, // Ocultar el botón de confirmar
        showClass: {
            popup: 'swal-popup-in'
        },
        hideClass: {
            popup: 'swal-popup-out'
        },
        didOpen: () => {
            Swal.showLoading(); // Muestra el ícono de carga (spinner)
        }
    });
}

/**
 * Muestra una alerta con detalles de auditoría en un formato de tabla moderna.
 * @param {object} datosAuditoria - Objeto con los datos a mostrar.
 * @param {string} datosAuditoria.usuarioAlta - Nombre del usuario que creó el registro.
 * @param {string} datosAuditoria.fechaAlta - Fecha de creación.
 * @param {string} datosAuditoria.usuarioModificacion - Nombre del usuario que modificó.
 * @param {string} datosAuditoria.fechaModificacion - Fecha de modificación.
 */
export function mostrarDetallesRegistro(datosAuditoria) {
    // Usamos el mixin para que el botón tenga el estilo de Bootstrap
    const SwalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-secondary',
        },
        showClass: {
            popup: 'swal-popup-zoom-in'
        },
        hideClass: {
            popup: 'swal-popup-zoom-out'
        },
        buttonsStyling: false
    });

    const contenidoHtml = `
        <div class="swal-info-container">
            <div class="swal-info-row">
                <div class="swal-info-label">
                    <i data-lucide="user-plus"></i>
                    <strong>Usuario Alta:</strong>
                </div>
                <div class="swal-info-value">
                    ${datosAuditoria.create_user || 'N/A'}
                </div>
            </div>
            <div class="swal-info-row">
                <div class="swal-info-label">
                    <i data-lucide="calendar-plus"></i>
                    <strong>Fecha Alta:</strong>
                </div>
                <div class="swal-info-value">
                    ${datosAuditoria.created_at || 'N/A'}
                </div>
            </div>
            <div class="swal-info-row usermod">
                <div class="swal-info-label">
                    <i data-lucide="user-cog"></i>
                    <strong>Usuario Mod:</strong>
                </div>
                <div class="swal-info-value">
                    ${datosAuditoria.update_user || ''}
                </div>
            </div>
            <div class="swal-info-row datemod">
                <div class="swal-info-label">
                    <i data-lucide="calendar-clock"></i>
                    <strong>Fecha Mod:</strong>
                </div>
                <div class="swal-info-value">
                    ${datosAuditoria.updated_at || ''}
                </div>
            </div>
        </div>
    `;

    SwalWithBootstrapButtons.fire({
        title: '<strong>Detalles del Registro</strong>',
        icon: 'info',
        html: contenidoHtml,
        confirmButtonText: 'Cerrar',
        width: 500,

        didOpen: () => {
            lucide.createIcons();
        }
    });

    if(!datosAuditoria.update_user) {
        document.querySelector('.usermod > .swal-info-value').style.color = 'color-mix(is srgb, currentColor 50%, var(--bs-body-bg) 50%)';
    }
}

export default { 
    dispararMensaje, 
    mostrarDetallesRegistro 
};