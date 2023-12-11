<?php

namespace APP\plugins\generic\authorVersion\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;
use PKP\security\Role;

class SubmittedVersionNotification extends Mailable
{
    use Configurable;

    protected static string $name = 'emails.submittedVersion.name';
    protected static string $description = 'emails.submittedVersion.description';
    protected static string $emailTemplateKey = 'SUBMITTED_VERSION_NOTIFICATION';
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER];

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
