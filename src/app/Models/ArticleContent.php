<?php

namespace Backpack\Articles\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ArticleContent extends Model
{
    use HasFactory;
    use FormatsUniqAttribute;

    protected $table = 'ak_article_contents';

    protected $guarded = ['id'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function getUniqStringAttribute(): string
    {
        $article = $this->relationLoaded('article') ? $this->getRelation('article') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            strtoupper((string) $this->lang),
            $article?->title ?? sprintf('article #%s', $this->article_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $article = $this->relationLoaded('article') ? $this->getRelation('article') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            strtoupper((string) $this->lang),
        ]);

        return $this->formatUniqHtml($headline, [
            $article?->title ?? sprintf('article #%s', $this->article_id ?? '?'),
        ]);
    }
}
