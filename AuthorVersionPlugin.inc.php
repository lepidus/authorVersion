<?php
/**
 * @file plugins/generic/authorVersion/AuthorVersionPlugin.inc.php
 *
 * 
 *
 * @class AuthorVersionPlugin
 * @ingroup plugins_generic_authorVersion
 * 
 *
 */
import('lib.pkp.classes.plugins.GenericPlugin');
class AuthorVersionPlugin extends GenericPlugin {
	/**
	 * @copydoc GenericPlugin::register()
	 */


	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled($mainContextId)) {

			// Para sobrescrever um template
			HookRegistry::register('TemplateResource::getFilename', array($this, '_overridePluginTemplates'));
			
			HookRegistry::register('Publication::canAuthorPublish', array($this, 'setAuthorCanPublishVersion'));
		
		}
		return $success;
	}

	/**
	 * Provide a name for this plugin
	 *
	 * The name will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return __('plugins.generic.authorVersion.displayName');
	}

	/**
	 * Provide a description for this plugin
	 *
	 * The description will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 *
	 * @return string
	 */
	public function getDescription() {
		return __('plugins.generic.authorVersion.description');
	}

	
	/**
	 * Let authors publish a version when this plugin is enabled
	 *
	 * @param string $hookName string
	 * @param array $args
	 * @return boolean
	 */
	function setAuthorCanPublishVersion($hookName, $args) {
		return false;
	}

}
