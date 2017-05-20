<?php

class MF2_Debugger {
	/**
	* Initialize the plugin.
	*/
	public static function init() {
		add_filter( 'query_vars', array( 'MF2_Debugger', 'query_var' ) );
		add_action( 'parse_query', array( 'MF2_Debugger', 'parse_query' ) );
	}

	public static function query_var($vars) {
		$vars[] = 'sldebug';
		$vars[] = 'sltarget';
		return $vars;
	}

	public static function parse_query($wp) {
		// check if it is a debug request or not
		if ( ! $wp->get( 'sldebug' ) ) {
			return;
		}
		$url = $wp->get( 'sldebug', 'form' );
		if ( 'form' === $url ) {
			status_header( 200 );
			self::form_header();
			self::post_form();
			self::form_footer();
			exit;
		}
		// If Not logged in, reject input
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			status_header( 400 );
			_e( 'The URL is Invalid', 'semantic-linkbacks' );
			exit;
		} else {
			// generate response to URL
			$response = Linkbacks_Handler::retrieve( $url );
			if ( is_wp_error( $response ) ) {
				status_header( 400 );
				return $response->get_error_message();
				exit;
			}
			$commentdata = array(
				'comment_type' => 'webmention',
				'comment_author_url' => $url,
				'comment_post_ID' => 0,
				'target' => $wp->get( 'sltarget', '' ),
				'remote_source_original' => wp_remote_retrieve_body( $response ),
				);
			$commentdata = Linkbacks_Handler::enhance( $commentdata );
			$commentdata = wp_array_slice_assoc( $commentdata, array(
				'comment_author',
				'comment_author_url',
				'comment_author_email',
				'comment_content',
				'comment_date',
				'comment_meta',
				'target',
				'remote_source_rels',
				'remote_source_properties',
			) );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			status_header( 200 );
			echo wp_json_encode( $commentdata );
			exit;

		}
	}
	public function form_header() {
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="profile" href="http://gmpg.org/xfn/11">
		<title><?php echo get_bloginfo( 'name' ); ?>  - <?php _e( 'Semantic Linkbacks Debugger', 'semantic-linkbacks' ); ?></title> 
	   </head>
		<body>
		<header> 
		   <h3><a href="<?php echo site_url(); ?>"><?php echo get_bloginfo( 'name' );?></a>
		   <a href="<?php echo admin_url(); ?>">(<?php _e( 'Dashboard', 'semantic-linkbacks' ); ?>)</a></h3>
		   <hr />
		   <h1> <?php _e( 'Semantic Linkbacks Debugger', 'semantic-linkbacks' ); ?></h1>
		</header>
		<?php
	}

	public static function form_footer() {
		?>
		</body>
		</html>
		<?php
	}

	public static function post_form( ) {
		?>
	  <div>
		<form action="<?php echo site_url();?>/?sldebug=<?php echo $action;?>" method="post" enctype="multipart/form-data">
		<p>
			<?php _e( 'URL:', 'semantic-linkbacks' ); ?>
		<input type="url" name="sldebug" size="70" />
		</p>
		<p> 
			<?php _e( 'Target:', 'semantic-linkbacks' ); ?>
		<input type="url" name="sltarget" id="sltarget" size="70" />
		</p>
			<input type="submit" />
	  </form>
	<?php // var_dump($data); ?>
	</div>
	<?php
	}

}
