<?php
// Endpoint: /api/usuarios.php
// GET: lista usuarios, opcionalmente filtrados por rol (?id_rol=3) o por
//      entrenador personalizado (?id_entrenador=X -> solo sus clientes con
//      al menos un plan tipo "personalizado" asignado por ese entrenador).
// POST: crea un usuario (mismo mecanismo que auth.php "registro", expuesto
//       aquí para que el admin o el entrenador puedan crear cuentas desde su panel).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {

    if (isset($_GET['id_entrenador'])) {
        // Clientes personalizados de un entrenador específico
        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id_usuario, u.nombre_completo, u.correo, u.telefono
             FROM usuarios u
             JOIN planes p ON p.id_cliente = u.id_usuario
             WHERE p.id_entrenador = ? AND p.tipo_plan = 'personalizado' AND p.estado = 'activo'"
        );
        $stmt->execute([$_GET['id_entrenador']]);
        responderJSON($stmt->fetchAll());
        exit;
    }

    if (isset($_GET['id_rol'])) {
        $stmt = $pdo->prepare('SELECT id_usuario, nombre_completo, correo, telefono, activo, tipo_cliente, tarifa_personalizada_acordada FROM usuarios WHERE id_rol = ? ORDER BY nombre_completo');
        $stmt->execute([$_GET['id_rol']]);
        responderJSON($stmt->fetchAll());
        exit;
    }

    $stmt = $pdo->query(
        'SELECT u.id_usuario, u.nombre_completo, u.correo, u.telefono, u.activo, u.tipo_cliente, u.tarifa_personalizada_acordada, r.nombre_rol
         FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol
         ORDER BY u.nombre_completo'
    );
    responderJSON($stmt->fetchAll());

} elseif ($metodo === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($input['nombre_completo'] ?? '');
    $correo = trim($input['correo'] ?? '');
    $password = $input['password'] ?? '';
    $idRol = (int) ($input['id_rol'] ?? 3);
    $tipoCliente = $input['tipo_cliente'] ?? 'mensual'; // solo relevante si idRol = cliente

    if (!$nombre || !$correo || !$password) {
        responderJSON(['error' => 'Faltan campos obligatorios'], 400);
    }

    $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ?');
    $existe->execute([$correo]);
    if ($existe->fetch()) {
        responderJSON(['error' => 'El correo ya está registrado'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (id_rol, nombre_completo, correo, telefono, password_hash, tipo_cliente, tarifa_personalizada_acordada)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $idRol, $nombre, $correo, $input['telefono'] ?? null, $hash,
        $tipoCliente, $input['tarifa_personalizada_acordada'] ?? null,
    ]);

    responderJSON(['mensaje' => 'Usuario creado', 'id_usuario' => $pdo->lastInsertId()], 201);

} elseif ($metodo === 'PUT') {
    // Cambiar el tipo de cliente (mensual <-> personalizado), su tarifa acordada,
    // y/o habilitar o deshabilitar la cuenta (activo). Solo actualiza los campos que llegan.
    $input = json_decode(file_get_contents('php://input'), true);
    $idUsuario = $input['id_usuario'] ?? null;

    if (!$idUsuario) {
        responderJSON(['error' => 'id_usuario es obligatorio'], 400);
    }

    $campos = [];
    $valores = [];

    if (array_key_exists('tipo_cliente', $input)) {
        $campos[] = 'tipo_cliente = ?';
        $valores[] = $input['tipo_cliente'];
    }
    if (array_key_exists('tarifa_personalizada_acordada', $input)) {
        $campos[] = 'tarifa_personalizada_acordada = ?';
        $valores[] = $input['tarifa_personalizada_acordada'];
    }
    if (array_key_exists('activo', $input)) {
        $campos[] = 'activo = ?';
        $valores[] = !empty($input['activo']) ? 1 : 0;
    }

    if (!$campos) {
        responderJSON(['error' => 'No hay campos para actualizar'], 400);
    }

    $valores[] = $idUsuario;
    $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id_usuario = ?')->execute($valores);

    responderJSON(['mensaje' => 'Usuario actualizado']);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
