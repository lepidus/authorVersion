<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\api\v1\authorVersion;

use APP\API\v1\submissions\SubmissionController;
use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\security\Role;

/** Author version creation with the native OPS policies and notification flow. */
class AuthorVersionCreationController extends SubmissionController
{
    public function getHandlerPath(): string
    {
        return 'authorVersionCreation';
    }

    public function getGroupRoutes(): void
    {
        // OPS registers this route with AUTHOR, then PKP's duplicate registration
        // removes that role. A dedicated plugin endpoint preserves the OPS intent
        // without changing the native editorial endpoints.
        Route::post('{submissionId}/publications/{publicationId}/version', $this->versionPublication(...))
            ->name('authorVersionCreation.publication.version')
            ->middleware(self::roleAuthorizer([Role::ROLE_ID_AUTHOR]))
            ->whereNumber(['submissionId', 'publicationId']);
    }

    public function versionPublication(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getLatestPublication();
        if (
            !$publication
            || (int) $publication->getId() !== (int) $illuminateRequest->route('publicationId')
            || (int) $publication->getData('status') !== Submission::STATUS_PUBLISHED
            || (int) $publication->getData('relationStatus') === Publication::PUBLICATION_RELATION_PUBLISHED
        ) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        return parent::versionPublication($illuminateRequest);
    }
}
