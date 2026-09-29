<?php
// Endpoint: /api/subir_logo.php
// POST (multipart, campo "logo"): sube un logo nuevo y lo deja como logo oficial del gimnasio.
// DELETE: restaura el logo por defecto (borra el personalizado).

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];
$directorioLogo = __DIR__ . '/../uploads/logo/';

function logoActual(PDO $pdo): ?string {
    $fila = $pdo->query('SELECT logo_url FROM configuracion_gimnasio WHERE id = 1')->fetch();
    return $fila['logo_url'] ?? null;
}

function borrarArchivoLogo(?string $rutaPublica, string $directorioLogo): void {
    // Solo borra archivos que estén dentro de uploads/logo (nunca el logo por defecto)
    if ($rutaPublica && str_starts_with($rutaPublica, '/uploads/logo/')) {
        $archivo = $directorioLogo . basename($rutaPublica);
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }
}

if ($metodo === 'POST') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        responderJSON(['error' => 'No se recibió ningún archivo válido'], 400);
    }

    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
        responderJSON(['error' => 'El logo no puede pesar más de 2 MB'], 400);
    }

    // Validación real del contenido (no solo la extensión)
    $info = @getimagesize($_FILES['logo']['tmp_name']);
    $extensiones = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($extensiones[$info[2]])) {
        responderJSON(['error' => 'Formato no válido. Usa PNG, JPG, WEBP o GIF'], 400);
    }

    if (!is_dir($directorioLogo) && !@mkdir($directorioLogo, 0755, true)) {
        responderJSON(['error' => 'No se pudo crear la carpeta de logos en el servidor'], 500);
    }

    $nombreArchivo = 'logo_' . uniqid() . '.' . $extensiones[$info[2]];
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $directorioLogo . $nombreArchivo)) {
        responderJSON(['error' => 'No se pudo guardar el archivo (revisa permisos de la carpeta uploads/logo)'], 500);
    }

    $rutaPublica = '/uploads/logo/' . $nombreArchivo;
    $anterior = logoActual($pdo);

    $pdo->exec('INSERT IGNORE INTO configuracion_gimnasio (id) VALUES (1)');
    $pdo->prepare('UPDATE configuracion_gimnasio SET logo_url = ? WHERE id = 1')->execute([$rutaPublica]);
    borrarArchivoLogo($anterior, $directorioLogo);

    responderJSON(['mensaje' => 'Logo actualizado', 'logo_url' => $rutaPublica]);

} elseif ($metodo === 'DELETE') {
    $anterior = logoActual($pdo);
    $pdo->exec('UPDATE configuracion_gimnasio SET logo_url = NULL WHERE id = 1');
    borrarArchivoLogo($anterior, $directorioLogo);
    responderJSON(['mensaje' => 'Logo restaurado al original', 'logo_url' => null]);

} else {
    responderJSON(['error' => 'Método no soportado'], 405);
}
