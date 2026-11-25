<?php
/**
 * Plugin Name:       No panel - No edit
 * Plugin URI:        https://github.com/plusboat/wp-nopnoe
 * Description:       When a user hides the admin toolbar, stop showing them edit links on the front end.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Plusboat
 * Author URI:        https://plusbo.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nopnoe
 *
 * @package NopNoe
 */

namespace NopNoe;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-nopnoe-plugin.php';

Plugin::boot();
