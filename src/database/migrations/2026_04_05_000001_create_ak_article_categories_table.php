<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ak_article_categories')) {
            Schema::create('ak_article_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('ak_article_categories')->nullOnDelete();
                $table->json('name');
                $table->string('slug')->default('')->index();
                $table->json('storefronts')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('lft')->default(0)->index();
                $table->unsignedInteger('rgt')->default(0)->index();
                $table->unsignedInteger('depth')->default(0)->index();
                $table->timestamps();
            });
        }

        $defaultCategoryId = DB::table('ak_article_categories')
            ->where('slug', 'main')
            ->value('id');

        if ($defaultCategoryId === null) {
            $locales = array_keys(config('backpack.crud.locales', ['en' => 'English']));
            if ($locales === []) {
                $locales = ['en'];
            }

            $name = [];
            foreach ($locales as $locale) {
                $name[$locale] = 'Main';
            }

            $defaultCategoryId = DB::table('ak_article_categories')->insertGetId([
                'parent_id' => null,
                'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'slug' => 'main',
                'storefronts' => null,
                'is_active' => 1,
                'lft' => 1,
                'rgt' => 2,
                'depth' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('ak_articles') && Schema::hasColumn('ak_articles', 'category_id')) {
            DB::table('ak_articles')
                ->whereNull('category_id')
                ->update(['category_id' => $defaultCategoryId]);

            DB::table('ak_articles')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('ak_article_categories')
                        ->whereColumn('ak_article_categories.id', 'ak_articles.category_id');
                })
                ->update(['category_id' => $defaultCategoryId]);

            try {
                Schema::table('ak_articles', function (Blueprint $table) {
                    $table->foreign('category_id')->references('id')->on('ak_article_categories')->restrictOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ak_articles')) {
            try {
                Schema::table('ak_articles', function (Blueprint $table) {
                    $table->dropForeign(['category_id']);
                });
            } catch (\Throwable $e) {
            }
        }

        Schema::dropIfExists('ak_article_categories');
    }
};
