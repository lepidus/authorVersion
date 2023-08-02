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
            HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
            HookRegistry::register('Publication::canAuthorPublish', array($this, 'setAuthorCanPublishVersion'));
            HookRegistry::register('Dispatcher::dispatch', array($this, 'setupAuthorVersionHandler'));
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


    public function setAuthorCanPublishVersion($hookName, $args)
    {
        return false;
    }

    public function loadResourcesToWorkflow($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
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
        
        $this->import('classes.components.forms.SubmitVersionForm');
        $submitVersionUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'authorVersion/submitVersion');
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

}
