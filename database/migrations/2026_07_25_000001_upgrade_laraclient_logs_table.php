<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades a v1 laraclient_logs table in place.
 *
 * v1 stored request_payload as the json_encode of the whole Guzzle options
 * array, headers and bearer tokens included. That column is dropped rather
 * than migrated: there is no safe way to un-leak credentials that are already
 * in it, and keeping it around would carry them forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laraclient_logs') || Schema::hasColumn('laraclient_logs', 'connection')) {
            return;
        }

        Schema::table('laraclient_logs', function (Blueprint $table) {
            $table->string('connection')->default('default')->after('id');
            $table->unsignedInteger('duration_ms')->nullable()->after('response_status');
            $table->unsignedTinyInteger('attempt')->default(1)->after('duration_ms');
            $table->boolean('cached')->default(false)->after('attempt');
            $table->json('request_headers')->nullable()->after('cached');
            $table->json('response_headers')->nullable()->after('request_headers');
            $table->text('exception')->nullable()->after('response_headers');

            $table->index(['connection', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('laraclient_logs', function (Blueprint $table) {
            $table->renameColumn('response_status', 'status');
        });

        Schema::table('laraclient_logs', function (Blueprint $table) {
            $table->dropColumn('request_payload');
        });

        Schema::table('laraclient_logs', function (Blueprint $table) {
            $table->longText('request_body')->nullable();
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: rolling back would recreate a column
    }
};
