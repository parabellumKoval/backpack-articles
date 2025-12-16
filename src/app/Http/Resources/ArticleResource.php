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
            'available_regions' => $this->resolveAvailableRegions(),
        ];
    }

    /**
     * Normalize available regions for hreflang usage.
     */
    protected function resolveAvailableRegions(): array
    {
        $regions = $this->countries ?? null;

        if (is_string($regions)) {
            $decoded = json_decode($regions, true);
            $regions = json_last_error() === JSON_ERROR_NONE ? $decoded : [$regions];
        }

        if (is_array($regions) && !empty($regions)) {
            return $this->normalizeRegions($regions);
        }

        return $this->fallbackRegions();
    }

    /**
     * Load default regions from store settings when article is global.
     */
    protected function fallbackRegions(): array
    {
        $facade = 'Backpack\\Store\\Facades\\Store';
        if (!class_exists($facade)) {
            return [];
        }

        $countries = $facade::countries() ?? [];
        if (empty($countries)) {
            return [];
        }

        return $this->normalizeRegions(array_keys($countries));
    }

    /**
     * Normalize region codes to a unique lower-case list.
     *
     * @param  array<int,string>  $regions
     * @return array<int,string>
     */
    protected function normalizeRegions(array $regions): array
    {
        $normalized = array_map(function ($value) {
            return strtolower(trim((string) $value));
        }, $regions);

        return array_values(array_unique(array_filter($normalized)));
    }
}
