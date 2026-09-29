<?php
// Endpoint: /api/ejercicios.php
// GET: lista todos los ejercicios del catálogo general.
// POST: crea un ejercicio nuevo (por si el que necesitas no existe todavía).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT id_ejercicio, nombre FROM ejercicios ORDER BY nombre ASC');
    responderJSON($stmt->fetchAll());

} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($input['nombre'] ?? '');

    if (!$nombre) {
        responderJSON(['error' => 'nombre es obligatorio'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO ejercicios (nombre, descripcion, nivel) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $input['descripcion'] ?? null, $input['nivel'] ?? 'principiante']);

    responderJSON(['mensaje' => 'Ejercicio creado', 'id_ejercicio' => $pdo->lastInsertId()], 201);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
