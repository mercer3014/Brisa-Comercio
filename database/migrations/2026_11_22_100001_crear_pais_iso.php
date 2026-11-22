<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Armonización de países entre fuentes con ISO 3166 (alpha-3, alpha-2 y M49).
 *
 * - pais_iso:        catálogo canónico (249 países/territorios ISO 3166-1 + ANT y SCG
 *                    históricos + XKX Kosovo), con nombre en español e inglés.
 * - pais_iso_alias:  nombres normalizados (sin tildes, mayúsculas, sin puntuación)
 *                    => iso3. Incluye variantes INE/MERCOSUR/FAOSTAT y zonas francas /
 *                    "no declarado" (iso3 NULL, categoria ZONA_FRANCA / NO_DETERMINADO).
 * - pais_iso_codigo: códigos propios de una fuente => iso3 (esquema INE: código de país
 *                    del INE; esquema M49: excepciones al M49 estándar, p.ej. 736 Sudán).
 * - pais.iso_metodo: cómo se resolvió el ISO de cada fila de pais (CODIGO_INE, M49,
 *                    ALIAS, CARGADOR, MANUAL, ZONA_FRANCA, NO_DETERMINADO o SIN_MATCH).
 * - pais.iso_alpha3 pasa a tener FK -> pais_iso(iso3).
 * - Trigger trg_pais_iso: al insertar/editar una fila de pais la vincula sola, así que
 *   TODOS los cargadores (también MERCOSUR) quedan cubiertos sin cambiar su código.
 * - Vistas v_pais_canonico (una fila por pais de fuente con su ISO) y v_pais_iso_fuentes
 *   (una fila por ISO con los pais_id de cada fuente).
 */
return new class extends Migration
{
    private const ACENTOS_DE = 'ÁÀÂÄÃÅáàâäãåÉÈÊËéèêëÍÌÎÏíìîïÓÒÔÖÕóòôöõÚÙÛÜúùûüÑñÇçÝýÿÐð';
    private const ACENTOS_A  = 'AAAAAAaaaaaaEEEEeeeeIIIIiiiiOOOOOoooooUUUUuuuuNnCcYyyNn';

    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE pais_iso (
                iso3       CHAR(3) PRIMARY KEY CHECK (iso3 ~ '^[A-Z]{3}$'),
                iso2       CHAR(2) UNIQUE CHECK (iso2 ~ '^[A-Z]{2}$'),
                m49        SMALLINT UNIQUE,
                nombre_es  VARCHAR(100) NOT NULL,
                nombre_en  VARCHAR(100) NOT NULL,
                region     VARCHAR(40),
                subregion  VARCHAR(60),
                vigente    BOOLEAN NOT NULL DEFAULT TRUE
            )
        SQL);
        DB::statement("COMMENT ON TABLE pais_iso IS 'Catálogo canónico de países ISO 3166-1 (alpha-3, alpha-2, numérico M49). vigente=false: códigos históricos (ANT, SCG).'");

        DB::statement(<<<'SQL'
            CREATE TABLE pais_iso_alias (
                alias      VARCHAR(150) PRIMARY KEY,
                iso3       CHAR(3) REFERENCES pais_iso(iso3) ON UPDATE CASCADE ON DELETE RESTRICT,
                categoria  VARCHAR(20) NOT NULL DEFAULT 'PAIS'
                           CHECK (categoria IN ('PAIS','ZONA_FRANCA','NO_DETERMINADO')),
                origen     VARCHAR(20),
                CHECK ((categoria = 'PAIS') = (iso3 IS NOT NULL))
            )
        SQL);
        DB::statement('CREATE INDEX idx_pais_iso_alias_iso3 ON pais_iso_alias (iso3)');
        DB::statement("COMMENT ON TABLE pais_iso_alias IS 'Nombre normalizado (geo_normalizar_nombre) => iso3. Para agregar una variante: INSERT con el alias ya normalizado.'");

        DB::statement(<<<'SQL'
            CREATE TABLE pais_iso_codigo (
                esquema        VARCHAR(10) NOT NULL,
                codigo         VARCHAR(20) NOT NULL,
                iso3           CHAR(3) REFERENCES pais_iso(iso3) ON UPDATE CASCADE ON DELETE RESTRICT,
                categoria      VARCHAR(20) NOT NULL DEFAULT 'PAIS'
                               CHECK (categoria IN ('PAIS','ZONA_FRANCA','NO_DETERMINADO')),
                nombre_origen  VARCHAR(150),
                PRIMARY KEY (esquema, codigo),
                CHECK ((categoria = 'PAIS') = (iso3 IS NOT NULL))
            )
        SQL);
        DB::statement("COMMENT ON TABLE pais_iso_codigo IS 'Código de país propio de una fuente => iso3. Esquema INE = código de país del INE Bolivia; M49 = excepciones al M49 estándar.'");

        $this->cargarCsv('pais_iso.csv', 'pais_iso', function (array $r) {
            return [
                'iso3' => $r['iso3'], 'iso2' => $r['iso2'] ?: null,
                'm49' => $r['m49'] !== '' ? (int) $r['m49'] : null,
                'nombre_es' => $r['nombre_es'], 'nombre_en' => $r['nombre_en'],
                'region' => $r['region'] ?: null, 'subregion' => $r['subregion'] ?: null,
                'vigente' => $r['vigente'] === 't',
            ];
        });
        $this->cargarCsv('pais_iso_alias.csv', 'pais_iso_alias', fn (array $r) => [
            'alias' => $r['alias'], 'iso3' => $r['iso3'] ?: null,
            'categoria' => $r['categoria'], 'origen' => $r['origen'] ?: null,
        ]);
        $this->cargarCsv('pais_iso_codigo.csv', 'pais_iso_codigo', fn (array $r) => [
            'esquema' => $r['esquema'], 'codigo' => $r['codigo'], 'iso3' => $r['iso3'] ?: null,
            'categoria' => $r['categoria'], 'nombre_origen' => $r['nombre_origen'] ?: null,
        ]);

        // ---------------------------------------------------------------- funciones
        $de = self::ACENTOS_DE;
        $a  = self::ACENTOS_A;
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION geo_normalizar_nombre(t TEXT) RETURNS TEXT
            LANGUAGE sql IMMUTABLE AS \$fn\$
                SELECT NULLIF(btrim(regexp_replace(regexp_replace(
                    upper(translate(coalesce(t, ''), '{$de}', '{$a}')),
                    '[^A-Z0-9 ]+', ' ', 'g'), '\\s+', ' ', 'g')), '')
            \$fn\$
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION geo_esquema_pais(p_fuente_id INT) RETURNS TEXT
            LANGUAGE sql STABLE AS $fn$
                SELECT CASE upper(o.sigla) WHEN 'INE' THEN 'INE' ELSE 'M49' END
                FROM fuente_datos f JOIN organizacion o ON o.organizacion_id = f.organizacion_id
                WHERE f.fuente_id = p_fuente_id
            $fn$
        SQL);

        // Orden: 1) código de la fuente (pais_iso_codigo), 2) M49 estándar, 3) alpha-2/3,
        // 4) nombre por alias (nombre tal cual, sin paréntesis, "B, A" -> "A B"),
        // 5) regla "ZONA FRANCA ...". Si el nombre dice zona franca / no determinado,
        // eso gana sobre un código numérico (MERCOSUR inventa códigos para esas filas).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION geo_resolver_pais_iso(
                p_esquema TEXT, p_codigo TEXT, p_nombre TEXT,
                OUT iso3 CHAR(3), OUT categoria TEXT, OUT metodo TEXT)
            LANGUAGE plpgsql STABLE AS $fn$
            DECLARE
                v_cod  TEXT := NULLIF(btrim(coalesce(p_codigo, '')), '');
                v_raw  TEXT := btrim(coalesce(p_nombre, ''));
                v_sin  TEXT;
                v_cands TEXT[] := '{}';
                v_base TEXT;
                v_m    TEXT[];
                v_n    TEXT;
                n_iso  CHAR(3); n_cat TEXT;
                c_iso  CHAR(3); c_cat TEXT; c_met TEXT;
            BEGIN
                -- Nombre
                IF v_raw <> '' THEN
                    v_sin := regexp_replace(v_raw, '\([^)]*\)', '', 'g');
                    FOREACH v_base IN ARRAY ARRAY[v_raw, v_sin] LOOP
                        v_n := geo_normalizar_nombre(v_base);
                        IF v_n IS NOT NULL AND NOT v_n = ANY(v_cands) THEN v_cands := v_cands || v_n; END IF;
                    END LOOP;
                    FOREACH v_base IN ARRAY ARRAY[v_raw, v_sin] LOOP
                        v_m := regexp_match(btrim(v_base), '^([^,.]+)[,.]\s*(.+)$');
                        IF v_m IS NOT NULL THEN
                            v_n := geo_normalizar_nombre(v_m[2] || ' ' || v_m[1]);
                            IF v_n IS NOT NULL AND NOT v_n = ANY(v_cands) THEN v_cands := v_cands || v_n; END IF;
                        END IF;
                    END LOOP;
                    SELECT a.iso3, a.categoria INTO n_iso, n_cat
                    FROM pais_iso_alias a WHERE a.alias = ANY(v_cands)
                    ORDER BY array_position(v_cands, a.alias::text) LIMIT 1;
                    IF n_cat IS NULL AND v_cands[1] LIKE 'ZONA FRANCA%' THEN n_cat := 'ZONA_FRANCA'; END IF;
                END IF;

                IF n_cat IN ('ZONA_FRANCA', 'NO_DETERMINADO') THEN
                    iso3 := NULL; categoria := n_cat; metodo := n_cat; RETURN;
                END IF;

                -- Código
                IF v_cod IS NOT NULL THEN
                    IF v_cod ~ '^-?[0-9]+(\.0+)?$' THEN
                        v_cod := (v_cod::numeric)::bigint::text;
                        SELECT c.iso3, c.categoria INTO c_iso, c_cat
                        FROM pais_iso_codigo c WHERE c.esquema = p_esquema AND c.codigo = v_cod;
                        IF c_cat IS NOT NULL THEN
                            c_met := 'CODIGO_' || p_esquema;
                        ELSIF p_esquema = 'M49' AND v_cod::bigint BETWEEN 1 AND 999 THEN
                            SELECT i.iso3 INTO c_iso FROM pais_iso i WHERE i.m49 = v_cod::int;
                            IF c_iso IS NOT NULL THEN c_cat := 'PAIS'; c_met := 'M49'; END IF;
                        END IF;
                    ELSIF upper(v_cod) ~ '^[A-Z]{2,3}$' THEN
                        SELECT i.iso3 INTO c_iso FROM pais_iso i
                        WHERE i.iso3 = upper(v_cod) OR i.iso2 = upper(v_cod) LIMIT 1;
                        IF c_iso IS NOT NULL THEN c_cat := 'PAIS'; c_met := 'ISO'; END IF;
                    END IF;
                END IF;

                IF c_cat IS NOT NULL THEN
                    iso3 := c_iso; categoria := c_cat; metodo := c_met; RETURN;
                END IF;
                IF n_cat IS NOT NULL THEN
                    iso3 := n_iso; categoria := n_cat; metodo := 'ALIAS'; RETURN;
                END IF;
                iso3 := NULL; categoria := NULL; metodo := NULL;
            END
            $fn$
        SQL);

        // ---------------------------------------------------------------- pais
        DB::statement('ALTER TABLE pais ADD COLUMN iso_metodo VARCHAR(20)');
        DB::statement("COMMENT ON COLUMN pais.iso_metodo IS 'Cómo se obtuvo iso_alpha3: CODIGO_INE, CODIGO_M49, M49, ISO, ALIAS, CARGADOR, MANUAL, ZONA_FRANCA, NO_DETERMINADO o SIN_MATCH. NULL = pendiente (el trigger lo resuelve).'");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION geo_pais_vincular_iso() RETURNS trigger
            LANGUAGE plpgsql AS $fn$
            DECLARE r RECORD; v_iso3 CHAR(3);
            BEGIN
                NEW.iso_alpha3 := NULLIF(upper(btrim(NEW.iso_alpha3)), '');
                -- Edición manual del ISO (p.ej. desde Catálogos): se respeta y se valida.
                IF TG_OP = 'UPDATE' AND NEW.iso_alpha3 IS DISTINCT FROM OLD.iso_alpha3
                   AND NEW.iso_metodo IS NOT DISTINCT FROM OLD.iso_metodo THEN
                    IF NEW.iso_alpha3 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pais_iso WHERE iso3 = NEW.iso_alpha3) THEN
                        RAISE EXCEPTION 'El código ISO3 % no existe en pais_iso', NEW.iso_alpha3;
                    END IF;
                    NEW.iso_metodo := 'MANUAL';
                ELSIF TG_OP = 'UPDATE' AND NEW.iso_metodo IS NOT NULL
                   AND (NEW.iso_metodo = 'MANUAL'
                        OR (NEW.codigo_pais = OLD.codigo_pais AND NEW.nombre = OLD.nombre)) THEN
                    NULL; -- nada que re-resolver
                ELSE
                    SELECT * INTO r FROM geo_resolver_pais_iso(geo_esquema_pais(NEW.fuente_id), NEW.codigo_pais::text, NEW.nombre);
                    IF r.iso3 IS NOT NULL THEN
                        NEW.iso_alpha3 := r.iso3; NEW.iso_metodo := r.metodo;
                    ELSIF NEW.iso_alpha3 IS NOT NULL AND EXISTS (SELECT 1 FROM pais_iso WHERE iso3 = NEW.iso_alpha3) THEN
                        NEW.iso_metodo := 'CARGADOR';
                    ELSIF r.categoria IS NOT NULL THEN
                        NEW.iso_alpha3 := NULL; NEW.iso_metodo := r.categoria;
                    ELSE
                        -- alpha-2 válido traído por el cargador
                        SELECT iso3 INTO v_iso3 FROM pais_iso WHERE iso2 = upper(btrim(NEW.iso_alpha2));
                        IF v_iso3 IS NOT NULL THEN
                            NEW.iso_alpha3 := v_iso3; NEW.iso_metodo := 'CARGADOR';
                        ELSE
                            NEW.iso_alpha3 := NULL; NEW.iso_metodo := 'SIN_MATCH';
                        END IF;
                    END IF;
                END IF;
                IF NEW.iso_alpha3 IS NOT NULL THEN
                    SELECT iso2 INTO NEW.iso_alpha2 FROM pais_iso WHERE iso3 = NEW.iso_alpha3;
                ELSIF NEW.iso_metodo <> 'MANUAL' THEN
                    NEW.iso_alpha2 := NULL;
                END IF;
                RETURN NEW;
            END
            $fn$
        SQL);
        DB::statement('CREATE TRIGGER trg_pais_iso BEFORE INSERT OR UPDATE ON pais FOR EACH ROW EXECUTE FUNCTION geo_pais_vincular_iso()');

        // Backfill de las filas existentes (iso_metodo NULL => el trigger resuelve).
        DB::statement('UPDATE pais SET iso_metodo = NULL');

        DB::statement('ALTER TABLE pais ADD CONSTRAINT fk_pais_iso_alpha3 FOREIGN KEY (iso_alpha3) REFERENCES pais_iso(iso3) ON UPDATE CASCADE ON DELETE SET NULL');
        DB::statement('CREATE INDEX idx_pais_iso_alpha3 ON pais (iso_alpha3)');

        // ---------------------------------------------------------------- vistas
        DB::statement(<<<'SQL'
            CREATE VIEW v_pais_canonico AS
            SELECT p.pais_id, p.fuente_id, o.organizacion_id, upper(o.sigla) AS fuente_sigla,
                   p.codigo_pais, p.nombre AS nombre_fuente,
                   p.iso_alpha3 AS iso3, i.iso2, i.m49, i.nombre_es AS nombre_canonico, i.nombre_en,
                   i.region, i.subregion, p.iso_metodo,
                   CASE WHEN p.iso_alpha3 IS NOT NULL THEN 'PAIS'
                        WHEN p.iso_metodo IN ('ZONA_FRANCA', 'NO_DETERMINADO') THEN p.iso_metodo
                        ELSE 'SIN_ISO' END AS categoria
            FROM pais p
            JOIN fuente_datos f ON f.fuente_id = p.fuente_id
            JOIN organizacion o ON o.organizacion_id = f.organizacion_id
            LEFT JOIN pais_iso i ON i.iso3 = p.iso_alpha3
        SQL);
        DB::statement(<<<'SQL'
            CREATE VIEW v_pais_iso_fuentes AS
            SELECT c.iso3, c.iso2, c.m49, c.nombre_canonico,
                   count(*) AS filas_pais,
                   array_agg(c.pais_id ORDER BY c.organizacion_id, c.pais_id) AS pais_ids,
                   string_agg(DISTINCT c.fuente_sigla, ', ') AS fuentes,
                   array_agg(c.pais_id) FILTER (WHERE c.fuente_sigla = 'INE')      AS pais_ids_ine,
                   array_agg(c.pais_id) FILTER (WHERE c.fuente_sigla = 'ALADI')    AS pais_ids_aladi,
                   array_agg(c.pais_id) FILTER (WHERE c.fuente_sigla = 'MERCOSUR') AS pais_ids_mercosur,
                   array_agg(c.pais_id) FILTER (WHERE c.fuente_sigla = 'FAOSTAT')  AS pais_ids_faostat
            FROM v_pais_canonico c
            WHERE c.iso3 IS NOT NULL
            GROUP BY c.iso3, c.iso2, c.m49, c.nombre_canonico
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_pais_iso_fuentes');
        DB::statement('DROP VIEW IF EXISTS v_pais_canonico');
        DB::statement('DROP TRIGGER IF EXISTS trg_pais_iso ON pais');
        DB::statement('DROP FUNCTION IF EXISTS geo_pais_vincular_iso()');
        DB::statement('ALTER TABLE pais DROP CONSTRAINT IF EXISTS fk_pais_iso_alpha3');
        DB::statement('DROP INDEX IF EXISTS idx_pais_iso_alpha3');
        DB::statement('ALTER TABLE pais DROP COLUMN IF EXISTS iso_metodo');
        DB::statement('DROP FUNCTION IF EXISTS geo_resolver_pais_iso(TEXT, TEXT, TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS geo_esquema_pais(INT)');
        DB::statement('DROP FUNCTION IF EXISTS geo_normalizar_nombre(TEXT)');
        DB::statement('DROP TABLE IF EXISTS pais_iso_codigo');
        DB::statement('DROP TABLE IF EXISTS pais_iso_alias');
        DB::statement('DROP TABLE IF EXISTS pais_iso');
    }

    private function cargarCsv(string $archivo, string $tabla, callable $map): void
    {
        $ruta = database_path('data/paises/'.$archivo);
        $fh = fopen($ruta, 'r');
        if (! $fh) {
            throw new \RuntimeException("No se pudo abrir {$ruta}");
        }
        $cab = fgetcsv($fh, 0, ',', '"', '');
        $lote = [];
        while (($fila = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($fila === [null] || count($fila) !== count($cab)) {
                continue;
            }
            $lote[] = $map(array_combine($cab, $fila));
            if (count($lote) === 200) {
                DB::table($tabla)->insert($lote);
                $lote = [];
            }
        }
        fclose($fh);
        if ($lote) {
            DB::table($tabla)->insert($lote);
        }
    }
};
