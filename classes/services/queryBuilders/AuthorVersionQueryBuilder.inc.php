<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class AuthorVersionQueryBuilder extends \APP\Services\QueryBuilders\SubmissionQueryBuilder
{
    protected $newVersion = false;
    protected $submittedVersion = false;

    public function filterByNewVersion($newVersion, $nonSubmitted)
    {
        $this->newVersion = $newVersion;
        $this->submittedVersion = !$nonSubmitted;

        return $this;
    }

    public function appGet($q)
    {
        if ($this->newVersion) {
            $q->leftJoin('publications as nvp', 'nvp.submission_id', '=', 's.submission_id')
                ->where('nvp.version', '>', 1)
                ->where('nvp.status', '!=', STATUS_PUBLISHED);

            $submittedVersionSubQuery = function ($query) {
                $query->select('publication_id')
                    ->from('publication_settings')
                    ->where('setting_name', '=', 'versionJustification');
            };

            if ($this->submittedVersion) {
                $q->whereIn('nvp.publication_id', $submittedVersionSubQuery);
            } else {
                $q->whereNotIn('nvp.publication_id', $submittedVersionSubQuery);
            }
        }

        return $q;
    }

}
