# No panel - No edit

A two-hook WordPress plugin: when a user hides the admin toolbar, stop showing them edit
links on the front end. No panel, no edit.

* **Plugin slug:** `nopnoe`
* **Requires:** WordPress 6.0+, PHP 7.4+
* **User documentation:** [readme.txt](readme.txt)
* **License:** GPL-2.0-or-later

## Why

WordPress already lets every user uncheck *Show Toolbar when viewing site*. When they do,
the admin bar goes away, but themes and plugins keep printing their own "Edit" links, so the
user is still invited into wp-admin from every page.

Removing those links by editing the theme works exactly until the next theme update. This
plugin does it with two documented filters and no configuration.

## How it works

The decision comes from one condition:

```php
is_user_logged_in() && ! is_admin() && ! wp_doing_ajax() && ! wp_is_json_request() && ! is_admin_bar_showing()
```

`is_admin_bar_showing()` already accounts for the user's profile preference and for the
`show_admin_bar` filter, so "no panel" implies "no edit" without the plugin keeping state of
its own.

When that condition holds, these filters stand down:

| Hook | Result |
| --- | --- |
| `get_edit_post_link` (display context) | `null` |
| `edit_comment_link` | `''` |
| `get_edit_comment_link` (display context) | `null` |
| `get_edit_term_link` | `null` |
| `edit_term_link` | `''` |

Notes on the shape of it:

* Only the `display` context is touched. Core builds the edit link in comment notification
  emails with the `url` context, so those links survive.
* `is_admin()`, `wp_doing_ajax()` and `wp_is_json_request()` guard the admin, Ajax and REST
  requests, so the editor and admin-side code keep their edit URLs.
* `edit_comment_link()` and `edit_term_link()` build their anchor even when the URL is
  empty, which is why both the URL filters and the output filters are needed.
* Nothing is stored. No options, no user meta, no tables, so there is no `uninstall.php`.

The behaviour can be overridden with one filter:

```php
add_filter( 'nopnoe_suppress_edit_links', '__return_true' );  // force on
add_filter( 'nopnoe_suppress_edit_links', '__return_false' ); // turn off
```

## Development

The plugin runs on WordPress Playground, which needs no Docker, no MySQL and no web server
setup. Requirements: Node.js 20+, PHP 8.1+ for the tooling, Composer.

```bash
composer install
npx @wp-playground/cli@latest start
```

`start` detects the plugin in the current directory, mounts it at
`wp-content/plugins/nopnoe`, activates it and opens the site at http://127.0.0.1:9400.
Log in with `admin` / `password`, then use Users → Profile to toggle *Show Toolbar when
viewing site* and watch the front end.

Useful flags: `--reset` (start from a clean site), `--wp=<version>`, `--php=<version>`.

## Testing

```bash
composer lint                                     # PHPCS, WordPress Coding Standards
npx @wp-playground/cli@latest run-blueprint \
  --blueprint=tests/smoke-blueprint.json \
  --mount="$PWD:/wordpress/wp-content/plugins/nopnoe"
```

The blueprint boots a real WordPress, activates the plugin and runs
[tests/smoke.php](tests/smoke.php), which asserts both directions: edit links disappear for
a user with the toolbar hidden, come back for a user with it shown, non-display contexts
keep their URLs, and logged-out visitors are untouched. The step exits non-zero when a check
fails.

The official Plugin Check plugin cannot run inside Playground: its PHPCS engine needs
`php://stdin`, which PHP.wasm does not provide. Run it natively instead, against a
throwaway WordPress:

```bash
bin/plugin-check.sh          # build, install and run every check
bin/plugin-check.sh --fresh  # rebuild the WordPress site and database first
bin/plugin-check.sh --stop   # stop the database when you are done
```

Requirements: WP-CLI, PHP and MariaDB. Everything the script creates lives in
`.plugincheck/` (git- and release-ignored) and is disposable. It starts its own database
instance on port 33099 using a `mysql.pid` in its datadir, and refuses to run if that port
belongs to some other server. Override the port with `NOPNOE_DB_PORT` and the PHP binary
with `PHP_BIN`.

It checks `dist/nopnoe`, meaning exactly what ships, not the repository with its
`vendor/` and `tests/`.

Current result: **0 errors, 0 warnings**. (Before the rename it showed three
`trademarked_term` warnings, because Plugin Check flags "wp" in the plugin name and slug —
its source marks that term `// it's allowed, but shows a warning.`, so it would have been
allowed, but the name no longer contains it.)

## Release

### The first release cannot be automated

WordPress.org has no API for the initial submission. You upload the zip at
[wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/) and a
human volunteer reviews it; only after approval does the SVN repository, and with it any
chance of automation, exist. Nothing in a workflow file can skip that queue.

The slug is assigned during review, derived from the plugin name (the FAQ example:
`Boaty McBoatface` → `boaty-mcboatface`). "No panel - No edit" would therefore become
`no-panel-no-edit`, not `nopnoe`. Everything here assumes `nopnoe` — text domain, zip folder,
SVN path — so use the **change slug** link that appears on the submission page right after
uploading, which can be used once before review begins. If that link is gone, email
`plugins@wordpress.org`; they will change it as long as the plugin has not been approved.
If a different slug is assigned anyway, the text domain and `SLUG` in the workflows follow it.

### After approval, tag and everything else is automated

The plugin folder inside the zip must be named `nopnoe`: 

```bash
bin/build-zip.sh             # writes dist/nopnoe.zip
unzip -l dist/nopnoe.zip  # confirm dev files are excluded
```

To publish, add the WordPress.org credentials as repository secrets:

| Secret | Value |
| --- | --- |
| `SVN_USERNAME` | your wordpress.org username |
| `SVN_PASSWORD` | your wordpress.org password |

Then bump the version in both `nopnoe.php` and `readme.txt`, commit, and tag **without a
`v` prefix**:

```bash
git tag 1.0.0
git push origin 1.0.0
```

[release.yml](.github/workflows/release.yml) then checks that the tag, the plugin header and
`Stable tag` all match, builds the zip, attaches it to a GitHub release, and pushes trunk plus
`tags/<version>` to WordPress.org through
[10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy).
The tag name is the SVN tag name, which is why the `v` matters.

Without the secrets the build and the GitHub release still run and the SVN step is skipped,
so forks and first-time setups are safe. `workflow_dispatch` with *dry run* builds and
validates without committing.

### Manual fallback

```bash
svn co https://plugins.svn.wordpress.org/nopnoe svn-nopnoe
rsync -a --delete --exclude-from=.distignore ./ svn-nopnoe/trunk/
svn add --force svn-nopnoe/trunk
svn cp svn-nopnoe/trunk svn-nopnoe/tags/1.0.0
svn ci svn-nopnoe -m "Release 1.0.0"
```

The `Contributors:` line in [readme.txt](readme.txt) must be your wordpress.org *username*,
not your display name — a mismatched value silently produces no contributor on the plugin
page. The username cannot be changed, but the name shown on the directory comes from your
profile's display name, which you can edit at
`https://profiles.wordpress.org/<username>/profile/edit/`.

## Credits

Built collaboratively with AI coding agents, reviewed and maintained by
[Plusboat](https://plusbo.at).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
