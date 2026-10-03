# Author Version Plugin 

This plugin allows the submission authors to create new versions of their submissions that have been posted. It's useful for servers where authors don't have permission to post/unpost, since in OPS version creation is attached to these features.

It also changes behavior for when a posted submission has its relations changed to inform the preprint has been published in a journal as a article. In this case, the author is prevented from changing relations or creating new versions.

## Compatibility

This development checkout targets **OPS 3.5.x**, with PHP 8.2 or later.
The previous released version targets OPS 3.4.0. This plugin is specific to OPS;
it does not declare support for OJS or OMP.

The OPS 3.5 port uses Laravel API controllers and Vue 3 workflow extensions.
Submitted and non-submitted versions are available as separate dashboard views.
Version submission, justification edits and deletion apply to the latest draft.
Authors create versions only from the latest posted publication. Each request
identifies both the submission and publication, so a stale modal cannot change
a newer version. Author creation and relation updates use dedicated endpoints
with the native OPS context, submission and production-stage policies.

## Development validation

Fast endpoint and workflow regressions:

```sh
php tests/api/author-version-handler.php
node tests/js/authorVersion.test.cjs
```

These isolated tests do not prove OPS authorization, persistence or browser integration.
The OPS CI runs the database/PHPUnit suite and the Cypress workflows against 3.5:

```sh
# From an installed OPS checkout with the plugin physically copied into plugins/generic/authorVersion:
lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage plugins/generic/authorVersion/tests
npx cypress run --spec 'plugins/generic/authorVersion/cypress/tests/**/*.cy.js'
```

The local validation target is declared in `tests/validation/ops-3.5.json`.
See [the validation report](tests/validation/README.md) for the executed checks and their limits.
Version and release metadata remain unchanged until a release is prepared.


## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/authorVersion/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation

1. Enter the administration area of ​​your OPS website through the __Dashboard__.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __authorVersion.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.


# License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2020-2023 Lepidus Tecnologia__

__Copyright (c) 2020-2023 SciELO__
