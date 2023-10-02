<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class AuthorVersionQueryBuilder extends \APP\Services\QueryBuilders\SubmissionQueryBuilder
{
    protected $newVersion = false;
    protected $nonSubmitted = false;

    public function filterByNewVersion($newVersion, $nonSubmitted)
    {
        $this->newVersion = $newVersion;
        $this->nonSubmitted = $nonSubmitted;

        return $this;
    }

    public function appGet($q)
    {
        if ($this->newVersion) {
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
