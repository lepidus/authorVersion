<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\classes;

use APP\submission\Collector;
use Illuminate\Database\Query\Builder;
use PKP\submission\PKPSubmission;
use WeakMap;

class NewVersionSubmissionFilter
{
    public const VIEWS = ['newVersion' => false, 'nonSubmittedVersion' => true];

    private static ?WeakMap $collectors = null;

    public static function register(Collector $collector, bool $nonSubmitted): void
    {
        self::$collectors ??= new WeakMap();
        self::$collectors[$collector] = $nonSubmitted;
    }

    public static function filterQuery(string $hookName, array $params): bool
    {
        [$query, $collector] = $params;
        if (self::$collectors === null || !isset(self::$collectors[$collector])) {
            return false;
        }
        $nonSubmitted = self::$collectors[$collector];
        // EXISTS preserves pagination/counts even if several unpublished versions exist.
        // Exclude superseded drafts: only the latest version is actionable.
        $query->whereExists(function (Builder $query) use ($nonSubmitted): void {
            $query->selectRaw('1')->from('publications as nvp')
                ->whereColumn('nvp.submission_id', 's.submission_id')
                ->where('nvp.version', '>', 1)
                ->where('nvp.status', PKPSubmission::STATUS_QUEUED)
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')->from('publications as newer')
                        ->whereColumn('newer.submission_id', 'nvp.submission_id')
                        ->whereColumn('newer.version', '>', 'nvp.version');
                });
            $justification = static function (Builder $query): void {
                $query->selectRaw('1')->from('publication_settings as nvps')
                    ->whereColumn('nvps.publication_id', 'nvp.publication_id')
                    ->where('nvps.setting_name', 'versionJustification')
                    ->whereNotNull('nvps.setting_value')
                    ->where('nvps.setting_value', '!=', '');
            };
            if ($nonSubmitted) {
                $query->whereNotExists($justification);
            } else {
                $query->whereExists($justification);
            }
        });
        return false;
    }
}
