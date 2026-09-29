<?php
// Script para ejecutar diariamente por cron (dentro del contenedor "app")
// Revisa qué suscripciones vencen en los próximos DIAS_AVISO días
// y registra una notificación pendiente por cada canal disponible del cliente.
//
// Programar en el Dockerfile o en el crontab del contenedor, ej:
// 0 8 * * * php /var/www/html/cron/verificar_vencimientos.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notificaciones.php';

const DIAS_AVISO = 2; // avisar cuando falten 2 días o menos para el vencimiento

$pdo = conectarDB();

$config = $pdo->query('SELECT alertas_correo_activas, dias_aviso_vencimiento FROM configuracion_gimnasio WHERE id = 1')->fetch();
$diasAviso = $config['dias_aviso_vencimiento'] ?? DIAS_AVISO;
$alertasCorreoActivas = $config['alertas_correo_activas'] ?? true;

$stmt = $pdo->prepare(
    "SELECT s.id_suscripcion, s.fecha_vencimiento, u.id_usuario, u.nombre_completo, u.correo, u.telefono
     FROM suscripciones s
     JOIN usuarios u ON s.id_cliente = u.id_usuario
     WHERE s.estado = 'activa'
       AND s.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
);
$stmt->execute([$diasAviso]);
$suscripcionesPorVencer = $stmt->fetchAll();

foreach ($suscripcionesPorVencer as $s) {

    $mensaje = "Hola {$s['nombre_completo']}, tu suscripción vence el {$s['fecha_vencimiento']}. "
             . "¡Recuerda renovar a tiempo para no perder tu cupo!";

    // Evitar enviar la misma alerta más de una vez el mismo día
    $yaEnviado = $pdo->prepare(
        "SELECT id_notificacion FROM notificaciones
         WHERE id_suscripcion = ? AND DATE(fecha_envio) = CURDATE()"
    );
    $yaEnviado->execute([$s['id_suscripcion']]);
    if ($yaEnviado->fetch()) {
        continue;
    }

    // Correo (solo si el interruptor de "Alertas por correo" está activo en Configuración)
    if ($alertasCorreoActivas) {
        $enviado = enviarCorreo($s['correo'], 'Tu suscripción está por vencer', $mensaje);
        registrarNotificacion($pdo, $s['id_usuario'], $s['id_suscripcion'], 'correo', $mensaje, $enviado);
    }

    // WhatsApp (si el cliente tiene teléfono registrado) — vía Bird, con plantilla aprobada
    if (!empty($s['telefono'])) {
        $enviadoWsp = enviarWhatsApp($s['telefono'], $s['nombre_completo'], $s['fecha_vencimiento']);
        registrarNotificacion($pdo, $s['id_usuario'], $s['id_suscripcion'], 'whatsapp', $mensaje, $enviadoWsp);
    }

    // Alerta visual para el panel del administrador (siempre se registra)
    registrarNotificacion($pdo, $s['id_usuario'], $s['id_suscripcion'], 'panel_admin', $mensaje, true);
}

echo count($suscripcionesPorVencer) . " suscripción(es) revisada(s).\n";
