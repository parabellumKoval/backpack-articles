<?php

namespace Backpack\Articles\app\Http\Controllers\Admin;

use Backpack\Articles\app\Http\Requests\ArticleRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;
use Backpack\Tag\app\Traits\TagFields;

/**
 * Class BannerCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ArticleCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    use HasImagesCrudComponents;
    use TagFields;

    public function setup()
    {
        $this->crud->setModel('Backpack\Articles\app\Models\Article');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/article');
        $this->crud->setEntityNameStrings('статья', 'Статьи');
    }

    protected function setupListOperation()
    {   

        $this->setupFilers();


        $this->addImagesColumn(['label' => 'Preview']);

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Название',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Статус',
        ]);
        
        CRUD::addColumn([
            'name' => 'published_at',
            'label' => 'Дата',
            'type' => 'datetime'
        ]);

        $this->setupTagColumns();
    }

    protected function setupCreateOperation()
    {
      $this->crud->setValidation(ArticleRequest::class);
			
      $langs = config('backpack.crud.locales', ['en' => 'English']);

      $this->crud->addField([
        'name' => 'lang',
        'label' => 'Язык',
        'type' => 'select_from_array',
        'options' => $langs,
        'tab' => 'Основное'
      ]);

      $this->crud->addField([
          'name' => 'title',
          'label' => 'Заголовок',
          'type' => 'text',
          'placeholder' => 'Название статьи',
          'tab' => 'Основное'
      ]);

      $this->crud->addField([
          'name' => 'slug',
          'label' => 'Slug (URL)',
          'type' => 'text',
          'hint' => 'Если оставить пустым будет сгенерирован из названия автоматически',
          'tab' => 'Основное'
      ]);

      $this->crud->addField([
          'name' => 'published_at',
          'label' => 'Дата публикации',
          'type' => 'datetime',
          'tab' => 'Основное'
      ]);

      $this->crud->addField([
          'name' => 'content',
          'label' => 'Содержание',
          'type' => 'ckeditor',
          'placeholder' => 'Полный текст',
          'tab' => 'Основное'
      ]);

      $this->crud->addField([
          'name' => 'excerpt',
          'label' => 'Краткое описание',
          'type' => 'ckeditor',
          'tab' => 'Основное'
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
          'tab' => 'Основное'
      ]);

      // $this->crud->addField([
      //     'label' => 'Category',
      //     'type' => 'relationship',
      //     'name' => 'category_id',
      //     'entity' => 'category',
      //     'attribute' => 'name',
      //     'inline_create' => true,
      //     'ajax' => true,
      // ]);
      
      $this->crud->addField([
          'name' => 'status',
          'label' => 'Статус',
          'type' => 'select_from_array',
          'options' => [
              'PUBLISHED' => 'PUBLISHED',
              'DRAFT' => 'DRAFT',
          ],
          'tab' => 'Основное'
      ]);

      $this->setupTagFields();
      $this->crud->modifyField('tags', ['tab' => 'Основное']);


        $this->addImagesField();

      // META TITLE
      $this->crud->addField([
        'name' => 'meta_title',
        'label' => "Meta Title", 
        'type' => 'text',
        'fake' => true, 
        'store_in' => 'seo',
        'tab' => 'SEO'
      ]);
      
      // META DESCRIPTION
      $this->crud->addField([
        'name' => 'meta_description',
        'label' => "Meta Description", 
        'type' => 'textarea',
        'fake' => true, 
        'store_in' => 'seo',
        'tab' => 'SEO'
      ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
