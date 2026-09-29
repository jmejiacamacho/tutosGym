<?php
// Endpoint: /api/maquinas.php
// CRUD de máquinas del gimnasio (crear con foto+etiqueta IA, listar, actualizar, eliminar)

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        // Listar todas las máquinas, o una sola si se pasa ?id=
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM maquinas WHERE id_maquina = ?');
            $stmt->execute([$_GET['id']]);
            $maquina = $stmt->fetch();
            $maquina
                ? responderJSON($maquina)
                : responderJSON(['error' => 'Máquina no encontrada'], 404);
        } else {
            $stmt = $pdo->query('SELECT * FROM maquinas ORDER BY fecha_registro DESC');
            responderJSON($stmt->fetchAll());
        }
        break;

    case 'POST':
        // Registrar una nueva máquina (el admin sube foto + el sistema sugiere etiqueta_ia)
        $input = json_decode(file_get_contents('php://input'), true);

        $nombre = trim($input['nombre'] ?? '');
        $foto = trim($input['foto'] ?? '');

        if (!$nombre || !$foto) {
            responderJSON(['error' => 'nombre y foto son obligatorios'], 400);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO maquinas (nombre, tipo, foto, etiqueta_ia, confianza_ia, confirmada_por_admin, id_gimnasio)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nombre,
            $input['tipo'] ?? null,
            $foto,
            $input['etiqueta_ia'] ?? null,
            $input['confianza_ia'] ?? null,
            $input['confirmada_por_admin'] ?? false,
            $input['id_gimnasio'] ?? null,
        ]);

        responderJSON(['mensaje' => 'Máquina registrada', 'id_maquina' => $pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        // Actualizar una máquina existente (ej: admin confirma o corrige la etiqueta de la IA)
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id_maquina'] ?? null;

        if (!$id) {
            responderJSON(['error' => 'id_maquina es obligatorio'], 400);
        }

        $stmt = $pdo->prepare(
            'UPDATE maquinas SET nombre = ?, tipo = ?, confirmada_por_admin = ?, estado = ?
             WHERE id_maquina = ?'
        );
        $stmt->execute([
            $input['nombre'] ?? null,
            $input['tipo'] ?? null,
            $input['confirmada_por_admin'] ?? false,
            $input['estado'] ?? 'activa',
            $id,
        ]);

        responderJSON(['mensaje' => 'Máquina actualizada']);
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        $id = $_GET['id'] ?? $input['id'] ?? null;

        if (!$id) {
            responderJSON(['error' => 'id es obligatorio'], 400);
        }

        $stmt = $pdo->prepare('DELETE FROM maquinas WHERE id_maquina = ?');
        $stmt->execute([$id]);

        responderJSON(['mensaje' => 'Máquina eliminada']);
        break;

    default:
        responderJSON(['error' => 'Método no soportado'], 405);
}
