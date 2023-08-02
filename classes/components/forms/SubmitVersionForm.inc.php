<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

class SubmitVersionForm extends FormComponent
{
    public function __construct($action)
    {
        $this->action = $action;
        $this->id = 'submitVersionForm';
        $this->method = 'POST';

        $this->addField(new FieldText('versionJustification', [
            'isRequired' => true,
            'label' => __('plugins.generic.authorVersion.submitVersionModal.versionJustification'),
            'description' => __('plugins.generic.authorVersion.submitVersionModal.versionJustification.description'),
            'size' => 'large'
        ]));
    }
}