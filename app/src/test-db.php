<?php
// Archivo de prueba: verifica que el contenedor PHP se conecta correctamente a MySQL

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'gimnasio_app';
$user = getenv('DB_USER') ?: 'gimnasio_user';
$password = getenv('DB_PASSWORD') ?: 'gimnasio_pass';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa a la base de datos '$dbname'.";
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
}
