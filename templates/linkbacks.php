<?php $facepiles = get_option( 'semantic_linkbacks_facepiles', array() ); ?>
<?php if ( in_array( 'reacji', $facepiles, true ) && Semantic_Linkbacks_Walker_Comment::$reactions ) : ?>
<div class="reactions">
	<h3><?php echo __( 'Reacjis', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'reacji',
		),
		Semantic_Linkbacks_Walker_Comment::$reactions
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'like', $facepiles, true ) && has_linkbacks( 'like' ) ) : ?>
<div class="likes">
	<h3><?php echo __( 'Likes', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'like',
		),
		get_linkbacks( 'like' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'favorite', $facepiles, true ) && has_linkbacks( 'favorite' ) ) : ?>
<div class="favorites">
	<h3><?php echo __( 'Favourites', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'favorite',
		),
		get_linkbacks( 'favorite' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'bookmark', $facepiles, true ) && has_linkbacks( 'bookmark' ) ) : ?>
<div class="bookmarks">
	<h3><?php echo __( 'Bookmarks', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'bookmark',
		),
		get_linkbacks( 'bookmark' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'repost', $facepiles, true ) && has_linkbacks( 'repost' ) ) : ?>
<div class="reposts">
	<h3><?php echo __( 'Reposts', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'repost',
		),
		get_linkbacks( 'repost' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'tag', $facepiles, true ) && has_linkbacks( 'tag' ) ) : ?>
<div class="tags">
	<h3><?php echo __( 'Tags', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'tag',
		),
		get_linkbacks( 'tag' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'listen', $facepiles, true ) && has_linkbacks( 'listen' ) ) : ?>
<div class="listens">
	<h3><?php echo __( 'Listening', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'listen',
		),
		get_linkbacks( 'listen' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'read', $facepiles, true ) && has_linkbacks( 'read' ) ) : ?>
<div class="reads">
	<h3><?php echo __( 'Reading', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'read',
		),
		get_linkbacks( 'read' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'follow', $facepiles, true ) && has_linkbacks( 'follow' ) ) : ?>
<div class="follows">
	<h3><?php echo __( 'Following', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'follow',
		),
		get_linkbacks( 'follow' )
	);
	?>
</div>
<?php endif; ?>

<?php if ( in_array( 'watch', $facepiles, true ) && has_linkbacks( 'watch' ) ) : ?>
<div class="watches">
	<h3><?php echo __( 'Watching', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'watch',
		),
		get_linkbacks( 'watch' )
	);
	?>
</div>
<?php endif; ?>



<?php if ( in_array( 'rsvp', $facepiles, true ) && has_linkbacks( 'rsvp' ) ) : ?>
<div class="rsvps">
	<h3><?php _e( 'RSVPs', 'semantic-linkbacks' ); ?></h3>

	<?php if ( in_array( 'rsvp:yes', $facepiles, true ) && has_linkbacks( 'rsvp:yes' ) ) : ?>
	<h4><?php _e( 'Yes', 'semantic-linkbacks' ); ?></h4>
	<?php
	list_linkbacks(
		array(
			'type' => 'rsvp-yes',
		),
		get_linkbacks( 'rsvp:yes' )
	);
	?>
	<?php endif; ?>

	<?php if ( in_array( 'invited', $facepiles, true ) && has_linkbacks( 'invited' ) ) : ?>
	<h4><?php _e( 'Invited', 'semantic-linkbacks' ); ?></h4>
	<?php
	list_linkbacks(
		array(
			'type' => 'invited',
		),
		get_linkbacks( 'invited' )
	);
	?>
	<?php endif; ?>

	<?php if ( in_array( 'rsvp:maybe', $facepiles, true ) && has_linkbacks( 'rsvp:maybe' ) ) : ?>
	<h4><?php _e( 'Maybe', 'semantic-linkbacks' ); ?></h4>
	<?php
	list_linkbacks(
		array(
			'type' => 'rsvp-maybe',
		),
		get_linkbacks( 'rsvp:maybe' )
	);
	?>
	<?php endif; ?>

	<?php if ( in_array( 'rsvp:no', $facepiles, true ) && has_linkbacks( 'rsvp:no' ) ) : ?>
	<h4><?php _e( 'No', 'semantic-linkbacks' ); ?></h4>
	<?php
	list_linkbacks(
		array(
			'type' => 'rsvp-no',
		),
		get_linkbacks( 'rsvp:no' )
	);
	?>
	<?php endif; ?>

	<?php if ( in_array( 'rsvp:interested', $facepiles, true ) && has_linkbacks( 'rsvp:interested' ) ) : ?>
	<h4><?php _e( 'Interested', 'semantic-linkbacks' ); ?></h4>
	<?php
	list_linkbacks(
		array(
			'type' => 'rsvp-interested',
		),
		get_linkbacks( 'rsvp:interested' )
	);
	?>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php if ( in_array( 'mention', $facepiles, true ) && has_linkbacks( 'mention' ) ) : ?>
<div class="mentions">
	<h3><?php echo __( 'Mentions', 'semantic-linkbacks' ); ?></h3>
	<?php
	list_linkbacks(
		array(
			'type' => 'mention',
		),
		get_linkbacks( 'mention' )
	);
	?>
</div>
<?php endif; ?>
