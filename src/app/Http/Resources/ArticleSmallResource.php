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
		    'image' => $this->image,
      ];
    }
}
