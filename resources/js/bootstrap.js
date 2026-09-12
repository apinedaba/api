/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Las páginas de Inertia permanecen abiertas durante bastante tiempo. Si la
// sesión rota su token CSRF (por ejemplo, después de volver a la pestaña), el
// primer POST no debe terminar mostrando el documento de error 419 de Laravel.
// Renovamos el token y repetimos esa misma petición una sola vez.
let csrfRefreshRequest;

const refreshCsrfToken = async () => {
    if (!csrfRefreshRequest) {
        csrfRefreshRequest = window.axios
            .get('/csrf-token', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(({ data }) => {
                const token = data?.token;

                if (!token) {
                    throw new Error('No fue posible renovar el token de seguridad.');
                }

                const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');

                if (tokenMeta) {
                    tokenMeta.setAttribute('content', token);
                }

                return token;
            })
            .finally(() => {
                csrfRefreshRequest = undefined;
            });
    }

    return csrfRefreshRequest;
};

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const request = error.config;

        if (
            error.response?.status !== 419 ||
            !request ||
            request._csrfRetry ||
            request.url === '/csrf-token'
        ) {
            return Promise.reject(error);
        }

        request._csrfRetry = true;

        try {
            await refreshCsrfToken();
            request.headers = request.headers || {};

            // Axios toma el valor actual de la cookie XSRF en cada petición.
            // No conservamos el encabezado de la carga inicial porque puede
            // tener un token anterior a una rotación de sesión.
            if (typeof request.headers.delete === 'function') {
                request.headers.delete('X-CSRF-TOKEN');
            } else {
                delete request.headers['X-CSRF-TOKEN'];
                delete request.headers['x-csrf-token'];
            }

            return window.axios.request(request);
        } catch (refreshError) {
            return Promise.reject(refreshError);
        }
    },
);
