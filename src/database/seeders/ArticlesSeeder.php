<?php

namespace Backpack\Articles\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Articles\app\Models\Article;

class ArticlesSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      Article::factory()
          ->count(30)
          ->create();
    }
}
