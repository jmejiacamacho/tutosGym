<?php
// Script de un solo uso: crea usuarios de prueba (admin, entrenador, cliente)
// más algunas máquinas/ejercicios/un plan/una suscripción de ejemplo,
// para poder probar las distintas vistas sin tener que crear todo a mano.
//
// Uso: entra a http://localhost:8085/seed_usuarios_prueba.php una vez.
// Es seguro correrlo varias veces: no duplica nada (usa correos únicos).
//
// ⚠️ Bórralo cuando termines de probar — no debe quedar expuesto en producción.

require_once __DIR__ . '/config/db.php';

$pdo = conectarDB();
$mensajes = [];

function crearUsuarioSiNoExiste(PDO $pdo, array $datos, array &$mensajes): int {
    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ?');
    $stmt->execute([$datos['correo']]);
    $existente = $stmt->fetch();

    if ($existente) {
        $mensajes[] = "Ya existía: {$datos['correo']} (id {$existente['id_usuario']})";
        return $existente['id_usuario'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (id_rol, nombre_completo, correo, telefono, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $datos['id_rol'],
        $datos['nombre'],
        $datos['correo'],
        $datos['telefono'] ?? null,
        password_hash($datos['password'], PASSWORD_BCRYPT),
    ]);
    $id = $pdo->lastInsertId();
    $mensajes[] = "Creado: {$datos['correo']} / {$datos['password']} (id $id)";
    return $id;
}

// --- 1. Usuarios de prueba ---
$idAdmin = crearUsuarioSiNoExiste($pdo, [
    'id_rol' => 1, 'nombre' => 'Admin General', 'correo' => 'admin@gymsoft.com',
    'password' => 'admin123', 'telefono' => '3000000001',
], $mensajes);

$idEntrenador = crearUsuarioSiNoExiste($pdo, [
    'id_rol' => 2, 'nombre' => 'Carlos Entrenador', 'correo' => 'entrenador@gymsoft.com',
    'password' => 'entrenador123', 'telefono' => '3000000002',
], $mensajes);

$idCliente = crearUsuarioSiNoExiste($pdo, [
    'id_rol' => 3, 'nombre' => 'Cliente de Prueba', 'correo' => 'cliente@gymsoft.com',
    'password' => 'cliente123', 'telefono' => '3000000003',
], $mensajes);

// --- 2. Ejercicios y máquina de ejemplo (solo si no existen) ---
$stmt = $pdo->query('SELECT COUNT(*) AS total FROM ejercicios');
if ($stmt->fetch()['total'] == 0) {
    $pdo->exec("INSERT INTO ejercicios (nombre, descripcion, nivel) VALUES
        ('Sentadilla', 'Ejercicio de pierna con máquina o barra', 'principiante'),
        ('Press de banca', 'Ejercicio de pecho', 'intermedio'),
        ('Remo sentado', 'Ejercicio de espalda', 'principiante')");
    $mensajes[] = 'Ejercicios de ejemplo creados';
}

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM maquinas WHERE confirmada_por_admin = TRUE");
if ($stmt->fetch()['total'] == 0) {
    $pdo->exec("INSERT INTO maquinas (nombre, tipo, foto, confirmada_por_admin, estado) VALUES
        ('Prensa de piernas', 'fuerza', '', TRUE, 'activa'),
        ('Press de banca', 'fuerza', '', TRUE, 'activa')");
    $mensajes[] = 'Máquinas de ejemplo creadas y confirmadas';
}

// Relacionarlas con los ejercicios (relación directa, sin depender de coincidencia de texto).
// Corre siempre (no solo la primera vez) para reparar relaciones de corridas anteriores del seed.
$ejercicios = $pdo->query("SELECT id_ejercicio, nombre FROM ejercicios")->fetchAll();
$maquinas = $pdo->query("SELECT id_maquina, nombre FROM maquinas")->fetchAll();
$idPrensa = null;
$idPress = null;
foreach ($maquinas as $m) {
    if (str_contains($m['nombre'], 'Prensa')) $idPrensa = $m['id_maquina'];
    if (str_contains($m['nombre'], 'Press')) $idPress = $m['id_maquina'];
}
$idSentadilla = $idPressBanca = $idRemo = null;
foreach ($ejercicios as $e) {
    if (str_contains($e['nombre'], 'Sentadilla')) $idSentadilla = $e['id_ejercicio'];
    if (str_contains($e['nombre'], 'Press de banca')) $idPressBanca = $e['id_ejercicio'];
    if (str_contains($e['nombre'], 'Remo')) $idRemo = $e['id_ejercicio'];
}
$relaciones = [
    [$idPrensa, $idSentadilla], [$idPrensa, $idRemo],
        [$idPress, $idPressBanca], [$idPress, $idSentadilla],
    ];
    foreach ($relaciones as [$idM, $idE]) {
        if ($idM && $idE) {
            $pdo->prepare('INSERT IGNORE INTO maquina_ejercicio (id_maquina, id_ejercicio) VALUES (?, ?)')->execute([$idM, $idE]);
        }
    }

// --- 3. Suscripción activa de ejemplo para el cliente ---
$stmt = $pdo->prepare("SELECT id_suscripcion FROM suscripciones WHERE id_cliente = ? AND estado = 'activa'");
$stmt->execute([$idCliente]);
if (!$stmt->fetch()) {
    $fechaInicio = date('Y-m-d');
    $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
    $pdo->prepare(
        "INSERT INTO suscripciones (id_cliente, id_tipo, fecha_inicio, fecha_vencimiento, estado)
         VALUES (?, 3, ?, ?, 'activa')" // id_tipo 3 = Mensual (según el seed del schema)
    )->execute([$idCliente, $fechaInicio, $fechaVencimiento]);
    $mensajes[] = 'Suscripción mensual de ejemplo creada para el cliente';
}

// --- 4. Plan de ejemplo (tipo "guía") para el cliente ---
$stmt = $pdo->prepare("SELECT id_plan FROM planes WHERE id_cliente = ? AND estado = 'activo'");
$stmt->execute([$idCliente]);
if (!$stmt->fetch()) {
    $pdo->prepare(
        "INSERT INTO planes (id_cliente, id_entrenador, nombre_plan, objetivo, tipo_plan, fecha_inicio, estado)
         VALUES (?, ?, 'Plan de bienvenida', 'Acondicionamiento general', 'guia', CURDATE(), 'activo')"
    )->execute([$idCliente, $idEntrenador]);
    $idPlan = $pdo->lastInsertId();

    $ejercicios = $pdo->query('SELECT id_ejercicio FROM ejercicios LIMIT 3')->fetchAll();
    $maquina = $pdo->query('SELECT id_maquina FROM maquinas LIMIT 1')->fetch();
    $dias = ['lunes', 'miercoles', 'viernes'];

    foreach ($ejercicios as $i => $ej) {
        $pdo->prepare(
            'INSERT INTO plan_ejercicio (id_plan, id_ejercicio, id_maquina, dia_semana, series, repeticiones)
             VALUES (?, ?, ?, ?, 3, 12)'
        )->execute([$idPlan, $ej['id_ejercicio'], $maquina['id_maquina'] ?? null, $dias[$i % 3]]);
    }
    $mensajes[] = 'Plan de ejemplo (tipo guía) creado con 3 ejercicios';
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Usuarios y datos de prueba listos ===\n\n";
foreach ($mensajes as $m) {
    echo "- $m\n";
}
echo "\nCredenciales para probar en login.html:\n";
echo "  Admin/Dueño:  admin@gymsoft.com       / admin123\n";
echo "  Entrenador:   entrenador@gymsoft.com  / entrenador123\n";
echo "  Cliente:      cliente@gymsoft.com     / cliente123\n";
echo "\n⚠️ Recuerda borrar este archivo (seed_usuarios_prueba.php) cuando termines de probar.\n";
