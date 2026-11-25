=== No Panel No Edit ===
Contributors: plusboat
Tags: admin bar, admin toolbar, edit links, toolbar, hide admin bar
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

When a user hides the admin toolbar, stop showing them edit links on the front end.

== Description ==

WordPress lets every user turn off *Show Toolbar when viewing site* on their profile. Do
that and the admin bar disappears from the front end. The edit links do not.

Themes keep printing their own "Edit" link under every post, next to every comment, and on
term descriptions. Some plugins do it too. So a user who has just told WordPress "I do not
want the editing interface while I read this site" still gets invited to click into wp-admin
from every page.

The usual fix has been to open the theme, find the `edit_post_link()` calls, delete them, and
repeat the whole exercise after the next theme update. That is what this plugin does instead.

* No toolbar, no edit links. That is the entire rule.
* Nothing to configure. There is no settings screen, because there is nothing to set.
* Nothing is written to the database.

= How it decides =

No Panel No Edit does not have its own preference. It reads the one WordPress already has:
if `is_admin_bar_showing()` is false for the current front end request, the edit links are
hidden too. That means the plugin follows the user's profile setting, and it also respects
another plugin that hides the toolbar with the `show_admin_bar` filter.

= What is hidden =

* Post edit links, including the ones your theme prints with `edit_post_link()` and any it
  builds by hand from `get_edit_post_link()`.
* Comment edit links, including the ones core prints in comment lists and the
  Comment Edit Link block prints in block themes.
* Term edit links from `get_edit_term_link()` and `edit_term_link()`.

= What is not touched =

* **wp-admin.** The toolbar, the list table row actions, the editor, the Site Editor: all
  unchanged. Only front end page views are affected.
* **Notification emails.** WordPress builds the edit link in comment notification emails
  with a non-display context, and this plugin stays away from it, so admins keep their links
  in their inbox.
* **REST and Ajax requests.** They keep their edit URLs, so the editor and any front end
  script that needs one still works.
* **Logged-out visitors.** Nothing changes for them.

The one thing no plugin can catch is a hard-coded link: if your theme prints
`<a href="<?php echo admin_url( 'post.php?post=' . get_the_ID() . '&action=edit' ); ?>">` by
hand, without going through a core helper, it will still be there.

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the zip with Plugins → Add New →
   Upload Plugin.
2. Activate it. There is nothing else to do.
3. Open your profile (Users → Profile) and uncheck *Show Toolbar when viewing site*, then
   visit the front end. The toolbar and the edit links are both gone.

== Frequently Asked Questions ==

= Does this hide the toolbar as well? =

No. WordPress already does that when a user unchecks the setting. This plugin only removes
the edit links that other code prints anyway.

= Why not just remove the edit links from my theme? =

You can, until the next theme update replaces your edits. This keeps working across theme
changes, and it applies to every theme and plugin that uses the core helpers.

= Does it apply to administrators too? =

It applies to every logged-in user who has the toolbar hidden, administrators included.

= Can I force the behaviour on, or turn it off? =

Yes. The decision comes from one filter:

`add_filter( 'nopnoe_suppress_edit_links', '__return_true' );`

Returning `false` from that filter turns the plugin off completely for the request.

= Does it work with block themes? =

Yes. Block themes get their edit links from the admin bar and from the Comment Edit Link
block, and both are covered.

= Is anything stored in the database? =

No. No options, no tables, no user meta. Uninstalling leaves nothing behind.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
* First release.
