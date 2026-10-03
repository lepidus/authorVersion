<?php

namespace APP\plugins\generic\authorVersion\classes\components\forms;

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;

define('FORM_VERSION_JUSTIFICATION', 'versionJustification');

class VersionJustificationForm extends FormComponent
{
    public function __construct($action, $submission = null)
    {
        $this->action = $action;
        $this->id = FORM_VERSION_JUSTIFICATION;
        $this->method = 'POST';

        $publication = $submission ? $submission->getLatestPublication() : null;

        $this->addField(new FieldText('versionJustification', [
            'label' => __('plugins.generic.authorVersion.lastVersionJustification'),
            'value' => $publication ? $publication->getData('versionJustification') : '',
            'isRequired' => true,
            'size' => 'large',
        ]));
    }
}
