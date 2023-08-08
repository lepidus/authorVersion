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
        $queryParams = $slimRequest->getQueryParams();

        $versionJustification = $requestParams['versionJustification'];
        $publicationId = (int) $queryParams['publicationId'];

        $publicationService = Services::get('publication');
        $publication = $publicationService->get($publicationId);
        $publicationService->edit($publication, ['versionJustification' => $versionJustification], $this->getRequest());

        $this->sendSubmittedVersionEmail($publication, $versionJustification);

        return $response->withStatus(200);
    }

    private function sendSubmittedVersionEmail($publication, $versionJustification)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $email = new MailTemplate('SUBMITTED_VERSION_NOTIFICATION', null, $context, false);
        $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));

        $managers = $this->getManagersAssigned($publication);
        foreach ($managers as $manager) {
            $email->addRecipient($manager->getEmail(), $manager->getFullName());
        }

        $submissionUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $publication->getData('submissionId'));

        $email->sendWithParams([
            'submissionTitle' => htmlspecialchars($publication->getLocalizedFullTitle()),
            'linkToSubmission' => $submissionUrl,
            'versionJustification' => $versionJustification
        ]);
    }

    private function getManagersAssigned($publication): array
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        $allAssignments = $stageAssignmentDao->getBySubmissionAndStageId($publication->getData('submissionId'), WORKFLOW_STAGE_ID_PRODUCTION);
        $managers = array();

        while ($assignment = $allAssignments->next()) {
            $userId = $assignment->getUserId();

            if($this->userIsManager($userId)) {
                $managers[] = $userDao->getById($userId);
            }
        }

        return $managers;
    }

    private function userIsManager($userId): bool
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userGroupsOfUser = $userGroupDao->getByUserId($userId);
        $managerGroupName = 'preprint server manager';

        while($userGroup = $userGroupsOfUser->next()) {
            if(strtolower($userGroup->getName('en_US')) == $managerGroupName) {
                return true;
            }
        }

        return false;
    }
}
