<?php
/**
 * Smoke test for No Panel No Edit.
 *
 * Runs inside a real WordPress, either as a Playground blueprint step or through
 * `wp eval-file`. Prints one line per check and throws when a check fails, so the run
 * fails loudly instead of passing quietly.
 *
 * @package NopNoE
 */

use NopNoe\Plugin;

$nopnoe_failures = 0;

$nopnoe_check = static function ( $condition, $message ) use ( &$nopnoe_failures ) {
	if ( $condition ) {
		echo "PASS  {$message}\n";
		return;
	}

	++$nopnoe_failures;
	echo "FAIL  {$message}\n";
};

$nopnoe_render = static function ( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
};

$nopnoe_user_id = 1;
wp_set_current_user( $nopnoe_user_id );

$nopnoe_post_id = wp_insert_post(
	array(
		'post_title'   => 'No Panel No Edit smoke test',
		'post_content' => 'Smoke test content.',
		'post_status'  => 'publish',
		'post_author'  => $nopnoe_user_id,
	)
);

$nopnoe_comment_id = wp_insert_comment(
	array(
		'comment_post_ID'      => $nopnoe_post_id,
		'comment_author'       => 'Smoke Tester',
		'comment_author_email' => 'smoke@example.com',
		'comment_content'      => 'A smoke test comment.',
		'comment_approved'     => 1,
		'user_id'              => $nopnoe_user_id,
	)
);

$nopnoe_term    = wp_insert_term( 'No Panel No Edit smoke', 'category' );
$nopnoe_term_id = is_array( $nopnoe_term ) ? $nopnoe_term['term_id'] : 0;

// Toolbar hidden: the case this plugin exists for.
update_user_meta( $nopnoe_user_id, 'show_admin_bar_front', 'false' );
unset( $GLOBALS['show_admin_bar'] );

$nopnoe_check( false === is_admin_bar_showing(), 'toolbar is hidden for the user' );
$nopnoe_check( true === Plugin::should_suppress(), 'plugin suppresses edit links' );
$nopnoe_check( null === get_edit_post_link( $nopnoe_post_id ), 'post edit URL is blanked' );
$nopnoe_check(
	'' === $nopnoe_render(
		static function () use ( $nopnoe_post_id ) {
			edit_post_link( 'Edit', '<span class="edit-link">', '</span>', $nopnoe_post_id );
		}
	),
	'theme edit post link is gone'
);
$nopnoe_check(
	is_string( get_edit_post_link( $nopnoe_post_id, 'url' ) ) && '' !== get_edit_post_link( $nopnoe_post_id, 'url' ),
	'non-display contexts keep their URL for notification emails'
);

$GLOBALS['comment'] = get_comment( $nopnoe_comment_id );

$nopnoe_comment_markup_off = $nopnoe_render(
	static function () {
		edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' );
	}
);
$nopnoe_term_markup_off = $nopnoe_render(
	static function () use ( $nopnoe_term_id ) {
		edit_term_link( 'Edit', '<span class="edit-link">', '</span>', $nopnoe_term_id );
	}
);

$nopnoe_check( null === get_edit_comment_link( $nopnoe_comment_id ), 'comment edit URL is blanked' );
$nopnoe_check(
	null === apply_filters( 'get_edit_comment_link', 'https://example.com/wp-admin/comment.php?c=1' ),
	'comment edit URL is blanked for a pre-6.7 single-argument call'
);
$nopnoe_check(
	false === strpos( $nopnoe_comment_markup_off, '<a' ) && false === strpos( $nopnoe_comment_markup_off, 'Edit' ),
	'comment edit link markup is gone'
);
$nopnoe_check( null === get_edit_term_link( $nopnoe_term_id, 'category' ), 'term edit URL is blanked' );
$nopnoe_check(
	false === strpos( $nopnoe_term_markup_off, '<a' ) && false === strpos( $nopnoe_term_markup_off, 'Edit' ),
	'term edit link markup is gone'
);

// Toolbar shown: the plugin must stay out of the way.
update_user_meta( $nopnoe_user_id, 'show_admin_bar_front', 'true' );
unset( $GLOBALS['show_admin_bar'] );

$nopnoe_check( true === is_admin_bar_showing(), 'toolbar is shown for the user' );
$nopnoe_check( false === Plugin::should_suppress(), 'plugin stands down' );
$nopnoe_check( null !== get_edit_post_link( $nopnoe_post_id ), 'post edit URL is back' );
$nopnoe_check(
	false !== strpos(
		$nopnoe_render(
			static function () use ( $nopnoe_post_id ) {
				edit_post_link( 'Edit', '<span class="edit-link">', '</span>', $nopnoe_post_id );
			}
		),
		'post-edit-link'
	),
	'theme edit post link is back'
);
$nopnoe_check( null !== get_edit_comment_link( $nopnoe_comment_id ), 'comment edit URL is back' );
$nopnoe_check(
	'https://example.com/wp-admin/comment.php?c=1' === apply_filters( 'get_edit_comment_link', 'https://example.com/wp-admin/comment.php?c=1' ),
	'pre-6.7 single-argument call passes the URL through when the toolbar is shown'
);
$nopnoe_check( null !== get_edit_term_link( $nopnoe_term_id, 'category' ), 'term edit URL is back' );
$nopnoe_check(
	false !== strpos(
		$nopnoe_render(
			static function () {
				edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' );
			}
		),
		'href'
	),
	'comment edit link markup is back'
);
$nopnoe_check(
	false !== strpos(
		$nopnoe_render(
			static function () use ( $nopnoe_term_id ) {
				edit_term_link( 'Edit', '<span class="edit-link">', '</span>', $nopnoe_term_id );
			}
		),
		'href'
	),
	'term edit link markup is back'
);

// Logged out: nothing to hide, nothing to touch.
wp_set_current_user( 0 );
unset( $GLOBALS['show_admin_bar'] );

$nopnoe_check( false === Plugin::should_suppress(), 'logged-out visitors are left alone' );

if ( $nopnoe_failures > 0 ) {
	throw new RuntimeException( sprintf( '%d smoke check(s) failed.', $nopnoe_failures ) );
}

echo "All smoke checks passed.\n";
