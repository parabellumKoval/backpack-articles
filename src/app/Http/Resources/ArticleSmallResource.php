<?php

namespace Backpack\Articles\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleSmallResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
		    'id' => $this->id,
		    'title' => $this->title,
		    'slug' => $this->slug,
		    'excerpt' => $this->excerpt,
		    'image' => $this->getFirstImageForApi(),
        'time' =>  $this->time,
        'category' => $this->when($this->resource->relationLoaded('category') && $this->category, function () {
          return [
            'id' => $this->category->id,
            'name' => $this->category->name,
            'slug' => $this->category->slug,
          ];
        }),
        'tags' => $this->resource->relationLoaded('tags')
          ? $this->tags->map(function ($tag) {
              return [
                'id' => $tag->id,
                'text' => $tag->value ?? $tag->text,
              ];
            })->values()
          : [],
      ];
    }
}
