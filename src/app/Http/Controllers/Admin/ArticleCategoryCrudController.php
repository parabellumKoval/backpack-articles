<?php

namespace Backpack\Articles\app\Http\Controllers\Admin;

use Backpack\Articles\app\Http\Requests\ArticleCategoryRequest;
use Backpack\Articles\app\Models\ArticleCategory;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ArticleCategoryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    use \Backpack\Helpers\app\Http\Controllers\Operations\ReorderDeepOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ServiceOperation;
    use \Backpack\Helpers\Traits\Admin\HasToggleColumns;

    public function setup()
    {
        $this->crud->setModel(ArticleCategory::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/article-category');
        $this->crud->setEntityNameStrings('категория статьи', 'Категории статей');
    }

    protected function setupReorderOperation()
    {
        $this->crud->set('reorder.label', 'name');
        $this->crud->set('reorder.max_level', 5);
    }

    protected function setupListOperation()
    {
        $storefrontOptions = ArticleCategory::storefrontOptions();
        $parentOptions = ArticleCategory::optionsForSelect();

        $this->crud->addFilter([
            'name' => 'parent_id',
            'label' => 'Родитель',
            'type' => 'select2',
        ], $parentOptions, function ($value) {
            if (is_numeric($value)) {
                $this->crud->query->where('parent_id', (int) $value);
            }
        });

        $this->crud->addFilter([
            'name' => 'is_active',
            'label' => 'Статус',
            'type' => 'select2',
        ], [
            1 => 'Активные',
            0 => 'Скрытые',
        ], function ($value) {
            $this->crud->query->where('is_active', (int) $value);
        });

        if ($storefrontOptions !== []) {
            $this->crud->addFilter([
                'name' => 'storefront',
                'label' => 'Storefront',
                'type' => 'select2',
            ], $storefrontOptions, function ($value) {
                $visibleIds = ArticleCategory::visibleIdsForStorefront((string) $value);
                if ($visibleIds === []) {
                    $this->crud->query->whereRaw('1=0');
                    return;
                }

                $this->crud->query->whereIn('id', $visibleIds);
            });
        }

        $this->addToggleColumn([
            'name' => 'is_active',
            'label' => 'Статус',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text_progress',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
        ]);

        CRUD::addColumn([
            'name' => 'parent',
            'label' => 'Родитель',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->parent?->uniqTitle ?? '—';
            },
        ]);

        CRUD::addColumn([
            'name' => 'depth',
            'label' => 'Уровень',
        ]);

        CRUD::addColumn([
            'name' => 'admin_storefronts_label',
            'label' => 'Storefronts',
            'type' => 'model_function',
            'function_name' => 'getAdminStorefrontsLabel',
        ]);

        CRUD::addColumn([
            'name' => 'articles',
            'label' => 'Статьи',
            'type' => 'relationship_count',
            'suffix' => ' шт.',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ArticleCategoryRequest::class);

        $this->crud->addField([
            'name' => 'is_active',
            'label' => 'Активна',
            'type' => 'boolean',
            'default' => 1,
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'tab' => 'Основное',
            'translatable' => true,
        ]);

        $this->crud->addField([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'hint' => 'Если оставить пустым, будет сгенерирован из названия',
            'tab' => 'Основное',
        ]);

        $this->crud->addField([
            'name' => 'parent_id',
            'label' => 'Родительская категория',
            'type' => 'select2_from_array',
            'options' => $this->parentOptions(),
            'allows_null' => true,
            'tab' => 'Основное',
        ]);

        if (ArticleCategory::storefrontOptions() !== []) {
            $this->crud->addField([
                'name' => 'storefronts',
                'label' => 'Storefronts',
                'type' => 'select2_from_array',
                'options' => ArticleCategory::storefrontOptions(),
                'allows_multiple' => true,
                'allows_null' => true,
                'hint' => 'Пусто — категория попадёт в storefront по умолчанию или унаследует storefront родителя',
                'tab' => 'Основное',
            ]);
        }
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function parentOptions(): array
    {
        $currentId = \Route::current()?->parameter('id');

        return ArticleCategory::optionsForSelect(is_numeric($currentId) ? (int) $currentId : null);
    }
}
