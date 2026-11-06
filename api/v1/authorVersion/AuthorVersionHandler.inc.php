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
        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();

        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        // Same production stage access required by the OPS versionPublication endpoint
        $stageRoles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR];
        if ($routeName !== 'deleteVersion') {
            $stageRoles[] = ROLE_ID_AUTHOR;
        }
        import('lib.pkp.classes.security.authorization.StageRolePolicy');
        $this->addPolicy(new StageRolePolicy($stageRoles, WORKFLOW_STAGE_ID_PRODUCTION, false));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function submitVersion($slimRequest, $response, $args)
    {
        $requestParams = (array) $slimRequest->getParsedBody();
        $versionJustification = $requestParams['versionJustification'] ?? null;
        if (!$this->isValidJustification($versionJustification)) {
            return $response->withStatus(400);
        }
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
        $requestParams = (array) $slimRequest->getParsedBody();
        $deletingJustification = $requestParams['deletingJustification'] ?? null;
        if (!$this->isValidJustification($deletingJustification)) {
            return $response->withStatus(400);
        }
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
        $requestParams = (array) $slimRequest->getParsedBody();
        $versionJustification = $requestParams['versionJustification'] ?? null;
        if (!$this->isValidJustification($versionJustification)) {
            return $response->withStatus(400);
        }
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
            'versionJustification' => htmlspecialchars($versionJustification, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        ];

        $this->sendEmailTemplate($emailTemplate, $managers, $params);
    }

    private function sendDeletedVersionEmail($publication, $deletingJustification)
    {
        $authors = $publication->getData('authors');
        if (empty($authors)) {
            return;
        }

        $request = $this->getRequest();
        $context = $request->getContext();
        $emailTemplate = 'DELETED_VERSION_NOTIFICATION';
        $recipientAuthor = $publication->getPrimaryAuthor() ?? $authors[0];

        $recipients = [
            ['email' => $recipientAuthor->getData('email'), 'name' => $recipientAuthor->getFullName()]
        ];

        $params = [
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'authorDashboard', 'submission', $publication->getData('submissionId')),
            'deletingJustification' => htmlspecialchars($deletingJustification, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
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

    private function isValidJustification($justification): bool
    {
        return is_string($justification) && trim($justification) !== '';
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
