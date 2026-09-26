<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0006: versi terbit tidak boleh kembali ke draft, dan hanya draft yang boleh dihapus.
 * Transisi sah: draft → published → superseded.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION entity_versions_guard_immutable() RETURNS trigger
                LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status <> 'draft' THEN
                        RAISE EXCEPTION 'entity version % is immutable (DELETE blocked)', OLD.id USING ERRCODE = 'insufficient_privilege';
                    END IF;
                    RETURN OLD;
                END IF;

                IF OLD.status <> 'draft' AND (
                    NEW.compiled_schema IS DISTINCT FROM OLD.compiled_schema
                    OR NEW.version <> OLD.version
                    OR NEW.entity_id <> OLD.entity_id
                    OR NEW.status = 'draft'
                    OR (OLD.status = 'superseded' AND NEW.status <> 'superseded')
                ) THEN
                    RAISE EXCEPTION 'entity version % is immutable', OLD.id USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER entity_versions_no_delete
                BEFORE DELETE ON entity_versions
                FOR EACH ROW EXECUTE FUNCTION entity_versions_guard_immutable();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS entity_versions_no_delete ON entity_versions;');
    }
};
