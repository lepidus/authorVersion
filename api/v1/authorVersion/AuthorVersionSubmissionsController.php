<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\api\v1\authorVersion;

use APP\API\v1\_submissions\BackendSubmissionsController;
use APP\core\Application;
use APP\plugins\generic\authorVersion\classes\NewVersionSubmissionFilter;
use APP\submission\Collector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\security\Role;

class AuthorVersionSubmissionsController extends BackendSubmissionsController
{
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $collector = parent::getSubmissionCollector($queryParams);
        NewVersionSubmissionFilter::register($collector, filter_var($queryParams['nonSubmitted'] ?? false, FILTER_VALIDATE_BOOLEAN));
        return $collector;
    }

    public function viewsCount(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $assignedWithRoles = array_map(intval(...), paramToArray($illuminateRequest->query('assignedWithRoles', [])));
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $isAuthorDashboard = $assignedWithRoles === [Role::ROLE_ID_AUTHOR];
        $canAccessUnassignedSubmissions = !$isAuthorDashboard && array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles);

        $counts = [];
        foreach (['newVersion' => false, 'nonSubmittedVersion' => true] as $viewId => $nonSubmitted) {
            $collector = $this->getSubmissionCollector(['nonSubmitted' => $nonSubmitted]);
            if (!$canAccessUnassignedSubmissions) {
                $collector->assignedTo([$request->getUser()->getId()], $assignedWithRoles ?: null);
            }
            $counts[$viewId] = $collector->getCount();
        }

        return response()->json($counts, Response::HTTP_OK);
    }
}
