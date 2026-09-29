<?php
// Endpoint: /api/subir_maquina.php
// Recibe una foto (multipart/form-data, campo "foto"), la guarda, la analiza
// con Google Cloud Vision (label detection) y sugiere a qué máquina del
// catálogo corresponde. El admin confirma/corrige después vía PUT /api/maquinas.php

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(['error' => 'Método no soportado'], 405);
}

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    responderJSON(['error' => 'No se recibió ninguna foto válida'], 400);
}

// --- 1. Guardar la foto en el servidor ---
$directorioFotos = __DIR__ . '/../uploads/maquinas/';
if (!is_dir($directorioFotos)) {
    mkdir($directorioFotos, 0755, true);
}

$nombreArchivo = uniqid('maquina_') . '_' . basename($_FILES['foto']['name']);
$rutaDestino = $directorioFotos . $nombreArchivo;
move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);
$rutaPublica = '/uploads/maquinas/' . $nombreArchivo;

// --- 2. Analizar la imagen con Google Cloud Vision (label detection) ---
[$etiquetaSugerida, $confianza] = analizarImagenConIA($rutaDestino);

// --- 3. Guardar el registro en la base de datos, pendiente de confirmación ---
$pdo = conectarDB();
$stmt = $pdo->prepare(
    'INSERT INTO maquinas (nombre, foto, etiqueta_ia, confianza_ia, confirmada_por_admin)
     VALUES (?, ?, ?, ?, FALSE)'
);
// El "nombre" inicial es la sugerencia de la IA; el admin lo corrige si hace falta
$stmt->execute([$etiquetaSugerida ?: 'Máquina sin identificar', $rutaPublica, $etiquetaSugerida, $confianza]);

responderJSON([
    'mensaje' => 'Foto subida y analizada',
    'id_maquina' => $pdo->lastInsertId(),
    'foto' => $rutaPublica,
    'etiqueta_sugerida' => $etiquetaSugerida,
    'confianza' => $confianza,
    'requiere_confirmacion' => true,
], 201);

/**
 * Envía la imagen a Google Cloud Vision y devuelve [etiqueta_sugerida, confianza]
 * comparando las etiquetas detectadas contra un catálogo de máquinas de gimnasio.
 */
function analizarImagenConIA(string $rutaImagen): array {
    $apiKey = getenv('GOOGLE_VISION_API_KEY');
    if (!$apiKey) {
        error_log('GOOGLE_VISION_API_KEY no configurada');
        return [null, 0];
    }

    $imagenBase64 = base64_encode(file_get_contents($rutaImagen));

    $payload = [
        'requests' => [[
            'image' => ['content' => $imagenBase64],
            'features' => [['type' => 'LABEL_DETECTION', 'maxResults' => 10]],
        ]],
    ];

    $ch = curl_init("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $respuesta = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($respuesta, true);
    $etiquetas = $data['responses'][0]['labelAnnotations'] ?? [];

    // Catálogo de palabras clave -> nombre estandarizado de la máquina.
    // Amplíalo con las máquinas reales de tu gimnasio.
    $catalogo = [
        'treadmill' => 'Caminadora',
        'bicycle' => 'Bicicleta estática',
        'exercise machine' => 'Máquina de ejercicio',
        'bench press' => 'Press de banca',
        'leg press' => 'Prensa de piernas',
        'dumbbell' => 'Mancuernas',
        'weight' => 'Zona de pesas libres',
        'rowing machine' => 'Remo',
        'elliptical' => 'Elíptica',
    ];

    foreach ($etiquetas as $etiqueta) {
        $descripcion = strtolower($etiqueta['description']);
        foreach ($catalogo as $clave => $nombreMaquina) {
            if (str_contains($descripcion, $clave)) {
                return [$nombreMaquina, round($etiqueta['score'] * 100, 2)];
            }
        }
    }

    // Si no coincide con el catálogo, devolvemos la etiqueta más confiable tal cual,
    // para que el admin la revise manualmente.
    if (!empty($etiquetas)) {
        return [$etiquetas[0]['description'], round($etiquetas[0]['score'] * 100, 2)];
    }

    return [null, 0];
}
