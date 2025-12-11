<?php

namespace Backpack\Articles\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'seo' => $this->seo,
            'tags' => $this->resource->relationLoaded('tags')
                ? $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'text' => $tag->text,
                    ];
                })->values()
                : [],
            'time' => $this->time,
            'content_slices' => $this->contentSlices,
        ];
    }
}
