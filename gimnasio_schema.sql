-- =========================================================
-- ESQUEMA COMPLETO Y UNIFICADO — Tutos Gym Club
-- Motor: MySQL / MariaDB (compatible con phpMyAdmin)
-- =========================================================

CREATE DATABASE IF NOT EXISTS gimnasio_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gimnasio_app;

-- ---------------------------------------------------------
-- 1. ROLES Y USUARIOS
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE  -- admin, entrenador, cliente
);

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    tipo_cliente ENUM('mensual','personalizado') DEFAULT 'mensual', -- solo aplica si id_rol = cliente[cite: 1, 2]
    tarifa_personalizada_acordada DECIMAL(10,2) DEFAULT NULL,        -- lo que el cliente acordó pagarle a su entrenador[cite: 1, 2]
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

-- ---------------------------------------------------------
-- 2. MÁQUINAS Y EJERCICIOS
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS grupos_musculares (
    id_grupo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE  -- pierna, espalda, pecho, brazo, core, etc.[cite: 1]
);

CREATE TABLE IF NOT EXISTS maquinas (
    id_maquina INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(100),                       -- ej: cardio, fuerza, peso libre[cite: 1]
    foto VARCHAR(255) NOT NULL,              -- ruta/URL de la foto tomada por el admin[cite: 1]
    etiqueta_ia VARCHAR(150),                -- etiqueta sugerida por el modelo de visión[cite: 1]
    confianza_ia DECIMAL(5,2),               -- % de confianza de la sugerencia de IA[cite: 1]
    confirmada_por_admin BOOLEAN DEFAULT FALSE,
    id_gimnasio INT,                         -- útil si más adelante hay varias sedes[cite: 1]
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activa','mantenimiento','fuera_de_servicio') DEFAULT 'activa'
);

CREATE TABLE IF NOT EXISTS ejercicios (
    id_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    id_grupo INT,
    descripcion TEXT,
    nivel ENUM('principiante','intermedio','avanzado') DEFAULT 'principiante',
    FOREIGN KEY (id_grupo) REFERENCES grupos_musculares(id_grupo)
);

-- Catálogo: qué ejercicios se pueden hacer en cada máquina
CREATE TABLE IF NOT EXISTS maquina_ejercicio (
    id_maquina INT NOT NULL,
    id_ejercicio INT NOT NULL,
    PRIMARY KEY (id_maquina, id_ejercicio),
    FOREIGN KEY (id_maquina) REFERENCES maquinas(id_maquina) ON DELETE CASCADE,
    FOREIGN KEY (id_ejercicio) REFERENCES ejercicios(id_ejercicio) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 3. PLANES DE ENTRENAMIENTO
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS planes (
    id_plan INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_entrenador INT NOT NULL,
    nombre_plan VARCHAR(150),
    objetivo VARCHAR(150),                   -- ej: pérdida de peso, hipertrofia, resistencia[cite: 1]
    tipo_plan ENUM('personalizado','guia') NOT NULL DEFAULT 'guia',
    fecha_inicio DATE,
    fecha_fin DATE,
    estado ENUM('activo','finalizado','pausado') DEFAULT 'activo',
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_entrenador) REFERENCES usuarios(id_usuario)
);

-- Ejercicios asignados dentro de un plan (filtrados según máquinas disponibles)
CREATE TABLE IF NOT EXISTS plan_ejercicio (
    id_plan_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    id_plan INT NOT NULL,
    id_ejercicio INT NOT NULL,
    id_maquina INT,
    dia_semana ENUM('lunes','martes','miercoles','jueves','viernes','sabado','domingo'),
    series INT,
    repeticiones INT,
    peso_sugerido DECIMAL(6,2),
    notas TEXT,
    FOREIGN KEY (id_plan) REFERENCES planes(id_plan) ON DELETE CASCADE,
    FOREIGN KEY (id_ejercicio) REFERENCES ejercicios(id_ejercicio),
    FOREIGN KEY (id_maquina) REFERENCES maquinas(id_maquina)
);

-- ---------------------------------------------------------
-- 4. SUSCRIPCIONES, PAGOS Y CONFIGURACIÓN
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS tipos_suscripcion (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,             -- diario, semanal, mensual[cite: 1]
    duracion_dias INT NOT NULL,              -- 1, 7, 30[cite: 1]
    precio DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS suscripciones (
    id_suscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_tipo INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    estado ENUM('activa','vencida','cancelada') DEFAULT 'activa',
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_tipo) REFERENCES tipos_suscripcion(id_tipo)
);

CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_suscripcion INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo_pago VARCHAR(50),                 -- efectivo, tarjeta, transferencia[cite: 1]
    FOREIGN KEY (id_suscripcion) REFERENCES suscripciones(id_suscripcion)
);

-- Pago que el cliente le hace directamente al entrenador personalizado
CREATE TABLE IF NOT EXISTS pagos_entrenador (
    id_pago_entrenador INT AUTO_INCREMENT PRIMARY KEY,
    id_entrenador INT NOT NULL,
    id_cliente INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo_pago VARCHAR(50),
    FOREIGN KEY (id_entrenador) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario)
);

-- Configuración general del establecimiento (una sola fila)[cite: 1, 2]
CREATE TABLE IF NOT EXISTS configuracion_gimnasio (
    id INT PRIMARY KEY DEFAULT 1,
    nombre_establecimiento VARCHAR(150) DEFAULT 'Tutos Gym Club',
    telefono_soporte VARCHAR(30),
    direccion VARCHAR(255),
    logo_url VARCHAR(255) DEFAULT NULL,
    horario_apertura_semana TIME DEFAULT '06:00:00',  -- lunes a viernes[cite: 1, 2]
    horario_cierre_semana TIME DEFAULT '22:00:00',
    horario_apertura_finde TIME DEFAULT '08:00:00',   -- sábado y domingo[cite: 1, 2]
    horario_cierre_finde TIME DEFAULT '14:00:00',
    alertas_correo_activas BOOLEAN DEFAULT TRUE,
    dias_aviso_vencimiento INT DEFAULT 2,
    mantenimiento_automatico_activo BOOLEAN DEFAULT FALSE
);

-- Fechas con horario especial o cierre total (festivos)[cite: 1, 2]
CREATE TABLE IF NOT EXISTS festividades_horario (
    id_festividad INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    nombre VARCHAR(100),
    cerrado BOOLEAN DEFAULT FALSE,
    horario_apertura TIME DEFAULT NULL,
    horario_cierre TIME DEFAULT NULL
);

-- Categorías de máquina administrables[cite: 1, 2]
CREATE TABLE IF NOT EXISTS categorias_maquina (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
);

-- ---------------------------------------------------------
-- 5. NOTIFICACIONES / ALERTAS
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_suscripcion INT,
    tipo_canal ENUM('correo','whatsapp','sms','panel_admin') NOT NULL,
    mensaje TEXT,
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado_envio ENUM('pendiente','enviado','fallido') DEFAULT 'pendiente',
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_suscripcion) REFERENCES suscripciones(id_suscripcion)
);

-- ---------------------------------------------------------
-- DATOS INICIALES
-- ---------------------------------------------------------

INSERT IGNORE INTO roles (nombre_rol) VALUES ('admin'), ('entrenador'), ('cliente');

INSERT IGNORE INTO tipos_suscripcion (nombre, duracion_dias, precio) VALUES
('Diario', 1, 8000),
('Semanal', 7, 45000),
('Mensual', 30, 150000);

INSERT IGNORE INTO categorias_maquina (nombre) VALUES ('fuerza'), ('cardio'), ('peso libre'), ('funcional'), ('otro');

INSERT IGNORE INTO configuracion_gimnasio (id, nombre_establecimiento) VALUES (1, 'Tutos Gym Club');