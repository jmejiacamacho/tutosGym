const API_MAQUINAS = 'api/maquinas.php';
const API_SUBIR = 'api/subir_maquina.php';
const API_EJERCICIOS = 'api/ejercicios.php';
const API_MAQUINA_EJERCICIO = 'api/maquina_ejercicio.php';

let todasLasMaquinas = [];
let todosLosEjercicios = [];
let idMaquinaPendiente = null;

function renderGrid() {
    const contenedor = document.getElementById('gridMaquinas');
    const busqueda = document.getElementById('buscarMaquina').value.toLowerCase();
    const filtradas = todasLasMaquinas.filter(m => m.nombre.toLowerCase().includes(busqueda));

    document.getElementById('contadorMaquinas').textContent = `Total: ${todasLasMaquinas.length} equipos`;

    if (!filtradas.length) {
        contenedor.innerHTML = '<p class="text-slate-400 col-span-4">No hay máquinas que coincidan. Registra la primera con el botón de arriba.</p>';
        return;
    }

    contenedor.innerHTML = filtradas.map(m => {
        const confirmada = m.confirmada_por_admin == 1;
        const activa = m.estado === 'activa';
        let estadoTexto, estadoColor, puntoColor;
        if (!confirmada) {
            estadoTexto = 'Pendiente de revisión';
            estadoColor = 'text-amber-600';
            puntoColor = 'bg-amber-500';
        } else if (activa) {
            estadoTexto = 'Operativa';
            estadoColor = 'text-emerald-600';
            puntoColor = 'bg-emerald-500';
        } else {
            estadoTexto = m.estado === 'mantenimiento' ? 'Mantenimiento' : 'Fuera de línea';
            estadoColor = m.estado === 'mantenimiento' ? 'text-amber-600' : 'text-rose-600';
            puntoColor = m.estado === 'mantenimiento' ? 'bg-amber-500' : 'bg-rose-500';
        }

        const foto = m.foto
            ? `<img src="${m.foto}" class="w-full h-32 object-cover rounded-xl mb-4" alt="${m.nombre}">`
            : `<div class="w-full h-32 bg-slate-100 rounded-xl mb-4 flex items-center justify-center text-slate-400 font-bold">🏋️ Sin foto</div>`;

        const nombreEscapado = m.nombre.replace(/'/g, "\\'");
        const tipoEscapado = (m.tipo || '').replace(/'/g, "\\'");

        return `
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between hover:shadow-md transition">
                <div>
                    ${foto}
                    <h3 class="font-bold text-slate-800">${m.nombre}</h3>
                    <p class="text-xs text-slate-400 mb-3">${m.tipo || 'Sin categoría'}</p>
                </div>
                <div class="flex justify-between items-center pt-3 border-t border-slate-100">
                    <span class="flex items-center gap-1.5 text-xs font-bold ${estadoColor}"><span class="w-2 h-2 rounded-full ${puntoColor}"></span> ${estadoTexto}</span>
                    <div class="flex gap-3">
                        <button onclick="abrirPanelMaquina(${m.id_maquina}, '${nombreEscapado}', '${tipoEscapado}', ${!confirmada})" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">
                            ${!confirmada ? 'Confirmar' : 'Ejercicios'}
                        </button>
                        <button onclick="borrarMaquina(${m.id_maquina})" class="text-red-500 hover:text-red-700 text-sm font-semibold">Borrar</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function cargarMaquinas() {
    try {
        const res = await fetch(API_MAQUINAS);
        todasLasMaquinas = await res.json();
        renderGrid();
    } catch (err) {
        document.getElementById('gridMaquinas').innerHTML = '<p class="text-red-600 col-span-4">Error al cargar las máquinas.</p>';
    }
}

document.getElementById('buscarMaquina').addEventListener('input', renderGrid);

document.getElementById('btnNuevaMaquina').addEventListener('click', () => {
    document.getElementById('formSubirMaquina').classList.toggle('hidden');
});

document.getElementById('formSubirMaquina').addEventListener('submit', async (e) => {
    e.preventDefault();
    const archivo = document.getElementById('inputFotoMaquina').files[0];
    if (!archivo) return;

    const msg = document.getElementById('mensajeSubida');
    const formData = new FormData();
    formData.append('foto', archivo);

    msg.className = 'text-xs w-full text-slate-500';
    msg.textContent = 'Analizando con IA...';

    try {
        const res = await fetch(API_SUBIR, { method: 'POST', body: formData });
        const data = await res.json();

        if (!res.ok) {
            msg.className = 'text-xs w-full text-red-600';
            msg.textContent = data.error || 'Error al subir la foto';
            return;
        }

        msg.textContent = '';
        document.getElementById('formSubirMaquina').reset();
        document.getElementById('formSubirMaquina').classList.add('hidden');
        cargarMaquinas();

        // Abre de una vez el panel para confirmar nombre/categoría/ejercicios
        document.getElementById('etiquetaSugerida').textContent = data.etiqueta_sugerida || 'No identificada';
        document.getElementById('confianzaSugerida').textContent = data.confianza || 0;
        await abrirPanelMaquina(data.id_maquina, data.etiqueta_sugerida || '', '', true);
    } catch (err) {
        msg.className = 'text-xs w-full text-red-600';
        msg.textContent = 'Error de conexión con el servidor';
    }
});

// --- Panel unificado: confirmar máquina nueva o editar ejercicios de una ya existente ---
async function abrirPanelMaquina(id, nombreActual, tipoActual, esNueva) {
    idMaquinaPendiente = id;

    document.getElementById('nombreConfirmar').value = nombreActual || '';
    document.getElementById('categoriaConfirmar').value = tipoActual || '';
    document.getElementById('textoSugerenciaIA').classList.toggle('hidden', !esNueva);

    if (!esNueva) {
        // Al editar una ya confirmada, no reescribimos la sugerencia de IA
        document.getElementById('etiquetaSugerida').textContent = '';
        document.getElementById('confianzaSugerida').textContent = '';
    }

    let idsMarcados = [];
    try {
        const res = await fetch(`${API_MAQUINA_EJERCICIO}?id_maquina=${id}`);
        idsMarcados = (await res.json()).map(String);
    } catch (err) {
        console.error(err);
    }

    renderChecklistEjercicios(idsMarcados);
    document.getElementById('tarjetaConfirmacion').classList.remove('hidden');
    document.getElementById('tarjetaConfirmacion').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function renderChecklistEjercicios(idsMarcados = []) {
    const contenedor = document.getElementById('checklistEjercicios');

    if (!todosLosEjercicios.length) {
        contenedor.innerHTML = '<p class="text-slate-400 text-sm">No hay ejercicios en el catálogo todavía. Agrega uno abajo.</p>';
        return;
    }

    contenedor.innerHTML = todosLosEjercicios.map(e => `
        <label class="flex items-center gap-2 py-1 text-sm">
            <input type="checkbox" class="chk-ejercicio-maquina" value="${e.id_ejercicio}" ${idsMarcados.includes(String(e.id_ejercicio)) ? 'checked' : ''}>
            ${e.nombre}
        </label>
    `).join('');
}

async function cargarTodosLosEjercicios() {
    try {
        const res = await fetch(API_EJERCICIOS);
        todosLosEjercicios = await res.json();
    } catch (err) {
        console.error(err);
    }
}

document.getElementById('btnAgregarEjercicioNuevo').addEventListener('click', async () => {
    const input = document.getElementById('nuevoEjercicioNombre');
    const nombre = input.value.trim();
    if (!nombre) return;

    try {
        const res = await fetch(API_EJERCICIOS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre }),
        });
        const data = await res.json();

        // Marca los ejercicios ya chequeados antes de re-renderizar, y le suma el nuevo (marcado)
        const marcadosActuales = [...document.querySelectorAll('.chk-ejercicio-maquina:checked')].map(c => c.value);
        await cargarTodosLosEjercicios();
        renderChecklistEjercicios([...marcadosActuales, String(data.id_ejercicio)]);

        input.value = '';
    } catch (err) {
        alert('Error al agregar el ejercicio');
    }
});

document.getElementById('btnConfirmarMaquina').addEventListener('click', () => confirmarMaquina(
    idMaquinaPendiente,
    document.getElementById('nombreConfirmar').value,
    document.getElementById('categoriaConfirmar').value
));

async function confirmarMaquina(id, nombre, tipo) {
    if (!id || !nombre) return;

    const idsEjercicios = [...document.querySelectorAll('.chk-ejercicio-maquina:checked')].map(c => c.value);

    await fetch(API_MAQUINAS, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_maquina: id, nombre, tipo, confirmada_por_admin: true, estado: 'activa' }),
    });

    await fetch(API_MAQUINA_EJERCICIO, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_maquina: id, ids_ejercicios: idsEjercicios }),
    });

    document.getElementById('tarjetaConfirmacion').classList.add('hidden');
    idMaquinaPendiente = null;
    cargarMaquinas();
}

async function borrarMaquina(id) {
    if (!confirm('¿Seguro que quieres eliminar esta máquina? Esta acción no se puede deshacer.')) return;
    await fetch(`${API_MAQUINAS}?id=${id}`, { method: 'DELETE' });
    cargarMaquinas();
}

async function cargarCategoriasEnSelect() {
    const select = document.getElementById('categoriaConfirmar');
    try {
        const res = await fetch('api/categorias.php');
        const categorias = await res.json();
        select.innerHTML = '<option value="">Categoría...</option>' +
            categorias.map(c => `<option value="${c.nombre}">${c.nombre}</option>`).join('');
    } catch (err) {
        console.error(err);
    }
}

cargarMaquinas();
cargarCategoriasEnSelect();
cargarTodosLosEjercicios();
