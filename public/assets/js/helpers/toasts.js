let activeToasts = [];
const MAX_VISIBLE_TOASTS = 3;

/**
 * Muestra un toast gestionando un límite máximo de toasts visibles.
 * @param {object} options - El objeto de configuración para Toastify.
 */
function mostrarToast(options) {

    activeToasts = activeToasts.filter(toast =>
        toast && toast.toastElement && document.body.contains(toast.toastElement)
    );

    while (activeToasts.length >= MAX_VISIBLE_TOASTS) {
        const oldestToast = activeToasts.shift();
        try {
            oldestToast.removeElement();
        } catch (e) {}
    }

    const newToast = Toastify(options);
    newToast.showToast();

    activeToasts.push(newToast);
}

/**
 * Muestra un toast de error utilizando Toastify.js y Lucide Icons.
 * @param {string} mensaje El mensaje principal a mostrar en el toast.
 * @param {string} titulo El título del toast (ej. 'Error de Validación').
 */
export function mostrarToastError(mensaje, titulo = 'Campos Incompletos') {
    // Creamos el elemento HTML para el contenido del toast
    const toastNode = document.createElement('div');
    toastNode.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i data-lucide="alert-circle" style="width: 36px; height: 36px; color: #dc3545;"></i>
            <div>
                <strong style="font-size: 20px;">${titulo}</strong>
                <div>${mensaje}</div>
            </div>
        </div>
    `;

    // Iniciamos el toast
    mostrarToast({
        node: toastNode,
        duration: 3000,
        gravity: "bottom",
        position: "right",
        stopOnFocus: false,
        className: "error",
        style: {
            background: "var(--bs-dark-bg-subtle, #333)",
            color: "var(--bs-body-color, #fff)",
            border: "1px solid var(--bs-border-color)",
            borderRadius: "8px",
            'min-width': "378.5px"
        }
    });

    // Inicializamos el ícono de Lucide dentro del toast
    lucide.createIcons({
        nodes: [toastNode.querySelector('i')]
    });
}

/**
 * Muestra un toast INDEPENDIENTE Y DE MÁXIMA PRIORIDAD con la cuenta regresiva.
 * Utiliza la librería Toastify.js.
 * @param {number} duracionToastMs - La duración del toast en milisegundos.
 */
export function mostrarToastAdvertencia(duracionToastMs) {
    // Creamos el elemento HTML para el contenido del toast
    const toastNode = document.createElement('div');
    toastNode.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i data-lucide="clock-4" style="width: 36px; height: 36px; color: #f8bb86;"></i>
            <div>
                <strong style="font-size: 25px;">Tu sesión expirará pronto</strong>
                <div id="toast-countdown">Cerrando en 10 segundos.</div>
            </div>
        </div>
    `;

    // Iniciamos el toast con Toastify
    const miToast = Toastify({
        node: toastNode, // Usamos nuestro HTML personalizado
        duration: duracionToastMs, // Duración del toast
        gravity: "bottom", // "top" o "bottom"
        position: "right", // "left", "center" o "right"
        stopOnFocus: false,
        className: "info", // Clase CSS para estilizarlo
        style: {
            // Estilos para que coincida con tu tema Bootstrap oscuro
            background: "var(--bs-dark-bg-subtle, #333)",
            color: "var(--bs-body-color, #fff)",
            border: "1px solid var(--bs-border-color)",
            borderRadius: "8px",
            'min-width': "378.5px"
        }
    }).showToast();

    // Inicializamos el ícono de Lucide dentro del toast
    lucide.createIcons({
        nodes: [toastNode.querySelector('i')]
    });

    // Lógica para actualizar el contador de segundos
    const countdownElement = toastNode.querySelector('#toast-countdown');
    let timeLeft = Math.ceil(duracionToastMs / 1000);
    
    const timerInterval = setInterval(() => {
        timeLeft--;
        if (timeLeft > 0) {
            countdownElement.textContent = `Cerrando en ${timeLeft} segundos.`;
        } else {
            clearInterval(timerInterval);
            miToast.cle
        }
    }, 1000);
}