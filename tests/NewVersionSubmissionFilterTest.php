<?php

declare(strict_types=1);

use APP\facades\Repo;
use APP\plugins\generic\authorVersion\classes\NewVersionSubmissionFilter;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\submission\PKPSubmission;

/** Exercise the publication filter against the OPS database, with rollback. */
class NewVersionSubmissionFilterTest extends TestCase
{
    private array $submissionIds = [];

    protected function setUp(): void
    {
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
    }

    public function testLatestDraftsAreSeparatedWithoutDuplicates(): void
    {
        $submitted = $this->createSubmission();
        $this->createPublication($submitted, 1, PKPSubmission::STATUS_PUBLISHED);
        $this->createPublication($submitted, 2, PKPSubmission::STATUS_QUEUED, 'Version two');
        $nonSubmitted = $this->createSubmission();
        $this->createPublication($nonSubmitted, 1, PKPSubmission::STATUS_PUBLISHED);
        $this->createPublication($nonSubmitted, 2, PKPSubmission::STATUS_QUEUED, 'Superseded');
        $this->createPublication($nonSubmitted, 3, PKPSubmission::STATUS_QUEUED);
        $posted = $this->createSubmission();
        $this->createPublication($posted, 1, PKPSubmission::STATUS_PUBLISHED);
        $this->createPublication($posted, 2, PKPSubmission::STATUS_QUEUED, 'Old draft');
        $this->createPublication($posted, 3, PKPSubmission::STATUS_PUBLISHED, 'Posted');
        $firstVersion = $this->createSubmission();
        $this->createPublication($firstVersion, 1, PKPSubmission::STATUS_QUEUED);

        self::assertSame([$submitted], $this->filteredIds(false));
        self::assertSame([$nonSubmitted], $this->filteredIds(true));
    }

    public function testOtherCollectorsRemainUnaffected(): void
    {
        $id = $this->createSubmission();
        $query = DB::table('submissions as s')->whereIn('s.submission_id', $this->submissionIds);
        NewVersionSubmissionFilter::filterQuery('Submission::Collector', [$query, Repo::submission()->getCollector()]);
        self::assertSame([$id], $query->pluck('s.submission_id')->map(intval(...))->all());
    }

    private function filteredIds(bool $nonSubmitted): array
    {
        $collector = Repo::submission()->getCollector();
        NewVersionSubmissionFilter::register($collector, $nonSubmitted);
        $query = DB::table('submissions as s')->whereIn('s.submission_id', $this->submissionIds);
        NewVersionSubmissionFilter::filterQuery('Submission::Collector', [$query, $collector]);
        return $query->orderBy('s.submission_id')->pluck('s.submission_id')->map(intval(...))->all();
    }

    private function createSubmission(): int
    {
        $contextId = DB::table('servers')->value('server_id');
        self::assertNotNull($contextId, 'OPS test dataset must contain a server.');
        $id = DB::table('submissions')->insertGetId(['context_id' => $contextId, 'locale' => 'en'], 'submission_id');
        $this->submissionIds[] = (int) $id;
        return (int) $id;
    }

    private function createPublication(int $submissionId, int $version, int $status, ?string $justification = null): void
    {
        $id = DB::table('publications')->insertGetId([
            'submission_id' => $submissionId, 'version' => $version, 'status' => $status,
        ], 'publication_id');
        if ($justification !== null) {
            DB::table('publication_settings')->insert([
                'publication_id' => $id, 'setting_name' => 'versionJustification', 'setting_value' => $justification,
            ]);
        }
    }
}
