<?php

namespace Backpack\Articles\app\Http\Controllers\Admin;

use Backpack\Articles\app\Http\Requests\ArticleRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
    
    use \App\Http\Controllers\Admin\Traits\ArticleCrud;

    public function setup()
    {
        $this->crud->setModel('Backpack\Articles\app\Models\Article');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/article');
        $this->crud->setEntityNameStrings('статья', 'Статьи');
    }

    protected function setupListOperation()
    {   
        $this->crud->addColumns([
            [
              'name' => 'id',
              'label' => 'ID',
            ],
            [
              'name' => 'image',
              'label' => 'Фото',
              'type' => 'image',
              'height' => '50px',
              'width'  => '50px',
            ],
            [
								'name' => 'title',
								'label' => 'Название',
						],
						[
								'name' => 'status',
								'label' => 'Статус',
						],
        ]);

        $this->listOperation();
    }

    protected function setupCreateOperation()
    {
      $this->crud->setValidation(ArticleRequest::class);
			

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
          'name' => 'date',
          'label' => 'Дата публикации',
          'type' => 'date',
          'default' => date('Y-m-d'),
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
          'name' => 'image',
          'label' => 'Изображение',
          'type' => 'browse',
          'tab' => 'Основное',
          'hint' => 'Названия файлов загруженных через файловый менеджен должны быть на латинице и без пробелов.'
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


      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        $this->updateOperation();
    }
}
