<?php

namespace Backpack\Articles\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'name' => ['required'],
            'slug' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:ak_article_categories,id'],
            'storefronts' => ['nullable', 'array'],
            'storefronts.*' => ['string', 'max:50'],
        ];
    }
}
