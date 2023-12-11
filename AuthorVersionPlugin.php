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

namespace APP\plugins\generic\authorVersion;

use PKP\plugins\GenericPlugin;
use APP\core\Application;
use PKP\plugins\Hook;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use APP\template\TemplateManager;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionHandler;

class AuthorVersionPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('Template::Workflow', [$this, 'addWorkflowModifications']);
            Hook::add('TemplateManager::display', [$this, 'loadResourcesToWorkflow']);
            Hook::add('Publication::canAuthorPublish', [$this, 'setAuthorCanPublishVersion']);
            Hook::add('Dispatcher::dispatch', [$this, 'setupAuthorVersionHandler']);
            Hook::add('Schema::get::publication', [$this, 'addOurFieldsToPublicationSchema']);
            Hook::add('Publication::version', [$this, 'preventsDuplicationOfVersionJustification']);
            Hook::add('Templates::Preprint::Details', [$this, 'showVersionJustificationOnPreprintDetails']);
            Hook::add('TemplateManager::display', [$this, 'addNewVersionSubmissionTab']);
            Hook::add('Submission::Collector', [$this, 'modifySubmissionCollector']);
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
        $schema = &$params[0];

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

        $newPublication = Repo::publication()->edit($newPublication, ['versionJustification' => null]);

        return false;
    }

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr = &$params[1];
        $request = Application::get()->getRequest();
        $requestedPage = $templateMgr->getTemplateVars('requestedPage');

        if ($requestedPage == 'authorDashboard') {
            $templateMgr->registerFilter("output", [$this, 'replaceRelationsButtonFilter']);
            $templateMgr->registerFilter("output", [$this, 'addNewAuthorActionsFilter']);
            $templateMgr->registerFilter("output", [$this, 'addVersionJustificationButtonFilter']);
        }

        if ($requestedPage == 'workflow') {
            $templateMgr->registerFilter("output", [$this, 'addVersionJustificationButtonFilter']);
            $templateMgr->registerFilter("output", [$this, 'addDeleteVersionButtonFilter']);
        }

        return false;
    }

    public function replaceRelationsButtonFilter($output, $templateMgr)
    {
        if (
            preg_match('/class="pkpWorkflow__header"/', $output)
            && preg_match('/<span[^>]+class="pkpPublication__relation"/', $output, $matches, PREG_OFFSET_CAPTURE)
        ) {
            $blockStartPosition = $matches[0][1];

            preg_match('/<\/span>/', $output, $matches, PREG_OFFSET_CAPTURE, $blockStartPosition);
            $blockEndPosition = $matches[0][1] + strlen('</span>');

            $newRelationsButton = $templateMgr->fetch($this->getTemplateResource('relationsButton.tpl'));

            $output = substr_replace($output, $newRelationsButton, $blockStartPosition, $blockEndPosition - $blockStartPosition);
            $templateMgr->unregisterFilter('output', array($this, 'replaceRelationsButtonFilter'));
        }

        return $output;
    }

    public function addNewAuthorActionsFilter($output, $templateMgr)
    {
        if (
            preg_match('/class="pkpWorkflow__header"/', $output)
            && preg_match_all('/<\/pkp-header>/', $output, $matches, PREG_OFFSET_CAPTURE)
        ) {
            $insertPosition = $matches[0][1][1];

            $newAuthorActions = $templateMgr->fetch($this->getTemplateResource('newAuthorActions.tpl'));

            $output = substr_replace($output, $newAuthorActions, $insertPosition, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addNewAuthorActionsFilter'));
        }

        return $output;
    }

    public function addVersionJustificationButtonFilter($output, $templateMgr)
    {
        if (
            preg_match('/class="pkpWorkflow__header"/', $output)
            && preg_match('/<span[^>]+class="pkpPublication__relation"/', $output, $matches, PREG_OFFSET_CAPTURE)
        ) {
            $posRelationsBeginning = $matches[0][1];

            $versionJustificationButton = $templateMgr->fetch($this->getTemplateResource('versionJustificationWorkflow.tpl'));

            $output = substr_replace($output, $versionJustificationButton, $posRelationsBeginning, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addVersionJustificationButtonFilter'));
        }
        return $output;
    }

    public function addDeleteVersionButtonFilter($output, $templateMgr)
    {
        if (
            preg_match('/class="pkpWorkflow__header"/', $output)
            && preg_match_all('/<template slot="actions">/', $output, $matches, PREG_OFFSET_CAPTURE)
        ) {
            $posPubActionsBeginning = $matches[0][1][1];
            $patternLength = strlen('<template slot="actions">');

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
        $submission = $templateMgr->getTemplateVars('submission');
        $formName = 'APP\plugins\generic\authorVersion\classes\components\forms\\' . $formName;

        $actionUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), "authorVersion/$actionOp", null, null, ['submissionId' => $submission->getId()]);
        $formComponent = new $formName($actionUrl, $submission);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$formComponent->id] = $formComponent->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    public function setupAuthorVersionHandler($hookName, $params)
    {
        $request = $params[0];
        $router = $request->getRouter();

        if (!($router instanceof \PKP\core\APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/authorVersion')) {
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
        $output = &$params[2];

        $publication = $templateMgr->getTemplateVars('publication');

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
        $apiUrl = $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), '_submissions');

        $lists = $templateMgr->getState('components');
        $userRoles = $templateMgr->getTemplateVars('userRoles');

        $includeAssignedEditorsFilter = array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles);
        $includeIssuesFilter = array_intersect(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
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

    public function modifySubmissionCollector($hookName, $params)
    {
        $query = &$params[0];
        $request = Application::get()->getRequest();

        if ($request->getUserVar('newVersion')) {
            $query->leftJoin('publications as nvp', 'nvp.submission_id', '=', 's.submission_id')
                ->where('nvp.version', '>', 1)
                ->where('nvp.status', '!=', PKPSubmission::STATUS_PUBLISHED);

            $submittedVersionSubQuery = function ($query) {
                $query->select('publication_id')
                    ->from('publication_settings')
                    ->where('setting_name', '=', 'versionJustification');
            };

            $filterSubmitted = !$request->getUserVar('nonSubmitted');

            if ($filterSubmitted) {
                $query->whereIn('nvp.publication_id', $submittedVersionSubQuery);
            } else {
                $query->whereNotIn('nvp.publication_id', $submittedVersionSubQuery);
            }
        }
    }
}
