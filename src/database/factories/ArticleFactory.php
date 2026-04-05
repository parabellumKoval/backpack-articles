<?php

namespace Backpack\Articles\database\factories;

use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $locales = array_keys(config('backpack.crud.locales', ['en' => 'English']));
        $locale = $this->faker->randomElement($locales);
        $title = $this->faker->sentence(5);
        $content = collect($this->faker->paragraphs(4))
            ->map(fn ($paragraph) => '<p>'.$paragraph.'</p>')
            ->implode('');

        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = Str::slug($this->faker->uuid());
        }

        $categoryId = null;
        if (Schema::hasTable('ak_article_categories')) {
            $categoryId = ArticleCategory::query()->orderBy('lft')->orderBy('id')->value('id');
        }

        return [
            'locale' => $locale,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->faker->paragraph(2, false),
            'content' => [$locale => $content],
            'status' => 'PUBLISHED',
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'extras' => [
                'reading_time_minutes' => $this->faker->numberBetween(2, 8),
            ],
            'images' => [
                [
                    'src' => $this->faker->imageUrl(640, 480, 'article', true),
                    'alt' => $title,
                ],
            ],
            'seo' => [
                'meta_title' => $title,
                'meta_description' => $this->faker->sentence(12),
            ],
        ];
    }
}
