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
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template != 'authorDashboard/authorDashboard.tpl') {
            return false;
        }

        $templateMgr->addJavaScript(
            'authorVersionWorkflowPage',
            $this->plugin->getPluginFullPath() . '/js/AuthorVersionWorkflowPage.js',
            [
                'priority' => STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->assign([
            'pageComponent' => 'AuthorVersionWorkflowPage',
        ]);

        return false;
    }

}
