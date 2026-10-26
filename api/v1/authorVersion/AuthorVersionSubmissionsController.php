<?php

declare(strict_types=1);

namespace APP\plugins\generic\authorVersion\api\v1\authorVersion;

use APP\API\v1\_submissions\BackendSubmissionsController;
use APP\plugins\generic\authorVersion\classes\NewVersionSubmissionFilter;
use APP\submission\Collector;

class AuthorVersionSubmissionsController extends BackendSubmissionsController
{
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $collector = parent::getSubmissionCollector($queryParams);
        NewVersionSubmissionFilter::register($collector, filter_var($queryParams['nonSubmitted'] ?? false, FILTER_VALIDATE_BOOLEAN));
        return $collector;
    }
}
