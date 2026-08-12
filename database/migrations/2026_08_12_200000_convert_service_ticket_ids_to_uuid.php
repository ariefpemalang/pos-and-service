<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_tickets') || ! $this->isIntegerKey('service_tickets', 'id')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        $ticketMap = [];
        foreach (DB::table('service_tickets')->get(['id']) as $row) {
            $ticketMap[(string) $row->id] = (string) Str::uuid7();
        }

        $logMap = [];
        if (Schema::hasTable('service_ticket_status_logs')) {
            foreach (DB::table('service_ticket_status_logs')->get(['id']) as $row) {
                $logMap[(string) $row->id] = (string) Str::uuid7();
            }
        }

        if (Schema::hasColumn('transactions', 'service_ticket_id')) {
            $this->dropForeignIfExists('transactions', 'service_ticket_id');

            Schema::table('transactions', function (Blueprint $table) {
                $table->uuid('service_ticket_uuid')->nullable()->after('service_ticket_id');
            });

            foreach (DB::table('transactions')->whereNotNull('service_ticket_id')->get(['id', 'service_ticket_id']) as $row) {
                DB::table('transactions')->where('id', $row->id)->update([
                    'service_ticket_uuid' => $ticketMap[(string) $row->service_ticket_id] ?? null,
                ]);
            }

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('service_ticket_id');
            });
            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('service_ticket_uuid', 'service_ticket_id');
            });
        }

        if (Schema::hasTable('service_ticket_status_logs')) {
            $this->dropForeignIfExists('service_ticket_status_logs', 'service_ticket_id');

            Schema::table('service_ticket_status_logs', function (Blueprint $table) {
                $table->uuid('uuid')->nullable();
                $table->uuid('service_ticket_uuid')->nullable();
            });

            foreach (DB::table('service_ticket_status_logs')->get(['id', 'service_ticket_id']) as $row) {
                DB::table('service_ticket_status_logs')->where('id', $row->id)->update([
                    'uuid' => $logMap[(string) $row->id],
                    'service_ticket_uuid' => $ticketMap[(string) $row->service_ticket_id] ?? null,
                ]);
            }

            $this->replaceIntegerPrimaryKey('service_ticket_status_logs', ['id', 'service_ticket_id']);
            Schema::table('service_ticket_status_logs', function (Blueprint $table) {
                $table->renameColumn('uuid', 'id');
                $table->renameColumn('service_ticket_uuid', 'service_ticket_id');
            });
            Schema::table('service_ticket_status_logs', function (Blueprint $table) {
                $table->primary('id');
            });
        }

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });

        foreach (DB::table('service_tickets')->get(['id']) as $row) {
            DB::table('service_tickets')->where('id', $row->id)->update([
                'uuid' => $ticketMap[(string) $row->id],
            ]);
        }

        $this->replaceIntegerPrimaryKey('service_tickets', ['id']);
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
        });
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->primary('id');
        });

        if (Schema::hasTable('service_ticket_status_logs')) {
            Schema::table('service_ticket_status_logs', function (Blueprint $table) {
                $table->foreign('service_ticket_id')->references('id')->on('service_tickets')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('transactions', 'service_ticket_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('service_ticket_id')->references('id')->on('service_tickets')->nullOnDelete();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // UUID keys are not converted back to auto-increment integers.
    }

    private function isIntegerKey(string $table, string $column): bool
    {
        $type = strtolower((string) Schema::getColumnType($table, $column));

        return in_array($type, ['bigint', 'integer', 'int', 'tinyint', 'smallint', 'mediumint'], true);
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if (! in_array($column, $foreign['columns'] ?? [], true)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($foreign) {
                $blueprint->dropForeign($foreign['name']);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function replaceIntegerPrimaryKey(string $table, array $columns): void
    {
        if (in_array('id', $columns, true) && $this->isIntegerKey($table, 'id')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL");
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->dropPrimary();
            $blueprint->dropColumn($columns);
        });
    }
};
