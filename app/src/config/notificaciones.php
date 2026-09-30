<?php
// Funciones de envío de notificaciones.
// Correo: API de Brevo por HTTPS (evita que un host como Render bloquee el puerto SMTP)
// WhatsApp: Bird (pendiente hasta tener el número empresarial)
//
// Credenciales esperadas como variables de entorno (ver docker-compose.yml):
//   BREVO_API_KEY, BREVO_SENDER_EMAIL (debe estar verificado como remitente en Brevo)
//   BIRD_API_KEY, BIRD_API_HOST, BIRD_WHATSAPP_TEMPLATE

// Envía un correo por la API de Brevo. $adjunto es opcional: ['ruta' => ..., 'nombre' => ...]
function enviarCorreoBrevo(string $destinatario, string $asunto, string $mensaje, ?array $adjunto = null): bool {
    $apiKey = getenv('BREVO_API_KEY');
    $remitente = getenv('BREVO_SENDER_EMAIL');

    if (!$apiKey || !$remitente) {
        error_log('Brevo no configurado: falta BREVO_API_KEY o BREVO_SENDER_EMAIL');
        return false;
    }

    $payload = [
        'sender' => ['name' => 'Tutos Gym Club', 'email' => $remitente],
        'to' => [['email' => $destinatario]],
        'subject' => $asunto,
        'textContent' => $mensaje,
    ];

    if ($adjunto && is_file($adjunto['ruta'])) {
        $payload['attachment'] = [[
            'content' => base64_encode(file_get_contents($adjunto['ruta'])),
            'name' => $adjunto['nombre'],
        ]];
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "api-key: {$apiKey}",
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $respuesta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($codigo >= 200 && $codigo < 300) {
        return true;
    }

    error_log("Error enviando correo (Brevo): $respuesta");
    return false;
}

function enviarCorreoConAdjunto(string $destinatario, string $asunto, string $mensaje, string $rutaAdjunto, string $nombreAdjunto): bool {
    return enviarCorreoBrevo($destinatario, $asunto, $mensaje, ['ruta' => $rutaAdjunto, 'nombre' => $nombreAdjunto]);
}

function enviarCorreo(string $destinatario, string $asunto, string $mensaje): bool {
    return enviarCorreoBrevo($destinatario, $asunto, $mensaje);
}

function enviarWhatsApp(string $telefono, string $nombreCliente, string $fechaVencimiento): bool {
    // La API de WhatsApp de Bird solo permite mensajes iniciados por la empresa
    // usando una plantilla PRE-APROBADA en el panel de Bird (no texto libre).
    // Crea la plantilla en Bird con dos variables, ej:
    //   "Hola {{1}}, tu suscripción vence el {{2}}. ¡Recuerda renovar a tiempo!"
    // y pon su nombre/slug en BIRD_WHATSAPP_TEMPLATE.

    $apiKey = getenv('BIRD_API_KEY');
    $host = getenv('BIRD_API_HOST') ?: 'us1.platform.bird.com'; // usar eu1.platform.bird.com si tu key empieza con bk_eu1_
    $template = getenv('BIRD_WHATSAPP_TEMPLATE');

    $url = "https://{$host}/v1/whatsapp/messages";

    $payload = [
        'to' => $telefono, // formato internacional: +57300xxxxxxx
        'template' => [
            'name' => $template,
            'language' => 'es',
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $nombreCliente],
                        ['type' => 'text', 'text' => $fechaVencimiento],
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$apiKey}",
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $respuesta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Bird responde 202 Accepted cuando el mensaje queda en cola de envío
    if ($codigo === 202) {
        return true;
    }

    error_log("Error enviando WhatsApp (Bird): $respuesta");
    return false;
}

function registrarNotificacion(PDO $pdo, int $idCliente, int $idSuscripcion, string $canal, string $mensaje, bool $exito): void {
    $stmt = $pdo->prepare(
        'INSERT INTO notificaciones (id_cliente, id_suscripcion, tipo_canal, mensaje, estado_envio)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$idCliente, $idSuscripcion, $canal, $mensaje, $exito ? 'enviado' : 'fallido']);
}
