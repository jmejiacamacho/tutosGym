const usuario = JSON.parse(sessionStorage.getItem('usuario') || 'null');

if (!usuario) {
    window.location.href = 'login.html';
} else if (usuario.id_rol != 3) {
    const destinoPorRol = { 1: 'index.html', 2: 'entrenador.html' };
    window.location.href = destinoPorRol[usuario.id_rol] || 'login.html';
}

document.getElementById('nombreCliente').textContent = usuario.nombre_completo;
document.getElementById('btnSalir').addEventListener('click', () => {
    sessionStorage.removeItem('usuario');
    window.location.href = 'login.html';
});

function badgeEstado(fechaVencimiento) {
    const hoy = new Date();
    const vencimiento = new Date(fechaVencimiento);
    const dias = Math.ceil((vencimiento - hoy) / (1000 * 60 * 60 * 24));

    if (dias < 0) return { texto: 'Vencida', clase: 'bg-red-100 text-red-700' };
    if (dias <= 2) return { texto: `Vence en ${dias} día(s)`, clase: 'bg-amber-100 text-amber-700' };
    return { texto: 'Activa', clase: 'bg-emerald-100 text-emerald-700' };
}

async function cargarSuscripcion() {
    const contenedor = document.getElementById('tarjetaSuscripcion');
    try {
        const res = await fetch(`api/suscripciones.php?id_cliente=${usuario.id_usuario}`);
        const suscripciones = await res.json();
        const activa = suscripciones.find(s => s.estado === 'activa');

        if (!activa) {
            contenedor.innerHTML = '<p class="text-sm text-slate-500">No tienes una suscripción activa. Habla con recepción.</p>';
            return;
        }

        const estado = badgeEstado(activa.fecha_vencimiento);
        contenedor.innerHTML = `
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-bold text-slate-800">${activa.tipo_nombre}</p>
                    <p class="text-sm text-slate-500">Del ${activa.fecha_inicio} al ${activa.fecha_vencimiento}</p>
                </div>
                <span class="text-xs font-bold px-3 py-1 rounded-full ${estado.clase}">${estado.texto}</span>
            </div>
        `;
    } catch (err) {
        contenedor.innerHTML = '<p class="text-sm text-red-600">Error al cargar tu suscripción.</p>';
    }
}

async function cargarPlan() {
    const contenedor = document.getElementById('contenidoPlan');
    try {
        const res = await fetch(`api/planes.php?id_cliente=${usuario.id_usuario}`);
        const planes = await res.json();

        if (!planes.length) {
            contenedor.innerHTML = '<p class="text-sm text-slate-500">Aún no tienes un plan asignado. Habla con tu entrenador.</p>';
            return;
        }

        contenedor.innerHTML = planes.map(plan => {
            if (plan.tipo_plan === 'personalizado') {
                return `
                    <div class="mb-4">
                        <p class="font-bold text-slate-800">${plan.nombre_plan}</p>
                        <p class="text-sm text-slate-500 mt-1">🧑‍🏫 Plan personalizado — tu entrenador te acompaña en cada sesión.</p>
                    </div>
                `;
            }

            const filas = plan.ejercicios.map(ej => `
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="py-2 pr-4">
                        ${ej.foto_maquina
                            ? `<img src="${ej.foto_maquina}" class="w-12 h-12 object-cover rounded-lg">`
                            : `<div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">🏋️</div>`}
                    </td>
                    <td class="py-2 pr-4 capitalize">${ej.dia_semana || '-'}</td>
                    <td class="py-2 pr-4">${ej.nombre_ejercicio}</td>
                    <td class="py-2 pr-4">${ej.nombre_maquina || '-'}</td>
                    <td class="py-2 pr-4">${ej.series || '-'}</td>
                    <td class="py-2">${ej.repeticiones || '-'}</td>
                </tr>
            `).join('');

            return `
                <div class="mb-4">
                    <p class="font-bold text-slate-800 mb-2">${plan.nombre_plan}</p>
                    <div class="overflow-x-auto -mx-2 px-2"><table class="w-full min-w-[560px] text-sm text-left whitespace-nowrap">
                        <thead>
                            <tr class="text-xs uppercase text-slate-400 border-b border-slate-200">
                                <th class="py-2 pr-4"></th>
                                <th class="py-2 pr-4">Día</th>
                                <th class="py-2 pr-4">Ejercicio</th>
                                <th class="py-2 pr-4">Máquina</th>
                                <th class="py-2 pr-4">Series</th>
                                <th class="py-2">Repeticiones</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                    </table></div>
                </div>
            `;
        }).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-sm text-red-600">Error al cargar tu plan.</p>';
    }
}

cargarSuscripcion();
cargarPlan();
