<?php

namespace APP\plugins\generic\authorVersion\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

class SubmitVersionForm extends FormComponent
{
    public function __construct($action)
    {
        $this->action = $action;
        $this->id = 'submitVersionForm';
        $this->method = 'POST';

        $this->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('form.submit')
            ],
        ]);
        $this->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);
        $this->addField(new FieldText('versionJustification', [
            'groupId' => 'default',
            'isRequired' => true,
            'label' => __('plugins.generic.authorVersion.submitVersionModal.versionJustification'),
            'description' => __('plugins.generic.authorVersion.submitVersionModal.versionJustification.description'),
            'size' => 'large'
        ]));
    }
}
