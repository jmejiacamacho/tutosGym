<?php
// Conexión reutilizable a la base de datos (PDO)
// Se incluye en cada endpoint con: require_once __DIR__ . '/../config/db.php';

function conectarDB(): PDO {
    $host = getenv('DB_HOST') ?: 'db';
    $dbname = getenv('DB_NAME') ?: 'gimnasio_app';
    $user = getenv('DB_USER') ?: 'gimnasio_user';
    $password = getenv('DB_PASSWORD') ?: 'gimnasio_pass';

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

// Respuesta JSON estándar para todos los endpoints
function responderJSON($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
