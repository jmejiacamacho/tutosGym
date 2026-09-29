const API_MAQUINAS = 'api/maquinas.php';
const API_SUBIR = 'api/subir_maquina.php';

let idMaquinaPendiente = null;

document.getElementById('formSubida').addEventListener('submit', async (e) => {
    e.preventDefault();
    const archivo = document.getElementById('inputFoto').files[0];
    if (!archivo) return;

    const formData = new FormData();
    formData.append('foto', archivo);

    const boton = e.target.querySelector('button');
    boton.disabled = true;
    boton.textContent = 'Analizando...';

    try {
        const res = await fetch(API_SUBIR, { method: 'POST', body: formData });
        const data = await res.json();

        if (!res.ok) {
            alert(data.error || 'Error al subir la foto');
            return;
        }

        idMaquinaPendiente = data.id_maquina;
        document.getElementById('etiquetaSugerida').textContent = data.etiqueta_sugerida || 'No identificada';
        document.getElementById('confianzaSugerida').textContent = data.confianza || 0;
        document.getElementById('nombreFinal').value = data.etiqueta_sugerida || '';
        document.getElementById('resultadoSubida').classList.remove('oculto');
    } catch (err) {
        alert('Error de conexión con el servidor');
        console.error(err);
    } finally {
        boton.disabled = false;
        boton.textContent = 'Subir y analizar';
    }
});

document.getElementById('btnConfirmar').addEventListener('click', async () => {
    const nombreFinal = document.getElementById('nombreFinal').value.trim();
    if (!nombreFinal || !idMaquinaPendiente) return;

    await fetch(API_MAQUINAS, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_maquina: idMaquinaPendiente,
            nombre: nombreFinal,
            confirmada_por_admin: true,
            estado: 'activa',
        }),
    });

    document.getElementById('resultadoSubida').classList.add('oculto');
    document.getElementById('formSubida').reset();
    cargarMaquinas();
});

async function cargarMaquinas() {
    const contenedor = document.getElementById('listaMaquinas');
    try {
        const res = await fetch(API_MAQUINAS);
        const maquinas = await res.json();

        if (!maquinas.length) {
            contenedor.innerHTML = '<p>Aún no hay máquinas registradas.</p>';
            return;
        }

        contenedor.innerHTML = maquinas.map(m => `
            <div class="item-maquina">
                <span>${m.nombre}</span>
                <span class="badge ${m.confirmada_por_admin == 1 ? 'confirmada' : ''}">
                    ${m.confirmada_por_admin == 1 ? 'Confirmada' : 'Pendiente de revisión'}
                </span>
            </div>
        `).join('');
    } catch (err) {
        contenedor.innerHTML = '<p>Error al cargar las máquinas.</p>';
    }
}

cargarMaquinas();
