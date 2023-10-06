<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldHTML;

define('FORM_DELETE_VERSION', 'deleteVersion');

class DeleteVersionForm extends FormComponent
{
    public function __construct($action)
    {
        $this->id = FORM_DELETE_VERSION;
        $this->action = $action;
        $this->method = 'POST';

        $this->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('common.delete')
            ],
        ]);
        $this->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);
        $this->addField(new FieldHTML('confirmation', [
            'description' => __('plugins.generic.authorVersion.deleteVersion.confirmation'),
            'groupId' => 'default',
        ]));
    }
}
