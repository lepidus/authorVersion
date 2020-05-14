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

	/**
	 * @copydoc GenericPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled($mainContextId)) {

			// By default OPS installation will not allow authors to publish. Override the default so that custom publishing rulesets can be used.
			\HookRegistry::register('Publication::canAuthorPublish', [$this, 'setAuthorCanPublish']);

			// Add a new ruleset for publishing
			\HookRegistry::register('Publication::validatePublish', [$this, 'validate']);

			// Test validation rules in this plugin
			\HookRegistry::register('Publication::testAuthorValidatePublish', [$this, 'validateTest']);

			// Show plugin rules for editors in settings
			\HookRegistry::register('Settings::Workflow::listScreeningPlugins', [$this, 'listRules']);

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

	/**
	 * Let authors publish when this plugin is enabled
	 *
	 * @param string $hookName string
	 * @param array $args
	 * @return boolean
	 */
	function setAuthorCanPublish($hookName, $args) {
		return true;
	}

	/**
	 * Show plugin rules for editors in settings
	 *
	 * @param string $hookName string
	 * @param array $args
	 * @return array $rules
	 */
	function listRules($hookName, $args) {
		$rules =& $args[0];
		$pluginRules['hasPublishedBefore'] = 
			"<p>" . $this->getDisplayName() . "<br />\n" . 
			__('plugins.generic.versaoDoAutor.required.publishedBefore') . "</p>\n";
		$rules = array_merge($rules, $pluginRules);
		return $rules;
	}

	/**
	 * Validate publish, only apply rules to authors
	 *
	 * @param string $hookName string
	 * @param array $args [[
	 * 	@option array Additional parameters passed with the hook
	 * 	@option errors array
	 * 	@option Publication
	 * ]]
	 * @return array $errors
	 */
	function validate($hookName, $args) {
		$errors =& $args[0];
		$publication = $args[1];
		$currentUser = \Application::get()->getRequest()->getUser();
		$currentContext = \Application::get()->getRequest()->getContext();

		// Only apply rules to authors, editors can always publish if other criteria is met
		if ($this->_isAuthor($currentUser->getId(), $publication->getData('submissionId'))){

			$errors = array_merge(
				$errors,
				$this->applyRules($currentUser->getId(), $currentContext->getId(), $publication->getData('submissionId'))
			);

		}
		return false;
	}

	/**
	 * Test validation rules with any user, context and submission
	 * 
	 * @param string $hookName string
	 * @param array $args [[
	 * 	@option array Additional parameters passed with the hook
	 * 	@option int $userId
	 * 	@option int $contextId
	 * 	@option int $submissionId
	 * ]]
	 * @return array $errors
	 */
	function validateTest($hookName, $args) {
		$errors =& $args[0];
		$userId = $args[1];
		$contextId = $args[2];
		$submissionId = $args[3];
		$errors = array_merge($errors, $this->applyRules($userId, $contextId, $submissionId));
		return $errors;
	}

	/**
	 * Apply rules used in this screening plugin and return errors
	 * @param int $userId
	 * @param int $contextId
	 * @param int $submissionId
	 * @return array $errors
	 */
	function applyRules($userId, $contextId, $submissionId) {
		$errors = [];
		// Check that user has published before
		if (!$this->_hasPublishedBefore($userId, $contextId)) {
			$errors['hasPublishedBefore'] = __('plugins.generic.versaoDoAutor.required.publishedBefore');
		}
		return $errors;
	}

	//
	// Custom rules for this screening plugin
	//

	/**
	 * Check if user has published before in this context
	 * @param int $userId
	 * @param int $contextId
	 * @return boolean
	 */
	function _hasPublishedBefore($userId, $contextId) {
		$submissionsIterator = Services::get('submission')->getMany([
			'contextId' => $contextId,
			'status' => STATUS_PUBLISHED,
		]);
		foreach ($submissionsIterator as $submission) {
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
	 		$usersAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, null, $userId);
	 		if (!$usersAssignments->wasEmpty()){
	 			return true;
	 		}
 		}
		return false;
	}

	//
	// Helpers
	//

	/**
	 * Check if current user is the author of the submission
	 * @param int $userId
	 * @param int $submissionId
	 * @return boolean
	 */
	function _isAuthor($userId, $submissionId) {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$usersAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_AUTHOR, WORKFLOW_STAGE_ID_PRODUCTION, $userId);
		if (!$usersAssignments->wasEmpty()){
			return true;
		}
		return false;
	}

}
