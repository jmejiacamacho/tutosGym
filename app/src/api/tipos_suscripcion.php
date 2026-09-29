<?php
// Endpoint: /api/tipos_suscripcion.php
// GET: lista los tipos de suscripción (diario/semanal/mensual) con su precio actual.
// PUT: actualiza el precio de un tipo específico (pueden variar de un año a otro).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM tipos_suscripcion ORDER BY duracion_dias ASC');
    responderJSON($stmt->fetchAll());

} elseif ($metodo === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $idTipo = $input['id_tipo'] ?? null;
    $precio = $input['precio'] ?? null;

    if (!$idTipo || $precio === null) {
        responderJSON(['error' => 'id_tipo y precio son obligatorios'], 400);
    }

    $pdo->prepare('UPDATE tipos_suscripcion SET precio = ? WHERE id_tipo = ?')->execute([$precio, $idTipo]);
    responderJSON(['mensaje' => 'Precio actualizado']);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
