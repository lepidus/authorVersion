<?php

namespace APP\plugins\generic\authorVersion\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use PKP\plugins\PluginRegistry;
use APP\facades\Repo;

class UpdateEmailTemplatesMigration extends Migration
{
    private const EMAIL_TEMPLATE_LOCALES = ['en', 'es', 'pt_BR'];

    public function up(): void
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'authorversionplugin');

        $plugin->addLocaleData();
        Repo::emailTemplate()->dao->installEmailTemplates(
            $plugin->getInstallEmailTemplatesFile(),
            self::EMAIL_TEMPLATE_LOCALES
        );
    }
}
