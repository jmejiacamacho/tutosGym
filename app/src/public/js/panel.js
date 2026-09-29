// La sesión, el nombre/iniciales del header y el botón "Salir" los maneja
// public/js/panel-common.js (incluido antes que este archivo en index.html).

function formatearMoneda(valor) {
    return '$' + Number(valor).toLocaleString('es-CO');
}

function badgeEstado(fechaVencimiento) {
    const hoy = new Date();
    const vencimiento = new Date(fechaVencimiento);
    const dias = Math.ceil((vencimiento - hoy) / (1000 * 60 * 60 * 24));

    if (dias < 0) return { texto: 'Vencida', clase: 'bg-red-50 text-red-600' };
    if (dias <= 2) return { texto: 'Por vencer', clase: 'bg-amber-50 text-amber-600' };
    return { texto: 'Activo', clase: 'bg-emerald-50 text-emerald-600' };
}

// --- Ingresos del mes ---
async function cargarIngresos() {
    try {
        const res = await fetch('api/reportes_ingresos.php');
        const data = await res.json();
        document.getElementById('kpiIngresos').textContent = formatearMoneda(data.ingreso_total_combinado);
    } catch (err) {
        document.getElementById('kpiIngresos').textContent = 'Error';
    }
}

// --- Suscripciones activas + resumen de las 3 más próximas a vencer ---
async function cargarSuscripciones() {
    const contenedor = document.getElementById('resumenSuscripciones');
    try {
        const res = await fetch('api/suscripciones.php');
        const suscripciones = await res.json();
        const activas = suscripciones.filter(s => s.estado === 'activa');

        document.getElementById('kpiMiembros').textContent = activas.length;

        if (!activas.length) {
            contenedor.innerHTML = '<p class="text-sm text-slate-400">No hay suscripciones activas.</p>';
            return;
        }

        const proximas = [...activas]
            .sort((a, b) => new Date(a.fecha_vencimiento) - new Date(b.fecha_vencimiento))
            .slice(0, 4);

        contenedor.innerHTML = proximas.map(s => {
            const estado = badgeEstado(s.fecha_vencimiento);
            return `
                <div class="flex justify-between items-center p-3 bg-slate-50 rounded-xl text-sm">
                    <span class="font-semibold text-slate-700">${s.nombre_completo}</span>
                    <span class="text-slate-500 text-xs">${s.tipo_nombre}</span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold ${estado.clase}">${estado.texto}</span>
                </div>
            `;
        }).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-sm text-red-600">Error al cargar suscripciones.</p>';
        document.getElementById('kpiMiembros').textContent = '--';
    }
}

// --- Máquinas: KPI + estado ---
async function cargarMaquinas() {
    const contenedor = document.getElementById('listaMaquinasResumen');
    try {
        const res = await fetch('api/maquinas.php');
        const maquinas = await res.json();

        const total = maquinas.length;
        const confirmadas = maquinas.filter(m => m.confirmada_por_admin == 1).length;
        const porcentaje = total ? Math.round((confirmadas / total) * 100) : 0;

        document.getElementById('kpiMaquinasFraccion').textContent = `${confirmadas}/${total}`;
        document.getElementById('kpiMaquinasPorcentaje').textContent = `${porcentaje}%`;
        document.getElementById('barraMaquinas').style.width = `${porcentaje}%`;

        if (!total) {
            contenedor.innerHTML = '<p class="text-sm text-slate-400 col-span-3">No hay máquinas registradas todavía.</p>';
            return;
        }

        contenedor.innerHTML = maquinas.slice(0, 3).map(m => {
            const confirmada = m.confirmada_por_admin == 1;
            const activa = m.estado === 'activa';
            const estadoTexto = !confirmada ? 'Pendiente revisión' : (activa ? 'Operativa' : 'Mantenimiento');
            const colorClase = !confirmada ? 'text-slate-500' : (activa ? 'text-emerald-600' : 'text-amber-600');
            const puntoClase = !confirmada ? 'bg-slate-400' : (activa ? 'bg-emerald-500' : 'bg-amber-500');

            return `
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex gap-3 items-center">
                    <div class="w-16 h-16 bg-slate-200 rounded-lg flex items-center justify-center text-slate-400 font-bold text-xs">🏋️</div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">${m.nombre}</h4>
                        <span class="flex items-center gap-1.5 text-xs font-bold ${colorClase} mt-1">
                            <span class="w-2 h-2 rounded-full ${puntoClase}"></span> ${estadoTexto}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    } catch (err) {
        contenedor.innerHTML = '<p class="text-sm text-red-600 col-span-3">Error al cargar las máquinas.</p>';
    }
}

// --- Reportes rápidos (resumen de texto, sin gráfica todavía) ---
async function cargarReportes() {
    const contenedor = document.getElementById('resumenReportes');
    try {
        const res = await fetch('api/reportes_ingresos.php');
        const data = await res.json();
        contenedor.innerHTML = `
            <div class="flex justify-between"><span>Ingresos del gimnasio</span><strong>${formatearMoneda(data.ingresos_gimnasio.total)}</strong></div>
            <div class="flex justify-between"><span>Ingresos entrenadores personalizados</span><strong>${formatearMoneda(data.ingresos_entrenadores_personalizados.total)}</strong></div>
            <div class="flex justify-between border-t border-slate-100 pt-2 mt-2"><span class="font-bold">Total combinado</span><strong>${formatearMoneda(data.ingreso_total_combinado)}</strong></div>
        `;
    } catch (err) {
        contenedor.innerHTML = '<p class="text-red-600">Error al cargar reportes.</p>';
    }
}

cargarIngresos();
cargarSuscripciones();
cargarMaquinas();
cargarReportes();
