<?php
/**
 * Provides the logic for responding to message menu option requests.
 * Any work done here needs to be completed fairly quickly, so anything intensive should be already cached locally.
 *
 * @package Slackemon
 */

function slackemon_get_slack_message_menu_options( $action_name, $action_value ) {

  $action_name = explode( '/', $action_name );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode();

  switch ( $action_name[0] ) {

    // Adding Pokemon to the battle team, via the Battle menu.
    case 'battle-team':

      if ( ! isset( $action_name[1] ) ) {
        return false;
      }

      // We only support the 'battle-team/add' action here for now.
      if ( 'add' !== $action_name[1] ) {
        return false;
      }

      $pokemon_collection = slackemon_search_player_pokemon( $action_value );

      // Remove Pokemon that are already in the battle team, because we can't add them again!
      $pokemon_collection = array_filter(
        $pokemon_collection,
        function( $_pokemon ) {
          if ( ! $_pokemon->is_battle_team ) {
            return true;
          }
        }
      );

      slackemon_sort_player_pokemon( $pokemon_collection, [ 'name', 'is_favourite', 'level', 'cp', 'ts' ] );

      $options = [];

      foreach ( $pokemon_collection as $_pokemon ) {
        $options[] = slackemon_get_battle_menu_add_option( $_pokemon );
      }

      return json_encode( [ 'options' => $options ] );

    break;

    // Item give/use/teach request.
    case 'items':

      if ( ! isset( $action_name[1] ) || ! isset( $action_name[2] ) ) {
        return false;
      }

      $method    = $action_name[1]; // 'give' 'use' or 'teach'.
      $item_id   = $action_name[2];
      $move_name = isset( $action_name[3] ) ? $action_name[3] : '';

      $pokemon_collection = slackemon_search_player_pokemon( $action_value );

      // Do we need to filter the collection based on who is actually eligible?
      switch ( $method ) {

        case 'teach':

          // Who is eligble to learn the move taught by this machine?
          // NOTE that the function called below is intensive, so it should have been cached shortly before this
          // option request was made available to the user.

          $teachable_pokemon = slackemon_get_user_teachable_pokemon( $move_name, 'force_use_cache' );

          $pokemon_collection = array_filter(
            $pokemon_collection,
            function( $_pokemon ) use ( $teachable_pokemon, $move_name ) {
              if ( in_array( $_pokemon->ts, $teachable_pokemon ) ) {

                // In case the user is loading the list a short time after teaching a Pokemon the same move (and thus
                // when the initial teachable list is still cached), we do a last check to remove that Pokemon from
                // the list.
                foreach ( $_pokemon->moves as $_move ) {
                  if ( $_move->name === $move_name ) {
                    return false;
                  }
                }

                return true;

              }
            }
          );

        break;

        case 'use':

          // Who is (currently) able to make use of this item, based on its supplementary data (if present)?

          $item_data = slackemon_get_item_data( $item_id );

          if ( isset( $item_data->{'supplementary-data'}->requirements ) ) {

            $pokemon_collection = array_filter(
              $pokemon_collection,
              function( $_pokemon ) use ( $item_data ) {
                foreach ( $item_data->{'supplementary-data'}->requirements as $entry ) {

                  $pass = true;

                  foreach ( $entry as $key => $value ) {
                    if ( $_pokemon->{ $key } != $value ) {
                      $pass = false;
                      continue 2;
                    }
                  }

                  if ( $pass ) {
                    return true;
                  }

                }
              }
            );
          }

        break; // Case 'use'.

      } // Switch method

      // Sort by name, falling back to favourite status and then level, and finally catch ts.
      slackemon_sort_player_pokemon( $pokemon_collection, [ 'name', 'is_favourite', 'level', 'ts' ] );

      $options = [];

      foreach ( $pokemon_collection as $_pokemon ) {

        $additional_info = '';
        switch ( $method ) {

          case 'give':
            $additional_info = (
              isset( $_pokemon->held_item ) ?
              slackemon_readable( slackemon_get_item_data( $_pokemon->held_item )->name ) :
              ''
            );
          break;

          case 'teach':
            $move_slots_available = ( SLACKEMON_MAX_KNOWN_MOVES - count( $_pokemon->moves ) );
            $additional_info = $move_slots_available . ' move slot' . ( 1 === $move_slots_available ? '' : 's' );
          break;

          case 'use':
            // TODO - different for every item, eg. show HP/faint status for potion/revive.... ivs for evolve.. etc.
          break;

        }

        $options[] = [
          'text' => (
            ( $is_desktop ? ':' . $_pokemon->name . ': ' : '' ) .
            slackemon_readable( $_pokemon->name ) .
            ' (L' . floor( $_pokemon->level ) .
            ( $additional_info ? ', ' . $additional_info : '' ) .
            ')' .
            ( $is_desktop   && $_pokemon->is_favourite ? ' :sparkling_heart:' : '' ) .
            ( ! $is_desktop && $_pokemon->is_favourite ? ' *'                 : '' )
          ),
          'value' => $_pokemon->ts,
        ];

      } // Foreach pokemon

      return json_encode( [ 'options' => $options ] );

    break; // Case items.

  } // Switch action_name 0.
} // Function slackemon_get_slack_message_menu_options

// The end!
