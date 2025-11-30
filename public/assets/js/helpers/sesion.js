import { apiFetch } from "./api.js";
import { mostrarToastAdvertencia } from "./toasts.js";

function mostrarModalSesionExpirada() {
    dispararMensaje(
        'Tu sesión ha expirado',
        'Por tu seguridad, hemos cerrado tu sesión.',
        'warning',
        {
            confirmButtonText: 'Ir al Login',

            allowOutsideClick: false, // Bloqueamos el cierre
            allowEscapeKey: false,    // Bloqueamos el cierre
            onConfirm: () => {

                // Redirigimos al usuario al hacer clic
                window.location.href = '/login';
            }
        }
    );
}

/**
 * Inicia el contador de sesión y muestra las alertas de expiración.
 * @param {string} tiempoSesion - El tiempo de sesión en formato "HH:MM:SS".
 */
function iniciarContadorSesion(tiempoSesion) {
    // --- Función auxiliar para convertir "HH:MM:SS" a milisegundos ---
    const convertirTiempoAMilisegundos = (tiempo) => {
        const partes = tiempo.split(':');
        const horas = parseInt(partes[0], 10);
        const minutos = parseInt(partes[1], 10);
        const segundos = parseInt(partes[2], 10);
        return (horas * 3600 + minutos * 60 + segundos) * 1000;
    };

    const duracionTotalMs = convertirTiempoAMilisegundos(tiempoSesion);
    const tiempoAdvertenciaMs = 10000;

    setTimeout(() => {
        if (Swal.isVisible()) {
            Swal.close();
        }
        mostrarModalSesionExpirada();
    }, duracionTotalMs);

    if (duracionTotalMs <= tiempoAdvertenciaMs) return;

    // 2. Temporizador para el Toast INDEPENDIENTE
    const tiempoParaMostrarAdvertencia = duracionTotalMs - tiempoAdvertenciaMs;
    setTimeout(() => {
        mostrarToastAdvertencia(tiempoAdvertenciaMs);
    }, tiempoParaMostrarAdvertencia);
}

export const configurarTiempoSesion = async () => {
    const { data } = await apiFetch('/tiempo_sesion');    
    const tiempoSesion = data.tiempoSesion;
    iniciarContadorSesion(tiempoSesion);
}
