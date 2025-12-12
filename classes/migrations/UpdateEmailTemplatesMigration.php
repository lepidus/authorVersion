<?php

namespace APP\plugins\generic\authorVersion\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateEmailTemplatesMigration extends Migration
{
    private const EMAIL_TEMPLATES = [
        'SUBMITTED_VERSION_NOTIFICATION',
        'DELETED_VERSION_NOTIFICATION'
    ];

    public function up(): void
    {
        // TODO
    }
}
