<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class AuthorVersionQueryBuilder extends \APP\Services\QueryBuilders\SubmissionQueryBuilder
{
    protected $newVersionSubmitted = false;

    public function filterByNewVersion($newVersionSubmitted)
    {
        $this->newVersionSubmitted = $newVersionSubmitted;
        return $this;
    }

    public function appGet($q)
    {
        if ($this->newVersionSubmitted) {
            $q->leftJoin('publications as nvp', 'nvp.submission_id', '=', 's.submission_id')
                ->leftJoin('publication_settings as nvps', 'nvp.publication_id', '=', 'nvps.publication_id')
                ->where(function ($q) {
                    $q->where('nvps.setting_name', '=', 'versionJustification');
                    $q->whereNotNull('nvps.setting_value');
                });
            $q->where('nvp.status', '!=', STATUS_PUBLISHED);
        }

        return $q;
    }
}
