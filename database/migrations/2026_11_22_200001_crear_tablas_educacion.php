<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Panel de Educación (track de educación, NASA Space Apps): tablas nuevas,
 * todas con prefijo edu_. Migración ADITIVA: no toca ninguna tabla existente
 * ni los datos de comercio exterior.
 *
 *  edu_participante            estudiante sin cuenta (apodo + token de sesión)
 *  edu_progreso_leccion        lecciones completadas (mejora 1)
 *  edu_reto / edu_intento_reto retos guiados y sus intentos (mejora 2)
 *  edu_insignia / edu_participante_insignia   puntos e insignias (mejora 2)
 *  edu_actividad*              modo docente (mejora 4)
 *
 * La respuesta correcta de un reto NO se guarda: se calcula al vuelo contra la
 * base (consulta_clave), así sigue válida cuando se carguen más años.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS edu_participante (
    participante_id SERIAL PRIMARY KEY,
    apodo           VARCHAR(40) NOT NULL,
    token           CHAR(32) NOT NULL UNIQUE,
    nivel           VARCHAR(12) NOT NULL DEFAULT 'colegio' CHECK (nivel IN ('colegio','universidad')),
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now(),
    ultimo_acceso   TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE edu_participante IS 'Estudiante del panel Educación, sin cuenta: solo un apodo (no se piden datos personales).';

CREATE TABLE IF NOT EXISTS edu_progreso_leccion (
    participante_id INT NOT NULL REFERENCES edu_participante(participante_id) ON DELETE CASCADE,
    leccion_slug    VARCHAR(80) NOT NULL,
    completada_en   TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (participante_id, leccion_slug)
);

CREATE TABLE IF NOT EXISTS edu_reto (
    reto_id           SERIAL PRIMARY KEY,
    slug              VARCHAR(80) NOT NULL UNIQUE,
    titulo            VARCHAR(160) NOT NULL,
    enunciado         TEXT NOT NULL,
    pista_colegio     TEXT,
    pista_universidad TEXT,
    pista_ruta        VARCHAR(200),
    pista_filtros     JSONB NOT NULL DEFAULT '{}'::jsonb,
    tipo_respuesta    VARCHAR(20) NOT NULL CHECK (tipo_respuesta IN ('opcion','numero','binaria')),
    consulta_clave    JSONB NOT NULL,
    puntos            SMALLINT NOT NULL DEFAULT 10 CHECK (puntos >= 0),
    nivel             VARCHAR(12) NOT NULL DEFAULT 'ambos' CHECK (nivel IN ('ambos','colegio','universidad')),
    insignia          VARCHAR(40),
    orden             SMALLINT NOT NULL DEFAULT 0,
    publicado         BOOLEAN NOT NULL DEFAULT TRUE
);
COMMENT ON COLUMN edu_reto.consulta_clave IS 'Qué consulta calcula la respuesta (EvaluadorReto). Nunca un valor fijo.';

CREATE TABLE IF NOT EXISTS edu_intento_reto (
    intento_id      BIGSERIAL PRIMARY KEY,
    reto_id         INT NOT NULL REFERENCES edu_reto(reto_id) ON DELETE CASCADE,
    participante_id INT NOT NULL REFERENCES edu_participante(participante_id) ON DELETE CASCADE,
    respuesta       TEXT NOT NULL,
    correcta        BOOLEAN NOT NULL,
    puntos          SMALLINT NOT NULL DEFAULT 0,
    nivel           VARCHAR(12),
    exploro         BOOLEAN NOT NULL DEFAULT FALSE,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_edu_intento_part ON edu_intento_reto (participante_id, reto_id);

CREATE TABLE IF NOT EXISTS edu_insignia (
    insignia_id SERIAL PRIMARY KEY,
    codigo      VARCHAR(40) NOT NULL UNIQUE,
    titulo      VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255),
    icono       VARCHAR(8),
    regla       VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS edu_participante_insignia (
    participante_id INT NOT NULL REFERENCES edu_participante(participante_id) ON DELETE CASCADE,
    insignia_id     INT NOT NULL REFERENCES edu_insignia(insignia_id) ON DELETE CASCADE,
    obtenido_en     TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (participante_id, insignia_id)
);

CREATE TABLE IF NOT EXISTS edu_actividad (
    actividad_id       SERIAL PRIMARY KEY,
    docente_usuario_id INT REFERENCES usuario(usuario_id) ON DELETE SET NULL,
    codigo_share       CHAR(6) NOT NULL UNIQUE,
    titulo             VARCHAR(160) NOT NULL,
    consigna           TEXT NOT NULL,
    nivel              VARCHAR(12) NOT NULL DEFAULT 'colegio' CHECK (nivel IN ('colegio','universidad')),
    configuracion      JSONB NOT NULL DEFAULT '{}'::jsonb,
    activa             BOOLEAN NOT NULL DEFAULT TRUE,
    creada_en          TIMESTAMPTZ NOT NULL DEFAULT now(),
    cierra_en          TIMESTAMPTZ
);
COMMENT ON COLUMN edu_actividad.codigo_share IS 'Código corto aleatorio para compartir con los alumnos (no es un id predecible).';

CREATE TABLE IF NOT EXISTS edu_actividad_pregunta (
    pregunta_id        SERIAL PRIMARY KEY,
    actividad_id       INT NOT NULL REFERENCES edu_actividad(actividad_id) ON DELETE CASCADE,
    enunciado          TEXT NOT NULL,
    tipo               VARCHAR(12) NOT NULL CHECK (tipo IN ('dato','opcion','abierta')),
    respuesta_esperada JSONB NOT NULL DEFAULT '{}'::jsonb,
    orden              SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON COLUMN edu_actividad_pregunta.respuesta_esperada IS 'dato: {consulta} calculada en vivo; opcion: {opciones, correcta}; abierta: {}';

CREATE TABLE IF NOT EXISTS edu_actividad_participante (
    participante_id SERIAL PRIMARY KEY,
    actividad_id    INT NOT NULL REFERENCES edu_actividad(actividad_id) ON DELETE CASCADE,
    alias           VARCHAR(40) NOT NULL,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE edu_actividad_participante IS 'Alumno de una actividad: solo alias (sin correo ni documento obligatorios; pueden ser menores).';

CREATE TABLE IF NOT EXISTS edu_actividad_respuesta (
    respuesta_id    BIGSERIAL PRIMARY KEY,
    pregunta_id     INT NOT NULL REFERENCES edu_actividad_pregunta(pregunta_id) ON DELETE CASCADE,
    participante_id INT NOT NULL REFERENCES edu_actividad_participante(participante_id) ON DELETE CASCADE,
    valor           TEXT,
    correcta        BOOLEAN,
    enviado_en      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_edu_resp_pregunta ON edu_actividad_respuesta (pregunta_id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS edu_actividad_respuesta;
DROP TABLE IF EXISTS edu_actividad_participante;
DROP TABLE IF EXISTS edu_actividad_pregunta;
DROP TABLE IF EXISTS edu_actividad;
DROP TABLE IF EXISTS edu_participante_insignia;
DROP TABLE IF EXISTS edu_insignia;
DROP TABLE IF EXISTS edu_intento_reto;
DROP TABLE IF EXISTS edu_reto;
DROP TABLE IF EXISTS edu_progreso_leccion;
DROP TABLE IF EXISTS edu_participante;
SQL);
    }
};
