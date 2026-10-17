# Alba Board development

- Use the existing checkout; each cloud task is already isolated. Do not create a Git worktree unless requested.
- Develop on `codex/` branches. Open pull requests targeting `main` for manual review and merge; do not merge automatically.
- Use repository-relative paths in agent documentation and memory instructions.
- The plugin entry point is `alba-board.php`; PHP modules are in `includes/`, browser assets in `assets/`, translations in `languages/`, and plugin documentation in `readme.txt`.
- The disposable cloud site and tools live alongside the checkout in `../alba-dev/`. Its `wordpress/wp-content/plugins/alba-board` symlink points to this checkout, so source edits apply immediately.
- Start retained services with `bash ../alba-dev/start.sh`. Use `../alba-dev/bin/wp` for WP-CLI and `../alba-dev/php/bin/php` for PHP 8.3. Run browser smoke checks with `node ../alba-dev/browser/smoke.cjs`.
- The local test site uses loopback port 8080, with administration at `/wp-admin/`. This is a disposable site, never production. Locally generated login credentials are kept in `../alba-dev/credentials.json`; never commit them or print them in logs.
- Before submitting changes, lint changed PHP files and exercise the affected frontend and authenticated admin behavior. There is no repository-provided build or automated test suite at onboarding time.
- Keep WordPress core, database data, downloaded tools, credentials, browser artifacts, and generated files outside the plugin checkout. Do not commit environment state or secrets.
