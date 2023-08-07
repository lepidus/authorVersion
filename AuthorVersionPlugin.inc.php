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
            HookRegistry::register('Templates::Preprint::Details', array($this, 'showVersionJustificationOnPreprintDetails'));
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
        $schema =& $params[0];

        $schema->properties->{'versionJustification'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr =& $params[1];
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

        if ($template != 'authorDashboard/authorDashboard.tpl') {
            return false;
        }

        $this->addSubmitVersionForm($templateMgr, $request);

        return false;
    }

    private function addSubmitVersionForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');
        $publication = $submission->getLatestPublication();

        $this->import('classes.components.forms.SubmitVersionForm');
        $submitVersionUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'authorVersion/submitVersion', null, null, ['publicationId' => $publication->getId()]);
        $submitVersionForm = new SubmitVersionForm($submitVersionUrl);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$submitVersionForm->id] = $submitVersionForm->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    public function setupAuthorVersionHandler($hookname, $request)
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
        $output =& $params[2];

        $publication = $templateMgr->get_template_vars('publication');

        $version = $publication->getData('version');
        $versionJustification = $publication->getData('versionJustification');

        if ($version > 1 and !is_null($versionJustification)) {
            $templateMgr->assign('versionJustification', $versionJustification);
            $output .= $templateMgr->fetch($this->getTemplateResource('versionJustificationBlock.tpl'));
        }

        return false;
    }

}
