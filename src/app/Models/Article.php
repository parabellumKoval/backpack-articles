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

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class Article extends Model
{
    use CrudTrait;
    use HasFactory;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;

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
    // protected $dates = [];

    protected $casts = [
      'extras' => 'array',
      'images' => 'array'
    ];

    protected $fakeColumns = ['meta_description', 'meta_title', 'extras_trans', 'seo', 'extras', 'images'];

    protected $translatable = ['title', 'excerpt', 'content', 'extras_trans', 'seo'];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function toArray(){
	    return [
		    'id' => $this->id,
		    'title' => $this->title,
		    'slug' => $this->slug,
		    'excerpt' => $this->excerpt,
		    'content' => $this->content,
		    'image' => $this->image,
        'seo' => $this->seo
	    ];
    }

    
    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_title',
            ],
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
     * getSlugOrNameAttribute
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


    /**
     * getImageAttribute
     *
     * Get first image from images array of the product or get image from parent product if exists 
     * 
     * @return Array|null Image is array(src, alt, title, size) 
     */
    public function getImageAttribute() {
      $image = $this->images[0] ?? null;

      return $image;
    }

    /**
     * getImageSrcAttribute
     *
     * Get src url address from getImageAttribute method
     * 
     * @return string|null string is image src url
     */
    public function getImageSrcAttribute() {
      $base_path = config('backpack.articles.image.base_path', '/');

      if(isset($this->image['src'])) {
        return $base_path . $this->image['src'];
      }else {
        return null;
      }
    }
    
    /**
     * getSeoArrayAttribute
     *
     * @return void
     */
    public function getSeoArrayAttribute() {
      return [
        'meta_title' => $this->seoDecoded->meta_title ?? null,
        'meta_description' => $this->seoDecoded->meta_description ?? null,
      ];
    }
    
    /**
     * getSeoDecodedAttribute
     *
     * @return void
     */
    public function getSeoDecodedAttribute() {
      return !empty($this->seo)? json_decode($this->seo): null;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
