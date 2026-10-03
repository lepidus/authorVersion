<?php

declare(strict_types=1);

use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionHandler;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionCreationController;
use APP\plugins\generic\authorVersion\api\v1\authorVersion\AuthorVersionRelationsController;
use APP\publication\Repository as PublicationRepository;
use APP\plugins\generic\authorVersion\AuthorVersionPlugin;
use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use PKP\core\APIRouter;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\Role;
use PHPUnit\Framework\TestCase;

class AuthorVersionApiRegistrationTest extends TestCase
{
    public function testPluginControllerRegistersThroughExpandedHookArguments(): void
    {
        $router = $this->createMock(APIRouter::class);
        $router->expects(self::once())->method('registerPluginApiControllers')
            ->with(self::callback(static fn (array $controllers): bool => count($controllers) === 3
                && $controllers[0] instanceof AuthorVersionHandler
                && $controllers[1] instanceof AuthorVersionCreationController
                && $controllers[2] instanceof AuthorVersionRelationsController));
        $hook = 'AuthorVersion::test::registration';
        Hook::add($hook, [new AuthorVersionPlugin(), 'setupAuthorVersionHandler']);
        try {
            Hook::run($hook, [$router]);
        } finally {
            Hook::clear($hook);
        }
    }

    public function testListRoutesRegisterThroughExpandedHookArguments(): void
    {
        $handler = $this->createMock(APIHandler::class);
        $handler->expects(self::exactly(2))->method('addRoute');
        $hook = 'AuthorVersion::test::listRoutes';
        Hook::add($hook, [new AuthorVersionPlugin(), 'setupVersionSubmissionLists']);
        try {
            Hook::run($hook, [null, $handler]);
        } finally {
            Hook::clear($hook);
        }
    }

    public function testCreationRouteUsesAuthorRoleAndNativeOperation(): void
    {
        $originalRouter = Route::getFacadeRoot();
        $router = new Router(new Dispatcher());
        Route::swap($router);
        try {
            $controller = new AuthorVersionCreationController();
            $controller->getGroupRoutes();
            $routes = $router->getRoutes()->getRoutes();
            self::assertCount(1, $routes);
            self::assertSame('authorVersionCreation', $controller->getHandlerPath());
            self::assertSame('{submissionId}/publications/{publicationId}/version', $routes[0]->uri());
            self::assertSame(['POST'], $routes[0]->methods());
            self::assertSame(['has.roles:' . Role::ROLE_ID_AUTHOR], $routes[0]->middleware());
            $callback = new ReflectionFunction($routes[0]->getAction('uses'));
            self::assertSame($controller, $callback->getClosureThis());
            self::assertSame('versionPublication', $callback->getName());
            self::assertContains('versionPublication', $controller->requiresSubmissionAccess);
            self::assertContains('versionPublication', $controller->requiresProductionStageAccess);
            self::assertContains(Role::ROLE_ID_AUTHOR, $controller->productionStageAccessRoles);
            self::assertSame(['has.user', 'has.context'], $controller->getRouteGroupMiddleware());
        } finally {
            Route::swap($originalRouter);
        }
    }

    public function testCreationRejectsStaleDraftAndPublishedRelation(): void
    {
        foreach ([
            null,
            ['id' => 22, 'status' => Submission::STATUS_PUBLISHED],
            ['id' => 21, 'status' => Submission::STATUS_QUEUED],
            ['id' => 21, 'status' => Submission::STATUS_PUBLISHED, 'relationStatus' => Publication::PUBLICATION_RELATION_PUBLISHED],
        ] as $data) {
            $publications = [];
            if ($data) {
                $publication = new Publication();
                $publication->setId($data['id']);
                $publication->setData('status', $data['status']);
                $publication->setData('relationStatus', $data['relationStatus'] ?? Publication::PUBLICATION_RELATION_NONE);
                $publications[] = $publication;
            }
            $submission = new Submission();
            $submission->setData('publications', collect($publications));
            $controller = $this->getMockBuilder(AuthorVersionCreationController::class)
                ->onlyMethods(['getAuthorizedContextObject'])->getMock();
            $controller->method('getAuthorizedContextObject')->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($submission);
            $request = Request::create('/authorVersionCreation/20/publications/21/version', 'POST');
            $route = new IlluminateRoute('POST', '/authorVersionCreation/{submissionId}/publications/{publicationId}/version', fn () => null);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            self::assertSame(400, $controller->versionPublication($request)->getStatusCode());
        }
    }

    public function testRelationRouteUsesAuthorRoleAndNativeProductionPolicies(): void
    {
        $originalRouter = Route::getFacadeRoot();
        $router = new Router(new Dispatcher());
        Route::swap($router);
        try {
            $controller = new AuthorVersionRelationsController();
            $controller->getGroupRoutes();
            $routes = $router->getRoutes()->getRoutes();
            self::assertCount(1, $routes);
            self::assertSame('authorVersionRelations', $controller->getHandlerPath());
            self::assertSame('{submissionId}/publications/{publicationId}/relate', $routes[0]->uri());
            self::assertSame(['PUT'], $routes[0]->methods());
            self::assertSame(['has.roles:' . Role::ROLE_ID_AUTHOR], $routes[0]->middleware());
            $callback = new ReflectionFunction($routes[0]->getAction('uses'));
            self::assertSame($controller, $callback->getClosureThis());
            self::assertSame('relatePublication', $callback->getName());
            self::assertContains('relatePublication', $controller->requiresSubmissionAccess);
            self::assertContains('relatePublication', $controller->requiresProductionStageAccess);
            self::assertContains(Role::ROLE_ID_AUTHOR, $controller->productionStageAccessRoles);
            self::assertSame(['has.user', 'has.context'], $controller->getRouteGroupMiddleware());
        } finally {
            Route::swap($originalRouter);
        }
    }

    public function testRelationRejectsMissingForeignAndLockedPublicationsBeforeMutation(): void
    {
        $originalRepository = app(PublicationRepository::class);
        try {
            foreach ([
                [null, 404],
                [['submissionId' => 22], 403],
                [['submissionId' => 20, 'status' => Submission::STATUS_PUBLISHED, 'relationStatus' => Publication::PUBLICATION_RELATION_PUBLISHED], 400],
            ] as [$data, $status]) {
                $publication = null;
                if ($data) {
                    $publication = new Publication();
                    $publication->setId(21);
                    foreach ($data as $key => $value) {
                        $publication->setData($key, $value);
                    }
                }
                $repository = $this->createMock(PublicationRepository::class);
                $repository->expects(self::once())->method('get')->with(21)->willReturn($publication);
                $repository->expects(self::never())->method('validate');
                $repository->expects(self::never())->method('relate');
                app()->instance(PublicationRepository::class, $repository);
                $submission = new Submission();
                $submission->setId(20);
                $controller = $this->getMockBuilder(AuthorVersionRelationsController::class)
                    ->onlyMethods(['getAuthorizedContextObject'])->getMock();
                $controller->method('getAuthorizedContextObject')->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($submission);
                $request = Request::create('/authorVersionRelations/20/publications/21/relate', 'PUT', ['relationStatus' => Publication::PUBLICATION_RELATION_NONE]);
                $route = new IlluminateRoute('PUT', '/authorVersionRelations/{submissionId}/publications/{publicationId}/relate', fn () => null);
                $route->bind($request);
                $request->setRouteResolver(fn () => $route);
                self::assertSame($status, $controller->relatePublication($request)->getStatusCode());
            }
        } finally {
            app()->instance(PublicationRepository::class, $originalRepository);
        }
    }
}
