<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

define('FORM_VERSION_JUSTIFICATION', 'versionJustification');

class VersionJustificationForm extends FormComponent
{
    public function __construct($action, $submission)
    {
        $this->action = $action;
        $this->id = FORM_VERSION_JUSTIFICATION;
        $this->method = 'POST';

        $publication = $submission->getLatestPublication();
        if(is_null($publication->getData('versionJustification'))) {
            $publication = $submission->getCurrentPublication();
        }

        $this->addField(new FieldText('versionJustification', [
            'label' => __('plugins.generic.authorVersion.lastVersionJustification'),
            'value' => $publication->getData('versionJustification'),
            'size' => 'large',
        ]));
    }
}
