<?php
// Endpoint: /api/configuracion.php
// GET: devuelve la configuración general del gimnasio (fila única, id=1).
// POST: actualiza esos valores.

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $config = $pdo->query('SELECT * FROM configuracion_gimnasio WHERE id = 1')->fetch();
    responderJSON($config ?: []);

} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare(
        'UPDATE configuracion_gimnasio SET
            nombre_establecimiento = ?,
            telefono_soporte = ?,
            direccion = ?,
            horario_apertura_semana = ?,
            horario_cierre_semana = ?,
            horario_apertura_finde = ?,
            horario_cierre_finde = ?,
            alertas_correo_activas = ?,
            mantenimiento_automatico_activo = ?
         WHERE id = 1'
    );
    $stmt->execute([
        $input['nombre_establecimiento'] ?? 'Mi Gimnasio',
        $input['telefono_soporte'] ?? null,
        $input['direccion'] ?? null,
        $input['horario_apertura_semana'] ?? '06:00',
        $input['horario_cierre_semana'] ?? '22:00',
        $input['horario_apertura_finde'] ?? '08:00',
        $input['horario_cierre_finde'] ?? '14:00',
        !empty($input['alertas_correo_activas']) ? 1 : 0,
        !empty($input['mantenimiento_automatico_activo']) ? 1 : 0,
    ]);

    responderJSON(['mensaje' => 'Configuración actualizada']);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
