<?php
// Endpoint: /api/pagos_entrenador.php
// Registra el pago que un cliente hace directamente a su entrenador personalizado
// (aparte de la mensualidad del gimnasio).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $idEntrenador = $input['id_entrenador'] ?? null;
    $idCliente = $input['id_cliente'] ?? null;
    $monto = $input['monto'] ?? null;

    if (!$idEntrenador || !$idCliente || !$monto) {
        responderJSON(['error' => 'id_entrenador, id_cliente y monto son obligatorios'], 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_entrenador (id_entrenador, id_cliente, monto, metodo_pago) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$idEntrenador, $idCliente, $monto, $input['metodo_pago'] ?? 'efectivo']);

    responderJSON(['mensaje' => 'Pago al entrenador registrado', 'id_pago_entrenador' => $pdo->lastInsertId()], 201);

} elseif ($metodo === 'GET') {
    $stmt = $pdo->query(
        'SELECT pe.*, ent.nombre_completo AS entrenador, cli.nombre_completo AS cliente
         FROM pagos_entrenador pe
         JOIN usuarios ent ON pe.id_entrenador = ent.id_usuario
         JOIN usuarios cli ON pe.id_cliente = cli.id_usuario
         ORDER BY pe.fecha_pago DESC'
    );
    responderJSON($stmt->fetchAll());

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
