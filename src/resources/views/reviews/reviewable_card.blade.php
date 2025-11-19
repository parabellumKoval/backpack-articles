{{--
Article Card for Reviews List
Displays article image and title (clickable)
--}}

@php
    $article = $reviewable ?? null;
    
    if (!$article) {
        echo '<span class="text-muted">—</span>';
        return;
    }
    
    // Get article data
    $image = $article->getFirstImageForApi()['url'] ?? null;
    $title = $article->title ?? 'Без названия';
    $editUrl = $editRoute ?? backpack_url('article/' . $article->id . '/edit');
    
    // Get review rating
    $rating = data_get($entry, 'rating', 0);
    
    // Rating configuration for stars
    $ratingColumn = [
        'name' => 'rating',
        'max' => 5,
        'color' => '#f2c200',
        'size' => '14px',
        'show_value' => true,
    ];
@endphp

<div class="reviewable-card article-card" style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
    {{-- Article Image --}}
    <div class="article-image" style="flex-shrink: 0;">
        @if($image)
            <img src="{{ $image }}" 
                 alt="{{ $title }}" 
                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e0e0e0;">
        @else
            <div style="width: 60px; height: 60px; background: #f5f5f5; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 1px solid #e0e0e0;">
                <i class="la la-file-alt" style="font-size: 24px; color: #ccc;"></i>
            </div>
        @endif
    </div>
    
    {{-- Article Info --}}
    <div class="article-info" style="flex-grow: 1; min-width: 0;">
        {{-- Article Title (clickable) --}}
        <div class="article-title" style="margin-bottom: 4px;">
            <a href="{{ $editUrl }}" 
               style="color: #333; font-weight: 500; text-decoration: none; font-size: 14px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"
               title="{{ $title }}">
                {{ $title }}
            </a>
        </div>
        
        {{-- Rating Stars --}}
        @if($rating > 0)
        <div class="article-rating">
            @include('crud::columns.rating_stars', ['column' => $ratingColumn, 'entry' => $entry])
        </div>
        @endif
    </div>
</div>
