# Alba Board: notes for coding agents

Alba Board is a visual, AI-ready (MCP) project management plugin for WordPress. It aims to automate work, save users time and help them perform better, through a beautiful, simple UI. It provides boards, lists and cards with comments, attachments, roles and permissions in WordPress admin and on the frontend (shortcode), plus an optional AI connection through the WordPress Abilities API and MCP.

## Code map
- `alba-board.php`: plugin header and bootstrap. Keep the version here in step with `Stable tag` in `readme.txt`.
- `includes/`: PHP modules, such as admin pages, AJAX and REST handlers, permissions, shortcodes and MCP/OAuth.
- `assets/`: CSS, JavaScript and images, shipped as-is with no build step.
- `languages/`: `.pot`, `.po` and `.mo` files for text domain `alba-board`.
- `readme.txt`: the WordPress.org listing and changelog. `uninstall.php`: data cleanup.

## Rules
- Only change what is needed for the task; do not do more than asked. When something is unclear, ask.
- Protect existing data. Do not rename or remove post types (`alba_board`, `alba_list`, `alba_card`), meta keys or option names unless it is required, for example for performance. Then include a safe migration that can run twice, keeps old data readable, and update `uninstall.php` to match.
- Follow the WordPress coding standards and the Plugin Directory guidelines. Sanitize input, escape output, check capabilities and nonces, and use `$wpdb->prepare()` for queries.
- Support PHP 7.4+, WordPress 5.8+, and both MySQL and MariaDB.
- Make all user-facing text translatable with the `alba-board` text domain.
- Keep the UI simple and consistent with the existing design; check phone and desktop widths.
- Do not load remote code or assets, add tracking that runs without consent, or obfuscate code.
- Work on a branch and open a pull request to `main`. Do not merge or release on your own.

## Testing and releases
This repository ships only the plugin. The maintainer tests, runs QA and prepares releases with a separate private toolkit (Cosmonauts).
- If your session started in that private workspace, follow its instructions for test sites, QA and releases.
- Otherwise, use any disposable WordPress install outside this folder, and never a production site.
- Never commit WordPress core, databases, credentials, test output or tool configuration to this repository.

Development files such as this one are excluded from the WordPress.org package through `.distignore` and `.gitattributes`.
