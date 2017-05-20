<?php

// Chromatix TM 09/05/2017
// Provides the logic for responding to message menu option list requests

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

$action_name = explode( '/', $action_name );
$is_desktop  = 'desktop' === slackemon_get_player_menu_mode();

switch ( $action_name[0] ) {

  // Item give/use/teach request
  case 'items':

    if ( ! isset( $action_name[1] ) || ! isset( $action_name[2] ) ) {
      return;
    }

    $method  = $action_name[1]; // 'give', 'use', or 'teach'
    $item_id = $action_name[2];

    $player_data        = slackemon_get_player_data();
    $pokemon_collection = $player_data->pokemon;

    // Filter the user's Pokemon that match the so-far entered value
    if ( $action_value ) {
      $pokemon_collection = array_filter( $pokemon_collection, function( $_pokemon ) use ( $action_value ) {
        if ( $action_value === substr( $_pokemon->name, 0, strlen( $action_value ) ) ) {
          return true;
        }
      });
    }

    // Do we need to filter further based on who is actually eligible?
    switch ( $method ) {

      case 'teach':

        // Who is eligble to learn the move taught by this machine?
        // NOTE that the function called below is intensive, so it should have been cached shortly before this
        // option request was made available to the user.

        $move_name = slackemon_get_machine_move_data( $item_id, true );
        $teachable_pokemon = slackemon_get_user_teachable_pokemon( $move_name, 'force_use_cache' );

        $pokemon_collection = array_filter( $pokemon_collection, function( $_pokemon ) use ( $teachable_pokemon ) {
          if ( in_array( $_pokemon->ts, $teachable_pokemon ) ) {
            return true;
          }
        });

      break;

      case 'use':

        // Who is (currently) able to make use of this item, based on its supplementary data (if present)?

        $item_data = slackemon_get_item_data( $item_id );

        if ( isset( $item_data->{'supplementary-data'}->requirements ) ) {

          $pokemon_collection = array_filter( $pokemon_collection, function( $_pokemon ) use ( $item_data ) {
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
          });
        }

      break; // Case 'use'

    } // Switch method

    // Sort by name, falling back to favourite status and then level, and finally catch ts
    usort( $pokemon_collection, function( $pokemon1, $pokemon2 ) {
      $compare = strcmp( $pokemon1->name, $pokemon2->name );
      if ( $compare !== 0 ) {
        return $compare > 0 ? 1 : -1; // Name
      } else if ( $pokemon1->is_favourite !== $pokemon2->is_favourite ) {
        return $pokemon2->is_favourite ? 1 : -1; // Is favourite fallback
      } elseif ( $pokemon1->level !== $pokemon2->level ) {
        return $pokemon1->level < $pokemon2->level ? 1 : -1; // Level fallback
      } else {
        return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent (catch ts) fallback
      }
    });

    $options = [];

    foreach ( $pokemon_collection as $_pokemon ) {

      $additional_info = '';
      switch ( $method ) {

        case 'give':
          $additional_info = (
            isset( $_pokemon->held_item ) ?
            pokedex_readable( slackemon_get_item_data( $_pokemon->held_item )->name ) :
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
          pokedex_readable( $_pokemon->name ) .
          ' (L' . floor( $_pokemon->level ) .
          ( $additional_info ? ', ' . $additional_info : '' ) .
          ')' .
          ( $is_desktop   && $_pokemon->is_favourite ? ' :sparkling_heart:' : '' ) .
          ( ! $is_desktop && $_pokemon->is_favourite ? ' *'                 : '' )
        ),
        'value' => $_pokemon->ts,
      ];

    } // Foreach pokemon

    echo json_encode([ 'options' => $options ]);

  break; // Case items

} // Switch action_name[0]

// The end!
