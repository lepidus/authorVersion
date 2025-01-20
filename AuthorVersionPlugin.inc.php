<?php

/**
 * @file plugins/generic/authorVersion/AuthorVersionPlugin.inc.php
 *
 * Copyright (c) 2020-2023 Lepidus Tecnologia
 * Copyright (c) 2020-2023 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @class AuthorVersionPlugin
 * @ingroup plugins_generic_authorVersion
 *
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class AuthorVersionPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('TemplateResource::getFilename', array($this, '_overridePluginTemplates')); // Para sobrescrever templates
            HookRegistry::register('Template::Workflow', array($this, 'addWorkflowModifications'));
            HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
            HookRegistry::register('Publication::canAuthorPublish', array($this, 'setAuthorCanPublishVersion'));
            HookRegistry::register('Dispatcher::dispatch', array($this, 'setupAuthorVersionHandler'));
            HookRegistry::register('Schema::get::publication', array($this, 'addOurFieldsToPublicationSchema'));
            HookRegistry::register('Publication::version', array($this, 'preventsDuplicationOfVersionJustification'));
            HookRegistry::register('Templates::Preprint::Details', array($this, 'showVersionJustificationOnPreprintDetails'));
            HookRegistry::register('TemplateManager::display', array($this, 'addNewVersionSubmissionTab'));
            HookRegistry::register('Submission::getMany::queryBuilder', array($this, 'modifySubmissionQueryBuilder'));
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.authorVersion.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.authorVersion.description');
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml';
    }

    public function setAuthorCanPublishVersion($hookName, $params)
    {
        return false;
    }

    public function addOurFieldsToPublicationSchema($hookName, $params)
    {
        $schema = & $params[0];

        $schema->properties->{'versionJustification'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function preventsDuplicationOfVersionJustification($hookName, $params)
    {
        $newPublication = &$params[0];
        $request = $params[2];

        $newPublication = Services::get('publication')->edit(
            $newPublication,
            ['versionJustification' => null],
            $request
        );

        return false;
    }

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr = & $params[1];
        $request = PKPApplication::get()->getRequest();
        $requestedPage = $templateMgr->getTemplateVars('requestedPage');

        if ($requestedPage == 'authorDashboard') {
            $templateMgr->registerFilter("output", array($this, 'addVersionJustificationButtonFilter'));
        }

        if ($requestedPage == 'workflow') {
            $templateMgr->registerFilter("output", array($this, 'addVersionJustificationButtonFilter'));
            $templateMgr->registerFilter("output", array($this, 'addDeleteVersionButtonFilter'));
        }

        return false;
    }

    public function addVersionJustificationButtonFilter($output, $templateMgr)
    {
        if (preg_match('/<span[^>]+class="pkpPublication__relation"/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posRelationsBeginning = $matches[0][1];

            $versionJustificationButton = $templateMgr->fetch($this->getTemplateResource('versionJustificationWorkflow.tpl'));

            $output = substr_replace($output, $versionJustificationButton, $posRelationsBeginning, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addVersionJustificationButtonFilter'));
        }
        return $output;
    }

    public function addDeleteVersionButtonFilter($output, $templateMgr)
    {
        $pattern = '/<template slot="actions">/';
        if (preg_match_all($pattern, $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posPubActionsBeginning = $matches[0][1][1];
            $patternLength = strlen($pattern);

            $deleteVersionButton = $templateMgr->fetch($this->getTemplateResource('deleteVersionButton.tpl'));

            $output = substr_replace($output, $deleteVersionButton, $posPubActionsBeginning + $patternLength, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addDeleteVersionButtonFilter'));
        }
        return $output;
    }

    public function loadResourcesToWorkflow($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];
        $request = Application::get()->getRequest();

        if ($template == 'authorDashboard/authorDashboard.tpl') {
            $this->addFormComponent($templateMgr, $request, 'SubmitVersionForm', 'submitVersion');
            $this->addFormComponent($templateMgr, $request, 'VersionJustificationForm', 'versionJustification');
        }

        if ($template == 'workflow/workflow.tpl') {
            $this->addFormComponent($templateMgr, $request, 'VersionJustificationForm', 'versionJustification');
            $this->addFormComponent($templateMgr, $request, 'DeleteVersionForm', 'deleteVersion');
        }

        $templateMgr->addStyleSheet(
            'authorVersionWorkflow',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/workflow.css',
            ['contexts' => ['backend']]
        );

        return false;
    }

    private function addFormComponent($templateMgr, $request, $formName, $actionOp)
    {
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');

        $this->import("classes.components.forms.$formName");
        $actionUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), "authorVersion/$actionOp", null, null, ['submissionId' => $submission->getId()]);
        $formComponent = new $formName($actionUrl, $submission);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$formComponent->id] = $formComponent->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    public function setupAuthorVersionHandler($hookName, $request)
    {
        $router = $request->getRouter();
        if (!($router instanceof \APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/authorVersion')) {
            $this->import('api.v1.authorVersion.AuthorVersionHandler');
            $handler = new AuthorVersionHandler();
        }

        if (!isset($handler)) {
            return;
        }

        $router->setHandler($handler);
        $handler->getApp()->run();
        exit;
    }

    public function showVersionJustificationOnPreprintDetails($hookName, $params)
    {
        $templateMgr = $params[1];
        $output = & $params[2];

        $publication = $templateMgr->get_template_vars('publication');

        $version = $publication->getData('version');
        $versionJustification = $publication->getData('versionJustification');

        if ($version > 1 and !is_null($versionJustification)) {
            $templateMgr->assign('versionJustification', $versionJustification);
            $output .= $templateMgr->fetch($this->getTemplateResource('versionJustificationBlock.tpl'));
        }

        return false;
    }

    public function addNewVersionSubmissionTab($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template !== 'dashboard/index.tpl') {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();
        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_submissions');

        $lists = $templateMgr->getState('components');
        $userRoles = $templateMgr->get_template_vars('userRoles');

        $includeAssignedEditorsFilter = array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], $userRoles);
        $includeIssuesFilter = array_intersect(
            [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            $userRoles
        );

        $newVersionListPanel = new \APP\components\listPanels\SubmissionsListPanel(
            'newVersion',
            __('plugins.generic.authorVersion.newVersionSubmissions'),
            [
                'apiUrl' => $apiUrl,
                'getParams' => [
                    'newVersion' => true,
                ],
                'lazyLoad' => true,
                'includeIssuesFilter' => $includeIssuesFilter,
                'includeAssignedEditorsFilter' => $includeAssignedEditorsFilter,
                'includeActiveSectionFiltersOnly' => true,
            ]
        );
        $panelConfig = $newVersionListPanel->getConfig();
        $panelConfig['filters'][0]['filters'] = [
            [
                'param' => 'nonSubmitted',
                'value' => true,
                'title' => __('plugins.generic.authorVersion.nonSubmittedVersions'),
            ]
        ];

        $lists[$newVersionListPanel->id] = $panelConfig;
        $templateMgr->setState(['components' => $lists]);

        $templateMgr->registerFilter("output", array($this, 'newVersionSubmissionTabFilter'));

        return false;
    }

    public function newVersionSubmissionTabFilter($output, $templateMgr)
    {
        if (preg_match('/<\/tab[^>]+>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $offset = $matches[0][1];

            $newOutput = substr($output, 0, $offset);
            $newOutput .= $templateMgr->fetch($this->getTemplateResource('newVersionSubmissionTab.tpl'));
            $newOutput .= substr($output, $offset);
            $output = $newOutput;
            $templateMgr->unregisterFilter('output', array($this, 'newVersionSubmissionTabFilter'));
        }
        return $output;
    }

    public function modifySubmissionQueryBuilder($hookName, $args)
    {
        $submissionQB = & $args[0];
        $requestArgs = $args[1];

        if (empty($requestArgs['newVersion'])) {
            return;
        }

        $this->import('classes.services.queryBuilders.AuthorVersionQueryBuilder');
        $submissionQB = new AuthorVersionQueryBuilder();
        $submissionQB
            ->filterByContext($requestArgs['contextId'])
            ->orderBy($requestArgs['orderBy'], $requestArgs['orderDirection'])
            ->assignedTo($requestArgs['assignedTo'])
            ->filterByStatus($requestArgs['status'])
            ->filterByStageIds($requestArgs['stageIds'])
            ->filterByIncomplete($requestArgs['isIncomplete'])
            ->filterByOverdue($requestArgs['isOverdue'])
            ->filterByDaysInactive($requestArgs['daysInactive'])
            ->filterByCategories(isset($requestArgs['categoryIds']) ? $requestArgs['categoryIds'] : null)
            ->filterByNewVersion($requestArgs['newVersion'], isset($requestArgs['nonSubmitted']) ? $requestArgs['nonSubmitted'] : false)
            ->searchPhrase($requestArgs['searchPhrase']);

        if (isset($requestArgs['count'])) {
            $submissionQB->limitTo($requestArgs['count']);
        }

        if (isset($requestArgs['offset'])) {
            $submissionQB->offsetBy($requestArgs['count']);
        }
    }
}
