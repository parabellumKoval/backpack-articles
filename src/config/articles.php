<?php

return [
  'per_page' => 12,

  'class' => 'Backpack\Articles\app\Models\Article',

  'image' => [
    'enable' => true,
    'base_path' => 'blog/',
  ],

  'resource' => [
    'small' => 'Backpack\Articles\app\Http\Resources\ArticleSmallResource',
    'large' => 'Backpack\Articles\app\Http\Resources\ArticleLargeResource'
  ]
];
