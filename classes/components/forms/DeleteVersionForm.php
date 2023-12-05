<?php

namespace APP\plugins\generic\authorVersion\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldText;

define('FORM_DELETE_VERSION', 'deleteVersionForm');

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
        $this->addField(new FieldText('deletingJustification', [
            'groupId' => 'default',
            'isRequired' => true,
            'label' => __('plugins.generic.authorVersion.deleteVersion.justification'),
            'description' => __('plugins.generic.authorVersion.deleteVersion.justification.description'),
            'size' => 'large'
        ]));
    }
}
