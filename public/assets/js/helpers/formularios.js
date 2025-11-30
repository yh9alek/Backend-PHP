import { mostrarToastError } from "./toasts.js";

function desactivarBotonesFormulario(formulario, ban) {

    const botonSubmit = formulario.querySelector('button[type="submit"]');
    const botonCerrar = formulario.querySelector('button[data-bs-dismiss="modal"]');

    botonSubmit.disabled = ban;
    botonCerrar.disabled = ban;

    if(!ban)
        botonSubmit.innerHTML = 'Guardar';

}

/**
 * Valida un formulario, incluyendo los componentes Select personalizados.
 * Utiliza las clases de validación de Bootstrap 5.
 *
 * @param {string} selectorFormulario El selector CSS del formulario a validar.
 * @returns {boolean} Devuelve true si el formulario es válido, de lo contrario false.
 */
function validarFormulario(selectorFormulario) {
    const formulario = document.querySelector(selectorFormulario);
    if (!formulario) {
        console.error('No se encontró el formulario:', selectorFormulario);
        return false;
    }

    // --- 1. VALIDACIÓN MANUAL DE SELECTS PERSONALIZADOS ---
    let selectsValidos = true;
    const selectsPersonalizados = formulario.querySelectorAll('.select');

    selectsPersonalizados.forEach(container => {
        // Encontramos la instancia de la clase correspondiente al contenedor
        const instanciaSelect = Select.instances.find(inst => inst.container === container);
        if (instanciaSelect) {
            // Usamos la validez del select nativo subyacente
            if (instanciaSelect.nativeSelect.checkValidity()) {
                instanciaSelect.markAsValid();
            } else {
                instanciaSelect.markAsInvalid();
                selectsValidos = false;
            }
        }
    });

    // --- 2. VALIDACIÓN NATIVA DEL FORMULARIO COMPLETO ---
    const botonSubmit = formulario.querySelector('button[type="submit"]');
    formulario.classList.add('was-validated');

    // Comprobamos la validez nativa de TODOS los campos (incluido nuestro select nativo oculto)
    const camposNativosValidos = formulario.checkValidity();

    // El formulario es válido solo si AMBAS validaciones pasan
    const formularioEsValido = camposNativosValidos && selectsValidos;

    if (!formularioEsValido) {
        if (botonSubmit) {
            const textoOriginal = botonSubmit.textContent;
            botonSubmit.disabled = true;
            setTimeout(() => {
                botonSubmit.disabled = false;
                if (textoOriginal) botonSubmit.textContent = textoOriginal;
            }, 3000);
        }
        mostrarToastError('Por favor, revise los campos marcados en rojo.');
        return false;
    }
    
    if (botonSubmit) {
        botonSubmit.innerHTML = `<div class="spinner-border text-light" style="width: 16px; height: 16px;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>`;
    }

    return true;
}

function habilitarFormulario(selectorFormulario, { data }) {
    const formulario = document.querySelector(selectorFormulario);

    formulario.classList.remove('was-validated');

    // Mostramos campos que fueron validados por el backend como faltantes
    if(data) {
        const inputs = data['inputs'];
        for(const name in inputs) {

            const input = formulario.querySelector(`input[name="${name}"]`)  ??
                          formulario.querySelector(`select[name="${name}"]`) ??
                          formulario.querySelector(`textarea[name="${name}"]`);

            if(!inputs[name]) {
                input.setAttribute('required', '');

                const select = input.closest('.select') ?? null;

                if(select && select.classList.contains('is-valid')) {
                    select.classList.remove('is-valid');
                    select.classList.add('is-invalid');
                }
                
                input.focus();
            }

        }
    }

    formulario.classList.add('was-validated');

    desactivarBotonesFormulario(formulario, false);
}

function cargarDatosFormulario({datos, form, titulo = null}) {
    const formulario = document.querySelector(form);
    formulario.querySelector('.modal-title > span').innerHTML = titulo;

    for (const key in datos) {
        const input = formulario.querySelector(`#${key}, [name="${key}"]`);
        if (input) {
            input.value = datos[key];
        }
    }
}

const limpiarModal = (modal) => {
    const form = modal.querySelector('form');
    if (form) {

        // 1. Quita la clase que muestra los errores
        form.classList.remove('was-validated');

        // 2. Resetea los valores de todos los campos del formulario
        form.reset();
        form.querySelector('input[name="id"]').value = "";
    }
};

export default {
    desactivarBotonesFormulario,
    validarFormulario,
    habilitarFormulario,
    cargarDatosFormulario,
    limpiarModal,
};