# Project instructions

This repository is a Magento Open Source 2.4.8-p5 application. The root `composer.json` is the Magento project package, and dependencies are installed in `vendor/`. Warden configuration lives in `.warden/`.


- The project runs under Warden.
- Do not install PHP, MariaDB, OpenSearch, etc. directly in WSL.
- Use warden shell for Magento/PHP commands.
- Preserve .env formatting.
- Do not modify Warden core files without asking for permission first.
- Run Magento commands through the existing environment.

## Where to work

- Put new application modules in `app/code/<Vendor>/<Module>/` and themes in `app/design/`. There is currently no custom `app/code` tree.
- Keep Magento module files together: `registration.php`, `etc/module.xml`, PHP classes, configuration XML, view assets, and tests under the module's `Test/` directory as appropriate.
- Treat `vendor/` as Composer managed. Do not edit installed package files for a lasting fix; use a custom module or a documented Composer patch.
- Treat `generated/`, `var/`, `pub/static/`, and `pub/media/` as runtime or generated data. Do not make source changes there.
- `app/etc/env.php`, `.env`, and Composer authentication files can contain credentials. Do not print, copy, or commit their contents. Prefer sample files when documenting configuration.

## Implementation conventions

- Follow existing Magento patterns and the repository's `.editorconfig`: four spaces for PHP and most files, two spaces for YAML and JSON, LF line endings, and a final newline.
- Keep changes scoped to the relevant module or theme. Use dependency injection and Magento service contracts where suitable; avoid modifying framework internals or using the Object Manager directly in application code.
- When adding a module, register it and declare its dependencies in `etc/module.xml` when ordering matters. Follow Magento conventions for `etc/di.xml`, `etc/webapi.xml`, `etc/acl.xml`, and declarative schema files.
- Change `composer.json` and `composer.lock` together when changing dependencies. Do not run a broad dependency update for an unrelated task.

## Validation

- For PHP changes, run `php -l <changed-file>` and the smallest relevant PHPUnit test. Unit and integration configurations are `dev/tests/unit/phpunit.xml.dist` and `dev/tests/integration/phpunit.xml.dist`; use `vendor/bin/phpunit -c <config> <test-path>`.
- For style checks, use the installed `vendor/bin/phpcs` or `vendor/bin/php-cs-fixer` on changed files. Follow the conventions already used by the nearby code.
- Integration tests and Magento CLI commands may need the Warden services and a configured database. Check the local environment before running commands that alter application state, such as `setup:upgrade`, reindexing, or cache flushing.
- Report what was tested and any checks that could not run. Do not claim tests passed if dependencies or services were unavailable.