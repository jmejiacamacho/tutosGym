<?php
// Endpoint: /api/categorias.php
// Categorías de máquina administrables desde Configuración (fuerza, cardio, etc.)
// GET: lista todas. POST: crea una. DELETE: elimina una por id.

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM categorias_maquina ORDER BY nombre ASC');
    responderJSON($stmt->fetchAll());

} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($input['nombre'] ?? '');

    if (!$nombre) {
        responderJSON(['error' => 'nombre es obligatorio'], 400);
    }

    $existe = $pdo->prepare('SELECT id_categoria FROM categorias_maquina WHERE nombre = ?');
    $existe->execute([$nombre]);
    if ($existe->fetch()) {
        responderJSON(['error' => 'Esa categoría ya existe'], 409);
    }

    $pdo->prepare('INSERT INTO categorias_maquina (nombre) VALUES (?)')->execute([$nombre]);
    responderJSON(['mensaje' => 'Categoría creada'], 201);

} elseif ($metodo === 'DELETE') {
    parse_str(file_get_contents('php://input'), $inputDelete);
    $id = $_GET['id'] ?? $inputDelete['id'] ?? null;

    if (!$id) {
        responderJSON(['error' => 'id es obligatorio'], 400);
    }

    $pdo->prepare('DELETE FROM categorias_maquina WHERE id_categoria = ?')->execute([$id]);
    responderJSON(['mensaje' => 'Categoría eliminada']);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
