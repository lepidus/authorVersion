<?php

declare(strict_types=1);

use APP\core\Request;
use APP\plugins\generic\authorVersion\AuthorVersionPlugin;
use PKP\core\Registry;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

class NewVersionDashboardViewsTest extends PKPTestCase
{
    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    public function testDualRoleUserSeesOnlyAuthorAssignmentsInMySubmissions(): void
    {
        $views = $this->views('mySubmissions', [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR]);
        self::assertSame('authorVersionAssigned', $views[0]['op']);
        self::assertSame([Role::ROLE_ID_AUTHOR], $views[0]['queryParams']['assignedWithRoles']);
        self::assertSame(1, $views[1]['queryParams']['nonSubmitted']);
    }

    public function testEditorialDashboardRetainsCoreManagerAndEditorScope(): void
    {
        $views = $this->views('editorial', [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR]);
        self::assertSame('authorVersion', $views[0]['op']);
        self::assertNotContains(Role::ROLE_ID_AUTHOR, $views[0]['queryParams']['assignedWithRoles']);
        $editorViews = $this->views('editorial', [Role::ROLE_ID_SUB_EDITOR]);
        self::assertSame('authorVersionAssigned', $editorViews[0]['op']);
    }

    public function testReviewerDashboardDoesNotReceiveVersionViews(): void
    {
        self::assertSame([], $this->views('reviewAssignments', [Role::ROLE_ID_REVIEWER]));
    }

    private function views(string $op, array $roles): array
    {
        $request = $this->createMock(Request::class);
        $request->method('getRequestedOp')->willReturn($op);
        Registry::set('request', $request);
        $views = [];
        (new AuthorVersionPlugin())->addNewVersionSubmissionViews('Dashboard::views', [&$views, $roles]);
        return $views;
    }
}
