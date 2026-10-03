<?php

/**
 * Isolated endpoint regression: php tests/api/author-version-handler.php
 * The doubles below cover version state and side effects, not PKP policies,
 * Laravel middleware, database persistence or real mail delivery.
 */

declare(strict_types=1);

namespace Illuminate\Http {
    class Request
    {
        public function __construct(private array $data = [])
        {
        }

        public function input(?string $key = null): mixed
        {
            return $key === null ? $this->data : ($this->data[$key] ?? null);
        }
    }

    class JsonResponse
    {
        public function __construct(public array $data, public int $status)
        {
        }
    }

    class Response
    {
        public const HTTP_OK = 200;
        public const HTTP_BAD_REQUEST = 400;
    }
}

namespace PKP\core {
    class PKPBaseController
    {
        public static ?\APP\submission\Submission $submission = null;

        public function &getAuthorizedContextObject(int $assocType): mixed
        {
            return self::$submission;
        }

        public function getRequest(): object
        {
            return new class () {
                public function getContext(): object
                {
                    return new class () {
                        public function getId(): int
                        {
                            return 1;
                        }
                    };
                }
            };
        }
    }
}

namespace APP\core {
    class Application
    {
        public const ASSOC_TYPE_SUBMISSION = 1;
    }
}

namespace APP\publication {
    class Publication
    {
        public function __construct(public array $data)
        {
        }

        public function getData(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }

        public function getId(): int
        {
            return $this->data['id'];
        }
    }
}

namespace APP\submission {
    class Submission
    {
        public const STATUS_PUBLISHED = 3;
        public const STATUS_QUEUED = 1;

        public function __construct(private ?\APP\publication\Publication $publication)
        {
        }

        public function getLatestPublication(): ?\APP\publication\Publication
        {
            return $this->publication;
        }
    }
}

namespace APP\facades {
    class Repo
    {
        public static array $edits = [];
        public static array $deletes = [];

        public static function publication(): object
        {
            return new class () {
                public function edit(object $publication, array $data): void
                {
                    Repo::$edits[] = $data;
                }

                public function delete(object $publication): void
                {
                    Repo::$deletes[] = $publication;
                }
            };
        }

        public static function user(): object
        {
            return new class () {
                public function get(int $id): object
                {
                    return new class () {
                        public function getEmail(): string
                        {
                            return 'manager@example.invalid';
                        }

                        public function getFullName(): string
                        {
                            return 'Manager';
                        }
                    };
                }
            };
        }

        public static function emailTemplate(): object
        {
            return new class () {
                public function getByKey(int $contextId, string $key): null
                {
                    return null;
                }
            };
        }
    }
}

namespace PKP\security {
    class Role
    {
        public const ROLE_ID_MANAGER = 16;
    }
}

namespace PKP\userGroup {
    class UserGroup
    {
        public static function withContextIds(int $id): object
        {
            return new class () {
                public function withUserIds(array $ids): self
                {
                    return $this;
                }

                public function where(string $column, int $value): self
                {
                    return $this;
                }

                public function exists(): bool
                {
                    return true;
                }
            };
        }
    }
}

namespace PKP\stageAssignment {
    class StageAssignment
    {
        public static array $userIds = [];
        public static function withSubmissionIds(array $ids): object
        {
            return new class () {
                public function withStageIds(array $ids): self
                {
                    return $this;
                }

                public function withContextId(int $id): self
                {
                    return $this;
                }

                public function distinct(): self
                {
                    return $this;
                }

                public function pluck(string $column): array
                {
                    return StageAssignment::$userIds;
                }
            };
        }
    }
}

namespace PKP\validation {
    class ValidatorFactory
    {
        public static function make(array $data, array $rules): object
        {
            return new class ($data, $rules) {
                public function __construct(private array $data, private array $rules)
                {
                }

                public function fails(): bool
                {
                    foreach ($this->rules as $field => $rules) {
                        if (!isset($this->data[$field])) {
                            return true;
                        }
                        if (in_array('string', $rules) && (!is_string($this->data[$field]) || trim($this->data[$field]) === '')) {
                            return true;
                        }
                        if (in_array('integer', $rules) && (!filter_var($this->data[$field], FILTER_VALIDATE_INT) || (int) $this->data[$field] < 1)) {
                            return true;
                        }
                    }

                    return false;
                }

                public function errors(): self
                {
                    return $this;
                }

                public function getMessages(): array
                {
                    return [array_key_first($this->rules) => ['Invalid justification']];
                }
            };
        }
    }
}

namespace {
    use APP\facades\Repo;
    use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionHandler;
    use APP\publication\Publication;
    use APP\submission\Submission;
    use Illuminate\Http\Request;
    use PKP\core\PKPBaseController;

    define('WORKFLOW_STAGE_ID_PRODUCTION', 5);

    function response(): object
    {
        return new class () {
            public function json(array $data, int $status): \Illuminate\Http\JsonResponse
            {
                return new \Illuminate\Http\JsonResponse($data, $status);
            }
        };
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function fixture(?array $data): void
    {
        $authors = new class () {
            public function isEmpty(): bool
            {
                return true;
            }
        };
        PKPBaseController::$submission = new Submission(
            $data === null ? null : new Publication($data + ['authors' => $authors, 'submissionId' => 1, 'id' => 2])
        );
        Repo::$edits = [];
        Repo::$deletes = [];
        \PKP\stageAssignment\StageAssignment::$userIds = [];
    }

    require dirname(__DIR__, 2) . '/api/v1/authorVersion/AuthorVersionHandler.php';
    $handler = new AuthorVersionHandler();
    $cases = 0;
    foreach (['submitVersion', 'deleteVersion', 'updateVersionJustification'] as $action) {
        $field = $action === 'deleteVersion' ? 'deletingJustification' : 'versionJustification';
        foreach ([[], [$field => ''], [$field => '  '], [$field => ['invalid']]] as $input) {
            fixture(['version' => 2, 'status' => 1]);
            check($handler->$action(new Request($input + ['publicationId' => 2]))->status === 400, "$action rejects invalid input");
            check(!Repo::$edits && !Repo::$deletes, "$action has no side effects on invalid input");
            $cases++;
        }
        foreach ([null, ['version' => 1, 'status' => 1], ['version' => 2, 'status' => 3], ['version' => 2, 'status' => '3'], ['version' => 2, 'status' => 5]] as $data) {
            fixture($data);
            check($handler->$action(new Request([$field => 'Reason', 'publicationId' => 2]))->status === 400, "$action rejects invalid version");
            check(!Repo::$edits && !Repo::$deletes, "$action has no side effects on invalid version");
            $cases++;
        }
        foreach ([null, 0, -1, 'wrong', [2], 3] as $publicationId) {
            fixture(['version' => 2, 'status' => 1]);
            check($handler->$action(new Request([$field => 'Reason', 'publicationId' => $publicationId]))->status === 400, "$action rejects missing, invalid or stale publication selection");
            check(!Repo::$edits && !Repo::$deletes, "$action has no side effects on wrong publication selection");
            $cases++;
        }
        fixture(['id' => 3, 'version' => 3, 'status' => 1]);
        check($handler->$action(new Request([$field => 'Reason', 'publicationId' => 2]))->status === 400, "$action rejects modal opened before version 3 was created");
        check(!Repo::$edits && !Repo::$deletes, "$action does not mutate version 3 from stale modal");
        $cases++;
        fixture(['version' => 2, 'status' => 1]);
        check($handler->$action(new Request([$field => 'Reason', 'publicationId' => 2]))->status === 200, "$action accepts unpublished version 2");
        check(
            $action === 'deleteVersion' ? count(Repo::$deletes) === 1 : Repo::$edits === [['versionJustification' => 'Reason']],
            "$action performs expected mutation"
        );
        $cases++;
    }
    fixture(['version' => 2, 'status' => 1, 'versionJustification' => 'Previous reason']);
    check($handler->submitVersion(new Request(['versionJustification' => 'Reason', 'publicationId' => 2]))->status === 400, 'Reject duplicate submit');
    check(!Repo::$edits && !Repo::$deletes, 'Duplicate submit has no side effects');
    $cases++;
    fixture(['version' => 2, 'status' => 1, 'versionJustification' => 'Previous reason']);
    check($handler->updateVersionJustification(new Request(['versionJustification' => 'Updated reason', 'publicationId' => 2]))->status === 200, 'Allow justification update');
    check(Repo::$edits === [['versionJustification' => 'Updated reason']], 'Persist updated justification');
    $cases++;
    fixture(['version' => 2, 'status' => 1, 'authors' => null]);
    try {
        $handler->deleteVersion(new Request(['deletingJustification' => 'Reason', 'publicationId' => 2]));
        check(false, 'Missing authors must interrupt deletion');
    } catch (\RuntimeException $exception) {
        check(str_contains($exception->getMessage(), 'Missing authors collection'), 'Report missing authors explicitly');
    }
    check(!Repo::$edits && !Repo::$deletes, 'Missing authors do not delete publication');
    $cases++;
    fixture(['version' => 2, 'status' => 1]);
    \PKP\stageAssignment\StageAssignment::$userIds = [8];
    try {
        $handler->submitVersion(new Request(['versionJustification' => 'Reason', 'publicationId' => 2]));
        check(false, 'Missing email template must interrupt submit');
    } catch (\RuntimeException $exception) {
        check(str_contains($exception->getMessage(), 'Missing authorVersion email template'), 'Report missing template explicitly');
    }
    check(!Repo::$edits && !Repo::$deletes, 'Missing template does not persist justification');
    $cases++;
    echo "$cases endpoint state and mutation checks passed (isolated doubles; no authorization proof).\n";
}
