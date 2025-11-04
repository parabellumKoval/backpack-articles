<?php

namespace Backpack\Articles\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Articles\database\factories\ArticleFactory;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// Images
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Backpack\Tag\app\Traits\Taggable;

class Article extends Model
{
    use CrudTrait;
    use HasFactory;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasImages;
    use Taggable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_articles';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    protected $dates = ['published_at'];

    protected $casts = [
      'extras' => 'array',
      'seo' => 'array',
      'images' => 'array'
    ];

    protected $fakeColumns = [
      'seo'
    ];
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function toArray(){
        $tags = $this->relationLoaded('tags')
            ? $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'text' => $tag->text,
                    'color' => $tag->color,
                ];
            })->toArray()
            : [];
	    return [
		    'id' => $this->id,
		    'title' => $this->title,
		    'slug' => $this->slug,
		    'excerpt' => $this->excerpt,
		    'content' => $this->content,
		    'image' => $this->getFirstImageForApi(),
            'seo' => $this->seo,
            'tags' => $tags,
	    ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return ArticleFactory::new();
    }
    
    protected static function boot()
    {
         parent::boot();
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }

    
    public static function imageProviderName(?string $attribute = null): string
    {
        return 'local';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'articles';
    }

    // public static function imageFieldPrefix(): string
    // {
    //     return (string) config('services.cdn.articles_url', '/');
    // }

    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_title',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopePublished($query)
    {
      return $query->where('status', 'PUBLISHED');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * getSlugOrTitleAttribute
     *
     * @return void
     */
    public function getSlugOrTitleAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }
        return $this->title;
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
