const API_USUARIOS = 'api/usuarios.php';
const nombresRol = { 1: 'Administrador', 2: 'Entrenador', 3: 'Cliente' };
const coloresRol = {
    1: 'bg-slate-800 text-white',
    2: 'bg-purple-50 text-purple-700',
    3: 'bg-slate-100 text-slate-700',
};

let todosLosUsuarios = [];
let mapaCandidatoInactividad = {}; // id_usuario -> { motivo }

// Reglas: diario sin renovar hace más de 30 días, o semanal sin renovar hace más de 7 días
function calcularCandidatosInactividad(suscripciones) {
    const mapa = {};
    const porCliente = {};
    suscripciones.forEach(s => {
        const actual = porCliente[s.id_cliente];
        if (!actual || new Date(s.fecha_vencimiento) > new Date(actual.fecha_vencimiento)) {
            porCliente[s.id_cliente] = s;
        }
    });

    Object.values(porCliente).forEach(s => {
        const dias = Math.floor((new Date() - new Date(s.fecha_vencimiento)) / (1000 * 60 * 60 * 24));
        if (s.tipo_nombre === 'Diario' && dias > 30) {
            mapa[s.id_cliente] = `Diaria sin renovar hace ${dias} días`;
        } else if (s.tipo_nombre === 'Semanal' && dias > 7) {
            mapa[s.id_cliente] = `Semanal sin renovar hace ${dias} días`;
        }
    });
    return mapa;
}

function renderTabla() {
    const tbody = document.getElementById('tablaUsuarios');
    const busqueda = document.getElementById('buscarUsuario').value.toLowerCase();
    const filtroRol = document.getElementById('filtroRol').value;

    let filas = todosLosUsuarios.filter(u =>
        u.nombre_completo.toLowerCase().includes(busqueda) || u.correo.toLowerCase().includes(busqueda)
    );
    if (filtroRol) {
        filas = filas.filter(u => String(u.id_rol) === filtroRol);
    }

    if (!filas.length) {
        tbody.innerHTML = '<tr><td class="p-4 text-slate-400" colspan="6">No hay usuarios que coincidan.</td></tr>';
        return;
    }

    tbody.innerHTML = filas.map(u => {
        const iniciales = u.nombre_completo.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
        return `
            <tr class="hover:bg-slate-50/50 transition">
                <td class="p-4 font-semibold flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">${iniciales}</div>
                    ${u.nombre_completo}
                </td>
                <td class="p-4 text-slate-500">${u.correo}</td>
                <td class="p-4 text-slate-500">${u.telefono || '-'}</td>
                <td class="p-4"><span class="px-2.5 py-1 rounded-md text-xs font-semibold ${coloresRol[u.id_rol] || ''}">${nombresRol[u.id_rol] || u.nombre_rol || '-'}</span>
                    ${u.id_rol == 3 && u.tipo_cliente === 'personalizado' ? '<span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700">Personalizado</span>' : ''}
                </td>
                <td class="p-4">
                    <span class="px-3 py-1 rounded-full text-xs font-bold ${u.activo == 1 ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'}">${u.activo == 1 ? 'Activo' : 'Deshabilitado'}</span>
                    ${mapaCandidatoInactividad[u.id_usuario] ? `<span title="${mapaCandidatoInactividad[u.id_usuario]}" class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-600">⚠ Sin renovar</span>` : ''}
                </td>
                <td class="p-4 whitespace-nowrap">
                    ${u.id_rol == 3 ? `<button onclick="cambiarTipoCliente(${u.id_usuario}, '${u.tipo_cliente || 'mensual'}')" class="text-blue-600 hover:text-blue-700 text-xs font-semibold mr-3">Cambiar tipo</button>` : ''}
                    <button onclick="alternarActivo(${u.id_usuario}, ${u.activo == 1 ? 'false' : 'true'})" class="text-xs font-semibold ${u.activo == 1 ? 'text-red-600 hover:text-red-700' : 'text-emerald-600 hover:text-emerald-700'}">
                        ${u.activo == 1 ? 'Deshabilitar' : 'Habilitar'}
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function cargarUsuarios() {
    try {
        const [resUsuarios, resSuscripciones] = await Promise.all([
            fetch(API_USUARIOS),
            fetch('api/suscripciones.php'),
        ]);
        todosLosUsuarios = await resUsuarios.json();
        // id_rol puede venir sin normalizar el tipo (string vs number) según el driver
        todosLosUsuarios.forEach(u => u.id_rol = Number(u.id_rol));

        const suscripciones = await resSuscripciones.json();
        mapaCandidatoInactividad = calcularCandidatosInactividad(suscripciones);

        renderTabla();
    } catch (err) {
        document.getElementById('tablaUsuarios').innerHTML = '<tr><td class="p-4 text-red-600" colspan="6">Error al cargar.</td></tr>';
    }
}

// --- Cambiar tipo de cliente (mensual <-> personalizado) ---
async function cambiarTipoCliente(idUsuario, tipoActual) {
    const nuevoTipo = prompt('Tipo de cliente ("mensual" o "personalizado"):', tipoActual);
    if (nuevoTipo === null) return;
    if (!['mensual', 'personalizado'].includes(nuevoTipo.trim().toLowerCase())) {
        alert('Escribe exactamente "mensual" o "personalizado".');
        return;
    }

    const payload = { id_usuario: idUsuario, tipo_cliente: nuevoTipo.trim().toLowerCase() };

    if (payload.tipo_cliente === 'personalizado') {
        const tarifa = prompt('Tarifa acordada con el entrenador (deja vacío si no aplica todavía):', '');
        if (tarifa) payload.tarifa_personalizada_acordada = tarifa;
    }

    await fetch(API_USUARIOS, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    cargarUsuarios();
}

// --- Habilitar / deshabilitar cuenta ---
async function alternarActivo(idUsuario, activar) {
    const verbo = activar ? 'habilitar' : 'deshabilitar';
    if (!confirm(`¿Seguro que quieres ${verbo} esta cuenta?`)) return;

    await fetch(API_USUARIOS, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_usuario: idUsuario, activo: activar }),
    });
    cargarUsuarios();
}

document.getElementById('buscarUsuario').addEventListener('input', renderTabla);
document.getElementById('filtroRol').addEventListener('change', renderTabla);

document.getElementById('btnRegistrarUsuario').addEventListener('click', () => {
    document.getElementById('formNuevoUsuario').classList.toggle('hidden');
});

document.getElementById('formNuevoUsuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeNuevoUsuario');

    const payload = {
        nombre_completo: document.getElementById('nuevoNombre').value,
        correo: document.getElementById('nuevoCorreo').value,
        password: document.getElementById('nuevoPassword').value,
        id_rol: document.getElementById('nuevoRol').value,
        telefono: document.getElementById('nuevoTelefono').value,
        tipo_cliente: document.getElementById('nuevoTipoCliente').value,
        tarifa_personalizada_acordada: document.getElementById('nuevoTarifaPersonalizada').value || null,
    };

    try {
        const res = await fetch(API_USUARIOS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        msg.className = res.ok ? 'text-xs md:col-span-5 text-emerald-600' : 'text-xs md:col-span-5 text-red-600';
        msg.textContent = res.ok ? 'Usuario creado correctamente.' : (data.error || 'Error al crear el usuario');

        if (res.ok) {
            document.getElementById('formNuevoUsuario').reset();
            document.getElementById('formNuevoUsuario').classList.add('hidden');
            cargarUsuarios();
        }
    } catch (err) {
        msg.className = 'text-xs md:col-span-5 text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

cargarUsuarios();
