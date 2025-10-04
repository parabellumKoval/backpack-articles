<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_articles', function (Blueprint $table) {
            $table->id();
            $table->string('lang', 10)->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('title');
            $table->string('slug')->default('');
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('image')->nullable();
            $table->enum('status', ['PUBLISHED', 'DRAFT'])->default('PUBLISHED');
            $table->date('date')->nullable();
            $table->json('extras')->nullable();
            $table->json('seo')->nullable();
            $table->json('countries')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_articles');
    }
}
