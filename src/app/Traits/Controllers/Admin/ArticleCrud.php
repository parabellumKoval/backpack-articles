<?php

namespace App\Http\Controllers\Admin\Traits;

trait ArticleCrud {
  
  // Extends of SetupCreateOperation
  public function createOperation() {
        
    // $this->crud->addField([
    //     'name'  => 'faq',
    //     'label' => 'Вопрос/ответ',
    //     'type'  => 'repeatable',
    //     'fields' => [
    //         [
    //             'name'    => 'q',
    //             'type'    => 'text',
    //             'label'   => 'Вопрос',
    //         ],
    //         [
    //             'name'    => 'a',
    //             'type' => 'ckeditor',
    //             'label'   => 'Ответ',
    //         ],
    //     ],
    
    //     // optional
    //     'new_item_label'  => 'Добавить элемент',
    //     'init_rows' => 0,
    //     'min_rows' => 0,
    //     'max_rows' => 100,
    //     'store_in' => 'extras',
    //     'fake' => true,
    //     'tab' => 'Вопрос/Ответ'
    
    //   ]);
  }

}