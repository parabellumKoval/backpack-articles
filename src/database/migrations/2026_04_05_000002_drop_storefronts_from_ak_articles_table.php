<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ak_articles') && Schema::hasColumn('ak_articles', 'storefronts')) {
            Schema::table('ak_articles', function (Blueprint $table) {
                $table->dropColumn('storefronts');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ak_articles') && !Schema::hasColumn('ak_articles', 'storefronts')) {
            Schema::table('ak_articles', function (Blueprint $table) {
                $table->json('storefronts')->nullable()->after('countries');
            });
        }
    }
};
