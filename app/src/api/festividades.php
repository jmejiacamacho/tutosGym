<?php
// Endpoint: /api/festividades.php
// Fechas con horario especial o cierre total (festivos, días especiales del gimnasio).
// GET: lista todas (ordenadas por fecha). POST: agrega una. DELETE: quita una por id.

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM festividades_horario ORDER BY fecha ASC');
    responderJSON($stmt->fetchAll());

} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fecha = $input['fecha'] ?? null;

    if (!$fecha) {
        responderJSON(['error' => 'fecha es obligatoria'], 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO festividades_horario (fecha, nombre, cerrado, horario_apertura, horario_cierre)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), cerrado = VALUES(cerrado),
             horario_apertura = VALUES(horario_apertura), horario_cierre = VALUES(horario_cierre)'
    );
    $stmt->execute([
        $fecha,
        $input['nombre'] ?? null,
        !empty($input['cerrado']) ? 1 : 0,
        !empty($input['cerrado']) ? null : ($input['horario_apertura'] ?? null),
        !empty($input['cerrado']) ? null : ($input['horario_cierre'] ?? null),
    ]);

    responderJSON(['mensaje' => 'Festividad guardada'], 201);

} elseif ($metodo === 'DELETE') {
    parse_str(file_get_contents('php://input'), $inputDelete);
    $id = $_GET['id'] ?? $inputDelete['id'] ?? null;

    if (!$id) {
        responderJSON(['error' => 'id es obligatorio'], 400);
    }

    $pdo->prepare('DELETE FROM festividades_horario WHERE id_festividad = ?')->execute([$id]);
    responderJSON(['mensaje' => 'Festividad eliminada']);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
