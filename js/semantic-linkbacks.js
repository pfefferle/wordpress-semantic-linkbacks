document.addEventListener( 'DOMContentLoaded', function() {
  var showAdditionalFacepileButtons = document.querySelectorAll( '.show-additional-facepiles' ),
    mentionLists = document.querySelectorAll( '.mention-list' );

  if ( showAdditionalFacepileButtons.length === 0 || mentionLists.length === 0 ) {
    return;
  }

  // Add `initialized` class to mention-list container. When JS is disabled or not working, we want to show all items.
  for ( var i = 0; i < mentionLists.length; i++ ) {
    var mentionList = mentionLists[i];
    mentionList.classList.add( 'initialized' );
  }

  // Loop the buttons to show additional facepiles.
  for ( var i = 0; i < showAdditionalFacepileButtons.length; i++ ) {
    var showAdditionalFacepileButton = showAdditionalFacepileButtons[i],
      hideAdditionalFacepileButton = showAdditionalFacepileButton.parentNode.parentNode.querySelector( '.hide-additional-facepiles' );

    showAdditionalFacepileButton.addEventListener( 'click', function() {
      toggleListItemClasses( this );
    } );

    hideAdditionalFacepileButton.addEventListener( 'click', function() {
      toggleListItemClasses( this );
    } );
  }

  /**
   * Toggle the classes of the list items that contain the buttons.
   *
   * @param {HTMLElement} clickedButton
   */
  var toggleListItemClasses = function( clickedButton ) {
    var buttonListItem = clickedButton.parentNode,
    otherButtonListItem = buttonListItem.classList.contains( 'is-hidden' )
      ? buttonListItem.parentNode.querySelector( '.additional-facepile-button-list-item:not(.is-hidden)' )
      : buttonListItem.parentNode.querySelector( '.additional-facepile-button-list-item.is-hidden' );

    buttonListItem.classList.toggle( 'is-hidden' );
    otherButtonListItem.classList.toggle( 'is-hidden' );
  }
});
