<?php
// Endpoint: /api/planes.php
// El entrenador crea un plan para un cliente y le asigna ejercicios,
// filtrando el catálogo según las máquinas realmente disponibles en el gimnasio.

require_once __DIR__ . '/../config/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = conectarDB();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id_cliente'])) {
            // Plan(es) activos de un cliente, con sus ejercicios (incluye foto de la máquina)
            $stmt = $pdo->prepare(
                "SELECT * FROM planes WHERE id_cliente = ? AND estado = 'activo'"
            );
            $stmt->execute([$_GET['id_cliente']]);
            $planes = $stmt->fetchAll();

            foreach ($planes as &$plan) {
                $stmtEj = $pdo->prepare(
                    'SELECT pe.*, e.nombre AS nombre_ejercicio, m.nombre AS nombre_maquina, m.foto AS foto_maquina
                     FROM plan_ejercicio pe
                     JOIN ejercicios e ON pe.id_ejercicio = e.id_ejercicio
                     LEFT JOIN maquinas m ON pe.id_maquina = m.id_maquina
                     WHERE pe.id_plan = ?'
                );
                $stmtEj->execute([$plan['id_plan']]);
                $plan['ejercicios'] = $stmtEj->fetchAll();
            }

            responderJSON($planes);

        } elseif (isset($_GET['id_entrenador'])) {
            // Rutinas creadas por este entrenador (para poder editarlas/borrarlas)
            $stmt = $pdo->prepare(
                "SELECT p.id_plan, p.nombre_plan, p.tipo_plan, p.fecha_inicio, p.estado,
                        u.nombre_completo AS nombre_cliente,
                        (SELECT COUNT(*) FROM plan_ejercicio pe WHERE pe.id_plan = p.id_plan) AS cantidad_ejercicios
                 FROM planes p
                 JOIN usuarios u ON p.id_cliente = u.id_usuario
                 WHERE p.id_entrenador = ? AND p.estado = 'activo'
                 ORDER BY p.id_plan DESC"
            );
            $stmt->execute([$_GET['id_entrenador']]);
            responderJSON($stmt->fetchAll());

        } elseif (isset($_GET['id_plan'])) {
            // Un solo plan con sus ejercicios (para precargar el formulario de edición)
            $stmt = $pdo->prepare('SELECT * FROM planes WHERE id_plan = ?');
            $stmt->execute([$_GET['id_plan']]);
            $plan = $stmt->fetch();
            if (!$plan) {
                responderJSON(['error' => 'Plan no encontrado'], 404);
            }
            $stmtEj = $pdo->prepare('SELECT id_ejercicio, id_maquina, dia_semana, series, repeticiones FROM plan_ejercicio WHERE id_plan = ?');
            $stmtEj->execute([$_GET['id_plan']]);
            $plan['ejercicios'] = $stmtEj->fetchAll();
            responderJSON($plan);

        } else {
            // Máquinas confirmadas + sus ejercicios posibles, para que el
            // entrenador arme el plan solo con lo que el gimnasio realmente tiene
            $stmt = $pdo->query(
                "SELECT m.id_maquina, m.nombre AS nombre_maquina, m.foto AS foto_maquina, e.id_ejercicio, e.nombre AS nombre_ejercicio
                 FROM maquinas m
                 JOIN maquina_ejercicio me ON m.id_maquina = me.id_maquina
                 JOIN ejercicios e ON me.id_ejercicio = e.id_ejercicio
                 WHERE m.confirmada_por_admin = TRUE AND m.estado = 'activa'"
            );
            responderJSON($stmt->fetchAll());
        }
        break;

    case 'POST':
        // Crear un plan con su lista de ejercicios
        $input = json_decode(file_get_contents('php://input'), true);

        $idCliente = $input['id_cliente'] ?? null;
        $idEntrenador = $input['id_entrenador'] ?? null;
        $ejercicios = $input['ejercicios'] ?? [];

        if (!$idCliente || !$idEntrenador || empty($ejercicios)) {
            responderJSON(['error' => 'id_cliente, id_entrenador y al menos un ejercicio son obligatorios'], 400);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO planes (id_cliente, id_entrenador, nombre_plan, objetivo, tipo_plan, fecha_inicio, fecha_fin, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, "activo")'
        );
        $stmt->execute([
            $idCliente,
            $idEntrenador,
            $input['nombre_plan'] ?? 'Plan personalizado',
            $input['objetivo'] ?? null,
            $input['tipo_plan'] ?? 'guia', // "personalizado" (entrenador acompaña, cobra aparte) o "guia" (se envía PDF)
            $input['fecha_inicio'] ?? date('Y-m-d'),
            $input['fecha_fin'] ?? null,
        ]);
        $idPlan = $pdo->lastInsertId();

        $stmtEj = $pdo->prepare(
            'INSERT INTO plan_ejercicio (id_plan, id_ejercicio, id_maquina, dia_semana, series, repeticiones, peso_sugerido, notas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($ejercicios as $ej) {
            $stmtEj->execute([
                $idPlan,
                $ej['id_ejercicio'],
                $ej['id_maquina'] ?? null,
                $ej['dia_semana'] ?? null,
                $ej['series'] ?? null,
                $ej['repeticiones'] ?? null,
                $ej['peso_sugerido'] ?? null,
                $ej['notas'] ?? null,
            ]);
        }

        $pdo->commit();

        // Si el plan es tipo "guía", se envía el PDF automáticamente al correo del cliente
        $pdfEnviado = null;
        if (($input['tipo_plan'] ?? 'guia') === 'guia') {
            try {
                require_once __DIR__ . '/../config/plan_pdf.php';
                $pdfEnviado = generarYEnviarPlanPDF($pdo, $idPlan);
            } catch (\Throwable $e) {
                error_log('Error generando/enviando el PDF del plan: ' . $e->getMessage());
                $pdfEnviado = false;
            }
        }

        responderJSON([
            'mensaje' => 'Plan creado',
            'id_plan' => $idPlan,
            'pdf_enviado' => $pdfEnviado,
        ], 201);
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $inputDelete);
        $idPlanBorrar = $_GET['id_plan'] ?? $inputDelete['id_plan'] ?? null;

        if (!$idPlanBorrar) {
            responderJSON(['error' => 'id_plan es obligatorio'], 400);
        }

        // plan_ejercicio se borra solo por el ON DELETE CASCADE del schema
        $pdo->prepare('DELETE FROM planes WHERE id_plan = ?')->execute([$idPlanBorrar]);

        responderJSON(['mensaje' => 'Rutina eliminada']);
        break;

    default:
        responderJSON(['error' => 'Método no soportado'], 405);
}
