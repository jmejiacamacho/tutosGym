<?php
// Endpoint: /api/maquina_ejercicio.php
// GET ?id_maquina=X: lista los ids de ejercicio ya asociados a esa máquina.
// POST { id_maquina, ids_ejercicios: [...] }: reemplaza las asociaciones de esa
// máquina por la lista dada (así queda disponible para el entrenador al instante).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $idMaquina = $_GET['id_maquina'] ?? null;
    if (!$idMaquina) {
        responderJSON(['error' => 'id_maquina es obligatorio'], 400);
    }

    $stmt = $pdo->prepare('SELECT id_ejercicio FROM maquina_ejercicio WHERE id_maquina = ?');
    $stmt->execute([$idMaquina]);
    responderJSON(array_column($stmt->fetchAll(), 'id_ejercicio'));

} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $idMaquina = $input['id_maquina'] ?? null;
    $idsEjercicios = $input['ids_ejercicios'] ?? [];

    if (!$idMaquina) {
        responderJSON(['error' => 'id_maquina es obligatorio'], 400);
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM maquina_ejercicio WHERE id_maquina = ?')->execute([$idMaquina]);

    $stmtInsert = $pdo->prepare('INSERT INTO maquina_ejercicio (id_maquina, id_ejercicio) VALUES (?, ?)');
    foreach ($idsEjercicios as $idEjercicio) {
        $stmtInsert->execute([$idMaquina, $idEjercicio]);
    }
    $pdo->commit();

    responderJSON(['mensaje' => 'Ejercicios de la máquina actualizados', 'cantidad' => count($idsEjercicios)]);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
