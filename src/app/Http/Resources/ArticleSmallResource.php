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
        'tags' => $this->resource->relationLoaded('tags')
          ? $this->tags->map(function ($tag) {
              return [
                'id' => $tag->id,
                'text' => $tag->text,
                // 'color' => $tag->color,
              ];
            })->values()
          : [],
      ];
    }
}
