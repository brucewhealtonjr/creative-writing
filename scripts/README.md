# Site maintenance scripts

These scripts document and verify the Person biographies, book authorship,
Gutenberg editor/link search, and Bing verification changes made in October 2026.
They require Drupal's bootstrapped runtime. Run an inspected script with
`vendor/bin/drush php:script /private/path/script.php` from the site root; do not
serve these files as public web endpoints.

`*-verify.php` files check the deployed behavior. The Person regression script
uses temporary database records inside a rolled-back transaction and disables
IndexNow notifications during those tests. Other verification scripts inspect
the site's existing records and public HTTP responses.

`*-update.php`, `*-prepare.php`, `*-publish.php`, `*-finish.php`, and conversion
scripts are site-specific maintenance operations, not an automatic deployment
sequence. Review their hard-coded node IDs and private backup paths before use.
Some are one-time migrations; rerunning every script is not appropriate.
`person-editor-verify.php` also adapts the two migrated biographies to the
existing allowed Gutenberg text format before checking entity validation.

The book conversion script is restricted to node 403. It does not convert all
book descriptions. Conversion scripts save revisions and compare the text before
and after block markup conversion.

Database dumps, uploaded images, and private runtime settings are deliberately
outside this repository. Restore those separately from private site backups.
Drupal configuration exports and custom module code do not contain the complete
live content database. Reconcile the current live configuration before importing
the repository's export; the checkout may contain unrelated historical drift.
