<?php
// Funciones de envío de notificaciones.
// Correo: Brevo vía SMTP (con PHPMailer)
// WhatsApp: Twilio Sandbox (ideal para el prototipo/demo, sin verificación de Meta)
//
// Credenciales esperadas como variables de entorno (ver docker-compose.yml):
//   BREVO_SMTP_USER, BREVO_SMTP_PASS
//   TWILIO_SID, TWILIO_TOKEN, TWILIO_WHATSAPP_FROM (ej: whatsapp:+14155238886, el número del sandbox)

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreoConAdjunto(string $destinatario, string $asunto, string $mensaje, string $rutaAdjunto, string $nombreAdjunto): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('BREVO_SMTP_USER');
        $mail->Password = getenv('BREVO_SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(getenv('BREVO_SMTP_USER'), 'Tutos Gym Club');
        $mail->addAddress($destinatario);
        $mail->addAttachment($rutaAdjunto, $nombreAdjunto);
        $mail->Subject = $asunto;
        $mail->Body = $mensaje;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando correo con adjunto: {$mail->ErrorInfo}");
        return false;
    }
}

function enviarCorreo(string $destinatario, string $asunto, string $mensaje): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('BREVO_SMTP_USER');
        $mail->Password = getenv('BREVO_SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(getenv('BREVO_SMTP_USER'), 'Tutos Gym Club');
        $mail->addAddress($destinatario);
        $mail->Subject = $asunto;
        $mail->Body = $mensaje;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando correo: {$mail->ErrorInfo}");
        return false;
    }
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
