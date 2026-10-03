<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\api\v1\authorVersion;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\mail\Mailable;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;
use PKP\validation\ValidatorFactory;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthorVersionHandler extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return 'authorVersion';
    }

    public function getRouteGroupMiddleware(): array
    {
        return ['has.user', 'has.context'];
    }

    public function getGroupRoutes(): void
    {
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];

        Route::post('submitVersion', $this->submitVersion(...))
            ->name('authorVersion.submitVersion')
            ->middleware(self::roleAuthorizer($roles));
        Route::post('deleteVersion', $this->deleteVersion(...))
            ->name('authorVersion.deleteVersion')
            ->middleware(self::roleAuthorizer([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]));
        Route::post('versionJustification', $this->updateVersionJustification(...))
            ->name('authorVersion.versionJustification')
            ->middleware(self::roleAuthorizer($roles));
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0];
        $actionName = static::getRouteActionName($illuminateRequest);
        $validator = ValidatorFactory::make(
            [
                'submissionId' => $this->getParameter('submissionId'),
                'publicationId' => $this->getParameter('publicationId'),
            ],
            [
                'submissionId' => ['required', 'integer', 'min:1'],
                'publicationId' => ['required', 'integer', 'min:1'],
            ]
        );
        if ($validator->fails()) {
            throw new BadRequestHttpException('Invalid submissionId or publicationId');
        }

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        // Match the OPS version/publication actions. Metadata write permission
        // cannot be used here: it disallows authors when an older version is published.
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
        if ($actionName !== 'deleteVersion') {
            $roles[] = Role::ROLE_ID_AUTHOR;
        }
        $this->addPolicy(new StageRolePolicy($roles, WORKFLOW_STAGE_ID_PRODUCTION, false));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function submitVersion(Request $illuminateRequest): JsonResponse
    {
        $errors = $this->validateJustification($illuminateRequest, 'versionJustification');
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getLatestPublication();
        if (
            !$this->isSelectedDraftVersion($publication, $illuminateRequest)
            || $publication->getData('versionJustification') !== null
        ) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $versionJustification = $illuminateRequest->input('versionJustification');
        $email = $this->prepareSubmittedVersionEmail($submission, $publication, $versionJustification);
        Repo::publication()->edit($publication, ['versionJustification' => $versionJustification]);
        if ($email) {
            Mail::send($email);
        }

        return response()->json([], Response::HTTP_OK);
    }

    public function deleteVersion(Request $illuminateRequest): JsonResponse
    {
        $errors = $this->validateJustification($illuminateRequest, 'deletingJustification');
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getLatestPublication();
        if (!$this->isSelectedDraftVersion($publication, $illuminateRequest)) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $this->sendDeletedVersionEmail($submission, $publication, $illuminateRequest->input('deletingJustification'));
        Repo::publication()->delete($publication);

        return response()->json([], Response::HTTP_OK);
    }

    public function updateVersionJustification(Request $illuminateRequest): JsonResponse
    {
        $errors = $this->validateJustification($illuminateRequest, 'versionJustification');
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getLatestPublication();
        if (!$this->isSelectedDraftVersion($publication, $illuminateRequest)) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        Repo::publication()->edit(
            $publication,
            ['versionJustification' => $illuminateRequest->input('versionJustification')]
        );

        return response()->json([], Response::HTTP_OK);
    }

    private function isSelectedDraftVersion(?Publication $publication, Request $request): bool
    {
        return $publication !== null
            && (int) $publication->getId() === (int) $request->input('publicationId')
            && (int) $publication->getData('status') === Submission::STATUS_QUEUED
            && (int) $publication->getData('version') > 1;
    }

    private function validateJustification(Request $request, string $field): array
    {
        $validator = ValidatorFactory::make($request->input(), [
            $field => ['required', 'string'],
            'publicationId' => ['required', 'integer', 'min:1'],
        ]);

        return $validator->fails() ? $validator->errors()->getMessages() : [];
    }

    private function prepareSubmittedVersionEmail(Submission $submission, Publication $publication, string $versionJustification): ?Mailable
    {
        $managers = $this->getManagersAssigned($publication);
        if ($managers) {
            return $this->prepareEmailFromTemplate(
                $submission,
                'SUBMITTED_VERSION_NOTIFICATION',
                $managers,
                ['versionJustification' => $versionJustification]
            );
        }

        return null;
    }

    private function sendDeletedVersionEmail(Submission $submission, Publication $publication, string $deletingJustification): void
    {
        $authors = $publication->getData('authors');
        if ($authors === null) {
            throw new RuntimeException('Missing authors collection for authorVersion publication ' . $publication->getId());
        }
        if ($authors->isEmpty()) {
            return;
        }
        $recipientAuthor = $publication->getPrimaryAuthor() ?? $authors->first();
        $email = $this->prepareEmailFromTemplate(
            $submission,
            'DELETED_VERSION_NOTIFICATION',
            [['email' => $recipientAuthor->getData('email'), 'name' => $recipientAuthor->getFullName()]],
            ['deletingJustification' => $deletingJustification]
        );
        Mail::send($email);
    }

    private function prepareEmailFromTemplate(Submission $submission, string $templateKey, array $recipients, array $params): Mailable
    {
        $context = $this->getRequest()->getContext();
        $keyToClassMap = [
            'SUBMITTED_VERSION_NOTIFICATION' => 'APP\\plugins\\generic\\authorVersion\\classes\\mail\\mailables\\SubmittedVersionNotification',
            'DELETED_VERSION_NOTIFICATION' => 'APP\\plugins\\generic\\authorVersion\\classes\\mail\\mailables\\DeletedVersionNotification',
        ];
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
        if (!$emailTemplate) {
            throw new RuntimeException('Missing authorVersion email template: ' . $templateKey);
        }
        $emailTemplateClass = $keyToClassMap[$templateKey];
        $email = new $emailTemplateClass($context, $submission, $params);
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to($recipients);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        return $email;
    }

    private function getManagersAssigned(Publication $publication): array
    {
        $contextId = $this->getRequest()->getContext()->getId();
        $userIds = StageAssignment::withSubmissionIds([$publication->getData('submissionId')])
            ->withStageIds([WORKFLOW_STAGE_ID_PRODUCTION])
            ->withContextId($contextId)
            ->distinct()
            ->pluck('stage_assignments.user_id');
        $managers = [];
        foreach ($userIds as $userId) {
            $isManager = UserGroup::withContextIds($contextId)
                ->withUserIds([(int) $userId])
                ->where('role_id', Role::ROLE_ID_MANAGER)
                ->exists();
            if (!$isManager) {
                continue;
            }
            $manager = Repo::user()->get((int) $userId);
            if (!$manager) {
                continue;
            }
            $managers[] = ['email' => $manager->getEmail(), 'name' => $manager->getFullName()];
        }

        return $managers;
    }
}
