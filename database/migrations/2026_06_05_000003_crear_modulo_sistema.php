<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modulo de sistema (usuarios, seguridad, operación).
 * Reproduce EXACTAMENTE estructura-db.sql: rol, permiso, rol_permiso, usuario,
 * usuario_rol, sesión (UUID), intento_acceso, bitacora_auditoria,
 * regla_validacion, configuración, tipo_cambio, carga_archivo, proceso_etl,
 * incidencia_calidad. NO crea la tabla `usuario`s de Laravel: la auth usa `usuario`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE rol (
                rol_id       SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre       VARCHAR(40) NOT NULL,
                descripcion  VARCHAR(255),
                CONSTRAINT uq_rol UNIQUE (nombre)
            );

            CREATE TABLE permiso (
                permiso_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                codigo       VARCHAR(60)  NOT NULL,
                descripcion  VARCHAR(255) NOT NULL,
                modulo       VARCHAR(40)  NOT NULL,
                CONSTRAINT uq_permiso UNIQUE (codigo)
            );

            CREATE TABLE rol_permiso (
                rol_id      SMALLINT NOT NULL REFERENCES rol(rol_id) ON DELETE CASCADE,
                permiso_id  INT      NOT NULL REFERENCES permiso(permiso_id) ON DELETE CASCADE,
                CONSTRAINT pk_rol_permiso PRIMARY KEY (rol_id, permiso_id)
            );

            CREATE TABLE usuario (
                usuario_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre_usuario    VARCHAR(50)  NOT NULL,
                correo            VARCHAR(150) NOT NULL,
                hash_contrasena   VARCHAR(255) NOT NULL,
                nombre_completo   VARCHAR(150) NOT NULL,
                activo            BOOLEAN      NOT NULL DEFAULT TRUE,
                debe_cambiar_pwd  BOOLEAN      NOT NULL DEFAULT TRUE,
                fecha_creacion    TIMESTAMPTZ  NOT NULL DEFAULT now(),
                ultimo_acceso     TIMESTAMPTZ,
                CONSTRAINT uq_usuario_nombre UNIQUE (nombre_usuario),
                CONSTRAINT uq_usuario_correo UNIQUE (correo)
            );

            CREATE TABLE usuario_rol (
                usuario_id   INT      NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
                rol_id       SMALLINT NOT NULL REFERENCES rol(rol_id) ON DELETE CASCADE,
                asignado_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT pk_usuario_rol PRIMARY KEY (usuario_id, rol_id)
            );

            CREATE TABLE sesion (
                sesion_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                usuario_id        INT NOT NULL REFERENCES usuario(usuario_id) ON DELETE CASCADE,
                token_hash        VARCHAR(255) NOT NULL,
                fecha_inicio      TIMESTAMPTZ  NOT NULL DEFAULT now(),
                fecha_expiracion  TIMESTAMPTZ  NOT NULL,
                ip_origen         INET,
                user_agent        VARCHAR(255),
                activa            BOOLEAN      NOT NULL DEFAULT TRUE
            );

            CREATE TABLE intento_acceso (
                intento_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre_usuario_intento  VARCHAR(50) NOT NULL,
                exito                   BOOLEAN     NOT NULL,
                ip_origen               INET,
                motivo_fallo            VARCHAR(100),
                fecha_hora              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE TABLE bitacora_auditoria (
                bitacora_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                usuario_id           INT REFERENCES usuario(usuario_id) ON DELETE SET NULL,
                accion               VARCHAR(40)  NOT NULL,
                entidad_afectada     VARCHAR(80),
                registro_afectado    VARCHAR(80),
                valores_anteriores   JSONB,
                valores_nuevos       JSONB,
                ip_origen            INET,
                fecha_hora           TIMESTAMPTZ  NOT NULL DEFAULT now()
            );

            CREATE TABLE regla_validacion (
                regla_id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre          VARCHAR(80)  NOT NULL,
                descripcion     VARCHAR(255) NOT NULL,
                campo_objetivo  VARCHAR(60),
                expresion       VARCHAR(255),
                severidad       VARCHAR(20) NOT NULL DEFAULT 'ADVERTENCIA'
                                CHECK (severidad IN ('INFO','ADVERTENCIA','ERROR')),
                activa          BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT uq_regla UNIQUE (nombre)
            );

            CREATE TABLE configuracion (
                clave              VARCHAR(60) PRIMARY KEY,
                valor              VARCHAR(255) NOT NULL,
                tipo_dato          VARCHAR(20)  NOT NULL DEFAULT 'texto',
                descripcion        VARCHAR(255),
                fecha_modificacion TIMESTAMPTZ  NOT NULL DEFAULT now(),
                usuario_modifico   INT REFERENCES usuario(usuario_id) ON DELETE SET NULL
            );

            CREATE TABLE tipo_cambio (
                tipo_cambio_id   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                fecha            DATE        NOT NULL,
                moneda_origen    CHAR(3)     NOT NULL,
                moneda_destino   CHAR(3)     NOT NULL,
                tasa             NUMERIC(12,6) NOT NULL CHECK (tasa > 0),
                CONSTRAINT uq_tipo_cambio UNIQUE (fecha, moneda_origen, moneda_destino)
            );

            CREATE TABLE carga_archivo (
                carga_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                organizacion_id       INT NOT NULL REFERENCES organizacion(organizacion_id) ON DELETE RESTRICT,
                fuente_id             INT REFERENCES fuente_datos(fuente_id) ON DELETE SET NULL,
                perfil_id             INT REFERENCES perfil_mapeo(perfil_id) ON DELETE SET NULL,
                usuario_id            INT REFERENCES usuario(usuario_id) ON DELETE SET NULL,
                nombre_archivo        VARCHAR(255) NOT NULL,
                tipo_flujo            VARCHAR(20)  NOT NULL CHECK (tipo_flujo IN ('EXPORTACION','IMPORTACION')),
                gestion               SMALLINT,
                mes                   SMALLINT CHECK (mes BETWEEN 1 AND 12),
                total_filas_leidas    INT NOT NULL DEFAULT 0,
                total_filas_validas   INT NOT NULL DEFAULT 0,
                total_filas_error     INT NOT NULL DEFAULT 0,
                estado                VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                                      CHECK (estado IN ('PENDIENTE','PROCESANDO','COMPLETADO','FALLIDO')),
                fecha_carga           TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE TABLE proceso_etl (
                proceso_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                carga_id          BIGINT NOT NULL REFERENCES carga_archivo(carga_id) ON DELETE CASCADE,
                estado            VARCHAR(20) NOT NULL DEFAULT 'EN_COLA'
                                  CHECK (estado IN ('EN_COLA','EN_EJECUCION','EXITOSO','FALLIDO')),
                fecha_inicio      TIMESTAMPTZ,
                fecha_fin         TIMESTAMPTZ,
                filas_procesadas  INT NOT NULL DEFAULT 0,
                mensaje_log       TEXT
            );

            CREATE TABLE incidencia_calidad (
                incidencia_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                carga_id            BIGINT NOT NULL REFERENCES carga_archivo(carga_id) ON DELETE CASCADE,
                regla_id            INT REFERENCES regla_validacion(regla_id) ON DELETE SET NULL,
                descripcion         VARCHAR(255) NOT NULL,
                severidad           VARCHAR(20) NOT NULL CHECK (severidad IN ('INFO','ADVERTENCIA','ERROR')),
                numero_fila         INT,
                valor_detectado     VARCHAR(255),
                estado_tratamiento  VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                                    CHECK (estado_tratamiento IN ('PENDIENTE','CORREGIDO','ACEPTADO','DESCARTADO')),
                fecha_deteccion     TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_usuario_rol_usuario   ON usuario_rol (usuario_id);
            CREATE INDEX idx_usuario_rol_rol       ON usuario_rol (rol_id);
            CREATE INDEX idx_rol_permiso_rol       ON rol_permiso (rol_id);
            CREATE INDEX idx_sesion_usuario        ON sesion (usuario_id);
            CREATE INDEX idx_sesion_activa         ON sesion (activa) WHERE activa = TRUE;
            CREATE INDEX idx_bitacora_usuario      ON bitacora_auditoria (usuario_id);
            CREATE INDEX idx_bitacora_fecha        ON bitacora_auditoria (fecha_hora);
            CREATE INDEX idx_carga_organizacion    ON carga_archivo (organizacion_id);
            CREATE INDEX idx_carga_estado          ON carga_archivo (estado);
            CREATE INDEX idx_incidencia_carga      ON incidencia_calidad (carga_id);
            CREATE INDEX idx_intento_fecha         ON intento_acceso (fecha_hora);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS incidencia_calidad CASCADE;
            DROP TABLE IF EXISTS proceso_etl CASCADE;
            DROP TABLE IF EXISTS carga_archivo CASCADE;
            DROP TABLE IF EXISTS tipo_cambio CASCADE;
            DROP TABLE IF EXISTS configuracion CASCADE;
            DROP TABLE IF EXISTS regla_validacion CASCADE;
            DROP TABLE IF EXISTS bitacora_auditoria CASCADE;
            DROP TABLE IF EXISTS intento_acceso CASCADE;
            DROP TABLE IF EXISTS sesion CASCADE;
            DROP TABLE IF EXISTS usuario_rol CASCADE;
            DROP TABLE IF EXISTS usuario CASCADE;
            DROP TABLE IF EXISTS rol_permiso CASCADE;
            DROP TABLE IF EXISTS permiso CASCADE;
            DROP TABLE IF EXISTS rol CASCADE;
        SQL);
    }
};
