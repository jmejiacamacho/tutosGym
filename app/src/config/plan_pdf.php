<?php
// Función reutilizable: genera el PDF de un plan y lo envía por correo al cliente.
// La usan tanto api/planes.php (envío automático si el plan es "guía")
// como api/enviar_plan_pdf.php (para reenvíos manuales).

require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;

function generarYEnviarPlanPDF(PDO $pdo, int $idPlan): bool {

    $stmt = $pdo->prepare(
        'SELECT p.*, u.nombre_completo, u.correo
         FROM planes p
         JOIN usuarios u ON p.id_cliente = u.id_usuario
         WHERE p.id_plan = ?'
    );
    $stmt->execute([$idPlan]);
    $plan = $stmt->fetch();

    if (!$plan) {
        return false;
    }

    $stmtEj = $pdo->prepare(
        'SELECT pe.*, e.nombre AS nombre_ejercicio, m.nombre AS nombre_maquina
         FROM plan_ejercicio pe
         JOIN ejercicios e ON pe.id_ejercicio = e.id_ejercicio
         LEFT JOIN maquinas m ON pe.id_maquina = m.id_maquina
         WHERE pe.id_plan = ?
         ORDER BY FIELD(pe.dia_semana, "lunes","martes","miercoles","jueves","viernes","sabado","domingo")'
    );
    $stmtEj->execute([$idPlan]);
    $ejercicios = $stmtEj->fetchAll();

    $nombreGimnasio = 'Tutos Gym Club';
    try {
        $cfg = $pdo->query('SELECT nombre_establecimiento FROM configuracion_gimnasio WHERE id = 1')->fetch();
        if (!empty($cfg['nombre_establecimiento'])) {
            $nombreGimnasio = $cfg['nombre_establecimiento'];
        }
    } catch (\Throwable $e) {
        // sin configuración: se usa el nombre por defecto
    }

    $filasTabla = '';
    foreach ($ejercicios as $ej) {
        $filasTabla .= '<tr>
            <td>' . htmlspecialchars(ucfirst($ej['dia_semana'] ?? '-')) . '</td>
            <td>' . htmlspecialchars($ej['nombre_ejercicio']) . '</td>
            <td>' . htmlspecialchars($ej['nombre_maquina'] ?? '-') . '</td>
            <td>' . htmlspecialchars($ej['series'] ?? '-') . '</td>
            <td>' . htmlspecialchars($ej['repeticiones'] ?? '-') . '</td>
        </tr>';
    }

    $html = '
    <html>
    <head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { color: #dc2626; margin-bottom: 4px; } .marca { color: #111; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #fdecec; }
    </style>
    </head>
    <body>
        <p class="marca">' . htmlspecialchars($nombreGimnasio) . '</p>
        <h1>Plan de entrenamiento</h1>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($plan['nombre_completo']) . '</p>
        <p><strong>Plan:</strong> ' . htmlspecialchars($plan['nombre_plan']) . '</p>
        <p><strong>Objetivo:</strong> ' . htmlspecialchars($plan['objetivo'] ?? '-') . '</p>
        <table>
            <tr><th>Día</th><th>Ejercicio</th><th>Máquina</th><th>Series</th><th>Repeticiones</th></tr>
            ' . $filasTabla . '
        </table>
    </body>
    </html>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $directorioPDF = __DIR__ . '/../uploads/planes/';
    if (!is_dir($directorioPDF)) {
        mkdir($directorioPDF, 0755, true);
    }
    $rutaPDF = $directorioPDF . "plan_{$idPlan}_" . uniqid() . '.pdf';
    file_put_contents($rutaPDF, $dompdf->output());

    return enviarCorreoConAdjunto(
        $plan['correo'],
        'Tu plan de entrenamiento — ' . $plan['nombre_plan'],
        "Hola {$plan['nombre_completo']}, adjunto encontrarás tu plan de entrenamiento. ¡Éxitos!",
        $rutaPDF,
        'plan_entrenamiento.pdf'
    );
}
