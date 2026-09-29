<?php
// Endpoint: /api/enviar_plan_pdf.php
// Reenvía manualmente el PDF del plan de un cliente por correo.
// Uso: POST { "id_plan": 5 }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/plan_pdf.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(['error' => 'Método no soportado'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$idPlan = $input['id_plan'] ?? null;

if (!$idPlan) {
    responderJSON(['error' => 'id_plan es obligatorio'], 400);
}

$pdo = conectarDB();
$enviado = generarYEnviarPlanPDF($pdo, (int) $idPlan);

responderJSON([
    'mensaje' => $enviado ? 'PDF generado y enviado por correo' : 'No se pudo generar/enviar el PDF (revisa que el plan exista)',
    'enviado' => $enviado,
]);
