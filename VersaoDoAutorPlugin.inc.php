<?php
/**
 * @file plugins/generic/versao-do-autor/VersaoDoAutorPlugin.inc.php
 *
 * 
 *
 * @class VersaoDoAutorPlugin
 * @ingroup plugins_generic_versao-do-autor
 * 
 *
 */
import('lib.pkp.classes.plugins.GenericPlugin');
class VersaoDoAutorPlugin extends GenericPlugin {
	public $canPublish = 0;
	/**
	 * @copydoc GenericPlugin::register()
	 */
	
	// public function setPermissao($permissao){
	// 	$this->canPublish = $permissao;
	// }

	// public function getPermissao(){
	// 	return $this->canPublish;
	// }

	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled($mainContextId)) {

			// Para sobrescrever um template
			HookRegistry::register('TemplateResource::getFilename', array($this, '_overridePluginTemplates'));
			
			HookRegistry::register('Publication::version', array($this, 'publicar'));
			HookRegistry::register('Publication::publish::before',  array($this, 'add'));
			HookRegistry::register('Publication::canAuthorPublish', array($this, 'setAuthorCanPublishVersion'));
			
			error_log("canPublish: " . $this->canPublish);

			if($this->canPublish == 1){
				error_log("com permissão");
				// HookRegistry::register('Publication::canAuthorPublish', array($this, 'setAuthorCanPublishVersion'));
			}
			else{
				error_log("sem permissão");
				// HookRegistry::register('Publication::canAuthorPublish',  array($this, 'setAuthorCantPublish'));
			}
		
		}
		return $success;
	}

	//
	// Required functions for all OPS screening plugins
	//

	/**
	 * Provide a name for this plugin
	 *
	 * The name will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return __('plugins.generic.versaoDoAutor.displayName');
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
		return __('plugins.generic.versaoDoAutor.description');
	}

	function publicar($hookName, $args) {
		$this->canPublish = 1;
		error_log('Permissão:' . $this->canPublish);
		HookRegistry::call('Publication::canAuthorPublish', array($this));
		error_log("cria versão");
		error_log($hookName);
		return $this;
	}

	function add($hookName, $args){
		$this->canPublish = 0;
		error_log('Permissão:' . $this->canPublish);
		HookRegistry::call('Publication::canAuthorPublish', array($this));
		error_log("cria publicação");
		error_log($hookName);
		return $this;
	}
	/**
	 * Let authors publish a version when this plugin is enabled
	 *
	 * @param string $hookName string
	 * @param array $args
	 * @return boolean
	 */
	function setAuthorCanPublishVersion($hookName, $args) {
		error_log('autor pode  publicar');
		return true;
	}

		/**
	 * Let authors publish a publication when this plugin is enabled
	 *
	 * @param string $hookName string
	 * @param array $args
	 * @return boolean
	 */
	function setAuthorCantPublish($hookName, $args) {
		error_log('autor n pode publicar');
		return false;
	}


}
