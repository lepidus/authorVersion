<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\components\forms\publication\RelationForm;
use APP\facades\Repo;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionHandler;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionCreationController;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionRelationsController;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionSubmissionsController;
use APP\plugins\generic\authorVersion\classes\components\forms\DeleteVersionForm;
use APP\plugins\generic\authorVersion\classes\components\forms\SubmitVersionForm;
use APP\plugins\generic\authorVersion\classes\components\forms\VersionJustificationForm;
use APP\plugins\generic\authorVersion\classes\NewVersionSubmissionFilter;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\core\APIRouter;
use PKP\core\PKPBaseController;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\Role;

/** Author-submitted publication versions for OPS 3.5. */
class AuthorVersionPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return $success;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', [$this, 'loadResourcesToWorkflow']);
            Hook::add('TemplateManager::display', [$this, 'addVersionNavigation']);
            Hook::add('Publication::canAuthorPublish', [$this, 'setAuthorCanPublishVersion']);
            Hook::add('APIHandler::endpoints::plugin', [$this, 'setupAuthorVersionHandler']);
            Hook::add('APIHandler::endpoints::_submissions', [$this, 'setupVersionSubmissionLists']);
            Hook::add('Schema::get::publication', [$this, 'addOurFieldsToPublicationSchema']);
            Hook::add('Publication::version', [$this, 'preventsDuplicationOfVersionJustification']);
            Hook::add('Publication::validate', [$this, 'validateLockedPublicationRelation']);
            Hook::add('Templates::Preprint::Details', [$this, 'showVersionJustificationOnPreprintDetails']);
            Hook::add('Dashboard::views', [$this, 'addNewVersionSubmissionViews']);
            Hook::add('Submission::Collector', [NewVersionSubmissionFilter::class, 'filterQuery']);
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
        $params[0]->properties->versionJustification = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
    }

    public function preventsDuplicationOfVersionJustification($hookName, $params)
    {
        $params[0] = Repo::publication()->edit($params[0], ['versionJustification' => null]);
        return false;
    }

    /** Apply the author relation lock to both native and plugin API writes. */
    public function validateLockedPublicationRelation($hookName, $params): bool
    {
        [, $publication, $props] = $params;
        if (
            !$publication
            || (int) $publication->getData('status') !== Submission::STATUS_PUBLISHED
            || (int) $publication->getData('relationStatus') !== Publication::PUBLICATION_RELATION_PUBLISHED
            || !array_intersect(['relationStatus', 'vorDoi'], array_keys($props))
        ) {
            return false;
        }

        $request = Application::get()->getRequest();
        if (!$request->getRouter() instanceof APIRouter) {
            return false;
        }

        $controller = PKPBaseController::getRouteController();
        $permitted = false;
        if ($controller) {
            $authorizedContext = $controller->getAuthorizedContext();
            $submission = $authorizedContext[Application::ASSOC_TYPE_SUBMISSION] ?? null;
            if (
                $submission
                && (int) $submission->getId() === (int) $publication->getData('submissionId')
                && isset($authorizedContext[Application::ASSOC_TYPE_USER_ROLES])
            ) {
                // Reuse native production access, excluding the author capability.
                $policy = new StageRolePolicy(
                    [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                    WORKFLOW_STAGE_ID_PRODUCTION,
                    false
                );
                $policy->setAuthorizedContext($authorizedContext);
                $permitted = $policy->effect() === AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        if (!$permitted) {
            // Even an unchanged status can clear the DOI when the native route
            // receives no vorDoi. Protect field presence, not just value changes.
            foreach (['relationStatus', 'vorDoi'] as $field) {
                if (array_key_exists($field, $props)) {
                    $params[0][$field][] = __('api.submissions.403.userCantEdit');
                }
            }
        }
        return false;
    }

    public function setupAuthorVersionHandler($hookName, $router)
    {
        $router->registerPluginApiControllers([new AuthorVersionHandler(), new AuthorVersionCreationController(), new AuthorVersionRelationsController()]);
        return false;
    }

    public function setupVersionSubmissionLists($hookName, $apiController, $handler)
    {
        $controller = new AuthorVersionSubmissionsController();
        $handler->addRoute('GET', 'authorVersion', $controller->getMany(...), 'authorVersion.list', [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER]);
        $handler->addRoute('GET', 'authorVersionAssigned', $controller->assigned(...), 'authorVersion.assigned', [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR]);
        return false;
    }

    public function addNewVersionSubmissionViews($hookName, $params)
    {
        $roles = $params[1];
        if (!array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR], $roles)) {
            return false;
        }
        $isAuthorDashboard = Application::get()->getRequest()->getRequestedOp() === 'mySubmissions';
        $op = !$isAuthorDashboard && array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $roles)
            ? 'authorVersion' : 'authorVersionAssigned';
        $assignedWithRoles = $isAuthorDashboard ? [Role::ROLE_ID_AUTHOR]
            : [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
        $params[0][] = [
            'id' => 'newVersion',
            'name' => __('plugins.generic.authorVersion.newVersionSubmissions'),
            'count' => 0,
            'op' => $op,
            'queryParams' => ['newVersion' => 1, 'assignedWithRoles' => $assignedWithRoles],
        ];
        $params[0][] = [
            'id' => 'nonSubmittedVersion',
            'name' => __('plugins.generic.authorVersion.newVersionSubmissions') . ' — ' . __('plugins.generic.authorVersion.nonSubmittedVersions'),
            'count' => 0,
            'op' => $op,
            'queryParams' => ['newVersion' => 1, 'nonSubmitted' => 1, 'assignedWithRoles' => $assignedWithRoles],
        ];
        return false;
    }

    public function addVersionNavigation($hookName, $params)
    {
        $templateMgr = $params[0];
        $menu = $templateMgr->getState('menu');
        if (!$menu) {
            return false;
        }
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        foreach (['dashboards' => 'editorial', 'mySubmissions' => 'mySubmissions'] as $menuId => $op) {
            if (!isset($menu[$menuId])) {
                continue;
            }
            foreach (['newVersion' => false, 'nonSubmittedVersion' => true] as $id => $nonSubmitted) {
                $menu[$menuId]['submenu'][$id] = [
                    'id' => $id,
                    'name' => __('plugins.generic.authorVersion.newVersionSubmissions') . ($nonSubmitted ? ' — ' . __('plugins.generic.authorVersion.nonSubmittedVersions') : ''),
                    'isCurrent' => $request->getRequestedPage() === 'dashboard' && $request->getRequestedOp() === $op && $request->getUserVar('currentViewId') === $id,
                    'url' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'dashboard', $op, [], ['currentViewId' => $id]),
                ];
            }
        }
        $templateMgr->setState(['menu' => $menu]);
        return false;
    }

    public function loadResourcesToWorkflow($hookName, $params)
    {
        [$templateMgr, $template] = $params;
        if ($template !== 'dashboard/editors.tpl') {
            return false;
        }
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return false;
        }
        $apiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersion');
        $config = [
            'apiUrl' => $apiUrl,
            'creationApiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersionCreation/__submissionId__/publications/__publicationId__/version'),
            'relationForm' => (new RelationForm($request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersionRelations/__submissionId__/publications/__publicationId__/relate')))->getConfig(),
            'relationPublished' => PUBLICATION_RELATION_PUBLISHED,
            'submitVersionForm' => (new SubmitVersionForm($request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersion/submitVersion')))->getConfig(),
            'versionJustificationForm' => (new VersionJustificationForm($request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersion/versionJustification')))->getConfig(),
            'deleteVersionForm' => (new DeleteVersionForm($request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'authorVersion/deleteVersion')))->getConfig(),
            'labels' => [
                'submitVersion' => __('plugins.generic.authorVersion.publication.submitVersion'),
                'versionJustification' => __('plugins.generic.authorVersion.versionJustification'),
                'deleteVersion' => __('plugins.generic.authorVersion.deleteVersion'),
                'createVersion' => __('publication.createVersion'),
                'createVersionConfirm' => __('publication.version.confirm'),
                'yes' => __('common.yes'),
                'no' => __('common.no'),
                'relation' => __('publication.relation'),
                'published' => __('publication.status.published'),
            ],
        ];
        $templateMgr->addJavaScript('authorVersionConfig', 'pkp.plugins = pkp.plugins || {}; pkp.plugins.authorVersion = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . ';', [
            'inline' => true,
            'contexts' => ['backend'],
            'priority' => TemplateManager::STYLE_SEQUENCE_LATE + 1,
        ]);
        $templateMgr->addJavaScript('authorVersionWorkflow', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/authorVersion.js', [
            'contexts' => ['backend'],
            'priority' => TemplateManager::STYLE_SEQUENCE_LATE + 2,
        ]);
        $templateMgr->addStyleSheet('authorVersionWorkflow', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/workflow.css', ['contexts' => ['backend']]);
        return false;
    }

    public function showVersionJustificationOnPreprintDetails($hookName, $params)
    {
        $templateMgr = $params[1];
        $publication = $templateMgr->getTemplateVars('publication');
        if (!$publication) {
            return false;
        }
        $justification = $publication->getData('versionJustification');
        if ((int) $publication->getData('version') > 1 && $justification !== null) {
            $templateMgr->assign('versionJustification', $justification);
            $params[2] .= $templateMgr->fetch($this->getTemplateResource('versionJustificationBlock.tpl'));
        }
        return false;
    }
}
