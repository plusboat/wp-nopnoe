<?php
/**
 * Smoke test for Plusboat Editless.
 *
 * Runs inside a real WordPress, either as a Playground blueprint step or through
 * `wp eval-file`. Prints one line per check and throws when a check fails, so the run
 * fails loudly instead of passing quietly.
 *
 * @package Plusboat_Editless
 */

use Plusboat\Editless\Plugin;

$editless_failures = 0;

$editless_check = static function ( $condition, $message ) use ( &$editless_failures ) {
	if ( $condition ) {
		echo "PASS  {$message}\n";
		return;
	}

	++$editless_failures;
	echo "FAIL  {$message}\n";
};

$editless_render = static function ( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
};

$editless_user_id = 1;
wp_set_current_user( $editless_user_id );

$editless_post_id = wp_insert_post(
	array(
		'post_title'   => 'Plusboat Editless smoke test',
		'post_content' => 'Smoke test content.',
		'post_status'  => 'publish',
		'post_author'  => $editless_user_id,
	)
);

$editless_comment_id = wp_insert_comment(
	array(
		'comment_post_ID'      => $editless_post_id,
		'comment_author'       => 'Smoke Tester',
		'comment_author_email' => 'smoke@example.com',
		'comment_content'      => 'A smoke test comment.',
		'comment_approved'     => 1,
		'user_id'              => $editless_user_id,
	)
);

$editless_term    = wp_insert_term( 'Plusboat Editless smoke', 'category' );
$editless_term_id = is_array( $editless_term ) ? $editless_term['term_id'] : 0;

// Toolbar hidden: the case this plugin exists for.
update_user_meta( $editless_user_id, 'show_admin_bar_front', 'false' );
unset( $GLOBALS['show_admin_bar'] );

$editless_check( false === is_admin_bar_showing(), 'toolbar is hidden for the user' );
$editless_check( true === Plugin::should_suppress(), 'plugin suppresses edit links' );
$editless_check( null === get_edit_post_link( $editless_post_id ), 'post edit URL is blanked' );
$editless_check(
	'' === $editless_render(
		static function () use ( $editless_post_id ) {
			edit_post_link( 'Edit', '<span class="edit-link">', '</span>', $editless_post_id );
		}
	),
	'theme edit post link is gone'
);
$editless_check(
	is_string( get_edit_post_link( $editless_post_id, 'url' ) ) && '' !== get_edit_post_link( $editless_post_id, 'url' ),
	'non-display contexts keep their URL for notification emails'
);

$GLOBALS['comment'] = get_comment( $editless_comment_id );

$editless_comment_markup_off = $editless_render(
	static function () {
		edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' );
	}
);
$editless_term_markup_off = $editless_render(
	static function () use ( $editless_term_id ) {
		edit_term_link( 'Edit', '<span class="edit-link">', '</span>', $editless_term_id );
	}
);

$editless_check( null === get_edit_comment_link( $editless_comment_id ), 'comment edit URL is blanked' );
$editless_check(
	null === apply_filters( 'get_edit_comment_link', 'https://example.com/wp-admin/comment.php?c=1' ),
	'comment edit URL is blanked for a pre-6.7 single-argument call'
);
$editless_check(
	false === strpos( $editless_comment_markup_off, '<a' ) && false === strpos( $editless_comment_markup_off, 'Edit' ),
	'comment edit link markup is gone'
);
$editless_check( null === get_edit_term_link( $editless_term_id, 'category' ), 'term edit URL is blanked' );
$editless_check(
	false === strpos( $editless_term_markup_off, '<a' ) && false === strpos( $editless_term_markup_off, 'Edit' ),
	'term edit link markup is gone'
);

// Toolbar shown: the plugin must stay out of the way.
update_user_meta( $editless_user_id, 'show_admin_bar_front', 'true' );
unset( $GLOBALS['show_admin_bar'] );

$editless_check( true === is_admin_bar_showing(), 'toolbar is shown for the user' );
$editless_check( false === Plugin::should_suppress(), 'plugin stands down' );
$editless_check( null !== get_edit_post_link( $editless_post_id ), 'post edit URL is back' );
$editless_check(
	false !== strpos(
		$editless_render(
			static function () use ( $editless_post_id ) {
				edit_post_link( 'Edit', '<span class="edit-link">', '</span>', $editless_post_id );
			}
		),
		'post-edit-link'
	),
	'theme edit post link is back'
);
$editless_check( null !== get_edit_comment_link( $editless_comment_id ), 'comment edit URL is back' );
$editless_check(
	'https://example.com/wp-admin/comment.php?c=1' === apply_filters( 'get_edit_comment_link', 'https://example.com/wp-admin/comment.php?c=1' ),
	'pre-6.7 single-argument call passes the URL through when the toolbar is shown'
);
$editless_check( null !== get_edit_term_link( $editless_term_id, 'category' ), 'term edit URL is back' );
$editless_check(
	false !== strpos(
		$editless_render(
			static function () {
				edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' );
			}
		),
		'href'
	),
	'comment edit link markup is back'
);
$editless_check(
	false !== strpos(
		$editless_render(
			static function () use ( $editless_term_id ) {
				edit_term_link( 'Edit', '<span class="edit-link">', '</span>', $editless_term_id );
			}
		),
		'href'
	),
	'term edit link markup is back'
);

// Logged out: nothing to hide, nothing to touch.
wp_set_current_user( 0 );
unset( $GLOBALS['show_admin_bar'] );

$editless_check( false === Plugin::should_suppress(), 'logged-out visitors are left alone' );

if ( $editless_failures > 0 ) {
	throw new RuntimeException( sprintf( '%d smoke check(s) failed.', $editless_failures ) );
}

echo "All smoke checks passed.\n";
