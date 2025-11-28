<?php

namespace Backpack\Articles\app\Events;

use Backpack\Articles\app\Models\Article;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $articleId,
        public string $slug,
        public string $action
    ) {
    }

    public static function for(Article $article, string $action): self
    {
        return new self($article->id, (string) $article->slug, $action);
    }
}
