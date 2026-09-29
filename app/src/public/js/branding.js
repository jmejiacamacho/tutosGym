// Aplica la identidad del gimnasio (logo + nombre) en cualquier pantalla.
// Lo que el dueño configure en Configuración (logo y nombre) manda sobre los valores por defecto del HTML.
// Marcadores en el HTML:  <img class="logo-gym">   y   <span class="nombre-gym">

const LOGO_POR_DEFECTO = 'public/img/logo-default.png';

function aplicarLogo(url) {
    const src = url || LOGO_POR_DEFECTO;
    document.querySelectorAll('.logo-gym').forEach(img => { img.src = src; });
    const favicon = document.querySelector('link[rel="icon"]');
    if (favicon) favicon.href = src;
}

function aplicarNombre(nombre) {
    if (!nombre) return;
    document.querySelectorAll('.nombre-gym').forEach(el => { el.textContent = nombre; });
    document.title = document.title.replace(/Tutos Gym Club|GymSoft Pro/, nombre);
}

(async function cargarIdentidad() {
    try {
        const res = await fetch('api/configuracion.php');
        const cfg = await res.json();
        aplicarLogo(cfg.logo_url);
        aplicarNombre(cfg.nombre_establecimiento);
    } catch (err) {
        // Sin conexión a la API: se queda con el logo y nombre por defecto del HTML
    }
})();
