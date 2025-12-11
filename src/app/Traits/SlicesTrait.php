<?php
namespace Backpack\Articles\app\Traits;

trait SlicesTrait {

    // protected $appends = ['content_slices'];

    public function getContentSlicesAttribute(): array
    {
        return static::splitContentToSlices($this->content ?? '');
    }

    /**
     * Разбить raw HTML на слайсы: html / image.
     */
    public static function splitContentToSlices(string $html): array
    {
        // Разбиваем по <img ...>, но сохраняем сами теги в массиве
        $pattern = '~(<img[^>]*>)~i';
        $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $slices = [];

        foreach ($parts as $part) {
            $part = (string) $part;

            if (stripos($part, '<img') === 0) {
                // IMAGE SLICE
                $slices[] = array_merge(
                    ['type' => 'image'],
                    static::parseImgTag($part)
                );
            } else {
                // HTML SLICE
                if (trim($part) === '') {
                    continue;
                }

                $slices[] = [
                    'type' => 'html',
                    'html' => $part,
                ];
            }
        }

        return $slices;
    }

    protected static function parseImgTag(string $tag): array
    {
        $attrs = [
            'src'    => null,
            'alt'    => null,
            'title'  => null,
            'width'  => null,
            'height' => null,
            'raw'    => $tag,
        ];

        foreach (['src','alt','title','width','height'] as $name) {
            if (preg_match('~'.$name.'\s*=\s*["\']([^"\']*)["\']~i', $tag, $m)) {
                $attrs[$name] = $m[1];
            }
        }

        $attrs['src'] = static::normalizeImageSrc($attrs['src']);

        return $attrs;
    }

    protected static function normalizeImageSrc(?string $src): ?string
    {
        if (!$src) {
            return $src;
        }

        $path = $src;
        $query = '';

        if (preg_match('~^https?://~i', $path) || strpos($path, '//') === 0) {
            $parsed = parse_url($path) ?: [];
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
        }

        if ($path === '') {
            return $src;
        }

        $path = '/'.ltrim($path, '/');

        $base = config('app.url') ?: env('APP_URL');
        if (!$base) {
            return $path.$query;
        }

        $base = rtrim($base, '/');

        return $base.$path.$query;
    }
}
