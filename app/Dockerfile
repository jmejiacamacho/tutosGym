FROM php:8.2-apache

# Extensiones necesarias para conectar con MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite (útil para rutas limpias en la API)
RUN a2enmod rewrite

# Instalar Composer y PHPMailer (envío de correo vía Brevo SMTP)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y unzip zip
COPY src/ /var/www/html/
RUN cd /var/www/html && composer require phpmailer/phpmailer dompdf/dompdf --no-interaction

# Dar permisos a www-data (Apache) sobre todo el proyecto — sin esto, Dompdf no puede
# escribir su caché de fuentes y las subidas de fotos/PDFs fallan silenciosamente
RUN chown -R www-data:www-data /var/www/html

# Programar el cron que revisa suscripciones por vencer (todos los días a las 8:00 AM)
RUN apt-get update && apt-get install -y cron \
    && echo "0 8 * * * php /var/www/html/cron/verificar_vencimientos.php >> /var/log/cron.log 2>&1" > /etc/cron.d/gimnasio-cron \
    && chmod 0644 /etc/cron.d/gimnasio-cron \
    && crontab /etc/cron.d/gimnasio-cron

EXPOSE 80

# Iniciar cron y Apache juntos al arrancar el contenedor
CMD service cron start && apache2-foreground
