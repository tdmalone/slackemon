<?php

// Chromatix TM 04/04/2017
// Evolution specific functions for Slackemon Go

function slackemon_evolve_user_pokemon( $spawn_ts, $evolve_to_id = null, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  $user_pokemon = slackemon_get_player_pokemon_data( $spawn_ts, $player_data );

  if ( ! $user_pokemon ) {
  	return false;
  }

  // Allow a specific Pokedex ID to be passed through to control the evolution
  // If this is done, we won't validate whether the user's Pokemon is eligble nor whether this evolution is even possible
  // Otherwise, we'll try to find an eligible evolution
  if ( ! $evolve_to_id ) {
    $evolve_to_id = slackemon_can_user_pokemon_evolve( $user_pokemon );
  }
  if ( ! $evolve_to_id ) {
    return false;
  }

  $new_pokemon_data = slackemon_get_pokemon_data( $evolve_to_id );
  $new_pokemon_species = slackemon_get_pokemon_species_data( $evolve_to_id );

  // Types
  $types = [];
  foreach ( $new_pokemon_data->types as $type ) {
    $types[] = slackemon_readable( $type->type->name );
  }

  // Teach a new move, if we're not at the maximum already
  if ( count( $user_pokemon->moves ) < SLACKEMON_MAX_KNOWN_MOVES ) {

    $new_move = slackemon_get_random_move( $new_pokemon_data->moves, $user_pokemon->moves );

    if ( $new_move ) {
      $user_pokemon->moves[] = $new_move;
    }
  }

  // Store old HP percentage
  $old_hp_percentage = $user_pokemon->hp / $user_pokemon->stats->hp;

  // Update Pokemon base data & stats
  $user_pokemon->pokedex   = $evolve_to_id;
  $user_pokemon->name      = $new_pokemon_data->name;
  $user_pokemon->types     = $types;
  $user_pokemon->abilities = $user_pokemon->abilities; // TODO, once these are in the game

  // Recalculate current stats based on new base stats & level
  $user_pokemon->stats->attack  = slackemon_calculate_stats( 'attack',  $user_pokemon );
  $user_pokemon->stats->defense = slackemon_calculate_stats( 'defense', $user_pokemon );
  $user_pokemon->stats->hp      = slackemon_calculate_stats( 'hp',      $user_pokemon );
  $user_pokemon->stats->speed   = slackemon_calculate_stats( 'speed',   $user_pokemon );
  $user_pokemon->stats->{'special-attack'}  = slackemon_calculate_stats( 'special-attack',  $user_pokemon );
  $user_pokemon->stats->{'special-defense'} = slackemon_calculate_stats( 'special-defense', $user_pokemon );

  // Recalculate CP
  $user_pokemon->cp = slackemon_calculate_cp( $user_pokemon->stats );

  // Recalculate HP to the same percentage it was before evolution
  $user_pokemon->hp = floor( $user_pokemon->stats->hp * $old_hp_percentage );

  // Can we increment the 'seen' value on an existing Pokedex entry?
  $pokemon_seen_before = false;
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $evolve_to_id == $pokedex_entry->id ) {
      $pokedex_entry->seen++;
      $pokemon_seen_before = true;
    }
  }

  // First time seen? Time to create a new Pokedex entry!
  if ( ! $pokemon_seen_before ) {
    $player_data->pokedex[] = json_decode( json_encode( [ // Make sure it's an object
      'id'     => (int) $evolve_to_id,
      'seen'   => 1,
      'caught' => 0,
    ]));
  }

  // Find the correct Pokedex entry to increment, and do the XP add too
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $evolve_to_id == $pokedex_entry->id ) {

      $xp_to_add = 500; // Base evolution XP

      if ( ! $pokedex_entry->caught ) {
        $xp_to_add += 500; // First unique Pokemon bonus!
      } else if ( $pokedex_entry->caught % 100 == 0 ) {
        $xp_to_add += 500; // Bonus on every 100
      } else if ( $pokedex_entry->caught % 10 == 0 ) {
        $xp_to_add += 100; // Bonus on every 10
      }

      slackemon_add_xp( $xp_to_add, $user_id );
      $pokedex_entry->caught++;

      return slackemon_save_player_data( $player_data, $user_id );

    }
  }

  // We should have returned above, but just in case we couldn't find the Pokedex entry for some reason...
  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_evolve_user_pokemon

function slackemon_get_evolution_chain_pokemon( $level1, $pokedex_id ) {

  if ( ! $level1 || ! $pokedex_id ) {
    return false;
  }

  if ( basename( $level1->species->url ) == $pokedex_id ) {
    return $level1;
  }

  foreach ( $level1->evolves_to as $level2 ) {
    if ( basename( $level2->species->url ) == $pokedex_id ) {
      return $level2;
    }

    foreach ( $level2->evolves_to as $level3 ) {
      if ( basename( $level3->species->url ) == $pokedex_id ) {
        return $level3;
      }
    }

  }

  return false;

} // Function slackemon_get_evolution_chain_pokemon

function slackemon_can_user_pokemon_evolve(
  $user_pokemon_data, $trigger_type = 'level-up', $return_all_possibilities = false, $item_name = null
) {

  $evolution_data = slackemon_get_pokemon_evolution_data( $user_pokemon_data->pokedex );

  $chain = slackemon_get_evolution_chain_pokemon( $evolution_data->chain, $user_pokemon_data->pokedex );
  $possibilities = [];

  foreach ( $chain->evolves_to as $_evolution ) {

    $pass = false;

    foreach ( $_evolution->evolution_details as $detail ) {

      // We only handle level-up & item triggers in the game right now
      // Other triggers for later implementation are 'trade' and 'shed' (used just for Shedninja)
      if ( 'level-up' !== $detail->trigger->name && 'use-item' !== $detail->trigger->name ) {
        slackemon_record_impossible_evolution( $_evolution, $detail, 'uncoded-trigger' );
      }

      if ( $trigger_type !== $detail->trigger->name ) {
        continue;
      }

      // Does this evolution require a certain item to have been used?
      if ( 'use-item' === $trigger_type && $item_name !== $detail->item->name ) {
        continue;
      }

      // Evolution conditions that aren't in the game yet (some may never be...)
      if ( $detail->min_beauty || $detail->turn_upside_down || $detail->trade_species || $detail->held_item || $detail->location ) {
        slackemon_record_impossible_evolution( $_evolution, $detail, 'condition-not-in-game' );
        continue;
      }

      // TODO: Magneton->Magnezone evolution is based on location only, so we need an alternative here

      // Evolution conditions that haven't been coded below yet, but are/will be available in the game
      if ( $detail->relative_physical_stats || $detail->needs_overworld_rain || $detail->party_type || $detail->party_species ) {
        slackemon_record_impossible_evolution( $_evolution, $detail, 'uncoded-condition' );
        continue;
      }

      // Does this evolution require a min_level that we're not at?
      if ( $detail->min_level && $user_pokemon_data->level < $detail->min_level ) {
        continue;
      }

      // Does this evolution require a min_happiness that we're not at?
      if ( $detail->min_happiness && $user_pokemon_data->happiness < $detail->min_happiness ) {
        continue;
      }

      // Does this evolution require a min_affection that we're not at?
      // Because we don't implement affection in this game, we use happiness instead and scale it accordingly.
      if (
        $detail->min_affection &&
        $user_pokemon_data->happiness < slackemon_affection_to_happiness( $detail->min_affection )
      ) {
        continue;
      }

      // Does this evolution only happen at a certain time of day?
      if ( $detail->time_of_day ) {
        if ( 'day' === $detail->time_of_day && ! slackemon_is_daytime() ) {
          continue;
        } elseif ( 'night' === $detail->time_of_day && slackemon_is_daytime() ) {
          continue;
        }
      }

      // Does this evolution require a gender that we're not?
      if ( $detail->gender ) {
        if (
          ( $detail->gender == 1 && $user_pokemon_data->gender != 'female' ) ||
          ( $detail->gender == 2 && $user_pokemon_data->gender != 'male'   ) ||
          ( $detail->gender == 3 && $user_pokemon_data->gender != false    )
        ) {
          continue;
        }
      }

      // Does this evolution require a move type that we don't know?
      if ( $detail->known_move_type ) {

        $move_pass = false;
        foreach ( $user_pokemon_data->moves as $_move ) {

          $_move_data = slackemon_get_move_data( $_move->name );

          if ( $_move_data->type->name !== $detail->known_move_type->name ) {
            continue;
          }

          // If we've made it here, we know a move of the correct type!
          $move_pass = true;

        } // Foreach move

        if ( ! $move_pass ) {
          continue;
        }

      } // If known_move_type

      // Does this evolution require knowing a certain move that we don't know?
      if ( $detail->known_move ) {

        $move_pass = false;
        foreach ( $user_pokemon_data->moves as $_move ) {

          if ( $_move->name !== $detail->known_move->name ) {
            continue;
          }

          // If we've made it here, we know the correct move!
          $move_pass = true;

        } // Foreach move

        if ( ! $move_pass ) {
          continue;
        }

      } // If known_move

      // If we made it here, this is an evolution we can follow!
      $pass = true;

    } // Foreach evolution_details

    if ( ! $pass ) {
      continue;
    }

    // If we've got here, this is an evolution we can follow!
    $possibilities[] = basename( $_evolution->species->url ); // The Pokedex ID of this evolution

  } // Foreach evolves_to

  // Return a random possibility if we're not returning all, in case there are multiple valid choices
  if ( $return_all_possibilities ) {
    return $possibilities;
  } else if ( count( $possibilities ) ) {
    return $possibilities[ array_rand( $possibilities ) ];
  } else {
    return false;
  }

} // Function slackemon_can_user_pokemon_evolve

function slackemon_start_evolution_message( $spawn_ts, $action, $user_id = USER_ID, $replace_all_attachments = false ) {

  // Get existing Pokemon data, and the original attachment so we can modify it
  $pokemon = slackemon_get_player_pokemon_data( $spawn_ts, null, $user_id );
  $original_attachment = $action->original_message->attachments[ $action->attachment_id - 1 ];

  // Clear the attachment footer & actions; add an evolving status message
  // We do this first so that there's something showing while the evolution GIF loads
  $original_attachment->text   .= "\n\n" . '*Eᴠᴏʟᴠɪɴɢ your ' . slackemon_readable( $pokemon->name ) . '...* :loading:';
  $original_attachment->footer  = '';
  $original_attachment->actions = [];
  $original_attachment->image_url = slackemon_get_cached_image_url(
    SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/' . $pokemon->name . '.gif'
  );

  if ( $replace_all_attachments ) {
    slackemon_do_action_response([ 'attachments' => [ $original_attachment, slackemon_back_to_menu_attachment() ] ]);
  } else {
    slackemon_update_triggering_attachment( $original_attachment, $action );
  }

  // Clear the fields, and load the evolution GIF
  $original_attachment->fields    = [];
  $original_attachment->image_url = slackemon_get_cached_image_url( SLACKEMON_INBOUND_URL . '/_images/slackemon-evolution2.gif' );

  if ( $replace_all_attachments ) {
    slackemon_do_action_response([ 'attachments' => [ $original_attachment, slackemon_back_to_menu_attachment() ] ]);
  } else {
    slackemon_update_triggering_attachment( $original_attachment, $action );
  }

  return true;

} // Function slackemon_start_evolution_message

function slackemon_end_evolution_message( $spawn_ts, $action, $user_id = USER_ID, $replace_all_attachments = false ) {

  $player_data = slackemon_get_player_data( $user_id );

  // Get the new Pokemon's data, plus its species API data
  $pokemon = slackemon_get_player_pokemon_data( $spawn_ts, $player_data );
  $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );

  // For effect, give the evolution animation some time
  sleep( 5 );

  // Start off with the full Pokemon view message, which we'll edit
  $message = slackemon_get_pokemon_view_message( $spawn_ts, 'pokemon/view/evolved', $action );
  $attachment_id = $action->attachment_id - 1;
  $attachment = $message['attachments'][ $attachment_id ];

  // Was this a new species for us?
  $total_caught_species = 0;
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $pokedex_entry->id == $pokemon->pokedex ) {
      $total_caught_species += $pokedex_entry->caught;
    }
  }

  // Add the evolution completion message, along with the XP awarded
  $attachment['text'] .= (
    "\n\n" .
    ':tada: *Cᴏɴɢʀᴀᴛᴜʟᴀᴛɪᴏɴs! Your ' . // Congratulations
    slackemon_readable( $species_data->evolves_from_species->name ) . ' has evolved into ' .
    slackemon_readable( $pokemon->name ) . '!!*' . "\n\n" .
    '*+500 XP*: Evolved a Pokémon' . "\n" .
    ( 1 == $total_caught_species ? '*+500 XP*: New Pokémon!' . "\n" : '' ) .
    (
      $total_caught_species % 100 == 0 ?
      '*+500 XP*: Bonus - ' . $total_caught_species . 'th of this species!' . "\n" :
      ''
    ) . (
      $total_caught_species % 10 == 0 && $total_caught_species % 100 != 0 ?
      '*+100 XP*: Bonus - ' . $total_caught_species . 'th of this species!' . "\n" :
      ''
    ) .
    "\n" .
    ':loading:'
  );

  // Backup and clear the fields and footer, so that the evolution message is the focus
  $_fields_backup = $attachment['fields'];
  $_footer_backup = $attachment['footer'];
  $attachment['fields'] = [];
  $attachment['footer'] = '';

  // Send the message
  $message['attachments'][ $attachment_id ] = $attachment;
  if ( $replace_all_attachments ) {
    slackemon_do_action_response([ 'attachments' => [ $attachment, slackemon_back_to_menu_attachment() ] ]);
  } else {
    slackemon_do_action_response( $message );
  }

  // Wait, while the user sees it
  sleep( 2 );

  // Now that the user has seen the evolution message, remove the loading GIF and add the fields & footer back in
  $attachment['text'] = str_replace( ':loading:', '', $attachment['text'] );
  $attachment['fields'] = $_fields_backup;
  $attachment['footer'] = $_footer_backup;

  // Finally, send the completed message!
  $message['attachments'][ $attachment_id ] = $attachment;
  if ( $replace_all_attachments ) {
    slackemon_do_action_response([ 'attachments' => [ $attachment, slackemon_back_to_menu_attachment() ] ]);
  } else {
    slackemon_do_action_response( $message );
  }

  return true;

} // Function slackemon_end_evolution_message

function slackemon_get_evolution_error_message( $spawn_ts, $action ) {

  $attachment = [
    'text' => ':no_entry: *Oops!* An error occured evolving your Pokémon. Please try again later.',
  ];

  $message = slackemon_update_triggering_attachment( $attachment, $action, false );

  return $message;

} // Function slackemon_get_evolution_error_message

function slackemon_record_impossible_evolution( $evolution, $detail, $reason = 'unknown-reason' ) {
  global $data_folder;

  $evolution_debug_filename = $data_folder . '/uncoded-evolutions.json';
  if ( ! file_exists( $evolution_debug_filename) ) {
    touch( $evolution_debug_filename );
  }

  $evolution_debug = json_decode( file_get_contents( $evolution_debug_filename ) );
  $json_id = 'id' . basename( $evolution->species->url );

  if ( ! $evolution_debug ) {
    $evolution_debug = new stdClass();
  }

  if ( ! isset( $evolution_debug->{ $reason } ) ) {
    $evolution_debug->{ $reason } = new stdClass();
  }

  if ( ! isset( $evolution_debug->{ $reason }->{ $json_id } ) ) {
    $evolution_debug->{ $reason }->{ $json_id } = $detail;
    return file_put_contents( $evolution_debug_filename, json_encode( $evolution_debug ) );
  }

  return false;

} // Function slackemon_record_impossible_evolution

// The end!

// ****************************
// Sample evolution chain data:

/*
{
  "baby_trigger_item": null,
  "id": 138,
  "chain":  {
    "evolution_details": [],
    "evolves_to":  [{
      "evolution_details":  [
        {
          "min_level": 22,
          "min_beauty": null,
          "time_of_day": "",
          "gender": null,
          "relative_physical_stats": null,
          "needs_overworld_rain": false,
          "turn_upside_down": false,
          "item": null,
          "trigger":  {
            "url": "http: \/\/pokeapi.co\/api\/v2\/evolution-trigger\/1\/",
            "name": "level-up"
          },
          "known_move_type": null,
          "min_affection": null,
          "party_type": null,
          "trade_species": null,
          "party_species": null,
          "min_happiness": null,
          "held_item": null,
          "known_move": null,
          "location": null
        }
      ],
      "evolves_to":  [
      ],
      "is_baby": false,
      "species":  {
        "url": "http: \/\/pokeapi.co\/api\/v2\/pokemon-species\/277\/",
        "name": "swellow"
      }
    }],
    "is_baby": false,
    "species":  {
      "url": "http: \/\/pokeapi.co\/api\/v2\/pokemon-species\/276\/",
      "name": "taillow"
    }
  }
}

{
  "baby_trigger_item": null,
  "id": 17,
  "chain": {
    "evolution_details": [],
    "evolves_to": [{
      "evolution_details": [{
        "min_level": 22,
        "min_beauty": null,
        "time_of_day": "",
        "gender": null,
        "relative_physical_stats": null,
        "needs_overworld_rain": false,
        "turn_upside_down": false,
        "item": null,
        "trigger": {
          "url": "http:\/\/pokeapi.co\/api\/v2\/evolution-trigger\/1\/",
          "name": "level-up"
        },
        "known_move_type": null,
        "min_affection": null,
        "party_type": null,
        "trade_species": null,
        "party_species": null,
        "min_happiness": null,
        "held_item": null,
        "known_move": null,
        "location": null
      }],
      "evolves_to": [{
        "evolution_details": [{
          "min_level": null,
          "min_beauty": null,
          "time_of_day": "",
          "gender": null,
          "relative_physical_stats": null,
          "needs_overworld_rain": false,
          "turn_upside_down": false,
          "item": null,
          "trigger": {
            "url": "http:\/\/pokeapi.co\/api\/v2\/evolution-trigger\/1\/",
            "name": "level-up"
          },
          "known_move_type": null,
          "min_affection": null,
          "party_type": null,
          "trade_species": null,
          "party_species": null,
          "min_happiness": 220,
          "held_item": null,
          "known_move": null,
          "location": null
        }],
        "evolves_to": [],
        "is_baby": false,
        "species": {
          "url": "http:\/\/pokeapi.co\/api\/v2\/pokemon-species\/169\/",
          "name": "crobat"
        }
      }],
      "is_baby": false,
      "species": {
        "url": "http:\/\/pokeapi.co\/api\/v2\/pokemon-species\/42\/",
        "name": "golbat"
      }
    }],
    "is_baby": false,
    "species": {
      "url": "http:\/\/pokeapi.co\/api\/v2\/pokemon-species\/41\/",
      "name": "zubat"
    }
  }
}

*/
