<?php
/**
 * Plugin Name:       Plusboat Editless
 * Plugin URI:        https://github.com/plusboat/plusboat-editless
 * Description:       When a user hides the admin toolbar, stop showing them edit links on the front end.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Plusboat
 * Author URI:        https://plusbo.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plusboat-editless
 *
 * @package Plusboat_Editless
 */

namespace Plusboat\Editless;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-plusboat-editless-plugin.php';

Plugin::boot();
