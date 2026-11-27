<?php
/**
 * Front end edit link suppression.
 *
 * @package Plusboat_Editless
 */

namespace Plusboat\Editless;

defined( 'ABSPATH' ) || exit;

/**
 * Hides edit links from users who are not shown the admin toolbar.
 *
 * The toolbar is the only edit affordance core prints on the front end by itself. Users who
 * turn it off in their profile are still handed the edit links that themes and plugins
 * render, which is why sites end up patching templates instead. No toolbar, no edit links.
 */
final class Plugin {

	/**
	 * Hooks the filters.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'get_edit_post_link', array( __CLASS__, 'hide_post_edit_link' ), 10, 3 );
		add_filter( 'get_edit_comment_link', array( __CLASS__, 'hide_comment_edit_link' ), 10, 3 );
		add_filter( 'edit_comment_link', array( __CLASS__, 'hide_comment_link_markup' ) );
		add_filter( 'get_edit_term_link', array( __CLASS__, 'hide_term_edit_link' ) );
		add_filter( 'edit_term_link', array( __CLASS__, 'hide_term_link_markup' ) );
	}

	/**
	 * Tells whether edit links are hidden for the current request.
	 *
	 * Front end page views only: admin screens, admin-ajax and REST requests keep their edit
	 * URLs, so admin-side code and the block editor are unaffected.
	 *
	 * @return bool
	 */
	public static function should_suppress() {
		$suppress = false;

		if (
			is_user_logged_in()
			&& ! is_admin()
			&& ! wp_doing_ajax()
			&& ! wp_is_json_request()
			&& ! is_admin_bar_showing()
		) {
			$suppress = true;
		}

		/**
		 * Filters whether front end edit links are hidden for the current request.
		 *
		 * @param bool $suppress Whether to hide edit links. Defaults to true when the current
		 *                       user is not shown the admin toolbar.
		 */
		return (bool) apply_filters( 'plusboat_editless_suppress_edit_links', $suppress );
	}

	/**
	 * Blanks the post edit URL used for display.
	 *
	 * @param string|null $link    Edit link URL.
	 * @param int         $post_id Post ID.
	 * @param string      $context Link context.
	 * @return string|null
	 */
	public static function hide_post_edit_link( $link, $post_id, $context ) {
		if ( 'display' !== $context || ! self::should_suppress() ) {
			return $link;
		}

		return null;
	}

	/**
	 * Blanks the comment edit URL used for display.
	 *
	 * WordPress only started passing `$comment_id` and `$context` to this filter in 6.7, so
	 * the parameters need defaults; before that the context was always 'display'.
	 *
	 * @param string|null $link       Edit link URL.
	 * @param int         $comment_id Comment ID.
	 * @param string      $context    Link context.
	 * @return string|null
	 */
	public static function hide_comment_edit_link( $link, $comment_id = 0, $context = 'display' ) {
		if ( 'display' !== $context || ! self::should_suppress() ) {
			return $link;
		}

		return null;
	}

	/**
	 * Removes the comment edit link markup.
	 *
	 * The anchor is built even when the URL is empty, so the output needs its own filter.
	 *
	 * @param string $link Anchor tag for the edit link.
	 * @return string
	 */
	public static function hide_comment_link_markup( $link ) {
		return self::should_suppress() ? '' : $link;
	}

	/**
	 * Blanks the term edit URL.
	 *
	 * @param string $link Edit link URL.
	 * @return string|null
	 */
	public static function hide_term_edit_link( $link ) {
		return self::should_suppress() ? null : $link;
	}

	/**
	 * Removes the term edit link markup.
	 *
	 * @param string $link Anchor tag for the edit link.
	 * @return string
	 */
	public static function hide_term_link_markup( $link ) {
		return self::should_suppress() ? '' : $link;
	}
}
