<?php global $comment; ?>
<label><?php _e( 'Source', 'semantic-linkbacks' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_source', true ); ?>" />
<br />

<label><?php _e( 'Canonical', 'semantic-linkbacks' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true ); ?>" />
<br />

<label><?php _e( 'Avatar', 'semantic-linkbacks' ); ?></label>
<input type="text" class="widefat" name="avatar" id="avatar" value="<?php echo get_comment_meta( $comment->comment_ID, 'avatar', true ); ?>" />
<br />
<?php if ( in_array( $comment->comment_type, array( 'trackback', 'pingback', 'webmention' ), true ) ) { ?>
<label><?php _e( 'Type', 'semantic-linkbacks' ); ?></label>
	<select name="semantic_linkbacks_type" id="semantic_linkbacks_type" width="90%">
		<?php Linkbacks_Handler::comment_type_select( get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_type', true ), true ); ?>
	</select>
<br />
<?php } ?>
