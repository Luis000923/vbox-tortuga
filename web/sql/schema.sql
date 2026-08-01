-- =============================================================================
-- Proyecto: Quitar Tortuga VBox - Esquema de base de datos
-- Autor: Luis Vides - Tec. en Ciberseguridad
-- Motor: MySQL / MariaDB (Hostinger shared hosting)
--
-- Importar desde phpMyAdmin (hPanel > Bases de datos > phpMyAdmin > Importar).
-- La base de datos ya debe existir y estar seleccionada antes de importar.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- devices: un registro por FIRMA de hardware unica (no se repiten specs).
-- La firma se calcula en PHP; aqui es UNIQUE para deduplicar con upsert.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devices (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    firma_hash      CHAR(64)        NOT NULL,
    cpu             VARCHAR(255)    NOT NULL DEFAULT '',
    nucleos         SMALLINT        NULL,
    hilos           SMALLINT        NULL,
    ram_gb          DECIMAL(6,2)    NULL,
    placa           VARCHAR(255)    NOT NULL DEFAULT '',
    bios            VARCHAR(255)    NOT NULL DEFAULT '',
    gpu             VARCHAR(255)    NOT NULL DEFAULT '',
    windows_edicion VARCHAR(255)    NOT NULL DEFAULT '',
    windows_version VARCHAR(64)     NOT NULL DEFAULT '',
    windows_build   VARCHAR(64)     NOT NULL DEFAULT '',
    arquitectura    VARCHAR(32)     NOT NULL DEFAULT '',
    virt_fw         TINYINT(1)      NULL,
    slat            TINYINT(1)      NULL,
    estado          ENUM('funciono','fallo') NOT NULL DEFAULT 'funciono',
    veces           INT UNSIGNED    NOT NULL DEFAULT 1,
    primera_fecha   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_fecha    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_firma (firma_hash),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- reportes: bug o "no funciona en esta maquina" (con diagnostico del porque).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reportes (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    firma_hash   CHAR(64)      NULL,
    tipo         ENUM('no_funciona','bug') NOT NULL DEFAULT 'no_funciona',
    descripcion  TEXT          NULL,
    diagnostico  JSON          NULL,
    creado       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_firma (firma_hash),
    KEY idx_creado (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- comentarios: testimonios publicos. Requieren aprobacion (aprobado=0 por defecto).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comentarios (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    firma_hash  CHAR(64)      NULL,
    nombre      VARCHAR(80)   NOT NULL,
    mensaje     VARCHAR(1000) NOT NULL,
    aprobado    TINYINT(1)    NOT NULL DEFAULT 0,
    creado      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aprobado (aprobado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- solicitudes_acceso: gente que pide colaborar en el proyecto.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitudes_acceso (
    id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre   VARCHAR(80)   NOT NULL,
    email    VARCHAR(160)  NOT NULL,
    github   VARCHAR(160)  NULL,
    motivo   VARCHAR(1000) NOT NULL,
    estado   ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    creado   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- admin_users: cuentas del panel. La contrasena se guarda con password_hash().
-- Genera el hash con:  php -r "echo password_hash('TU_PASS', PASSWORD_DEFAULT);"
-- Luego inserta:
--   INSERT INTO admin_users (usuario, password_hash) VALUES ('luis', '<hash>');
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario       VARCHAR(60)   NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    creado        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- rate_limit: control simple de flood por IP + endpoint (ventana temporal).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limit (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ip        VARBINARY(16) NOT NULL,
    endpoint  VARCHAR(40)   NOT NULL,
    creado    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lookup (ip, endpoint, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
