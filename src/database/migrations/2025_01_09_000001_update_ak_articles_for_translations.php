<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_article_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->constrained('ak_articles')
                ->cascadeOnDelete();
            $table->string('lang', 10);
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'lang']);
        });

        $defaultLocale = config('app.locale')
            ?? config('app.fallback_locale')
            ?? 'en';

        DB::table('ak_articles')
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($defaultLocale) {
                foreach ($articles as $article) {
                    $locale = $article->lang ?: $defaultLocale;

                    if ($locale === null || $locale === '') {
                        $locale = $defaultLocale;
                    }

                    DB::table('ak_article_contents')->insert([
                        'article_id' => $article->id,
                        'lang' => $locale,
                        'content' => $article->content,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $titleTranslations = [$locale => (string) $article->title];
                    $excerptTranslations = [];
                    if ($article->excerpt !== null) {
                        $excerptTranslations[$locale] = $article->excerpt;
                    }

                    $seoDecoded = $article->seo ? json_decode($article->seo, true) : null;
                    $seoTranslations = [];
                    if (is_array($seoDecoded) && $seoDecoded !== []) {
                        $seoTranslations[$locale] = $seoDecoded;
                    }

                    DB::table('ak_articles')
                        ->where('id', $article->id)
                        ->update([
                            'title' => json_encode($titleTranslations, JSON_UNESCAPED_UNICODE),
                            'excerpt' => $excerptTranslations === []
                                ? '{}'
                                : json_encode($excerptTranslations, JSON_UNESCAPED_UNICODE),
                            'seo' => $seoTranslations === []
                                ? '{}'
                                : json_encode($seoTranslations, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });

        DB::statement('ALTER TABLE `ak_articles` MODIFY `title` JSON NOT NULL');
        DB::statement('ALTER TABLE `ak_articles` MODIFY `excerpt` JSON NULL');
        DB::statement('ALTER TABLE `ak_articles` MODIFY `seo` JSON NULL');

        Schema::table('ak_articles', function (Blueprint $table) {
            $table->dropColumn(['content', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::table('ak_articles', function (Blueprint $table) {
            $table->string('lang', 10)->nullable()->after('id');
            $table->longText('content')->nullable()->after('slug');
        });

        DB::statement('ALTER TABLE `ak_articles` MODIFY `title` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `ak_articles` MODIFY `excerpt` TEXT NULL');

        Schema::dropIfExists('ak_article_contents');
    }
};
