<?php
// Endpoint: /api/suscripciones.php
// Maneja la creación y consulta de suscripciones (diario/semanal/mensual)

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id_cliente'])) {
            // Historial de suscripciones de un cliente específico
            $stmt = $pdo->prepare(
                'SELECT s.*, t.nombre AS tipo_nombre, t.precio
                 FROM suscripciones s
                 JOIN tipos_suscripcion t ON s.id_tipo = t.id_tipo
                 WHERE s.id_cliente = ?
                 ORDER BY s.fecha_inicio DESC'
            );
            $stmt->execute([$_GET['id_cliente']]);
            responderJSON($stmt->fetchAll());
        } else {
            // Todas las suscripciones activas (vista general para el admin)
            $stmt = $pdo->query(
                'SELECT s.*, u.nombre_completo, t.nombre AS tipo_nombre
                 FROM suscripciones s
                 JOIN usuarios u ON s.id_cliente = u.id_usuario
                 JOIN tipos_suscripcion t ON s.id_tipo = t.id_tipo
                 ORDER BY s.fecha_vencimiento ASC'
            );
            responderJSON($stmt->fetchAll());
        }
        break;

    case 'POST':
        // Crear una nueva suscripción: la fecha de vencimiento se calcula sola
        // según duracion_dias del tipo (diario=1, semanal=7, mensual=30)
        $input = json_decode(file_get_contents('php://input'), true);
        $idCliente = $input['id_cliente'] ?? null;
        $idTipo = $input['id_tipo'] ?? null;

        if (!$idCliente || !$idTipo) {
            responderJSON(['error' => 'id_cliente e id_tipo son obligatorios'], 400);
        }

        $tipo = $pdo->prepare('SELECT duracion_dias, precio FROM tipos_suscripcion WHERE id_tipo = ?');
        $tipo->execute([$idTipo]);
        $tipoData = $tipo->fetch();

        if (!$tipoData) {
            responderJSON(['error' => 'Tipo de suscripción no válido'], 404);
        }

        $fechaInicio = $input['fecha_inicio'] ?? date('Y-m-d');
        $fechaVencimiento = date('Y-m-d', strtotime($fechaInicio . " +{$tipoData['duracion_dias']} days"));

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO suscripciones (id_cliente, id_tipo, fecha_inicio, fecha_vencimiento, estado)
             VALUES (?, ?, ?, ?, "activa")'
        );
        $stmt->execute([$idCliente, $idTipo, $fechaInicio, $fechaVencimiento]);
        $idSuscripcion = $pdo->lastInsertId();

        // Registrar el pago correspondiente automáticamente
        $stmtPago = $pdo->prepare(
            'INSERT INTO pagos (id_suscripcion, monto, metodo_pago) VALUES (?, ?, ?)'
        );
        $stmtPago->execute([$idSuscripcion, $tipoData['precio'], $input['metodo_pago'] ?? 'efectivo']);

        $pdo->commit();

        responderJSON([
            'mensaje' => 'Suscripción creada',
            'id_suscripcion' => $idSuscripcion,
            'fecha_vencimiento' => $fechaVencimiento,
        ], 201);
        break;

    case 'PUT':
        // Renovar o cancelar una suscripción existente
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id_suscripcion'] ?? null;
        $estado = $input['estado'] ?? null;

        if (!$id || !$estado) {
            responderJSON(['error' => 'id_suscripcion y estado son obligatorios'], 400);
        }

        $stmt = $pdo->prepare('UPDATE suscripciones SET estado = ? WHERE id_suscripcion = ?');
        $stmt->execute([$estado, $id]);

        responderJSON(['mensaje' => 'Suscripción actualizada']);
        break;

    default:
        responderJSON(['error' => 'Método no soportado'], 405);
}
