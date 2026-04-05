<?php

namespace Backpack\Articles\app\Http\Controllers\Admin;

use Backpack\Articles\app\Http\Requests\ArticleRequest;
use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Models\ArticleCategory;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Helpers\Traits\Admin\HasSeoFilters;
use Backpack\Tag\app\Traits\TagFields;
use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;

class ArticleCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ServiceOperation;
    use HasImagesCrudComponents;
    use TagFields;
    use HasSeoFilters;
    use \Backpack\Helpers\Traits\Admin\HasToggleColumns;

    public function setup()
    {
        $this->crud->setModel(Article::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/article');
        $this->crud->setEntityNameStrings('статья', 'Статьи');
    }

    protected function setupListOperation()
    {
        $this->setupFilers();

        $countryOptions = $this->getCountryOptions();
        $categoryOptions = ArticleCategory::optionsForSelect();
        $storefrontOptions = ArticleCategory::storefrontOptions();

        $this->addSeoFilledFilter([
            'name' => 'seo_status',
            'label' => 'Заполнено SEO',
            'field' => 'seo',
            'properties' => ['meta_title', 'meta_description'],
            'options' => [
                'empty' => 'Не заполнено',
                'filled' => 'Заполнено',
            ],
        ]);

        $this->crud->addFilter([
            'name' => 'status',
            'label' => 'Статус',
            'type' => 'select2',
        ], [
            'PUBLISHED' => 'PUBLISHED',
            'DRAFT' => 'DRAFT',
        ], function ($status) {
            $value = strtoupper(trim((string) $status));

            if ($value !== '') {
                $this->crud->query->where('status', $value);
            }
        });

        $this->crud->addFilter([
            'name' => 'country',
            'label' => 'Страна',
            'type' => 'select2',
        ], $countryOptions, function ($country) {
            $normalized = strtolower(trim((string) $country));

            if ($normalized !== '') {
                $this->crud->query->whereJsonContains('countries', $normalized);
            }
        });

        if ($storefrontOptions !== []) {
            $this->crud->addFilter([
                'name' => 'storefront',
                'label' => 'Storefront',
                'type' => 'select2',
            ], $storefrontOptions, function ($storefront) {
                $categoryIds = ArticleCategory::visibleIdsForStorefront((string) $storefront);

                if ($categoryIds === []) {
                    $this->crud->query->whereRaw('1=0');
                    return;
                }

                $this->crud->query->whereIn('category_id', $categoryIds);
            });
        }

        $this->crud->addFilter([
            'name' => 'category_id',
            'label' => 'Категория статьи',
            'type' => 'select2',
        ], $categoryOptions, function ($value) {
            if (!is_numeric($value)) {
                return;
            }

            $ids = ArticleCategory::expandIdsToSubtree([(int) $value]);
            $this->crud->query->whereIn('category_id', $ids === [] ? [-1] : $ids);
        });

        $this->crud->addFilter([
            'name' => 'published_at',
            'label' => 'Дата публикации',
            'type' => 'date_range',
        ], false, function ($value) {
            $range = json_decode($value, true);

            if (!is_array($range)) {
                return;
            }

            if (!empty($range['from'])) {
                $this->crud->query->whereDate('published_at', '>=', $range['from']);
            }

            if (!empty($range['to'])) {
                $this->crud->query->whereDate('published_at', '<=', $range['to']);
            }
        });

        $this->addImagesColumn(['label' => 'Preview']);

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Название',
            'type' => 'text_progress',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
        ]);

        // CRUD::addColumn([
        //     'name' => 'category',
        //     'label' => 'Категория статьи',
        //     'type' => 'closure',
        //     'function' => function ($entry) {
        //         return $entry->category?->uniqTitle ?? '—';
        //     },
        // ]);

        CRUD::addColumn([
            'name' => 'seo',
            'label' => 'SEO',
            'type' => 'seo_status_linear',
            'seo_field' => 'seo',
            'properties' => [
                'meta_title' => 'Meta title',
                'meta_description' => 'Meta description',
            ],
            'empty_text' => 'Не заполнено',
        ]);

        $this->addToggleColumn([
            'name' => 'status',
            'label' => 'Статус',
            'toggle' => [
                'values' => [
                    'checked' => 'PUBLISHED',
                    'unchecked' => 'DRAFT',
                ],
            ],
        ]);

        CRUD::addColumn([
            'name' => 'published_at',
            'label' => 'Дата',
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'countries',
            'label' => 'Страны',
            'type' => 'closure',
            'function' => function ($entry) use ($countryOptions) {
                $codes = is_array($entry->countries) ? $entry->countries : [];

                if ($codes === []) {
                    return '—';
                }

                $labels = array_map(function ($code) use ($countryOptions) {
                    $normalized = strtolower((string) $code);

                    return $countryOptions[$normalized] ?? strtoupper($normalized);
                }, $codes);

                return implode(', ', $labels);
            },
        ]);

        CRUD::addColumn([
            'name' => 'category_storefront',
            'label' => 'Storefront',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->category?->getAdminStorefrontsLabel() ?? '—';
            },
        ]);

        $this->setupTagColumns();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ArticleRequest::class);

        $countryOptions = $this->getCountryOptions();
        $categoryOptions = ArticleCategory::optionsForSelect();

        $this->crud->addField([
            'name' => 'countries',
            'label' => 'Страны',
            'type' => 'select2_from_array',
            'options' => $countryOptions,
            'allows_null' => true,
            'allows_multiple' => true,
            'tab' => 'Основное',
            'hint' => 'Оставьте пустым, чтобы статья была доступна во всех странах',
        ]);

        $this->crud->addField([
            'name' => 'category_id',
            'label' => 'Категория статьи',
            'type' => 'select2_from_array',
            'options' => $categoryOptions,
            'allows_null' => false,
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'title',
            'label' => 'Заголовок',
            'type' => 'text',
            'placeholder' => 'Название статьи',
            'tab' => 'Основное',
            'translatable' => true,
        ]);

        $this->crud->addField([
            'name' => 'slug',
            'label' => 'Slug (URL)',
            'type' => 'text',
            'hint' => 'Если оставить пустым будет сгенерирован из названия автоматически',
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'published_at',
            'label' => 'Дата публикации',
            'type' => 'datetime',
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'content',
            'label' => 'Содержание',
            'type' => 'ckeditor',
            'placeholder' => 'Полный текст',
            'tab' => 'Основное',
            'translatable' => true,
        ]);

        $this->crud->addField([
            'name' => 'excerpt',
            'label' => 'Краткое описание',
            'type' => 'ckeditor',
            'tab' => 'Основное',
            'translatable' => true,
        ]);

        $this->crud->addField([
            'name' => 'reading_time_minutes',
            'label' => 'Минуты чтения',
            'type' => 'number',
            'attributes' => [
                'min' => 1,
                'step' => 1,
            ],
            'fake' => true,
            'store_in' => 'extras',
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'status',
            'label' => 'Статус',
            'type' => 'select_from_array',
            'options' => [
                'PUBLISHED' => 'PUBLISHED',
                'DRAFT' => 'DRAFT',
            ],
            'tab' => 'Основное',
        ]);

        $this->setupTagFields();
        $this->crud->modifyField('tags', ['tab' => 'Основное']);

        $this->addImagesField();

        $this->crud->addField([
            'name' => 'meta_title',
            'label' => 'Meta Title',
            'type' => 'countable_textarea',
            'fake' => true,
            'store_in' => 'seo',
            'tab' => 'SEO',
            'translatable' => true,
            'rows' => 2,
            'resizable' => true,
            'recommended_length' => 70,
        ]);

        $this->crud->addField([
            'name' => 'meta_description',
            'label' => 'Meta Description',
            'type' => 'countable_textarea',
            'fake' => true,
            'store_in' => 'seo',
            'tab' => 'SEO',
            'translatable' => true,
            'rows' => 3,
            'resizable' => true,
            'recommended_length' => 160,
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function getCountryOptions(): array
    {
        $options = config('articles.countries', []);

        if (!is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $code => $label) {
            $key = strtolower(is_int($code) ? (string) $label : (string) $code);
            $normalized[$key] = (string) $label;
        }

        return $normalized;
    }
}
