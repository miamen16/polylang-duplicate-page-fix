<?php
/**
 * Polylang Duplicate Page Fix
 *
 * Prevents Duplicate Page from copying Polylang translation relationships
 * to duplicated posts/pages.
 *
 * @package PDP
 * @author  Aura Systems
 *
 * @wordpress-plugin
 * Plugin Name:       Polylang Duplicate Page Fix
 * Plugin URI:        https://www.aura.llc/
 * Description:       Prevents Duplicate Page from incorrectly linking duplicated posts/pages to the original Polylang translations.
 * Version:           1.0.0
 * Author:            Aura Systems
 * Author URI:        https://www.aura.llc/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pdpf
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.2
 *
 * Requires Plugins:  polylang
 */

defined( 'ABSPATH' ) || exit;

final class PDP_Polylang_Duplicate_Page_Fix {

	/**
	 * Original post being duplicated.
	 *
	 * @var int
	 */
	private static $original_id = 0;

	/**
	 * New post created by Duplicate Page.
	 *
	 * @var int
	 */
	private static $duplicate_id = 0;

	/**
	 * Whether the shutdown callback has been registered.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'admin_action_dt_duplicate_post_as_draft',
			array( __CLASS__, 'start_duplicate_request' ),
			1
		);

		add_action(
			'save_post',
			array( __CLASS__, 'capture_new_post' ),
			999,
			3
		);
	}

	/**
	 * Capture the original post ID from Duplicate Page's admin action.
	 *
	 * @return void
	 */
	public static function start_duplicate_request() {
		if ( ! self::polylang_available() ) {
			return;
		}

		self::$original_id = isset( $_REQUEST['post'] )
			? absint( $_REQUEST['post'] )
			: 0;

		if ( self::$original_id ) {
			self::register_shutdown();
		}
	}

	/**
	 * Capture the newly inserted duplicate.
	 *
	 * Duplicate Page creates the duplicate using wp_insert_post(), which fires
	 * save_post for the new post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function capture_new_post( $post_id, $post, $update ) {
		if (
			! self::$original_id ||
			$update ||
			! $post instanceof WP_Post ||
			self::$original_id === absint( $post_id )
		) {
			return;
		}

		self::$duplicate_id = absint( $post_id );
		self::register_shutdown();
	}

	/**
	 * Register cleanup after Duplicate Page has finished copying data.
	 *
	 * @return void
	 */
	private static function register_shutdown() {
		if ( self::$shutdown_registered ) {
			return;
		}

		self::$shutdown_registered = true;

		register_shutdown_function(
			array( __CLASS__, 'separate_duplicate_translation_group' )
		);
	}

	/**
	 * Put the duplicate into its own Polylang translation group.
	 *
	 * We intentionally do not use pll_save_post_translations() here.
	 * Calling it with a duplicate that has the same language as the original
	 * can rewrite the existing translation group.
	 *
	 * Instead, we create a new post_translations term containing only the
	 * duplicate and replace the duplicate's copied term relationship.
	 *
	 * @return void
	 */
	public static function separate_duplicate_translation_group() {
		if ( ! self::polylang_available() ) {
			return;
		}

		$duplicate_id = absint( self::$duplicate_id );

		if ( ! $duplicate_id || ! get_post( $duplicate_id ) ) {
			return;
		}

		$taxonomy = 'post_translations';

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$language = pll_get_post_language( $duplicate_id, 'slug' );

		if ( ! $language && self::$original_id ) {
			$language = pll_get_post_language( self::$original_id, 'slug' );

			if ( $language ) {
				pll_set_post_language( $duplicate_id, $language );
			}
		}

		if ( ! $language ) {
			return;
		}

		/*
		 * Create a completely new Polylang translation group containing only
		 * the duplicated post.
		 */
		$group_slug = 'pll_duplicate_' . $duplicate_id . '_' . wp_generate_uuid4();

		$result = wp_insert_term(
			$group_slug,
			$taxonomy,
			array(
				'slug'        => $group_slug,
				'description' => maybe_serialize(
					array(
						$language => $duplicate_id,
					)
				),
			)
		);

		if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
			return;
		}

		$new_term_id = absint( $result['term_id'] );

		/*
		 * Replace only the duplicate's copied post_translations term.
		 * The original post and its existing translations are not changed.
		 */
		wp_set_object_terms(
			$duplicate_id,
			array( $new_term_id ),
			$taxonomy,
			false
		);

		clean_object_term_cache( $duplicate_id, $taxonomy );
		clean_term_cache( $new_term_id, $taxonomy );
	}

	/**
	 * Check whether the required Polylang API is available.
	 *
	 * @return bool
	 */
	private static function polylang_available() {
		return function_exists( 'pll_get_post_language' )
			&& function_exists( 'pll_set_post_language' );
	}
}

PDP_Polylang_Duplicate_Page_Fix::init();
