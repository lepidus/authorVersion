<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\api\v1\authorVersion;

use APP\API\v1\submissions\SubmissionController;
use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\userGroup\UserGroup;

/** Author relations with native OPS production access and schema validation. */
class AuthorVersionRelationsController extends SubmissionController
{
    public function getHandlerPath(): string
    {
        return 'authorVersionRelations';
    }

    public function getGroupRoutes(): void
    {
        Route::put('{submissionId}/publications/{publicationId}/relate', $this->relatePublication(...))
            ->name('authorVersionRelations.publication.relate')
            ->middleware(self::roleAuthorizer([Role::ROLE_ID_AUTHOR]))
            ->whereNumber(['submissionId', 'publicationId']);
    }

    public function relatePublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));
        if (!$publication) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }
        if ((int) $submission->getId() !== (int) $publication->getData('submissionId')) {
            return response()->json(['error' => __('api.publications.403.submissionsDidNotMatch')], Response::HTTP_FORBIDDEN);
        }
        if (
            (int) $publication->getData('status') === Submission::STATUS_PUBLISHED
            && (int) $publication->getData('relationStatus') === Publication::PUBLICATION_RELATION_PUBLISHED
        ) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        // Follow the native relate endpoint's accepted fields and schema validation.
        $params = array_intersect_key($illuminateRequest->input(), array_flip(['relationStatus', 'vorDoi']));
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $params);
        if (!isset($params['relationStatus'])) {
            return response()->json(['relationStatus' => [__('validator.filled')]], Response::HTTP_BAD_REQUEST);
        }
        $context = $request->getContext();
        $errors = Repo::publication()->validate($publication, $params, $submission, $context);
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::publication()->relate($publication, $params['relationStatus'], $params['vorDoi'] ?? '');
        $publication = Repo::publication()->get($publication->getId());
        if (!$publication) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        // Native OPS 3.5 passes a nested array here, which Laravel whereIn rejects
        // after the relation has already been saved. Keep context IDs flat.
        $userGroups = UserGroup::withContextIds([$context->getId()])->get();
        $genres = DAORegistry::getDAO('GenreDAO')->getByContextId($context->getId())->toAssociativeArray();

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            Response::HTTP_OK
        );
    }
}
