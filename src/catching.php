<?php
/**
 * Pokemon-catching specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_catch_message( $spawn_ts, $action, $from_battle = false, $force_battle_result = '', $user_id = USER_ID ) {

  $catch_attempt_ts = time();
  $spawn_data       = slackemon_get_spawn_data( $spawn_ts, slackemon_get_player_region( $user_id ), $user_id );
  
  $catch_too_late   = (
    'flee-late' === $force_battle_result ||
    $spawn_ts < $catch_attempt_ts - SLACKEMON_FLEE_TIME_LIMIT
  );

  // Initial 'catching...' message
  $message = [];
  $message['text'] = $action ? $action->original_message->text : '';
  $message['attachments'] = $action ? $action->original_message->attachments : [];

  // Remove the previous actions attachment
  array_pop( $message['attachments'] );

  // Add a new actions attachment
  $message['attachments'][] = [
    'title' => 'Trying to catch ' . slackemon_readable( $spawn_data->name ) . '...',
    'text'  => ':pokeball_bounce:',
  ];

  if ( ! $catch_too_late && 'flee' !== $force_battle_result ) {

    // Send message, and wait for a bit before we continue
    slackemon_send2slack( $message );

    if ( 'catch' === $force_battle_result ) {

      // Don't wait as long here - it's obvious from a battle win that this is going to be a successful catch.
      sleep( 1 );

    } else {
      sleep( 5 );
    }

  }

  // Remove the previous actions attachment (yes, again, because we've finished trying to catch now ;) )
  array_pop( $message['attachments'] );

  // If this was part of a battle, we also need to remove the user's battle attachment
  // The battle will also now end, either in a catch or flee, so we should make sure we close up the battle
  if ( $from_battle ) {

    array_pop( $message['attachments'] );
    
    $active_battle   = slackemon_get_user_active_battles( $user_id )[0];
    $battle_result   = 'catch' === $force_battle_result ? 'won' : 'lost';
    $award_battle_xp = 'catch' === $force_battle_result ? true : false;

    $battle_message  = slackemon_complete_battle(
      $battle_result, $active_battle->hash, $user_id, $award_battle_xp, false
    );

    $battle_pokemon = slackemon_get_battle_current_pokemon( $active_battle->hash, $user_id );

  }

  // Attempt a catch, providing it's not too late
  // We'll pass off to slackemon_do_catch() for processing here, which will use the RNG + update player data incl XP
  if ( $catch_too_late && ! $from_battle ) {
    $is_caught = false;
  } else if ( $from_battle && 'flee-late' === $force_battle_result ) {
    $is_caught = false;
  } else {
    $is_caught = slackemon_do_catch(
      $spawn_ts, $catch_attempt_ts, $user_id, $from_battle ? $active_battle->hash : false, $force_battle_result
    );
  }

  if ( $is_caught ) {

    // Remove the message default text
    $message['attachments'][0]->pretext = '';

    $player_data = slackemon_get_player_data( $user_id );
    $total_caught_species = 0;
    $total_caught_all = 0;

    foreach ( $player_data->pokedex as $pokedex_entry ) {
      $total_caught_all += $pokedex_entry->caught;
      if ( $pokedex_entry->id == $spawn_data->pokedex ) {
        $total_caught_species += $pokedex_entry->caught;
      }
    }

    // Do we add the 'first ever catch' attachment?
    if ( 1 === $total_caught_all ) {

      $message['attachments'][] = [
        'title' => 'Well done! You caught ' . slackemon_readable( $spawn_data->name ) . '! :tada:',
        'color' => '#333333',
        'text' => (
          'Now, keep an eye on your direct messages, as Pokémon could appear at any time.' . "\n" .
          'You can check out your Pokémon via the Main Menu, which you can access below or at any time ' .
          'by typing `' . SLACKEMON_SLASH_COMMAND . '`.' . "\n" .
          'Will you be the very best there ever was?? :sports_medal:'
        ),
      ];

    }

    // Cut out the user's own battle attachment, and their battle header attachment.
    if ( $from_battle ) {
      array_pop(   $message['attachments'] );
      array_shift( $message['attachments'] );
    }

    // Quick catch logic, to determine XP message for this bonus
    $quick_catch_limit = round( SLACKEMON_FLEE_TIME_LIMIT / 5 ); // Eg. within 1 minute for a 5 minute spawn time
    $quick_catch_grace = MINUTE_IN_SECONDS / 4; // To allow for long spawn decisions
    $quick_catch_readable = floor( $quick_catch_limit / 60 ); // In minute(s)

    // Add new results & actions attachments

    $message['attachments'][] = [
      'color'   => '#333333',
      ( $from_battle ? 'pretext' : 'text' ) => (
        (
          $total_caught_all > 1 ?
          '*YAY! You caught ' . slackemon_readable( $spawn_data->name ) . '!* :tada:' . "\n" :
          ''
        ) . (
          $from_battle ?
          str_replace( '*Your Pokémon:*', '', $battle_message['text'] ) :
          ''
        ) . (
          '*+100 XP*: Successful catch' . "\n"
        ) . (
          1 == $total_caught_species ?
          '*+500 XP*: New Pokémon!' . "\n" :
          ''
        ) . (
          $total_caught_species % 100 == 0 ?
          '*+500 XP*: Bonus - ' . $total_caught_species . 'th catch of this species!' . "\n" :
          ''
        ) . (
          $total_caught_species % 10 == 0 && $total_caught_species % 100 != 0 ?
          '*+100 XP*: Bonus - ' . $total_caught_species . 'th catch of this species!' . "\n" :
          ''
        ) . (
          $catch_attempt_ts < $spawn_ts + $quick_catch_limit + $quick_catch_grace ?
          '   *+50 XP*: Bonus - caught within ' . $quick_catch_readable  . ' ' .
          'minute' . ( 1 == $quick_catch_readable ? '' : 's' ) . '!' . "\n" :
          ''
        )
      )
    ];

    $message['attachments'][] = [
      'color'    => '#333333',
      'fallback' => 'Return to Main Menu',
      'actions' => [
        [
          'name'  => $from_battle ? 'pokemon/view/caught/battle' : 'pokemon/view/caught',
          'text'  => ':eye: About ' . slackemon_readable( $spawn_data->name ),
          'type'  => 'button',
          'value' => $spawn_ts,
          'style' => 'primary',
        ], (
          $from_battle ?
          [
            'name'  => 'pokemon/view/caught/battle',
            'text'  => ':eye: View ' . slackemon_readable( $battle_pokemon->name ),
            'type'  => 'button',
            'value' => $battle_pokemon->ts,
          ] :
          []
        ), [
          'name'  => 'menu',
          'text'  => ':leftwards_arrow_with_hook: Main Menu',
          'type'  => 'button',
          'value' => 'main',
        ],
      ],
    ];
    
  } else {

    $pokemon_data = slackemon_get_pokemon_data( $spawn_data->pokedex );
    $species_data = slackemon_get_pokemon_species_data( $spawn_data->pokedex );

    // Change first attachment (the sprite) to the Pokemon with its back turned
    $message['attachments'][0] = [
      'color' => slackemon_get_color_as_hex( $species_data->color->name ),
      'image_url' => slackemon_get_cached_image_url(
        'female' === $spawn_data->gender && $pokemon_data->sprites->back_female ?
        $pokemon_data->sprites->back_female :
        $pokemon_data->sprites->back_default
      ),
      'fallback' => slackemon_readable( $spawn_data->name ),
    ];

    // Remove the spawn stats attachment (only if it's there) & the message default text.
    $message['text'] = '';
    if ( count( $message['attachments'] ) > 1 ) {
      array_pop( $message['attachments'] );

      // An additional attachment needs to popped if it's from a battle, due to the battle header attachment.
      if ( $from_battle ) {
        array_pop( $message['attachments'] );
      }
    }

    // More appropriate verb for flee?
    $flee_verb = 'ran away';
         if ( in_array( 'Flying',  $spawn_data->types ) ) { $flee_verb = 'flew away';                 }
    else if ( in_array( 'Water',   $spawn_data->types ) ) { $flee_verb = 'swam away';                 }
    else if ( in_array( 'Ghost',   $spawn_data->types ) ) { $flee_verb = 'floated away';              }
    else if ( in_array( 'Rock',    $spawn_data->types ) ) { $flee_verb = 'dug a hole and escaped';    }
    else if ( in_array( 'Ground',  $spawn_data->types ) ) { $flee_verb = 'dug a hole and escaped';    }
    else if ( in_array( 'Psychic', $spawn_data->types ) ) { $flee_verb = 'disappeared into thin air'; }
    else if ( in_array( 'Dragon',  $spawn_data->types ) ) { $flee_verb = 'escaped';                   }
    else if ( in_array( 'Bug',     $spawn_data->types ) ) { $flee_verb = 'crawled away';              }

    // Add a new actions attachment
    $message['attachments'][] = [
      'color' => '#333333',
      'text' => (
        '*Oh no! ' .
        slackemon_readable( $spawn_data->name ) . ' ' .
        $flee_verb . '!* :disappointed:' . "\n" .
        (
          $catch_too_late && ! $from_battle ?
          'Pokémon don\'t tend to hang around for long - try to catch within ' .
          'about ' . round( SLACKEMON_FLEE_TIME_LIMIT / 60 ) . ' minutes!' :
          (
            'flee-late' === $force_battle_result ?
            'You\'ll need to move a bit faster next time!' :
            'Better luck next time.' . "\n" .
            '*+25 XP*: Attempted ' . ( $force_battle_result ? 'wild battle' : 'catch' )
          )
        )
      ),
      'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
      'mrkdwn_in' => [ 'text' ],
      'actions' => [
        [
          'name' => 'menu',
          'text' => ':leftwards_arrow_with_hook: Main Menu',
          'type' => 'button',
          'value' => 'main',
        ],
      ],
    ];

  } // If is_caught / else

  return $message;

} // Function slackemon_get_catch_message

function slackemon_do_catch( $spawn_ts, $catch_attempt_ts, $user_id = USER_ID, $battle_hash = false, $force_battle_result = '' ) {

  $spawn_data = slackemon_get_spawn_data( $spawn_ts, slackemon_get_player_region( $user_id ), $user_id );
  $player_data = slackemon_get_player_data( $user_id, true );

  if ( $battle_hash ) {
    $battle_data = slackemon_get_battle_data( $battle_hash );
    $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );
    $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $opponent_id );
  }

  // Was this spawn triggered by a new player onboarding? If so, make it a definite catch for that player.
  if (
    isset( $spawn_data->trigger->type ) &&
    'onboarding' === $spawn_data->trigger->type &&
    $user_id === $spawn_data->trigger->user_id
  ) {
    $is_caught = true;

  // Has the user waited too long to catch this Pokemon?
  } else if ( ! $battle_hash && $spawn_ts < $catch_attempt_ts - SLACKEMON_FLEE_TIME_LIMIT ) {
    $is_caught = false;

  // Otherwise, random chance... unless that has already been handled by the battle logic
  } else {

    if ( 'flee' === $force_battle_result ) {
      $is_caught = false;
    } else if ( 'catch' === $force_battle_result ) {
      $is_caught = true;
    } else if ( $battle_hash ) {

      $hp_percentage_integer = $opponent_pokemon->hp / $opponent_pokemon->stats->hp;
      $is_caught = (
        random_int( 1, SLACKEMON_BASE_FLEE_CHANCE * SLACKEMON_BATTLE_FLEE_MULTIPLIER / $hp_percentage_integer ) > 1
      );

    } else {

      // Eg. `random_int( 1, 4 )` for a 1 in 4 chance of NOT catching
      $is_caught = random_int( 1, SLACKEMON_BASE_FLEE_CHANCE ) > 1;

    }

    if ( ! $is_caught ) {

      $player_data->xp += 25; // Pokemon fled, add 25 XP

      foreach ( $player_data->pokedex as $pokedex_entry ) {
        if ( $spawn_data->pokedex == $pokedex_entry->id ) {
          if ( ! isset( $pokedex_entry->fled ) ) {
            $pokedex_entry->fled = 0;
          }
          $pokedex_entry->fled++;
          slackemon_save_player_data( $player_data, $user_id, true );
        }
      }

    } // If not is_caught
  } // Trigger / time delay / else

  if ( ! $is_caught ) {
    return false;
  }

  // Does the wild Pokemon's HP / PP need adjusting from their battle?
  if ( $battle_hash ) {
    $spawn_data->hp    = $opponent_pokemon->hp;
    $spawn_data->moves = $opponent_pokemon->moves;
    $spawn_data->battles->last_participated = $opponent_pokemon->battles->last_participated;
  }

  // Add entry to player's collection
  $spawn_data->is_battle_team = false;
  $spawn_data->is_favourite   = false;
  unset( $spawn_data->trigger ); // We don't need this anymore
  unset( $spawn_data->users   ); // We don't need this anymore
  $player_data->pokemon[] = $spawn_data;

  // Find the correct Pokedex entry to increment, and do the XP add too
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $spawn_data->pokedex == $pokedex_entry->id ) {

      $xp_to_add = 100; // Base catch XP is 100

      if ( ! $pokedex_entry->caught ) {
        $xp_to_add += 500; // First unique Pokemon bonus!
      } else if ( $pokedex_entry->caught % 100 == 0 ) {
        $xp_to_add += 500; // Bonus on every 100 catches
      } else if ( $pokedex_entry->caught % 10 == 0 ) {
        $xp_to_add += 100; // Bonus on every 10 catches
      }

      // Bonus if caught quickly
      if ( $catch_attempt_ts < $spawn_ts + round( SLACKEMON_FLEE_TIME_LIMIT / 5 ) + MINUTE_IN_SECONDS / 4 ) {
        $xp_to_add += 50;
      }

      $player_data->xp += $xp_to_add;
      $pokedex_entry->caught++;

      return slackemon_save_player_data( $player_data, $user_id, true );

    }
  }

  // We should have returned above, but just in case we couldn't find the Pokedex entry for some reason...
  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_do_catch

function slackemon_start_catch_battle( $spawn_ts, $action, $user_id = USER_ID ) {

  // Are we already in battle?
  if ( slackemon_is_player_in_battle( $user_id ) ) {
    slackemon_send2slack([
      'text'    => ':exclamation: *Oops!* You\'re already in a battle - you can\'t start another one just yet. :smile:',
      'channel' => $user_id, // Sending the channel through forces a new message to be sent, rather than potentially
                             // accidentally replacing the message which could become the battle shortly.
    ]);
    return false;
  }

  // First, check that this isn't too late
  $catch_attempt_ts = time();
  $catch_too_late = $spawn_ts < $catch_attempt_ts - SLACKEMON_FLEE_TIME_LIMIT;
  if ( $catch_too_late ) {
    slackemon_do_action_response( slackemon_get_catch_message( $spawn_ts, $action ) );
    return false;
  }

  // Now, there's a chance the Pokemon might flee before we battle...
  // This increases the chance of staying compared to a standard catch
  $is_accepting_battle = random_int( 1, SLACKEMON_BASE_FLEE_CHANCE * SLACKEMON_BATTLE_FLEE_MULTIPLIER ) > 1;
  if ( ! $is_accepting_battle ) {
    slackemon_do_action_response( slackemon_get_catch_message( $spawn_ts, $action, false, 'flee' ) );
    return false;
  }

  $battle_team = slackemon_get_battle_team( $user_id, true );

  if ( ! $battle_team ) {
    $message = slackemon_update_triggering_attachment(
      [
        'text' => ':open_mouth: *Oops! You don\'t have enough Pokémon in your team to battle at the moment!',
        'actions' => [
          [
            'name' => 'catch',
            'text' => ':pokeball: Throw Pokéball',
            'type' => 'button',
            'value' => $spawn['ts'],
            'style' => 'primary',
          ],
        ],
      ],
      $action,
      false
    );
    return $message;
  }

  $battle_ts = time();
  $inviter_id = $user_id;
  $invitee_id = $spawn_ts;

  $battle_hash = slackemon_get_battle_hash( $battle_ts, $inviter_id, $invitee_id );
  $spawn_data  = slackemon_get_spawn_data( $spawn_ts, slackemon_get_player_region( $user_id ), $user_id );

  // Start with a random Pokemon from the team, for now (until we code in choosing at the start)
  $inviter_pokemon = $battle_team[ array_rand( $battle_team ) ];

  // Wild Pokemon, naturally, battles as itself
  $invitee_pokemon = $spawn_data;

  $battle_data = [
    'ts' => $battle_ts,
    'hash' => $battle_hash,
    'type' => 'wild',
    'users' => [
      $inviter_id => [
        'team' => [ $inviter_pokemon ],
        'status' => [ 'current' => $inviter_pokemon->ts ],
        'response_url' => RESPONSE_URL,
      ],
      $invitee_id => [
        'team' => [ $invitee_pokemon ],
        'status' => [ 'current' => $invitee_pokemon->ts ],
        'response_url' => false,
      ],
    ],
    'last_move_ts' => $battle_ts,
    'turn' => $invitee_id,
  ];

  // Set player in battle
  slackemon_set_player_in_battle( $inviter_id );

  // For consistency, turn the whole thing into an object rather than an array
  $battle_data = json_decode( json_encode( $battle_data ) );

  // Save battle data without warning about it not being locked, since it is a new file
  slackemon_save_battle_data( $battle_data, $battle_hash, 'battle', false, false );

  // Get first attachment
  slackemon_send2slack([
    'attachments' => slackemon_get_battle_attachments( $battle_hash, $inviter_id, 'first', '' ),
  ], RESPONSE_URL );

  // Wild Pokemon gets to move first
  sleep( 4 ); // Wait before the computer moves...
  $move = slackemon_get_best_move( $invitee_pokemon, $inviter_pokemon );
  slackemon_do_battle_move( $move->name, $battle_hash, $action, true, $invitee_id );

} // Function slackemon_start_catch_battle

// The end!
