const API_SUSCRIPCIONES = 'api/suscripciones.php';
const API_USUARIOS = 'api/usuarios.php';

let todasLasSuscripciones = [];

function formatearFecha(f) {
    const [y, m, d] = f.split('-');
    return `${d}/${m}/${y}`;
}

function estadoVisual(fechaVencimiento, estadoBD) {
    if (estadoBD === 'cancelada') return { texto: 'Cancelada', clase: 'bg-slate-100 text-slate-500' };
    const dias = Math.ceil((new Date(fechaVencimiento) - new Date()) / (1000 * 60 * 60 * 24));
    if (dias < 0) return { texto: 'Vencido', clase: 'bg-red-50 text-red-600' };
    if (dias <= 2) return { texto: 'Por vencer', clase: 'bg-amber-50 text-amber-600' };
    return { texto: 'Activo', clase: 'bg-emerald-50 text-emerald-600' };
}

function renderTabla() {
    const tbody = document.getElementById('tablaSuscripciones');
    const busqueda = document.getElementById('buscarSuscripcion').value.toLowerCase();
    const filtro = document.getElementById('filtroEstado').value;

    let filas = todasLasSuscripciones.filter(s => s.nombre_completo.toLowerCase().includes(busqueda));
    if (filtro) {
        filas = filas.filter(s => {
            const dias = Math.ceil((new Date(s.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24));
            const esVencida = dias < 0;
            return filtro === 'vencida' ? esVencida : !esVencida;
        });
    }

    if (!filas.length) {
        tbody.innerHTML = '<tr><td class="p-4 text-slate-400" colspan="4">No hay suscripciones que coincidan.</td></tr>';
        return;
    }

    tbody.innerHTML = filas.map(s => {
        const estado = estadoVisual(s.fecha_vencimiento, s.estado);
        const iniciales = s.nombre_completo.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
        return `
            <tr class="hover:bg-slate-50/50 transition">
                <td class="p-4 font-semibold flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">${iniciales}</div>
                    ${s.nombre_completo}
                </td>
                <td class="p-4 text-slate-500">${s.tipo_nombre}</td>
                <td class="p-4 text-slate-500">${formatearFecha(s.fecha_vencimiento)}</td>
                <td class="p-4"><span class="px-3 py-1 rounded-full text-xs font-bold ${estado.clase}">${estado.texto}</span></td>
            </tr>
        `;
    }).join('');
}

async function cargarSuscripciones() {
    try {
        const res = await fetch(API_SUSCRIPCIONES);
        todasLasSuscripciones = await res.json();
        renderTabla();
    } catch (err) {
        document.getElementById('tablaSuscripciones').innerHTML = '<tr><td class="p-4 text-red-600" colspan="4">Error al cargar.</td></tr>';
    }
}

async function cargarClientesParaSelect() {
    const select = document.getElementById('nuevoCliente');
    try {
        const res = await fetch(`${API_USUARIOS}?id_rol=3`);
        const clientes = await res.json();
        select.innerHTML = '<option value="">Cliente...</option>' +
            clientes.map(c => `<option value="${c.id_usuario}">${c.nombre_completo}</option>`).join('');
    } catch (err) {
        select.innerHTML = '<option value="">Error al cargar</option>';
    }
}

document.getElementById('buscarSuscripcion').addEventListener('input', renderTabla);
document.getElementById('filtroEstado').addEventListener('change', renderTabla);

document.getElementById('btnNuevaSuscripcion').addEventListener('click', () => {
    document.getElementById('formNuevaSuscripcion').classList.toggle('hidden');
});

document.getElementById('formNuevaSuscripcion').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mensajeNuevaSuscripcion');

    const payload = {
        id_cliente: document.getElementById('nuevoCliente').value,
        id_tipo: document.getElementById('nuevoTipo').value,
        metodo_pago: document.getElementById('nuevoMetodoPago').value,
    };

    try {
        const res = await fetch(API_SUSCRIPCIONES, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        msg.className = res.ok ? 'text-xs md:col-span-4 text-emerald-600' : 'text-xs md:col-span-4 text-red-600';
        msg.textContent = res.ok ? `Suscripción creada, vence el ${data.fecha_vencimiento}.` : (data.error || 'Error al crear');

        if (res.ok) {
            document.getElementById('formNuevaSuscripcion').reset();
            document.getElementById('formNuevaSuscripcion').classList.add('hidden');
            cargarSuscripciones();
        }
    } catch (err) {
        msg.className = 'text-xs md:col-span-4 text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

cargarSuscripciones();
cargarClientesParaSelect();
