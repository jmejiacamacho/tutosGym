// Uso compartido en todas las páginas de administración/entrenador: valida
// que haya sesión, que el rol tenga permiso para ver ESTA página, pinta el
// nombre/iniciales reales del usuario logueado, y conecta "Salir".
//
// Cada página declara qué roles puede ver en <body data-roles-permitidos="1,2">
// (1=admin/dueño, 2=entrenador, 3=cliente). Si no se declara, no se restringe.

const usuarioSesion = JSON.parse(sessionStorage.getItem('usuario') || 'null');

if (!usuarioSesion) {
    window.location.href = 'login.html';
} else {
    const rolesPermitidos = document.body.dataset.rolesPermitidos;
    if (rolesPermitidos) {
        const permitidos = rolesPermitidos.split(',').map(r => r.trim());
        if (!permitidos.includes(String(usuarioSesion.id_rol))) {
            alert('No tienes permiso para ver esta sección.');
            // Cada rol vuelve a su propia pantalla principal
            const destinoPorRol = { 1: 'index.html', 2: 'entrenador.html', 3: 'mi-plan.html' };
            window.location.href = destinoPorRol[usuarioSesion.id_rol] || 'login.html';
        }
    }

    const elNombre = document.getElementById('nombreUsuario');
    const elIniciales = document.getElementById('inicialesUsuario');
    const elBtnSalir = document.getElementById('btnSalir');

    if (elNombre) elNombre.textContent = usuarioSesion.nombre_completo;
    if (elIniciales) {
        elIniciales.textContent = usuarioSesion.nombre_completo
            .split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
    }
    if (elBtnSalir) {
        elBtnSalir.addEventListener('click', () => {
            sessionStorage.removeItem('usuario');
            window.location.href = 'login.html';
        });
    }

    // Menú hamburguesa (sidebar deslizable en móvil)
    const btnMenu = document.getElementById('btnMenu');
    const sidebar = document.getElementById('sidebarMovil');
    const overlay = document.getElementById('overlaySidebarMovil');
    if (btnMenu && sidebar && overlay) {
        const abrirMenu = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        };
        const cerrarMenu = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };
        btnMenu.addEventListener('click', abrirMenu);
        overlay.addEventListener('click', cerrarMenu);
        // Cerrar automáticamente al tocar un link del menú (útil en móvil)
        sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', cerrarMenu));
    }
}
