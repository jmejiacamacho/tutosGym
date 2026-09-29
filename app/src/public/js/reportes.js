function formatearMoneda(valor) {
    return '$' + Number(valor).toLocaleString('es-CO');
}

async function cargarKPIs() {
    try {
        const [resIngresos, resSuscripciones, resMaquinas] = await Promise.all([
            fetch('api/reportes_ingresos.php'),
            fetch('api/suscripciones.php'),
            fetch('api/maquinas.php'),
        ]);
        const ingresos = await resIngresos.json();
        const suscripciones = await resSuscripciones.json();
        const maquinas = await resMaquinas.json();

        document.getElementById('kpiIngresosTotales').textContent = formatearMoneda(ingresos.ingreso_total_combinado);

        const activas = suscripciones.filter(s => s.estado === 'activa').length;
        document.getElementById('kpiMiembrosActivos').textContent = activas;

        const totalMaquinas = maquinas.length;
        const confirmadas = maquinas.filter(m => m.confirmada_por_admin == 1).length;
        const porcentaje = totalMaquinas ? Math.round((confirmadas / totalMaquinas) * 100) : 0;
        document.getElementById('kpiMaquinasReporte').textContent = `${confirmadas}/${totalMaquinas}`;
        document.getElementById('barraMaquinasReporte').style.width = `${porcentaje}%`;

        // Desglose de ingresos
        const contenedor = document.getElementById('desgloseIngresos');
        let html = `
            <div class="flex justify-between p-3 bg-slate-50 rounded-lg">
                <span>Mensualidades del gimnasio</span>
                <strong>${formatearMoneda(ingresos.ingresos_gimnasio.total)} (${ingresos.ingresos_gimnasio.cantidad_pagos} pagos)</strong>
            </div>
        `;

        const detalle = ingresos.ingresos_entrenadores_personalizados.detalle_por_entrenador;
        if (detalle.length) {
            html += detalle.map(e => `
                <div class="flex justify-between p-3 bg-slate-50 rounded-lg">
                    <span>Entrenador personalizado — ${e.nombre_completo}</span>
                    <strong>${formatearMoneda(e.total)} (${e.cantidad_pagos} pagos)</strong>
                </div>
            `).join('');
        }

        html += `
            <div class="flex justify-between p-3 bg-blue-50 rounded-lg border border-blue-100 font-bold">
                <span>Total combinado</span>
                <span>${formatearMoneda(ingresos.ingreso_total_combinado)}</span>
            </div>
        `;
        contenedor.innerHTML = html;

        // Tabla de ingreso neto por cliente
        const tbody = document.getElementById('tablaIngresosPorCliente');
        if (!ingresos.ingresos_por_cliente.length) {
            tbody.innerHTML = '<tr><td class="py-2 text-slate-400" colspan="4">No hay pagos registrados en este periodo.</td></tr>';
        } else {
            tbody.innerHTML = ingresos.ingresos_por_cliente.map(c => `
                <tr class="border-b border-slate-100">
                    <td class="py-2 pr-4 font-medium text-slate-700">${c.nombre_completo}
                        ${c.tipo_cliente === 'personalizado' ? '<span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700">Personalizado</span>' : ''}
                    </td>
                    <td class="py-2 pr-4">${formatearMoneda(c.pagado_gimnasio)}</td>
                    <td class="py-2 pr-4">${formatearMoneda(c.pagado_entrenador)}</td>
                    <td class="py-2 font-bold">${formatearMoneda(c.total_neto)}</td>
                </tr>
            `).join('');
        }

    } catch (err) {
        console.error(err);
    }
}

cargarKPIs();
