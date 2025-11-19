<?php

namespace Backpack\Articles\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleContent extends Model
{
    use HasFactory;

    protected $table = 'ak_article_contents';

    protected $guarded = ['id'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
