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

        $templateMgr->registerFilter("output", array($this, 'addVersionJustificationButtonFilter'));

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

    public function loadResourcesToWorkflow($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];
        $request = Application::get()->getRequest();

        if ($template == 'authorDashboard/authorDashboard.tpl') {
            $this->addSubmitVersionForm($templateMgr, $request);
        }

        if ($template == 'authorDashboard/authorDashboard.tpl' or $template == 'workflow/workflow.tpl') {
            $this->addVersionJustificationForm($templateMgr, $request);
        }

        $templateMgr->addStyleSheet(
            'authorVersionWorkflow',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/workflow.css',
            ['contexts' => ['backend']]
        );

        return false;
    }

    private function addSubmitVersionForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');

        $this->import('classes.components.forms.SubmitVersionForm');
        $submitVersionUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'authorVersion/submitVersion', null, null, ['submissionId' => $submission->getId()]);
        $submitVersionForm = new SubmitVersionForm($submitVersionUrl);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$submitVersionForm->id] = $submitVersionForm->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    private function addVersionJustificationForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');

        $this->import('classes.components.forms.VersionJustificationForm');
        $updateJustificationUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'authorVersion/versionJustification', null, null, ['submissionId' => $submission->getId()]);
        $versionJustificationForm = new VersionJustificationForm($updateJustificationUrl, $submission);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$versionJustificationForm->id] = $versionJustificationForm->getConfig();

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
                    'newVersionSubmitted' => true,
                ],
                'lazyLoad' => true,
                'includeIssuesFilter' => $includeIssuesFilter,
                'includeAssignedEditorsFilter' => $includeAssignedEditorsFilter,
                'includeActiveSectionFiltersOnly' => true,
            ]
        );

        $lists[$newVersionListPanel->id] = $newVersionListPanel->getConfig();
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

        if (empty($requestArgs['newVersionSubmitted'])) {
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
            ->filterByNewVersion($requestArgs['newVersionSubmitted'])
            ->searchPhrase($requestArgs['searchPhrase']);

        if (isset($requestArgs['count'])) {
            $submissionQB->limitTo($requestArgs['count']);
        }

        if (isset($requestArgs['offset'])) {
            $submissionQB->offsetBy($requestArgs['count']);
        }
    }
}
