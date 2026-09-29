<?php
// Endpoint: /api/auth.php
// Acciones: registro y login de usuarios (admin, entrenador, cliente)

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

$pdo = conectarDB();

if ($accion === 'registro') {

    $nombre = trim($input['nombre_completo'] ?? '');
    $correo = trim($input['correo'] ?? '');
    $password = $input['password'] ?? '';
    $idRol = (int)($input['id_rol'] ?? 3); // 3 = cliente por defecto

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
        'INSERT INTO usuarios (id_rol, nombre_completo, correo, password_hash) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$idRol, $nombre, $correo, $hash]);

    responderJSON(['mensaje' => 'Usuario registrado correctamente', 'id_usuario' => $pdo->lastInsertId()], 201);

} elseif ($accion === 'login') {

    $correo = trim($input['correo'] ?? '');
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ?');
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        responderJSON(['error' => 'Credenciales inválidas'], 401);
    }

    if ($usuario['activo'] == 0) {
        responderJSON(['error' => 'Esta cuenta está deshabilitada. Habla con el gimnasio para reactivarla.'], 403);
    }

    unset($usuario['password_hash']); // nunca devolver el hash al cliente

    responderJSON(['mensaje' => 'Login exitoso', 'usuario' => $usuario]);

} else {
    responderJSON(['error' => 'Acción no reconocida. Usa "registro" o "login".'], 400);
}
