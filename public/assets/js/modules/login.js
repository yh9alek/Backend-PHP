import { apiFetch, setAuthToken } from "../helpers/api.js";

const login = (user, password) => {

    return apiFetch('/login', {
        method: 'POST',
        body: new URLSearchParams({
            user, password
        }),
    });

};

const boton = document.querySelector('button[type="submit"]');
const usernameInput = document.querySelector('input[name="username"]');
const passInput = document.querySelector('input[name="pass"]');
const notificaciones = document.querySelector('.notificaciones');

boton.addEventListener('click', async e => {
    e.preventDefault();

    notificaciones.innerHTML = '';
    boton.innerHTML = `<div class="spinner-border text-light" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>`;

    boton.disabled = true;

    try {
        const data = await login(usernameInput.value, passInput.value);

        if (data.token) {

            setAuthToken(data.token);
            window.location.href = '/inicio';
            
        } else {
            // Esto es por si la API devuelve 200 OK pero con un mensaje de error
            const errorMessage = data.message || 'Error del servidor, favor de notificar a soporte.';
            notificaciones.innerHTML = `<p class="alert alert-danger w-100">${errorMessage}</p>`;
        }
    } catch (error) {
        const errorMessage = error.message;

        notificaciones.innerHTML = `<p class="alert alert-danger w-100">${errorMessage}</p>`;
        passInput.value = '';

        // Ahora accedemos a la propiedad 'error' a través de `error.data`
        if (error.data && error.data.error === 1) {
            usernameInput.value = '';
        }

    } finally {
        boton.innerHTML = 'Acceder';
        boton.disabled = false;
    }

});