import { apiFetch } from "./helpers/api.js";

import { cargarModulo } from "./helpers/cagar-modulo.js";
import { configurarTiempoSesion } from "./helpers/sesion.js";

import componentes from "./helpers/components/index.js";
import alertas from "./helpers/alertas.js";
import formularios from "./helpers/formularios.js";

window.apiFetch                    = apiFetch;

// Componentes
window.Grid                        = componentes.Grid;
window.Select                      = componentes.Select;

// Alertas
window.mostrarDetallesRegistro     = alertas.mostrarDetallesRegistro;
window.dispararMensaje             = alertas.dispararMensaje;

// Formularios
window.validarFormulario           = formularios.validarFormulario;
window.habilitarFormulario         = formularios.habilitarFormulario;
window.cargarDatosFormulario       = formularios.cargarDatosFormulario;
window.limpiarModal                = formularios.limpiarModal;
window.desactivarBotonesFormulario = formularios.desactivarBotonesFormulario;

configurarTiempoSesion();

const sidebar     = document.querySelector('#sidebarNav');
const pageContent = document.querySelector('.page-content');

// --- MANEJO DE EVENTOS ---

sidebar.addEventListener('click', async (e) => {

    // Si el sidebar esta cerrado y damos click en un icono, lo abrimos
    const item = e.target.closest('.nav-item');
    if(document.body.classList.contains('sidebar-folded') && item) {
        document.body.classList.remove('sidebar-folded');
    }

    // Verificamos si el clic fue en un enlace con el atributo data-module
    const link = e.target.closest('button[data-modulo]');
    
    if (link) {
        e.preventDefault(); // Evita que el enlace recargue la página

        const navLink = e.target.closest('button[data-modulo]').querySelector('.nav-link');

        navLink.innerHTML += 
        `<div class="spinner-modulo spinner-border text-light" role="status">
            <span class="visually-hidden"></span>
        </div>`;

        const rutaModulo = link.dataset.modulo;
        const nombre     = link.textContent;

        await cargarModulo(rutaModulo, pageContent);

        navLink.innerHTML = nombre;
    }
});

// // Opcional: Cargar un módulo por defecto al iniciar la app
// document.addEventListener('DOMContentLoaded', () => {
//     cargarModulo('/dashboard');
// });