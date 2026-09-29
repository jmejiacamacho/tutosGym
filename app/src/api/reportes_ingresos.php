<?php
// Endpoint: /api/reportes_ingresos.php
// Panel financiero para el dueño: separa el ingreso del gimnasio (mensualidades)
// del ingreso que cada entrenador personalizado cobra por su cuenta.
// Filtros opcionales: ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');

$pdo = conectarDB();

$desde = $_GET['desde'] ?? date('Y-m-01'); // por defecto, desde el 1° del mes actual
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// --- Ingresos del gimnasio (mensualidades diarias/semanales/mensuales) ---
$stmtGimnasio = $pdo->prepare(
    'SELECT COALESCE(SUM(monto), 0) AS total, COUNT(*) AS cantidad
     FROM pagos
     WHERE DATE(fecha_pago) BETWEEN ? AND ?'
);
$stmtGimnasio->execute([$desde, $hasta]);
$ingresosGimnasio = $stmtGimnasio->fetch();

// --- Ingresos de entrenadores personalizados, desglosado por entrenador ---
// (cualquier entrenador puede tener clientes personalizados; se basa en los pagos reales, no en un "tipo" fijo)
$stmtEntrenadores = $pdo->prepare(
    'SELECT u.id_usuario, u.nombre_completo,
            COALESCE(SUM(pe.monto), 0) AS total,
            COUNT(pe.id_pago_entrenador) AS cantidad_pagos
     FROM usuarios u
     JOIN pagos_entrenador pe
       ON pe.id_entrenador = u.id_usuario
      AND DATE(pe.fecha_pago) BETWEEN ? AND ?
     GROUP BY u.id_usuario, u.nombre_completo
     ORDER BY total DESC'
);
$stmtEntrenadores->execute([$desde, $hasta]);
$ingresosEntrenadores = $stmtEntrenadores->fetchAll();

$totalEntrenadores = array_sum(array_column($ingresosEntrenadores, 'total'));

// --- Ingreso neto por cliente en el periodo (mensualidad del gym + lo que paga aparte a su entrenador) ---
$stmtPorCliente = $pdo->prepare(
    "SELECT u.id_usuario, u.nombre_completo, u.tipo_cliente,
            COALESCE((SELECT SUM(pg.monto) FROM pagos pg
                      JOIN suscripciones s ON pg.id_suscripcion = s.id_suscripcion
                      WHERE s.id_cliente = u.id_usuario AND DATE(pg.fecha_pago) BETWEEN ? AND ?), 0) AS pagado_gimnasio,
            COALESCE((SELECT SUM(pe.monto) FROM pagos_entrenador pe
                      WHERE pe.id_cliente = u.id_usuario AND DATE(pe.fecha_pago) BETWEEN ? AND ?), 0) AS pagado_entrenador
     FROM usuarios u
     WHERE u.id_rol = 3
     HAVING pagado_gimnasio > 0 OR pagado_entrenador > 0
     ORDER BY (pagado_gimnasio + pagado_entrenador) DESC"
);
$stmtPorCliente->execute([$desde, $hasta, $desde, $hasta]);
$ingresosPorCliente = array_map(function ($c) {
    $c['pagado_gimnasio'] = (float) $c['pagado_gimnasio'];
    $c['pagado_entrenador'] = (float) $c['pagado_entrenador'];
    $c['total_neto'] = $c['pagado_gimnasio'] + $c['pagado_entrenador'];
    return $c;
}, $stmtPorCliente->fetchAll());

responderJSON([
    'periodo' => ['desde' => $desde, 'hasta' => $hasta],
    'ingresos_gimnasio' => [
        'total' => (float) $ingresosGimnasio['total'],
        'cantidad_pagos' => (int) $ingresosGimnasio['cantidad'],
    ],
    'ingresos_entrenadores_personalizados' => [
        'total' => (float) $totalEntrenadores,
        'detalle_por_entrenador' => $ingresosEntrenadores,
    ],
    'ingresos_por_cliente' => $ingresosPorCliente,
    'ingreso_total_combinado' => (float) $ingresosGimnasio['total'] + (float) $totalEntrenadores,
]);
