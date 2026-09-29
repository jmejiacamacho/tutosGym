const API_TIPOS_SUSCRIPCION = 'api/tipos_suscripcion.php';

// --- Precios de suscripción ---
function formatearMoneda(valor) {
    return '$' + Number(valor).toLocaleString('es-CO');
}

async function cargarPreciosSuscripcion() {
    const contenedor = document.getElementById('listaPreciosSuscripcion');
    try {
        const res = await fetch(API_TIPOS_SUSCRIPCION);
        const tipos = await res.json();

        contenedor.innerHTML = tipos.map(t => `
            <div class="flex items-center justify-between gap-4 p-3 bg-slate-50 rounded-xl">
                <div>
                    <p class="font-semibold text-slate-700">${t.nombre}</p>
                    <p class="text-xs text-slate-400">${t.duracion_dias} día(s) de vigencia</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-slate-400">$</span>
                    <input type="number" data-id-tipo="${t.id_tipo}" class="input-precio w-32 px-3 py-2 border border-slate-200 rounded-lg text-sm text-right" value="${t.precio}">
                    <button onclick="guardarPrecio(${t.id_tipo}, this)" class="text-xs bg-slate-900 text-white px-3 py-2 rounded-lg font-semibold hover:bg-slate-800">Guardar</button>
                </div>
            </div>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar los precios.</p>';
    }
}

async function guardarPrecio(idTipo, boton) {
    const input = document.querySelector(`.input-precio[data-id-tipo="${idTipo}"]`);
    const precio = input.value;

    boton.textContent = 'Guardando...';
    try {
        await fetch(API_TIPOS_SUSCRIPCION, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_tipo: idTipo, precio }),
        });
        boton.textContent = '✅ Guardado';
        setTimeout(() => { boton.textContent = 'Guardar'; }, 1500);
    } catch (err) {
        boton.textContent = 'Error';
    }
}

cargarPreciosSuscripcion();

const API_CONFIGURACION = 'api/configuracion.php';
const API_FESTIVIDADES = 'api/festividades.php';
const API_CATEGORIAS = 'api/categorias.php';

// --- Datos del gimnasio + horarios ---
async function cargarConfiguracion() {
    try {
        const res = await fetch(API_CONFIGURACION);
        const config = await res.json();

        document.getElementById('cfgNombre').value = config.nombre_establecimiento || '';
        document.getElementById('cfgTelefono').value = config.telefono_soporte || '';
        document.getElementById('cfgDireccion').value = config.direccion || '';
        document.getElementById('cfgHorarioAperturaSemana').value = (config.horario_apertura_semana || '06:00:00').slice(0, 5);
        document.getElementById('cfgHorarioCierreSemana').value = (config.horario_cierre_semana || '22:00:00').slice(0, 5);
        document.getElementById('cfgHorarioAperturaFinde').value = (config.horario_apertura_finde || '08:00:00').slice(0, 5);
        document.getElementById('cfgHorarioCierreFinde').value = (config.horario_cierre_finde || '14:00:00').slice(0, 5);
        document.getElementById('cfgAlertasCorreo').checked = config.alertas_correo_activas == 1;
        document.getElementById('cfgMantenimiento').checked = config.mantenimiento_automatico_activo == 1;
    } catch (err) {
        console.error(err);
    }
}

document.getElementById('formDatosGimnasio').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeConfiguracion');

    const payload = {
        nombre_establecimiento: document.getElementById('cfgNombre').value,
        telefono_soporte: document.getElementById('cfgTelefono').value,
        direccion: document.getElementById('cfgDireccion').value,
        horario_apertura_semana: document.getElementById('cfgHorarioAperturaSemana').value,
        horario_cierre_semana: document.getElementById('cfgHorarioCierreSemana').value,
        horario_apertura_finde: document.getElementById('cfgHorarioAperturaFinde').value,
        horario_cierre_finde: document.getElementById('cfgHorarioCierreFinde').value,
        alertas_correo_activas: document.getElementById('cfgAlertasCorreo').checked,
        mantenimiento_automatico_activo: document.getElementById('cfgMantenimiento').checked,
    };

    try {
        const res = await fetch(API_CONFIGURACION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        msg.className = res.ok ? 'text-xs text-emerald-600' : 'text-xs text-red-600';
        msg.textContent = res.ok ? 'Cambios guardados.' : 'Error al guardar.';
    } catch (err) {
        msg.className = 'text-xs text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

// Guardar automáticamente cuando se cambian los switches (no solo con el botón)
['cfgAlertasCorreo', 'cfgMantenimiento'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        document.getElementById('formDatosGimnasio').dispatchEvent(new Event('submit', { cancelable: true }));
    });
});

// --- Festividades ---
async function cargarFestividades() {
    const contenedor = document.getElementById('listaFestividades');
    try {
        const res = await fetch(API_FESTIVIDADES);
        const festividades = await res.json();

        if (!festividades.length) {
            contenedor.innerHTML = '<p class="text-slate-400">No hay festividades registradas.</p>';
            return;
        }

        contenedor.innerHTML = festividades.map(f => `
            <div class="flex justify-between items-center p-2 bg-slate-50 rounded-lg">
                <span class="font-medium text-slate-700">${f.fecha} — ${f.nombre || 'Sin nombre'}</span>
                <span class="text-slate-500 text-xs">
                    ${f.cerrado == 1 ? 'Cerrado todo el día' : `${(f.horario_apertura || '').slice(0,5)} - ${(f.horario_cierre || '').slice(0,5)}`}
                </span>
                <button onclick="borrarFestividad(${f.id_festividad})" class="text-red-500 hover:text-red-700 text-xs font-semibold">Borrar</button>
            </div>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar festividades.</p>';
    }
}

document.getElementById('formFestividad').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        fecha: document.getElementById('festFecha').value,
        nombre: document.getElementById('festNombre').value,
        cerrado: document.getElementById('festCerrado').checked,
        horario_apertura: document.getElementById('festApertura').value,
        horario_cierre: document.getElementById('festCierre').value,
    };

    await fetch(API_FESTIVIDADES, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });

    document.getElementById('formFestividad').reset();
    cargarFestividades();
});

async function borrarFestividad(id) {
    if (!confirm('¿Eliminar esta festividad?')) return;
    await fetch(`${API_FESTIVIDADES}?id=${id}`, { method: 'DELETE' });
    cargarFestividades();
}

// --- Categorías de máquina ---
async function cargarCategorias() {
    const contenedor = document.getElementById('listaCategorias');
    try {
        const res = await fetch(API_CATEGORIAS);
        const categorias = await res.json();

        if (!categorias.length) {
            contenedor.innerHTML = '<p class="text-slate-400">No hay categorías todavía.</p>';
            return;
        }

        contenedor.innerHTML = categorias.map(c => `
            <span class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 rounded-full text-sm text-slate-700">
                ${c.nombre}
                <button onclick="borrarCategoria(${c.id_categoria})" class="text-red-500 hover:text-red-700 font-bold">×</button>
            </span>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar categorías.</p>';
    }
}

document.getElementById('formCategoria').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('nuevaCategoria');

    await fetch(API_CATEGORIAS, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre: input.value }),
    });

    input.value = '';
    cargarCategorias();
});

async function borrarCategoria(id) {
    if (!confirm('¿Eliminar esta categoría?')) return;
    await fetch(`${API_CATEGORIAS}?id=${id}`, { method: 'DELETE' });
    cargarCategorias();
}

cargarConfiguracion();
cargarFestividades();
cargarCategorias();


// --- Logo del gimnasio ---
const API_LOGO = 'api/subir_logo.php';

document.getElementById('formLogo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeLogo');
    const archivo = document.getElementById('inputLogo').files[0];
    if (!archivo) return;

    const formData = new FormData();
    formData.append('logo', archivo);
    msg.className = 'text-xs text-slate-400';
    msg.textContent = 'Subiendo...';

    try {
        const res = await fetch(API_LOGO, { method: 'POST', body: formData });
        const data = await res.json();

        if (!res.ok) {
            msg.className = 'text-xs text-red-600';
            msg.textContent = data.error || 'Error al subir el logo';
            return;
        }

        aplicarLogo(data.logo_url); // actualiza de inmediato el menú, la vista previa y la pestaña
        document.getElementById('formLogo').reset();
        msg.className = 'text-xs text-emerald-600';
        msg.textContent = 'Logo actualizado en toda la aplicación.';
    } catch (err) {
        msg.className = 'text-xs text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

document.getElementById('btnRestaurarLogo').addEventListener('click', async () => {
    if (!confirm('¿Volver al logo original de Tutos Gym Club?')) return;
    const msg = document.getElementById('mensajeLogo');
    try {
        await fetch(API_LOGO, { method: 'DELETE' });
        aplicarLogo(null);
        msg.className = 'text-xs text-emerald-600';
        msg.textContent = 'Logo original restaurado.';
    } catch (err) {
        msg.className = 'text-xs text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});
