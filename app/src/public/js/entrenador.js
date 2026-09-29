const usuario = JSON.parse(sessionStorage.getItem('usuario') || 'null');

// --- Crear cliente ---
document.getElementById('formCrearCliente').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeCrear');

    const payload = {
        nombre_completo: document.getElementById('clienteNombre').value,
        correo: document.getElementById('clienteCorreo').value,
        password: document.getElementById('clientePassword').value,
        telefono: document.getElementById('clienteTelefono').value,
        tipo_cliente: document.getElementById('clienteTipoCliente').value,
        tarifa_personalizada_acordada: document.getElementById('clienteTarifaPersonalizada').value || null,
        id_rol: 3, // cliente
    };

    try {
        const res = await fetch('api/usuarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        msg.className = res.ok ? 'text-xs mt-2 text-emerald-600' : 'text-xs mt-2 text-red-600';
        msg.textContent = res.ok ? 'Cliente creado correctamente.' : (data.error || 'Error al crear el cliente');

        if (res.ok) {
            document.getElementById('formCrearCliente').reset();
            cargarClientesParaPlan();
        }
    } catch (err) {
        msg.className = 'text-xs mt-2 text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

// --- Mis clientes personalizados (con distintivo) ---
async function cargarMisClientes() {
    const contenedor = document.getElementById('listaClientes');
    try {
        const res = await fetch(`api/usuarios.php?id_entrenador=${usuario.id_usuario}`);
        const clientes = await res.json();

        if (!clientes.length) {
            contenedor.innerHTML = '<p class="text-slate-400">Aún no tienes clientes personalizados asignados.</p>';
            return;
        }

        contenedor.innerHTML = clientes.map(c => `
            <div class="flex justify-between items-center p-2 bg-slate-50 rounded-lg">
                <span class="font-semibold text-slate-700 flex items-center gap-2">
                    ${c.nombre_completo}
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">Personalizado</span>
                </span>
                <span class="text-slate-500 text-xs">${c.correo}</span>
            </div>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar tus clientes.</p>';
    }
}

// --- Selector de cliente para armar rutina (todos los clientes, no solo personalizados) ---
async function cargarClientesParaPlan() {
    const opciones = await construirOpcionesClientes();
    document.getElementById('planCliente').innerHTML = opciones;
    document.getElementById('tipoCambioCliente').innerHTML = opciones;
}

async function construirOpcionesClientes() {
    try {
        const res = await fetch('api/usuarios.php?id_rol=3');
        const clientes = await res.json();
        return '<option value="">Cliente...</option>' +
            clientes.map(c => `<option value="${c.id_usuario}">${c.nombre_completo} (${c.correo})</option>`).join('');
    } catch (err) {
        return '<option value="">Error al cargar clientes</option>';
    }
}

// --- Cambiar tipo de cliente (mensual <-> personalizado) ---
document.getElementById('formCambiarTipo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeCambioTipo');
    const idCliente = document.getElementById('tipoCambioCliente').value;

    if (!idCliente) {
        msg.className = 'text-xs mt-2 text-red-600';
        msg.textContent = 'Elige un cliente.';
        return;
    }

    const payload = {
        id_usuario: idCliente,
        tipo_cliente: document.getElementById('tipoCambioNuevoTipo').value,
        tarifa_personalizada_acordada: document.getElementById('tipoCambioTarifa').value || null,
    };

    try {
        await fetch('api/usuarios.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        msg.className = 'text-xs mt-2 text-emerald-600';
        msg.textContent = 'Cliente actualizado.';
        document.getElementById('formCambiarTipo').reset();
        cargarMisClientes();
    } catch (err) {
        msg.className = 'text-xs mt-2 text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

// --- Catálogo de ejercicios (con foto de la máquina) ---
async function cargarCatalogo(idsMarcados = []) {
    const contenedor = document.getElementById('listaEjerciciosCatalogo');
    try {
        const res = await fetch('api/planes.php');
        const catalogo = await res.json();

        if (!catalogo.length) {
            contenedor.innerHTML = '<p class="text-slate-400">No hay máquinas confirmadas todavía. Pide al admin que confirme alguna.</p>';
            return;
        }

        contenedor.innerHTML = catalogo.map(item => {
            const marcado = idsMarcados.includes(String(item.id_ejercicio));
            const foto = item.foto_maquina
                ? `<img src="${item.foto_maquina}" class="w-8 h-8 object-cover rounded-md">`
                : `<span class="w-8 h-8 rounded-md bg-slate-100 flex items-center justify-center">🏋️</span>`;
            return `
                <label class="flex items-center gap-2 py-1">
                    <input type="checkbox" class="chk-ejercicio" data-id-ejercicio="${item.id_ejercicio}" data-id-maquina="${item.id_maquina}" ${marcado ? 'checked' : ''}>
                    ${foto}
                    <span>${item.nombre_ejercicio} <span class="text-slate-400">— ${item.nombre_maquina}</span></span>
                </label>
            `;
        }).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar el catálogo.</p>';
    }
}

// --- Guardar rutina (crear o, si se está editando, reemplazar) ---
document.getElementById('formPlan').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajePlan');

    const idCliente = document.getElementById('planCliente').value;
    const diaSeleccionado = document.getElementById('planDia').value;
    const series = document.getElementById('planSeries').value || null;
    const repeticiones = document.getElementById('planRepeticiones').value || null;

    const ejerciciosSeleccionados = [...document.querySelectorAll('.chk-ejercicio:checked')].map(chk => ({
        id_ejercicio: chk.dataset.idEjercicio,
        id_maquina: chk.dataset.idMaquina,
        dia_semana: diaSeleccionado,
        series,
        repeticiones,
    }));

    if (!idCliente || !ejerciciosSeleccionados.length) {
        msg.className = 'text-xs mt-2 text-red-600';
        msg.textContent = 'Elige un cliente y al menos un ejercicio.';
        return;
    }

    const payload = {
        id_cliente: idCliente,
        id_entrenador: usuario.id_usuario,
        nombre_plan: document.getElementById('planNombre').value || 'Plan personalizado',
        tipo_plan: document.getElementById('planTipo').value,
        ejercicios: ejerciciosSeleccionados,
    };

    const idEditando = document.getElementById('planIdEditando').value;

    try {
        // Si es una edición: borramos la rutina anterior y creamos una nueva con los datos actuales
        if (idEditando) {
            await fetch(`api/planes.php?id_plan=${idEditando}`, { method: 'DELETE' });
        }

        const res = await fetch('api/planes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        msg.className = res.ok ? 'text-xs mt-2 text-emerald-600' : 'text-xs mt-2 text-red-600';
        msg.textContent = res.ok
            ? (idEditando ? 'Rutina actualizada.' : (payload.tipo_plan === 'guia' ? 'Rutina guardada y PDF enviado al cliente.' : 'Rutina guardada.'))
            : (data.error || 'Error al guardar la rutina');

        if (res.ok) {
            cancelarEdicion();
            cargarMisClientes();
            cargarMisRutinas();
        }
    } catch (err) {
        msg.className = 'text-xs mt-2 text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

function cancelarEdicion() {
    document.getElementById('formPlan').reset();
    document.querySelectorAll('.chk-ejercicio').forEach(c => c.checked = false);
    document.getElementById('planIdEditando').value = '';
    document.getElementById('btnGuardarPlan').textContent = 'Guardar rutina';
    document.getElementById('btnCancelarEdicion').classList.add('hidden');
}

document.getElementById('btnCancelarEdicion').addEventListener('click', cancelarEdicion);

// --- Mis rutinas creadas (listar, editar, borrar) ---
async function cargarMisRutinas() {
    const contenedor = document.getElementById('listaRutinas');
    try {
        const res = await fetch(`api/planes.php?id_entrenador=${usuario.id_usuario}`);
        const rutinas = await res.json();

        if (!rutinas.length) {
            contenedor.innerHTML = '<p class="text-slate-400">Aún no has creado ninguna rutina.</p>';
            return;
        }

        contenedor.innerHTML = rutinas.map(r => `
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                <div>
                    <p class="font-semibold text-slate-700">${r.nombre_plan} — ${r.nombre_cliente}</p>
                    <p class="text-xs text-slate-400">${r.tipo_plan === 'personalizado' ? 'Personalizado' : 'Guía'} · ${r.cantidad_ejercicios} ejercicio(s)</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="editarRutina(${r.id_plan})" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">Editar</button>
                    <button onclick="borrarRutina(${r.id_plan})" class="text-red-600 hover:text-red-700 text-sm font-semibold">Eliminar</button>
                </div>
            </div>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar tus rutinas.</p>';
    }
}

async function editarRutina(idPlan) {
    try {
        const res = await fetch(`api/planes.php?id_plan=${idPlan}`);
        const plan = await res.json();

        document.getElementById('planIdEditando').value = idPlan;
        document.getElementById('planCliente').value = plan.id_cliente;
        document.getElementById('planNombre').value = plan.nombre_plan;
        document.getElementById('planTipo').value = plan.tipo_plan;

        const primerEjercicio = plan.ejercicios[0];
        if (primerEjercicio) {
            document.getElementById('planDia').value = primerEjercicio.dia_semana || 'lunes';
            document.getElementById('planSeries').value = primerEjercicio.series || 3;
            document.getElementById('planRepeticiones').value = primerEjercicio.repeticiones || 12;
        }

        const idsEjercicios = plan.ejercicios.map(e => String(e.id_ejercicio));
        await cargarCatalogo(idsEjercicios);

        document.getElementById('btnGuardarPlan').textContent = 'Actualizar rutina';
        document.getElementById('btnCancelarEdicion').classList.remove('hidden');
        window.scrollTo({ top: document.getElementById('formPlan').offsetTop - 20, behavior: 'smooth' });
    } catch (err) {
        alert('Error al cargar la rutina para editar.');
    }
}

async function borrarRutina(idPlan) {
    if (!confirm('¿Seguro que quieres eliminar esta rutina? Esta acción no se puede deshacer.')) return;
    await fetch(`api/planes.php?id_plan=${idPlan}`, { method: 'DELETE' });
    cargarMisRutinas();
}

cargarMisClientes();
cargarClientesParaPlan();
cargarCatalogo();
cargarMisRutinas();
