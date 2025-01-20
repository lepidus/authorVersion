<?php

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.mail.MailTemplate');

class AuthorVersionHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'authorVersion';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $this->_endpoints = array(
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/submitVersion',
                    'handler' => array($this, 'submitVersion'),
                    'roles' => $roles
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/deleteVersion',
                    'handler' => array($this, 'deleteVersion'),
                    'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]
                ),
                array(
                    'pattern' => $this->getEndpointPattern() . '/versionJustification',
                    'handler' => array($this, 'updateVersionJustification'),
                    'roles' => $roles
                ),
            ),
        );
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
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

        if (!is_null($publication->getData('versionJustification'))
            || $publication->getData('status') == STATUS_PUBLISHED
            || $publication->getData('version') == 1
        ) {
            return $response->withStatus(400);
        }

        $publicationService = Services::get('publication');
        $publicationService->edit($publication, ['versionJustification' => $versionJustification], $this->getRequest());

        $this->sendSubmittedVersionEmail($publication, $versionJustification);

        return $response->withStatus(200);
    }

    public function deleteVersion($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $deletingJustification = $requestParams['deletingJustification'];
        $submission = $this->getSubmission($slimRequest);
        $publication = $submission->getLatestPublication();

        if ($publication->getData('status') == STATUS_PUBLISHED or $publication->getData('version') == 1) {
            return $response->withStatus(400);
        }

        $this->sendDeletedVersionEmail($publication, $deletingJustification);
        Services::get('publication')->delete($publication);

        return $response->withStatus(200);
    }

    public function updateVersionJustification($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $versionJustification = $requestParams['versionJustification'];
        $submission = $this->getSubmission($slimRequest);
        $publication = $submission->getLatestPublication();

        if ($publication->getData('status') == STATUS_PUBLISHED || $publication->getData('version') == 1) {
            return $response->withStatus(400);
        }

        $publicationService = Services::get('publication');
        $publicationService->edit($publication, ['versionJustification' => $versionJustification], $this->getRequest());

        return $response->withStatus(200);
    }

    private function sendSubmittedVersionEmail($publication, $versionJustification)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $emailTemplate = 'SUBMITTED_VERSION_NOTIFICATION';
        $managers = $this->getManagersAssigned($publication);
        $params = [
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $publication->getData('submissionId')),
            'versionJustification' => $versionJustification
        ];

        $this->sendEmailTemplate($emailTemplate, $managers, $params);
    }

    private function sendDeletedVersionEmail($publication, $deletingJustification)
    {
        if (empty($authors)) {
            return;
        }

        $request = $this->getRequest();
        $context = $request->getContext();
        $emailTemplate = 'DELETED_VERSION_NOTIFICATION';
        $recipientAuthor = $publication->getPrimaryAuthor();

        if (!$recipientAuthor) {
            $recipientAuthor = $publication->getData('authors')[0];
        }

        $recipients = [
            ['email' => $recipientAuthor->getData('email'), 'name' => $recipientAuthor->getFullName()]
        ];

        $params = [
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'authorDashboard', 'submission', $publication->getData('submissionId')),
            'deletingJustification' => $deletingJustification
        ];

        $this->sendEmailTemplate($emailTemplate, $recipients, $params);
    }

    private function sendEmailTemplate(string $templateName, array $recipients, array $params)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $email = new MailTemplate($templateName, null, $context, false);
        $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));

        foreach ($recipients as $recipient) {
            $email->addRecipient($recipient['email'], $recipient['name']);
        }

        $email->sendWithParams($params);
    }

    private function getSubmission($slimRequest)
    {
        $queryParams = $slimRequest->getQueryParams();
        $submissionId = (int) $queryParams['submissionId'];

        $submissionService = Services::get('submission');
        return $submissionService->get($submissionId);
    }

    private function getManagersAssigned($publication): array
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        $allAssignments = $stageAssignmentDao->getBySubmissionAndStageId($publication->getData('submissionId'), WORKFLOW_STAGE_ID_PRODUCTION);
        $managers = array();

        while ($assignment = $allAssignments->next()) {
            $userId = $assignment->getUserId();

            if ($this->userIsManager($userId)) {
                $manager = $userDao->getById($userId);
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
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userGroupsOfUser = $userGroupDao->getByUserId($userId);
        $managerGroupName = 'preprint server manager';

        while ($userGroup = $userGroupsOfUser->next()) {
            if (strtolower($userGroup->getName('en_US')) == $managerGroupName) {
                return true;
            }
        }

        return false;
    }
}
