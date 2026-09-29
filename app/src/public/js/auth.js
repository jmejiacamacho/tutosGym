const API_AUTH = 'api/auth.php';

function mostrarMensaje(texto, tipo) {
    const el = document.getElementById('mensajeEstado');
    el.textContent = texto;
    el.className = tipo; // 'exito' o 'error'
}

// --- Login ---
document.getElementById('formLogin').addEventListener('submit', async (e) => {
    e.preventDefault();

    const correo = document.getElementById('loginCorreo').value;
    const password = document.getElementById('loginPassword').value;

    try {
        const res = await fetch(API_AUTH, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'login', correo, password }),
        });
        const data = await res.json();

        if (!res.ok) {
            mostrarMensaje(data.error || 'Error al iniciar sesión', 'error');
            return;
        }

        // Guardamos el usuario en la sesión del navegador para las demás pantallas
        sessionStorage.setItem('usuario', JSON.stringify(data.usuario));
        mostrarMensaje(`¡Bienvenido, ${data.usuario.nombre_completo}!`, 'exito');

        // Redirige según el rol: admin/dueño al panel, entrenador a su panel, cliente a su plan
        setTimeout(() => {
            const destinoPorRol = { 1: 'index.html', 2: 'entrenador.html', 3: 'mi-plan.html' };
            window.location.href = destinoPorRol[data.usuario.id_rol] || 'login.html';
        }, 800);

    } catch (err) {
        mostrarMensaje('Error de conexión con el servidor', 'error');
    }
});
