<?php

use PKP\handler\APIHandler;
use PKP\security\Role;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\submission\PKPSubmission;
use PKP\db\DAORegistry;
use APP\facades\Repo;
use APP\core\Application;
use Illuminate\Support\Facades\Mail;
use PKP\mail\Mailable;

class AuthorVersionHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'authorVersion';
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];
        $this->_endpoints = [
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/submitVersion',
                    'handler' => [$this, 'submitVersion'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/deleteVersion',
                    'handler' => [$this, 'deleteVersion'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/versionJustification',
                    'handler' => [$this, 'updateVersionJustification'],
                    'roles' => $roles
                ],
            ],
        ];
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function submitVersion($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $versionJustification = $requestParams['versionJustification'];
        $submission = $this->getSubmission($slimRequest);
        $publication = $submission->getLatestPublication();

        if(!is_null($publication->getData('versionJustification'))
            || $publication->getData('status') == PKPSubmission::STATUS_PUBLISHED
            || $publication->getData('version') == 1
        ) {
            return $response->withStatus(400);
        }

        Repo::publication()->edit($publication, ['versionJustification' => $versionJustification]);

        $this->sendSubmittedVersionEmail($publication, $versionJustification);

        return $response->withStatus(200);
    }

    public function deleteVersion($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $deletingJustification = $requestParams['deletingJustification'];
        $submission = $this->getSubmission($slimRequest);
        $publication = $submission->getLatestPublication();

        if($publication->getData('status') == PKPSubmission::STATUS_PUBLISHED or $publication->getData('version') == 1) {
            return $response->withStatus(400);
        }

        $this->sendDeletedVersionEmail($publication, $deletingJustification);
        Repo::publication()->delete($publication);

        return $response->withStatus(200);
    }

    public function updateVersionJustification($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $versionJustification = $requestParams['versionJustification'];
        $submission = $this->getSubmission($slimRequest);
        $publication = $submission->getLatestPublication();

        if($publication->getData('status') == PKPSubmission::STATUS_PUBLISHED || $publication->getData('version') == 1) {
            return $response->withStatus(400);
        }

        Repo::publication()->edit($publication, ['versionJustification' => $versionJustification], $this->getRequest());

        return $response->withStatus(200);
    }

    private function sendSubmittedVersionEmail($publication, $versionJustification)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $emailTemplateKey = 'SUBMITTED_VERSION_NOTIFICATION';
        $managers = $this->getManagersAssigned($publication);
        $params = [
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $publication->getData('submissionId')),
            'versionJustification' => $versionJustification
        ];

        $this->sendEmailTemplate($emailTemplateKey, $managers, $params);
    }

    private function sendDeletedVersionEmail($publication, $deletingJustification)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $emailTemplateKey = 'DELETED_VERSION_NOTIFICATION';
        $primaryAuthor = $publication->getPrimaryAuthor();
        $recipients = [
            ['email' => $primaryAuthor->getData('email'), 'name' => $primaryAuthor->getFullName()]
        ];

        $params = [
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'authorDashboard', 'submission', $publication->getData('submissionId')),
            'deletingJustification' => $deletingJustification
        ];

        $this->sendEmailTemplate($emailTemplateKey, $recipients, $params);
    }

    private function sendEmailFromTemplate(string $templateKey, array $recipients, array $params)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            $templateKey
        );

        $email = new Mailable($params);
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to($recipients);
        $email->subject($emailTemplate->getSubject());
        $email->body($emailTemplate->getBody());

        Mail::send($email);
    }

    private function getSubmission($slimRequest)
    {
        $queryParams = $slimRequest->getQueryParams();
        $submissionId = (int) $queryParams['submissionId'];

        return Repo::submission()->get($submissionId);
    }

    private function getManagersAssigned($publication): array
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $allAssignments = $stageAssignmentDao->getBySubmissionAndStageId($publication->getData('submissionId'), WORKFLOW_STAGE_ID_PRODUCTION);
        $managers = array();

        while ($assignment = $allAssignments->next()) {
            $userId = $assignment->getUserId();

            if($this->userIsManager($userId)) {
                $manager = Repo::user()->get($userId);
                $managers[] = [
                    'email' => $manager->getEmail(),
                    'name' => $manager->getFullName()
                ];
            }
        }

        return $managers;
    }

    private function userIsManager($userId): bool
    {
        $userGroupsOfUser = Repo::userGroup()->getCollector()
            ->filterByUserIds([$userId])
            ->getMany();
        $managerGroupName = 'preprint server manager';

        foreach ($userGroupsOfUser as $userGroup) {
            if(strtolower($userGroup->getName('en_US')) == $managerGroupName) {
                return true;
            }
        }

        return false;
    }
}
