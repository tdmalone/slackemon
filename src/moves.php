<?php
/**
 * Move related functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_random_move( $pokemon_teachable_moves, $pokemon_current_moves ) {

  // First we need to process the Pokemon's current moves, and ensure that we're not going to re-teach it something
  // it already knows.

  $available_moves = [];
  $pokemon_current_moves = array_map( function( $_move ) {
    return $_move->name;
  }, $pokemon_current_moves );

  foreach ( $pokemon_teachable_moves as $_move ) {
    if ( ! in_array( $_move->move->name, $pokemon_current_moves ) ) {
      $available_moves[] = $_move;
    }
  }

  if ( ! count( $available_moves ) ) {
    return false;
  }

  // From the available moves, choose a random one and return its data.

  $_rand     = random_int( 0, count( $available_moves ) - 1 );
  $move_data = slackemon_get_move_data( $available_moves[ $_rand ]->move->name );

  $new_move = [
    'name'       => $move_data->name,
    'pp'         => $move_data->pp,
    'pp-current' => $move_data->pp,
  ];

  return json_decode( json_encode( $new_move ) ); // Return as a simple object.

} // Function slackemon_get_random_move.

// Helper function to get multiple moves at once, while ensuring uniqueness.
function slackemon_get_random_moves( $pokemon_teachable_moves, $pokemon_existing_moves = [], $moves_to_teach = 2 ) {

  $new_moves = [];

  for ( $i = 1; $i <= $moves_to_teach; $i++ ) {

    $new_move = slackemon_get_random_move(
      $pokemon_teachable_moves,
      array_merge( $pokemon_existing_moves, $new_moves ) // Ensure moves just taught are still excluded.
    );

    // If a unique move could not be found, false is returned.
    if ( $new_move ) {
      $new_moves[] = $new_move;
    }

  }

  return $new_moves;

} // Function slackemon_get_random_moves.

function slackemon_get_cumulative_move_power( $moves, $types ) {

  $moves = slackemon_sort_battle_moves( $moves, $types );
  $total_power = 0;

  foreach ( $moves as $_move ) {
    $_move_data = slackemon_get_move_data( $_move->name );
    if ( $_move_data->power ) {
      $total_power += $_move_data->power * slackemon_get_move_stab_multipler( $_move, $types );
    }
  }

  return floor( $total_power );

} // Function slackemon_get_cumulative_move_power.

function slackemon_sort_battle_moves( $moves, $types ) {

  // Convert arguments to objects if arrays (as they will be if coming directly from a spawn).
  if ( is_array( $moves[0] ) ) {
    $moves = json_decode( json_encode( $moves ) );
  }
  if ( is_array( $types ) ) {
    $types = json_decode( json_encode( $types ) );
  }

  // Sort moves by power (ascending), falling back to the default order (key, ascending).
  foreach ( $moves as $_key => $_move ) {
    $_move->key = $_key; // We need to store the key to make sorting a lot simpler.
    $_move->data = slackemon_get_move_data( $_move->name );
  }
  usort( $moves, function( $move1, $move2 ) use ( $types ) {

    $move1_with_stab = $move1->data->power * slackemon_get_move_stab_multipler( $move1, $types );
    $move2_with_stab = $move2->data->power * slackemon_get_move_stab_multipler( $move2, $types );

    if ( $move1->data->power !== $move2->data->power ) {
      return $move1->data->power > $move2->data->power ? 1 : -1; // Move power.
    } else if ( $move1_with_stab !== $move2_with_stab ) {
      return $move1_with_stab > $move2_with_stab ? 1 : -1; // Move power with STAB taken into account.
    } else {
      return $move1->key > $move2->key ? 1 : -1; // Default order (key, ascending).
    }

  });

  // Now that they're sorted, unset the stored key & data so we don't pollute the player file when we next save.
  foreach ( $moves as $_move ) {
    unset( $_move->key );
    unset( $_move->data );
  }

  return $moves;

} // Function slackemon_sort_battle_moves.

function slackemon_calculate_move_damage( $move, $attacker, $defender, $options = [] ) {

  // Parse options.

  $defaults = [
    'inverse_type_effectiveness' => false,
  ];

  $options = array_merge( $defaults, $options );

  if ( is_array( $attacker ) ) {
    $attacker = json_decode( json_encode( $attacker ) );
  }

  if ( is_array( $defender ) ) {
    $defender = json_decode( json_encode( $defender ) );
  }

  // Get move data
  $move_data = slackemon_get_move_data( $move->name );

  if ( 'status' !== $move_data->damage_class->name ) {

    // Calculate the move's type effectiveness.
    $type_effectiveness = slackemon_get_move_type_effectiveness(
      $move, $defender, $options['inverse_type_effectiveness']
    );

    $maybe_inverse_suffix = $options['inverse_type_effectiveness'] ? ' in an inverse battle' : '';

    // Generate the type effectiveness message
    if ( $type_effectiveness > 2 ) {
      $type_message = 'It\'s _super_ effective' . $maybe_inverse_suffix . '!';
    } else if ( $type_effectiveness > 1 ) {
      $type_message = 'It\'s very effective' . $maybe_inverse_suffix . '!';
    } else if ( $type_effectiveness < 1 && $type_effectiveness > 0 ) {
      $type_message = 'It\'s not very effective' . $maybe_inverse_suffix . '.';
    } else if ( $type_effectiveness == 0 ) {
      $type_message = 'It\'s _completely_ ineffective' . $maybe_inverse_suffix . '!';
    } else {
      $type_message = '';
    }

    // Does this move get STAB? Note also that the Adaptability ability can raise the 1.5 here to 2.
    $stab = slackemon_get_move_stab_multipler( $move, $attacker->types );

    // Calculate the move's damage.
    // HT: http://bulbapedia.bulbagarden.net/wiki/Damage.

    $l = floor( $attacker->level );
    $p = $move_data->power;
    $a = (
      'special' === $move_data->damage_class->name ? $attacker->stats->{'special-attack'}  : $attacker->stats->attack
    );
    $d = (
      'special' === $move_data->damage_class->name ? $defender->stats->{'special-defense'} : $defender->stats->defense
    );
    $t = 1; // Target:  0.75 if the move has more than one target; 1 otherwise (not implemented).
    $w = 1; // Weather: 1.5  if water type move during rain or fire during heat; 0.5 for opposite; 1 otherwise (TODO).
    $B = 1; // Badge:   1.25 if player has badge corresponding to the move type; 1 otherwise (not implemented).
    $c = 1; // Critical Hit: 1.5-2 for critical hit; 1 otherwise (TODO).
    $r = random_int( 85, 100 ) / 100; // Random factor.
    $s = $stab;
    $T = $type_effectiveness;
    $b = 1; // Burn:  0.5 if attacker is burned and doesn't have appropriate abilities; 1 otherwise (TODO).
    $o = 1; // Other: 1   usually, but can take values based on interactions between moves/abilities/items (TODO).

    $damage_raw = (
      ( floor( ( ( floor( 2 * $l / 5 ) + 2 ) * $p * $a / $d ) / 50 ) + 2 ) *
      ( $t * $w * $B * $c * $r * $s * $T * $b * $o )
    );

    // Finally, round it off, and ensure every damage move does at least 1 HP damage.
    $damage_rounded    = max( 1, floor( $damage_raw ) );
    $damage_percentage = floor( $damage_rounded / $defender->stats->hp * 100 );

  } // If not status move

  // Send debugging info
  slackemon_battle_debug(
    'For *'       . slackemon_readable( $move_data->name ) . '* ' .
    'by *'        . slackemon_readable( $attacker->name  ) . '* ' .
    'against '    . slackemon_readable( $defender->name  ) . ':'           . "\n"  .
    '*Class* '    . slackemon_readable( $move_data->damage_class->name )   . ' | ' .
    '*Category* ' . slackemon_readable( $move_data->meta->category->name ) . "\n"  .
    (
      'status' === $move_data->damage_class->name ?
      '' :
      '*Power* x'   . ( $move_data->power ? $move_data->power : '0' )      . ' | ' .
      '*Stab* '     . $s . ' | *Type* ' . $T . ' | *Random* ' . $r         . "\n"  .
      '*Damage before rounding* ' . $damage_raw        . "\n" .
      '*Damage after rounding* '  . $damage_rounded    . "\n" .
      '*Damage percentage* '      . $damage_percentage . '%' . "\n"
    ) .
    '*Supplementary data* ' . (
      isset( $move_data->{ 'supplementary-data' } ) ?
      json_encode( $move_data->{ 'supplementary-data' } ) :
      '_(none)_'
    )
  );

  return json_decode( json_encode([
    'damage'             => isset( $damage_rounded     ) ? $damage_rounded     : 0,
    'damage_percentage'  => isset( $damage_percentage  ) ? $damage_percentage  : 0,
    'type_effectiveness' => isset( $type_effectiveness ) ? $type_effectiveness : 1,
    'type_message'       => isset( $type_message       ) ? $type_message       : '',
    'damage_class'       => $move_data->damage_class->name,
    'move_category'      => $move_data->meta->category->name,
  ]) );

} // Function slackemon_calculate_move_damage.

function slackemon_get_move_type_effectiveness( $move, $defender, $inverse_type_effectiveness = false ) {

  // Base type effectiveness. This should always be 1.
  $type_effectiveness = 1;

  $move_data = slackemon_get_move_data( $move->name );

  if (
    isset( $move_data->{ 'supplementary-data' }->{ 'ignore-type-effectiveness' } ) &&
    $move_data->{ 'supplementary-data' }->{ 'ignore-type-effectiveness' }
  ) {

    return $type_effectiveness;

  }

  $type_url  = 'http://pokeapi.co/api/v2/type/' . $move_data->type->name . '/';
  $type_data = slackemon_get_cached_url( $type_url, [ 'json' => true ] );
  $relations = $type_data->damage_relations;

  // Adjust the type effectiveness as per the API data.
  // Note that if this is an inverse battle, type effectiveness is reversed, except for immunities, which don't apply
  // in inverse (ref: https://bulbapedia.bulbagarden.net/wiki/Inverse_Battle).

  foreach ( $relations->half_damage_to as $_relation ) {
    if ( in_array( ucfirst( $_relation->name ), $defender->types ) ) {
      if ( $inverse_type_effectiveness ) {
        $type_effectiveness *= 2;
      } else {
        $type_effectiveness *= .5;
      }
    }
  }

  foreach ( $relations->no_damage_to as $_relation ) {
    if ( in_array( ucfirst( $_relation->name ), $defender->types ) ) {
      if ( ! $inverse_type_effectiveness ) {
        $type_effectiveness *= 0;
      }
    }
  }

  foreach ( $relations->double_damage_to as $_relation ) {
    if ( in_array( ucfirst( $_relation->name ), $defender->types ) ) {
      if ( $inverse_type_effectiveness ) {
        $type_effectiveness *= .5;
      } else {
        $type_effectiveness *= 2;
      }
    }
  }

  return $type_effectiveness;

} // Function slackemon_get_move_type_effectiveness.

function slackemon_get_best_move( $attacker, $defender = null ) {

  // TODO - make this smarter, obviously - probably calc the damage on the fly so we take advantage of types/stab etc.

  $highest_power = 0;
  $move_name     = '';

  if ( is_array( $attacker ) ) {
    $attacker = json_decode( json_encode( $attacker ) );
  }

  if ( is_array( $defender ) ) {
    $defender = json_decode( json_encode( $defender ) );
  }

  foreach ( $attacker->moves as $_move ) {

    if ( ! $_move->{'pp-current'} ) {
      continue;
    }

    $_move_data  = slackemon_get_move_data( $_move->name );
    $_move_power = $_move_data->power ? $_move_data->power : 0;

    if ( $_move_power >= $highest_power ) {
      $highest_power = $_move_power;
      $move = $_move;
    }

  }

  if ( ! isset( $move ) ) {
    $move = slackemon_get_backup_move();
  }

  return $move;

} // Function slackemon_get_best_move.

function slackemon_get_backup_move() {

  $move = [
    'name'       => 'struggle',
    'pp'         => '999',
    'pp-current' => '999',
  ];

  return json_decode( json_encode( $move ) ); // Return as a simple object.

} // Function slackemon_get_backup_move.

function slackemon_get_move_stab_multipler( $move, $pokemon_types ) {

  if ( is_object( $move ) ) {
    $move_name = $move->name;
  } else if ( is_array( $move ) ) {
    $move_name = $move['name'];
  } else if ( is_string( $move ) ) {
    $move_name = $move;
  }

  $move_data = slackemon_get_move_data( $move_name );

  if (
    isset( $move_data->{ 'supplementary-data' }->{ 'ignore-stab' } ) &&
    $move_data->{ 'supplementary-data' }->{ 'ignore-stab' }
  ) {

    $stab_multiplier = 1;

  } else {

    $stab_multiplier = in_array( ucfirst( $move_data->type->name ), $pokemon_types ) ? 1.5 : 1;

  }

  return $stab_multiplier;

} // Function slackemon_get_move_stab_multipler.

/** Get the user's Pokemon that can learn a certain move. Must cache, because this is intensive. */
function slackemon_get_user_teachable_pokemon( $move_name, $cache_mode = '', $user_id = USER_ID ) {
  global $data_folder;

  $cache_filename = $data_folder . '/moves/' . $user_id . '.' . $move_name . '.teachable';

  if ( 'force_update_cache' !== $cache_mode && slackemon_file_exists( $cache_filename, 'local' ) ) {
    if (
      'force_use_cache' === $cache_mode ||
      slackemon_filemtime( $cache_filename, 'local' ) > time() - MINUTE_IN_SECONDS * 5
    ) {
      $user_teachable_pokemon = json_decode( slackemon_file_get_contents( $cache_filename, 'local' ) );
      return $user_teachable_pokemon;
    }
  }

  $pokemon_collection     = slackemon_get_player_data( $user_id )->pokemon;
  $user_teachable_pokemon = [];

  foreach ( $pokemon_collection as $_pokemon ) {

    $_pokemon_data = slackemon_get_pokemon_data( $_pokemon->pokedex );

    foreach ( $_pokemon_data->moves as $teachable_move ) {
      if ( $teachable_move->move->name === $move_name ) {

        // Before adding, check that the Pokemon doesn't already know this move.
        foreach ( $_pokemon->moves as $existing_move ) {
          if ( $existing_move->name === $move_name ) {
            continue 2;
          }
        }

        $user_teachable_pokemon[] = $_pokemon->ts;

      }
    }

    // We must unset the global Pokemon data cache, because we'll run out of memory if we don't.
    global $_cached_slackemon_pokemon_data;
    unset( $_cached_slackemon_pokemon_data[ $_pokemon->pokedex ] );

  }

  slackemon_file_put_contents( $cache_filename, json_encode( $user_teachable_pokemon ), 'local' );

  return $user_teachable_pokemon;

} // Function slackemon_get_user_teachable_pokemon.

function slackemon_delete_user_pokemon_move( $spawn_ts, $move_name, $user_id = USER_ID ) {

  // Get player data for writing.
  $player_data = slackemon_get_player_data( $user_id, true );
  $pokemon     = slackemon_get_player_pokemon_data( $spawn_ts, $player_data );

  // Protect against deleting a move if only one remains.
  if ( count( $pokemon->moves ) <= 1 ) {
    slackemon_save_player_data( $player_data, $user_id, true );
    return false;
  }

  foreach ( $pokemon->moves as $key => $move ) {
    if ( $move->name === $move_name ) {
      $move_found = true;
      unset( $pokemon->moves[ $key ] );
      break;
    }
  }

  // Return false if we didn't find the move we were after, for some reason.
  if ( ! $move_found ) {
    slackemon_save_player_data( $player_data, $user_id, true );
    return false;
  }

  // Re-index keys
  $pokemon->moves = array_values( $pokemon->moves );

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_delete_user_pokemon_move.

// The end!
