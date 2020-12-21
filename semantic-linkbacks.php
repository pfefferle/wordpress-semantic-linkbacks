<?php
/**
 * Plugin Name: Semantic-Linkbacks
 * Plugin URI: https://github.com/pfefferle/wordpress-semantic-linkbacks
 * Description: Semantic Linkbacks for WebMentions, Trackbacks and Pingbacks
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 3.10.4
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: semantic-linkbacks
 * Requires PHP: 5.4
 */

add_action( 'plugins_loaded', array( 'Semantic_Linkbacks_Plugin', 'init' ), 11 );

// initialize admin settings
add_action( 'admin_init', array( 'Semantic_Linkbacks_Plugin', 'admin_init' ) );

/**
 * Semantic linkbacks class
 *
 * @author Matthias Pfefferle
 */
class Semantic_Linkbacks_Plugin {
	public static $version = '3.10.4';
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {

		if ( ! function_exists( 'Emoji\detect_emoji' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/p3k/emoji-detector/src/Emoji.php';
		}

		require_once dirname( __FILE__ ) . '/includes/class-linkbacks-walker-comment.php';
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		if ( ! class_exists( 'Webmention_Avatar_Handler' ) ) {
			require_once dirname( __FILE__ ) . '/includes/class-linkbacks-avatar-handler.php';
			add_action( 'init', array( 'Linkbacks_Avatar_Handler', 'init' ) );
		}

		require_once dirname( __FILE__ ) . '/includes/class-linkbacks-handler.php';
		add_action( 'init', array( 'Linkbacks_Handler', 'init' ) );

		require_once dirname( __FILE__ ) . '/includes/class-linkbacks-mf2-handler.php';
		add_action( 'init', array( 'Linkbacks_MF2_Handler', 'init' ) );

		require_once dirname( __FILE__ ) . '/includes/class-linkbacks-notifications.php';
		add_action( 'init', array( 'Linkbacks_Notifications', 'init' ) );

		add_action( 'wp_enqueue_scripts', array( 'Semantic_Linkbacks_Plugin', 'enqueue_scripts' ) );

		remove_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_title_filter' ), 21 );
		remove_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_content_filter' ), 22 );

		self::register_settings();
		self::plugin_textdomain();
	}

	public static function admin_init() {
		self::privacy_declaration();
		$page = function_exists( 'webmention_init' ) ? 'webmention' : 'discussion';
		add_settings_section(
			'semantic-linkbacks',
			__( 'Semantic Linkbacks Settings', 'semantic-linkbacks' ),
			array( 'Semantic_Linkbacks_Plugin', 'settings' ),
			$page
		);
		add_settings_field(
			'semantic_linkbacks_facepiles',
			__( 'Automatically embed facepiles <small>(may not work on all themes)</small> for:', 'semantic-linkbacks' ),
			array( 'Semantic_Linkbacks_Plugin', 'facepile_checkboxes' ),
			$page,
			'semantic-linkbacks'
		);
		add_settings_field(
			'semantic_linkbacks_facepile_fold_limit',
			__( 'Initial number of faces to show in facepiles <small>(0 for all)</small>', 'semantic-linkbacks' ),
			array( 'Semantic_Linkbacks_Plugin', 'facepile_fold_limit' ),
			$page,
			'semantic-linkbacks'
		);
	}

	public static function facepile_fold_limit() {
		printf( '<input type="number" min="0" step="1" name="semantic_linkbacks_facepiles_fold_limit" id="semantic_linkbacks_facepiles_fold_limit" class="small-text" value="%d" />', get_option( 'semantic_linkbacks_facepiles_fold_limit' ) );
	}

	public static function facepile_checkboxes() {
		$strings  = Linkbacks_Handler::get_comment_type_strings();
		$facepile = get_option( 'semantic_linkbacks_facepiles' );
		echo '<div id="facepile-all">';
		foreach ( $strings as $key => $value ) {
			printf( '<input name="semantic_linkbacks_facepiles[]" type="checkbox" value="%1$s" id="%1$s" %2$s /><label for="%1$s">%3$s</label><br />', $key, checked( in_array( $key, $facepile, true ), true, false ), $value );
		}
		echo '</div>';
	}

	public static function register_settings() {
		$option_group = function_exists( 'webmention_init' ) ? 'webmention' : 'discussion';
		register_setting(
			$option_group,
			'semantic_linkbacks_facepiles',
			array(
				'type'         => 'string',
				'description'  => __( 'Types to show in Facepiles', 'semantic-linkbacks' ),
				'show_in_rest' => true,
				'default'      => array_keys( Linkbacks_Handler::get_comment_type_strings() ),
			)
		);
		register_setting(
			$option_group,
			'semantic_linkbacks_facepiles_fold_limit',
			array(
				'type'         => 'integer',
				'description'  => __( 'Initial number of faces to show in facepiles', 'semantic-linkbacks' ),
				'show_in_rest' => true,
				'default'      => 8,
			)
		);
	}

	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		// Note to self, the third argument must not be hardcoded, to account for relocated folders.
		load_plugin_textdomain( 'semantic-linkbacks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Add Semantic Linkbacks options to the webmentions settings page.
	 */
	public static function settings() {
		_e( 'For webmentions that do not have avatars you can pick from several locally served default avatars in the Discussion Settings', 'semantic-linkbacks' );

		if ( ! function_exists( 'mb_internal_encoding' ) ) {
			?>
		<p class="notice notice-warning"><?php _e( 'This server does not have the php-mbstring package installed and Emoji reactions have been disabled.', 'semantic-linkbacks' ); ?></p>
			<?php
		}
	}

	/**
	 * Add CSS and JavaScript
	 */
	public static function enqueue_scripts() {
		wp_enqueue_style( 'semantic-linkbacks-css', plugin_dir_url( __FILE__ ) . 'css/semantic-linkbacks.css', array(), self::$version );

		if ( is_singular() && 0 !== (int) get_option( 'semantic_linkbacks_facepiles_fold_limit', 8 ) ) {
			wp_enqueue_script( 'semantic-linkbacks', plugin_dir_url( __FILE__ ) . 'js/semantic-linkbacks.js', array( 'jquery' ), self::$version, true );
		}
	}

	public static function privacy_declaration() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content = __(
				'For received webmentions, pingbacks and trackbacks, such as responding to a post or article, this site stores information retrieved from the source
				in order to provide a richer comment. Items such as author name and image, summary of the text, etc may be stored if present in the source and are
				solely to provide richer comments. We will remove any of this on request.',
				'semantic-linkbacks'
			);
			wp_add_privacy_policy_content(
				'Semantic-Linkbacks',
				wp_kses_post( wpautop( $content, false ) )
			);
		}
	}

}
