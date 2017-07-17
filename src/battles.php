<?php
/**
 * Battle specific functions for Slackemon.
 *
 * @package Slackemon
 */

/** Cronned function (through /slackemon battle-updates) which should run every minute. */
function slackemon_do_battle_updates() {

  $active_players = slackemon_get_player_ids( [ 'active_only' => true ] );

  $now = time();
  $one_minute_ago          = $now - MINUTE_IN_SECONDS * 1;
  $two_minutes_ago         = $now - MINUTE_IN_SECONDS * 2;
  $twenty_minutes_ago      = $now - MINUTE_IN_SECONDS * 20;
  $twenty_one_minutes_ago  = $now - MINUTE_IN_SECONDS * 21;
  $twenty_five_minutes_ago = $now - MINUTE_IN_SECONDS * 25;

  // First up, use this opportunity to check if any Pokemon and their moves need reviving after battle (i.e. HP & PP)
  // HP & PP will restore at a rate of 5% per minute (by default), with an additional delay of 10 minutes if fainted
  // (also, because active_only is true, Pokemon will only restore if a user is online and NOT in another battle!).
  foreach ( $active_players as $player_id ) {

    $player_data      = slackemon_get_player_data( $player_id );
    $player_pokemon   = $player_data->pokemon;
    $changes_required = false; // Tracks whether changes are required for this player file, so
                               // that we don't lock/unlock needlessly.

    // TODO: These routines currently exclude fainted Pokemon from *battles that started less than 20 minutes ago*, 
    //       rather than battles that *ended* 10 minutes ago. We need to store battle *end* time with the Pokemon
    //       data itself.

    // So we don't needlessly lock up player files that need no changes, we do a check first on our unlocked player
    // file to see if any changes are required. As soon as we know we need to make a change, we can break the loop.
    foreach ( $player_pokemon as $_pokemon ) {

      if ( 0 == $_pokemon->hp && $_pokemon->battles->last_participated > $twenty_minutes_ago ) {
        continue;
      }

      if ( $_pokemon->hp < $_pokemon->stats->hp ) {
        $changes_required = true;
        break; // We know we need to make a change, so we can break this loop.
      }

      foreach ( $_pokemon->moves as $_move ) {
        if ( $_move->{'pp-current'} < $_move->pp ) {
          $changes_required = true;
          break 2; // Like above, but break the outer loop.
        }
      }

    } // Foreach player_pokemon

    // If changes are required, get the player data again with a file lock, and make the required changes.
    if ( $changes_required ) {
      $player_data    = slackemon_get_player_data( $player_id, true );
      $player_pokemon = $player_data->pokemon;

      foreach ( $player_pokemon as $_pokemon ) {

        if ( 0 == $_pokemon->hp && $_pokemon->battles->last_participated > $twenty_minutes_ago ) {
          continue;
        }

        if ( $_pokemon->hp < $_pokemon->stats->hp ) {
          $_pokemon->hp += SLACKEMON_HP_RESTORE_RATE * $_pokemon->stats->hp;
          $_pokemon->hp  = min( $_pokemon->hp, $_pokemon->stats->hp );
        }

        foreach ( $_pokemon->moves as $_move ) {
          if ( $_move->{'pp-current'} < $_move->pp ) {
            $_move->{'pp-current'} += SLACKEMON_HP_RESTORE_RATE * $_move->pp;
            $_move->{'pp-current'}  = min( $_move->{'pp-current'}, $_move->pp );
          }
        }

      } // Foreach player_pokemon

      slackemon_save_player_data( $player_data, $player_id, true );

    } // If changes_required
  } // Foreach player 

  // Check whether there's any new turns to alert players of (within the last 1-2 mins)...
  // ...or turns expiring soon (they expire at 25 minutes, due to Slack 30 minute action response_url timeout)...
  // ...or turns which *have* expired.

  $active_battles = slackemon_get_all_active_battles();

  foreach ( $active_battles as $_battle ) {

    $user_id = $_battle->turn;
    $opponent_id = slackemon_get_battle_opponent_id( $_battle->hash, $user_id );

    // For non P2P battles, we expire a lot sooner (assuming the flee limit is a lot sooner, that is!)
    // We also just do it directly to the user.
    if ( 'p2p' !== $_battle->type ) {

      if (
        slackemon_is_user_human( $user_id ) &&
        $_battle->last_move_ts < max( time() - SLACKEMON_FLEE_TIME_LIMIT, $twenty_five_minutes_ago )
      ) {

        slackemon_send2slack(
          slackemon_get_catch_message( $opponent_id, null, true, 'flee-late', $user_id ),
          $_battle->users->{ $user_id }->response_url
        );
        
      }

      // Don't run the rest of this loop iteration, it's for P2P battles only.
      continue;

    } // If not p2p battle.

    $opponent_name = slackemon_get_slack_user_first_name( $opponent_id );

    // New turns
    if ( $_battle->last_move_ts < $one_minute_ago && $_battle->last_move_ts > $two_minutes_ago ) {
      
      slackemon_send2slack([
        'text' => (
          'Heads up - it\'s your turn in your battle against ' . $opponent_name . '. :simple_smile:'
        ),
        'channel' => $user_id,
      ]);
    }

    // Expiring turns.
    if ( $_battle->last_move_ts < $twenty_minutes_ago && $_battle->last_move_ts > $twenty_one_minutes_ago ) {
      slackemon_send2slack([
        'text' => (
          ':warning: *Uh oh - ' . $opponent_name . ' has been waiting for your move for 20 minutes!*' . "\n" .
          'You need to make a move in the *next 5 minutes* to avoid forfeiting the battle. :timer_clock:'
        ),
        'channel' => $user_id,
      ]);
    }

    // Expired turns - battle ends.
    if ( $_battle->last_move_ts < $twenty_five_minutes_ago ) {
      slackemon_end_battle( $_battle->hash, 'timeout', $user_id );
    }

  } // Foreach active_battles.
} // Function slackemon_do_battle_updates.

/** Starts a P2P battle. See slackemon_start_catch_battle() in catching.php for battles with wild Pokemon. */
function slackemon_start_battle( $battle_hash, $action ) {

  // Get the invite data and remove the invite.
  $invite_data = slackemon_get_invite_data( $battle_hash, true );

  if ( ! slackemon_validate_battle_readiness( $invite_data, $action ) ) {
    return false;
  }

  $inviter_id = $invite_data->inviter_id;
  $invitee_id = $invite_data->invitee_id;

  $inviter_battle_team = slackemon_get_battle_team( $inviter_id, true );
  $invitee_battle_team = slackemon_get_battle_team( $invitee_id, true );

  $battle_data = [
    'ts'             => $invite_data->ts,
    'hash'           => $battle_hash,
    'type'           => 'p2p',
    'challenge_type' => $invite_data->challenge_type,
    'users'          => [
      $inviter_id => [
        'team'   => $inviter_battle_team,
        'status' => [
          'current'         => false,
          'swaps_remaining' => SLACKEMON_BATTLE_SWAP_LIMIT,
        ],
        'response_url' => '', // Not available until the inviter is given an action to perform.
      ],
      $invitee_id => [
        'team'   => $invitee_battle_team,
        'status' => [
          'current'         => false,
          'swaps_remaining' => SLACKEMON_BATTLE_SWAP_LIMIT,
        ],
        'response_url' => RESPONSE_URL,
      ],
    ],
    'last_move_ts' => time(),
    'turn'         => $invitee_id,
  ];

  // If we have a battle team leader, start with them. Otherwise, start with a random Pokemon from the team.
  $inviter_team_leader = slackemon_get_battle_team_leader( $inviter_id );
  $invitee_team_leader = slackemon_get_battle_team_leader( $invitee_id );
  if ( $inviter_team_leader && isset( $inviter_battle_team[ 'ts' . $inviter_team_leader ] )  ) {
    $inviter_pokemon = slackemon_get_player_pokemon_data( $inviter_team_leader, null, $inviter_id );
  } else {
    $inviter_random_key = array_rand( $battle_data['users'][ $inviter_id ]['team'] );
    $inviter_pokemon = $battle_data['users'][ $inviter_id ]['team'][ $inviter_random_key ];
  }
  if ( $invitee_team_leader && isset( $invitee_battle_team[ 'ts' . $invitee_team_leader ] )  ) {
    $invitee_pokemon = slackemon_get_player_pokemon_data( $invitee_team_leader, null, $invitee_id );
  } else {
    $invitee_random_key = array_rand( $battle_data['users'][ $invitee_id ]['team'] );
    $invitee_pokemon = $battle_data['users'][ $invitee_id ]['team'][ $invitee_random_key ];
  }

  // Set the start Pokemon.
  $battle_data['users'][ $inviter_id ]['status']['current'] = $inviter_pokemon->ts;
  $battle_data['users'][ $invitee_id ]['status']['current'] = $invitee_pokemon->ts;

  // Maybe record these Pokemon as 'seen' in the user's Pokedex?
  slackemon_maybe_record_battle_seen_pokemon( $inviter_id, $invitee_pokemon->pokedex );
  slackemon_maybe_record_battle_seen_pokemon( $invitee_id, $inviter_pokemon->pokedex );

  // Set players in battle.
  slackemon_set_player_in_battle( $inviter_id );
  slackemon_set_player_in_battle( $invitee_id );

  // For consistency, turn the whole thing into an object rather than an array.
  $battle_data = json_decode( json_encode( $battle_data ) );

  // Save battle data without warning about it not being locked, since it is a new file.
  slackemon_save_battle_data( $battle_data, $battle_hash, 'battle', false, false );

  // Respond to the invitee.
  $invitee_message = [
    'text'             => (
      ':grin: *You have accepted ' . slackemon_get_slack_user_first_name( $inviter_id ) . '\'s challenge!*'
    ),
    'attachments'      => slackemon_get_battle_attachments( $battle_hash, $invitee_id, 'start' ),
    'replace_original' => true,
  ];
  $invitee_message_result = slackemon_send2slack( $invitee_message );

  // If the message sent ok, alert the inviter.
  // TODO: Send the battle attachments here too, so they don't have to wait for the first move.
  if ( $invitee_message_result ) {

    slackemon_post2slack([
      'text' => (
        ':laughing: *' . slackemon_get_slack_user_first_name( $invitee_id ) . ' has accepted ' .
        'your battle challenge!*' . "\n" .
        'It\'s their move first - so hang tight just a sec!'
      ),
      'channel' => $inviter_id,
    ]);

    return true;

  }

  // If we get here, return false. This means an issue occured with sending the battle start message.
  return false;

} // Function slackemon_start_battle.

/**
 * Checks that a P2P battle can start, including verifying that both trainers have a battle team and that their
 * team passes the rules for the selected challenge type. This function also takes care of responding to both users
 * about the status.
 *
 * @param obj $invite_data An invite data object.
 * @param obj $action      The action object passed by Slack from the invite acceptance button invocation.
 * @return bool Whether or not the battle can go ahead.
 */
function slackemon_validate_battle_readiness( $invite_data, $action ) {

  if ( ! $invite_data ) {

    slackemon_update_triggering_attachment(
      ':no_entry: *Oops!* That battle challenge doesn\'t seem to exist anymore.' . "\n" .
      'It may have already been accepted, declined, or cancelled.',
      $action
    );

    return false;

  }

  $inviter_id = $invite_data->inviter_id;
  $invitee_id = $invite_data->invitee_id;

  // Get teams, automatically replacing fainted Pokemon with fillers.
  $inviter_battle_team = slackemon_get_battle_team( $inviter_id, true, false, $invite_data->challenge_type );
  $invitee_battle_team = slackemon_get_battle_team( $invitee_id, true, false, $invite_data->challenge_type );

  // Check challenge eligibility.
  if ( $inviter_battle_team && $invitee_battle_team ) {
    $inviter_eligibility = slackemon_is_player_eligible_for_challenge( $invite_data->challenge_type, $inviter_id );
    $invitee_eligibility = slackemon_is_player_eligible_for_challenge( $invite_data->challenge_type, $invitee_id );
  }

  // If all checks have passed, we're good to go now!
  if ( $inviter_battle_team && $invitee_battle_team && $inviter_eligibility && $invitee_eligibility ) {
    return true;
  }

  // Otherwise we need to cancel the battle - we either don't have *enough* non-fainted eligible-for-this-challenge
  // Pokemon on at least one of the teams, OR at least one of the teams is not eligible.

  // Define the responses to the players for each possible situation.
  $inviter_name = slackemon_get_slack_user_first_name( $inviter_id );
  $invitee_name = slackemon_get_slack_user_first_name( $invitee_id );
  $responses    = slackemon_get_invite_cancellation_responses( $inviter_name, $invitee_name );

  // Determine which message to send to each user.
  if ( ! $inviter_battle_team && ! $invitee_battle_team ) {
    $inviter_message = $responses['team_size']['inviter']['self'];
    $invitee_message = $responses['team_size']['invitee']['self'];
  } elseif ( ! $inviter_battle_team ) {
    $inviter_message = $responses['team_size']['inviter']['self'];
    $invitee_message = $responses['team_size']['inviter']['other'];
  } elseif ( ! $invitee_battle_team ) {
    $inviter_message = $responses['team_size']['invitee']['other'];
    $invitee_message = $responses['team_size']['invitee']['self'];
  } elseif ( ! $inviter_eligibility && ! $invitee_eligibility ) {
    $inviter_message = $responses['eligibility']['inviter']['self'];
    $invitee_message = $responses['eligibility']['invitee']['self'];
  } elseif ( ! $inviter_eligibility ) {
    $inviter_message = $responses['eligibility']['inviter']['self'];
    $invitee_message = $responses['eligibility']['inviter']['other'];
  } elseif ( ! $invitee_eligibility ) {
    $inviter_message = $responses['eligibility']['invitee']['other'];
    $invitee_message = $responses['eligibility']['invitee']['self'];
  }

  // Use the built-in action response URL detector to send directly to the invitee (who invoked this action)
  slackemon_send2slack([
    'text'             => $invitee_message,
    'attachments'      => [ slackemon_back_to_menu_attachment() ],
    'replace_original' => true,
  ]);

  // Create a new Slackemon message for the inviter.
  slackemon_post2slack([
    'text'        => $inviter_message,
    'attachments' => [ slackemon_back_to_menu_attachment() ],
    'channel'     => $inviter_id,
  ]);

  return false;

} // Function slackemon_validate_battle_readiness.

// Usually means user has surrendered ($reason = 'surrender') but can also be used for timeouts etc.
// Running through this function will result in *only the opponent getting battle experience*
function slackemon_end_battle( $battle_hash, $reason, $user_id = USER_ID ) {

  $loser_id = $user_id;
  $winner_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  $battle_data = slackemon_get_battle_data( $battle_hash );

  switch ( $reason ) {

    case 'timeout':

      // This option is only supported for p2p battles.

      if ( 'p2p' === $battle_data->type ) {

        $winner_name = slackemon_get_slack_user_first_name( $winner_id );

        $loser_message = (
          ':exclamation: *Unfortunately, your battle with ' . $winner_name . ' has expired.*' . "\n" .
          'This is because you did not make a move within 25 minutes. ' . $winner_name . ' will receive full ' .
          'experience points for this battle. Perhaps try again later when you have some more time up your ' .
          'sleeve. :slightly_smiling_face:'
        );

        slackemon_send2slack([
          'text' => $loser_message,
          'channel' => $loser_id,
        ]);

        slackemon_post2slack([
          'text' => (
            ':face_with_rolling_eyes: *Unfortunately, your battle with ' .
            slackemon_get_slack_user_first_name( $loser_id ) . ' has expired.*' . "\n" .
            slackemon_get_slack_user_first_name( $loser_id ) . ' did not make a move within 25 minutes. You ' .
            'still get full experience points for your part in the battle though - click the _Complete_ button ' .
            'below to receive them!'
          ),
          'attachments' => [
            [
              'fallback' => 'Complete Battle',
              'actions' => [
                [
                  'name' => 'battles/complete',
                  'text' => 'Complete Battle',
                  'type' => 'button',
                  'value' => $battle_hash . '/won',
                  'style' => 'primary',
                ],
              ],
            ],
          ],
          'channel' => $winner_id,
        ]);

      } // If p2p battle.

    break; // Case timeout.

    case 'surrender':

      switch ( $battle_data->type ) {

        case 'p2p':

          slackemon_send2slack([
            'text' => 'You have surrended the battle!', // TODO: Expand on this, lol, when surrenders become possible.
          ]);

        break; // Case p2p.

        case 'wild':

          $user_pokemon = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
          
          $user_pokemon_message = '';

          $user_pokemon_message .= SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $user_pokemon->name . ': ' : '';
          $user_pokemon_message .= slackemon_readable( $user_pokemon->name ) . ' ';
          $user_pokemon_message .= (
            $user_pokemon->battles->last_participated == $battle_data->ts ?
            'has ' . floor( $user_pokemon->hp / $user_pokemon->stats->hp * 100 ) . '% HP remaining' :
            'didn\'t participate in this battle'
          );

          slackemon_send2slack([
            'text' => (
              ':white_check_mark: *Ok, you got away safely!* :relieved:' . "\n\n" .
              $user_pokemon_message
            ),
            'attachments' => [
              slackemon_back_to_menu_attachment(),
            ],
          ]);

        break; // Case wild.

      } // Switch battle type.

    break; // Case surrender.

  } // Switch reason.

  // Complete the battle for the loser now.
  // The winner's completion will happen when they follow their battle complete action.
  slackemon_complete_battle( 'lost', $battle_hash, $loser_id, false, false );

} // Function slackemon_end_battle.

// Tally up and apply the battle stats for the user.
function slackemon_complete_battle(
  $battle_result, $battle_hash, $user_id = USER_ID, $award_xp_to_user = true, $send_response_to_user = true
) {

  // Get the battle data, including from a 'complete' battle in case a user has already run this function.
  $battle_data = slackemon_get_battle_data( $battle_hash, true );

  // Bow out early if the battle data is not available OR if this was a friendly battle.
  if ( ! $battle_data || slackemon_is_friendly_battle( $battle_data ) ) {
    return slackemon_battle_has_ended_message();
  }

  if ( 'won' === $battle_result ) {
    $message = slackemon_complete_battle_for_winner( $battle_data, $user_id, $award_xp_to_user );
  } else if ( 'lost' === $battle_result ) {
    $message = slackemon_complete_battle_for_loser( $battle_data, $user_id, $award_xp_to_user );
  }

  slackemon_move_completed_battle_file( $battle_hash );

  if ( $send_response_to_user ) {

    $back_to_menu_attachment = slackemon_back_to_menu_attachment();

    // Show a button to view the Pokemon the user fought with, if only 1 Pokemon was on the team (i.e. a wild battle).
    $team_as_array = get_object_vars( $battle_data->users->{ $user_id }->team );
    if ( 1 === count( $team_as_array ) ) {

      $battle_pokemon = array_pop( $team_as_array );

      array_unshift(
        $back_to_menu_attachment['actions'],
        [
          'name' => 'pokemon/view/battle',
          'text' => ':eye: View ' . slackemon_readable( $battle_pokemon->name ),
          'type' => 'button',
          'value' => $battle_pokemon->ts,
        ]
      );

    }

    $message['attachments'][] = $back_to_menu_attachment;
    slackemon_send2slack( $message );

  }

  return $message;

} // Function slackemon_complete_battle.

/**
 * Completes a winner's battle, including applying all stat changes and providing the battle results message to be
 * sent to the user.
 *
 * @param obj  $battle_data
 * @param str  $user_id
 * @param bool $award_xp_to_user Whether or not to award the user XP. Generally always true for the winner!
 */
function slackemon_complete_battle_for_winner( $battle_data, $user_id, $award_xp_to_user ) {

  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode( $user_id );
  $opponent_id = slackemon_get_battle_opponent_id( $battle_data->hash, $user_id );

  $winning_team = $battle_data->users->{ $user_id     }->team;
  $losing_team  = $battle_data->users->{ $opponent_id }->team;

  // What's the experience & effort points gained from the opponent's fainted Pokemon?
  $effort_yield     = slackemon_get_ev_yield( $losing_team );
  $experience_yield = slackemon_get_xp_yield( $losing_team );

  // Apply experience & any other relevant changes to eligible Pokemon, while starting to generate the user response.
  $results = slackemon_apply_battle_team_results( $winning_team, $battle_data, $effort_yield, $experience_yield );

  // Get player data for writing.
  $player_data = slackemon_get_player_data( $user_id, true );

  // Apply new Pokemon data to the player's collection, while further working on the user response.
  $results = slackemon_apply_battle_winners_to_collection( $player_data, $results );

  // Add player XP. $award_xp_to_user should always be true for the winner!
  if ( $award_xp_to_user ) {

    if ( 'wild' === $battle_data->type ) {
      $xp_to_add = 175 + $experience_yield['total'];
    } else {
      $xp_to_add = 500 + $experience_yield['total'];
    }

    $player_data->xp += floor( $xp_to_add );

    $xp_gain_message = '';

    foreach ( $experience_yield['itemised'] as $_pokemon ) {

      $xp_gain_message .= (
        ( $_pokemon['xp_yield'] < 10 ?  '   ' : '' ) . // Spacing.
        ( $_pokemon['xp_yield'] < 100 ? '   ' : '' ) . // More spacing.
        '*+' . $_pokemon['xp_yield'] . ' XP*: Defeated a ' .
        'level ' . $_pokemon['level'] . ' ' .
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $_pokemon['name'] . ': ' : '' ) .
        slackemon_readable( $_pokemon['name'] ) .
        (
          SLACKEMON_EXP_GAIN_MODIFIER > 1 ?
          ' :low_brightness:' . ( $is_desktop ? '*x' . SLACKEMON_EXP_GAIN_MODIFIER . '*' : '' ) :
          ''
        ) .
        "\n"
      );

    } // For each itemised experience_yield.
  } // If award_xp_to_user.

  // Modify 'trainer battle' stats.
  if ( 'wild' !== $battle_data->type ) {
    $player_data->battles->won++;
    $player_data->battles->participated++;
    $player_data->battles->last_won = $battle_data->ts;
  }

  $player_data->battles->last_participated = $battle_data->ts;

  slackemon_save_player_data( $player_data, $user_id, true );
  slackemon_set_player_not_in_battle( $user_id );

  // Put message together.
  $message = [
    'text' => (
      '*Your Pokémon:*' . "\n" .
      $results['response'] . "\n" .
      (
        'wild' === $battle_data->type ?
        '*+175 XP*: Won a wild battle' :
        '*+500 XP*: Won a trainer battle! :tada:'
      ) . "\n" .
      ( $award_xp_to_user ? $xp_gain_message : '' )
    ),
  ];

  return $message;

} // Function slackemon_complete_battle_for_winner.

function slackemon_complete_battle_for_loser( $battle_data, $user_id, $award_xp_to_user ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode( $user_id );
  $pokemon_experience_message = '';
  $battle_pokemon_by_ts = [];

  // Save HP, happiness and participation data.
  foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {

    if ( ! $_pokemon->hp ) {
      $_pokemon->happiness -= 1; // Happiness reduction of 1 due to fainting.
      $_pokemon->happiness  = max( 0, $_pokemon->happiness ); // Ensure we don't go below 0.
    }

    $battle_pokemon_by_ts[ $_pokemon->ts ] = $_pokemon;

  }

  // Get player data for writing
  $player_data = slackemon_get_player_data( $user_id, true );

  // Apply new Pokemon data to the player's collection.
  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( isset( $battle_pokemon_by_ts[ $_pokemon->ts ] ) ) {

      $_pokemon_intro = (
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $_pokemon->name . ': ' : '' ) .
        slackemon_readable( $_pokemon->name )
      );

      $_pokemon->hp        = $battle_pokemon_by_ts[ $_pokemon->ts ]->hp;
      $_pokemon->moves     = $battle_pokemon_by_ts[ $_pokemon->ts ]->moves;
      $_pokemon->stats     = $battle_pokemon_by_ts[ $_pokemon->ts ]->stats;
      $_pokemon->battles   = $battle_pokemon_by_ts[ $_pokemon->ts ]->battles;
      $_pokemon->happiness = $battle_pokemon_by_ts[ $_pokemon->ts ]->happiness;

      if ( ! $_pokemon->hp ) {
        $pokemon_experience_message .= $_pokemon_intro . ' fainted :frowning:' . "\n";
      } else if ( $_pokemon->battles->last_participated !== $battle_data->ts ) {
        $pokemon_experience_message .= $_pokemon_intro . ' didn\'t participate in this battle' . "\n";
      } else {
        $pokemon_experience_message .= (
          $_pokemon_intro . ' has ' . floor( $_pokemon->hp / $_pokemon->stats->hp * 100 ) . '% HP remaining' . "\n"
        );
      }

    } // If pokemon was in this battle.
  } // Foreach player_data pokemon.

  // Add player XP.
  if ( $award_xp_to_user ) {
    $player_data->xp += 25;
  }

  // Modify 'trainer battle' stats.
  if ( 'wild' !== $battle_data->type ) {
    $player_data->battles->participated++;
  }

  $player_data->battles->last_participated = $battle_data->ts;

  slackemon_save_player_data( $player_data, $user_id, true );
  slackemon_set_player_not_in_battle( $user_id );

  // Put message together.
  $message = [
    'text' => (
      '*Your Pokémon:*' . "\n" .
      $pokemon_experience_message . "\n" .
      ( $award_xp_to_user ? '*+25 XP*: Participated in a battle' : '' )
    ),
  ];

  return $message;

} // Function slackemon_complete_battle_for_loser

/**
 * Applies results from a battle to the winning team, and supplies response data for sending back to the user.
 *
 * @param arr $battle_team      An array containing Pokemon objects. These objects will be modified in place.
 * @param obj $battle_data      The battle data object.
 * @param arr $effort_yield     The effort yield returned by a call to slackemon_get_ev_yield().
 * @param int $experience_yield The experience yield returned by a call to slackemon_get_xp_yield().
 */
function slackemon_apply_battle_team_results( &$battle_team, $battle_data, $effort_yield, $experience_yield ) {

  $results = [
    'response' => '',
  ];

  foreach ( $battle_team as $_pokemon ) {

    // Skip recalculation if this Pokemon fainted.
    if ( ! $_pokemon->hp ) {

      $_pokemon->happiness -= 1; // Happiness reduction of 1 due to fainting.
      $_pokemon->happiness  = max( 0, $_pokemon->happiness ); // Ensure we don't go below 0.

      $results['response'] .= (
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $_pokemon->name . ': ' : '' ) .
        slackemon_readable( $_pokemon->name, false ) . ' ' .
        'fainted :frowning:' . "\n"
      );

      continue;
      
    }

    // Skip if this Pokemon didn't get to participate at all.
    if ( $_pokemon->battles->last_participated !== $battle_data->ts ) {

      $results['response'] .= (
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $_pokemon->name . ': ' : '' ) .
        slackemon_readable( $_pokemon->name, false ) . ' ' .
        'didn\'t participate in this battle' . "\n"
      );

      continue;

    }

    // Experience & EVs.
    $_pokemon->xp  += $experience_yield['total'];
    $_pokemon->evs  = slackemon_apply_evs( $_pokemon, $effort_yield );

    // Level.
    $old_level       = $_pokemon->level; // Store for happiness calculation shortly.
    $_pokemon->level = slackemon_calculate_level( $_pokemon );

    // Happiness (as a result of levelling up).
    $_pokemon->happiness = slackemon_calculate_level_up_happiness( $old_level, $_pokemon );

    // Stats.
    $_pokemon->stats->attack              = slackemon_calculate_stats( 'attack',          $_pokemon );
    $_pokemon->stats->defense             = slackemon_calculate_stats( 'defense',         $_pokemon );
    $_pokemon->stats->hp                  = slackemon_calculate_stats( 'hp',              $_pokemon );
    $_pokemon->stats->speed               = slackemon_calculate_stats( 'speed',           $_pokemon );
    $_pokemon->stats->{'special-attack'}  = slackemon_calculate_stats( 'special-attack',  $_pokemon );
    $_pokemon->stats->{'special-defense'} = slackemon_calculate_stats( 'special-defense', $_pokemon );

    // CP.
    $_pokemon->cp = slackemon_calculate_cp( $_pokemon->stats );

    // Trainer battle stats.
    if ( 'wild' !== $battle_data->type ) {
      $_pokemon->battles->won++;
      $_pokemon->battles->last_won = $battle_data->ts;
    }

  } // Foreach player battle team Pokemon.

  $results['team'] = $battle_team;

  return $results;

} // Function slackemon_apply_battle_team_results.

/**
 * Applies a winning team to a user's Pokemon collection, and supplies additional response data to send back to the
 * user. This function should be called *after* applying changes to the team's stats in
 * slackemon_apply_battle_team_results().
 *
 * @param obj $player_data  The player data to write the battle winners to. Assumes a file lock has been obtained.
 * @param arr $results      A results array as returned by slackemon_apply_battle_team_results().
 * @return arr Returns the $results array, with a modified 'response' value for sending back to the user.
 */
function slackemon_apply_battle_winners_to_collection( &$player_data, $results ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode( $player_data->user_id );

  foreach ( $player_data->pokemon as $_pokemon ) {

    // Skip if the Pokemon wasn't in this battle.
    if ( ! isset( $results['team']->{ 'ts' . $_pokemon->ts } ) ) {
      continue;
    }

    // Did this Pokemon participate and not faint? It will have an XP difference if so - that's how we detect it.
    if ( $_pokemon->xp != $results['team']->{ 'ts' . $_pokemon->ts }->xp ) {

      $_pokemon_intro = (
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $_pokemon->name . ': ' : '' ) .
        slackemon_readable( $_pokemon->name, false )
      );

      if ( $results['team']->{ 'ts' . $_pokemon->ts }->level > $_pokemon->level ) {

        $results['response'] .= (
          $_pokemon_intro . ' has levelled up ' .
          ( $is_desktop ? 'from level ' . $_pokemon->level . ' ' : '' ) .
          'to ' . $results['team']->{ 'ts' . $_pokemon->ts }->level . '!' . "\n"
        );

        if ( slackemon_can_user_pokemon_evolve( $results['team']->{ 'ts' . $_pokemon->ts } ) ) {
          $results['response'] .= '*' . $_pokemon_intro . ' is ready to evolve!! :open_mouth:*' . "\n";
        }

        $happiness_percent_old = floor( $_pokemon->happiness / 255 * 100 );
        $happiness_percent_new = floor( $results['team']->{ 'ts' . $_pokemon->ts }->happiness / 255 * 100 );

        if ( $happiness_percent_new > $happiness_percent_old ) {

          $results['response'] .= (
            $_pokemon_intro . '\'s happiness has increased ' .
            ( $is_desktop ? 'from ' . $happiness_percent_old . '% ' : '' ) .
            'to ' . $happiness_percent_new . '%' . "\n"
          );

        } // If happiness increase.

        if ( $results['team']->{ 'ts' . $_pokemon->ts }->cp > $_pokemon->cp ) {
          $results['response'] .= (
            $_pokemon_intro . '\'s CP has increased ' .
            ( $is_desktop ? 'from ' . $_pokemon->cp . ' ' : '' ) .
            'to ' . $results['team']->{ 'ts' . $_pokemon->ts }->cp . "\n"
          );
        }

        if ( $results['team']->{ 'ts' . $_pokemon->ts }->stats->hp > $_pokemon->stats->hp ) {
          $results['response'] .= (
            $_pokemon_intro . '\'s HP has increased ' .
            ( $is_desktop ? 'from ' . $_pokemon->stats->hp . ' ' : '' ) .
            'to ' . $results['team']->{ 'ts' . $_pokemon->ts }->stats->hp . "\n"
          );
        }

      } else {

        $results['response'] .= $_pokemon_intro . ' gained experience from this battle' . "\n";

      } // If level increase / else.
    } // If Pokemon has XP difference.

    // Apply the changes from the team to the user's collection.
    // NOTE: Any other action that affects the items listed here should be prevented from being taken during a battle,
    // because it will be overriden now as the battle completes. That includes teaching moves, evolving, and in future
    // also giving and using items.
    $_pokemon->hp        = $results['team']->{ 'ts' . $_pokemon->ts }->hp;
    $_pokemon->xp        = $results['team']->{ 'ts' . $_pokemon->ts }->xp;
    $_pokemon->cp        = $results['team']->{ 'ts' . $_pokemon->ts }->cp;
    $_pokemon->evs       = $results['team']->{ 'ts' . $_pokemon->ts }->evs;
    $_pokemon->moves     = $results['team']->{ 'ts' . $_pokemon->ts }->moves;
    $_pokemon->level     = $results['team']->{ 'ts' . $_pokemon->ts }->level;
    $_pokemon->stats     = $results['team']->{ 'ts' . $_pokemon->ts }->stats;
    $_pokemon->battles   = $results['team']->{ 'ts' . $_pokemon->ts }->battles;
    $_pokemon->happiness = $results['team']->{ 'ts' . $_pokemon->ts }->happiness;

  } // Foreach player_data pokemon.

  return $results;

} // Function slackemon_apply_battle_winners_to_collection.

function slackemon_do_battle_move( $move_name_or_swap_ts, $battle_hash, $action, $user_id = USER_ID, $options = [] ) {

  // Parse options and make our initial decisions on them.

  $defaults = [
    'is_first_move'          => false,
    'is_swap'                => false,
    'is_user_initiated_swap' => false,
    'previous_move_notice'   => '',
  ];

  $options = array_merge( $defaults, $options );

  if ( $options['is_swap'] ) {
    $new_pokemon_ts = $move_name_or_swap_ts;
  } else {
    $move_name      = $move_name_or_swap_ts;
  }

  $battle_data = slackemon_get_battle_data( $battle_hash );

  if ( ! $battle_data || $battle_data->last_move_ts < time() - MINUTE_IN_SECONDS * 25 ) {
    return slackemon_battle_has_ended_message();
  }

  // In case timeouts have allowed a user to move twice, we need to make sure it's this user's turn.
  if ( $battle_data->turn !== $user_id ) {
    return;
  }

  // If this is a swap that the user initiated, and the user has no swaps remianing, we must fail.
  if (
    $options['is_swap'] &&
    $options['is_user_initiated_swap'] &&
    ! $battle_data->users->{ $user_id }->status->swaps_remaining
  ) {
    return;
  }

  // Get battle data again:
  // 1) without allowing for data from a complete battle (default), and
  // 2) including asking for a file lock.
  $battle_data = slackemon_get_battle_data( $battle_hash, false, true );

  // Get opponent ID.
  $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  // Update the last move time to now.
  $battle_data->last_move_ts = time();

  if ( 'p2p' === $battle_data->type || slackemon_is_user_human( $user_id ) ) {
    $battle_data->users->{ $user_id }->response_url = RESPONSE_URL;
  }

  // Is this move a swap?
  if ( $options['is_swap'] ) {

    $old_pokemon    = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
    $battle_team    = $battle_data->users->{ $user_id }->team;

    foreach ( $battle_team as $_pokemon ) {
      if ( $_pokemon->ts == $new_pokemon_ts ) {
        $new_pokemon = $_pokemon;
        break;
      }
    }

    // Set the new current Pokemon for this user.
    $battle_data->users->{ $user_id }->status->current = $new_pokemon->ts;

    // If this was a user initiated swap, reduce the number of swaps remaining.
    if ( $options['is_user_initiated_swap'] ) {
      $battle_data->users->{ $user_id }->status->swaps_remaining--;
    }

    // Possibly record this in the opponent's Pokedex as a brand new 'seen' Pokemon.
    slackemon_maybe_record_battle_seen_pokemon( $opponent_id, $new_pokemon->pokedex );

    $move_message = (
      'swapped ' . slackemon_readable( $old_pokemon->name ) . ' ' .
      'for *' . slackemon_readable( $new_pokemon->name ) . '*! ' .
      ucfirst( slackemon_get_gender_pronoun( $new_pokemon->gender ) ) . ' has ' . $new_pokemon->cp . ' CP.'
    );

    // Get the current Pokemon (incl. the new Pokemon for the current user).
    $user_pokemon     = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
    $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_data, $opponent_id );

  // This move is a traditional battle move.
  } else {

    $user_pokemon     = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
    $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_data, $opponent_id );

    // Get the move from the Pokemon's known moves.
    foreach ( $user_pokemon->moves as $_move ) {
      if ( $_move->name === $move_name ) {
        $move = $_move;
      }
    }

    // If move was not found or has no PP, we resort to our backup move instead (usually Struggle).
    if ( ! isset( $move ) || ! floor( $move->{'pp-current'} ) ) {
      $move = slackemon_get_backup_move();
    }

    // Get the move data.
    $move_data = slackemon_get_move_data( $move->name );

    // Calculate the move's damage.
    $damage_options = [
      'inverse_type_effectiveness' => 'type-inverse' === $battle_data->challenge_type[0],
    ];
    $damage = slackemon_calculate_move_damage( $move, $user_pokemon, $opponent_pokemon, $damage_options );

    // Do the damage!
    $opponent_pokemon->hp -= $damage->damage;
    $opponent_pokemon->hp  = max( 0, $opponent_pokemon->hp ); // Ensure the HP doesn't go below 0.

    // Update the PP
    $move->{'pp-current'}--;

    // Deal with special effects of the move
    // TODO - This needs to be expanded a lot more!

    $meta_message = '';

    if ( $move_data->meta->drain ) {

      // Drain is expressed as a percentage of the damage done, and can be negative if the attack caused recoil.
      $drain_amount     = ( $move_data->meta->drain / 100 ) * $damage->damage;
      $drain_percentage = floor( $drain_amount / $user_pokemon->stats->hp * 100 );

      $user_pokemon->hp += $drain_amount;
      $user_pokemon->hp = max( 0, $user_pokemon->hp ); // Ensure the HP doesn't go below 0.
      $user_pokemon->hp = min( $user_pokemon->stats->hp, $user_pokemon->hp ); // Ensure the HP doesn't go above the max

      if ( $move_data->meta->drain > 0 ) {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' drained ' .
          $drain_percentage . '% HP from ' . slackemon_readable( $opponent_pokemon->name ) . '!_'
        );
      } else {
        $meta_message .= (
          '_The recoil damaged ' . slackemon_readable( $user_pokemon->name ) . ' ' .
          'by ' . abs( $drain_percentage ) . '%!_'
        );
      }

    } // If drain.

    if ( $move_data->meta->healing ) {

      // Healing is expressed as a percentage of the user's maximum HP, and can be negative if they hurt themselves.
      $healing_amount     = $move_data->meta->healing / 100 * $user_pokemon->stats->hp;
      $healing_percentage = floor( $healing_amount / $user_pokemon->stats->hp * 100 );

      $user_pokemon->hp += $healing_amount;
      $user_pokemon->hp = max( 0, $user_pokemon->hp ); // Ensure the HP doesn't go below 0.
      $user_pokemon->hp = min( $user_pokemon->stats->hp, $user_pokemon->hp ); // Ensure the HP doesn't go above the max

      if ( $move_data->meta->healing > 0 ) {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' got healed ' .
          'by ' . $healing_percentage . '%!_'
        );
      } else {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' hurt ' .
          ( 'male' === $user_pokemon->gender ? 'him' : 'her' ) . 'self, ' .
          'causing ' . abs( $healing_percentage ) . '% damage!_'
        );
      }

    } // If healing

    $move_message = 'used *' . slackemon_readable( $move->name ) . '*. ';

    if ( $damage->damage ) {
      $move_message .= (
        'It ' . ( 1 == $damage->damage_percentage ? 'only ' : '' ) .
        'did ' . $damage->damage_percentage . '% damage' .
        ( 1 != $damage->damage_percentage && $damage->damage_percentage < 10 ? '.' : '!' ) . ' ' .
        $damage->type_message .
        ( $meta_message ? "\n" . $meta_message : '' )
      );
    } else {
      $move_message .= $meta_message;
    }

    // Did the opponent faint?
    if ( ! $opponent_pokemon->hp ) {
      $move_message .= "\n\n" . '*' . slackemon_readable( $opponent_pokemon->name ) . ' has fainted!*';
    }

    // Make sure the Pokemon gets credit if this was its first move in this battle
    if ( $user_pokemon->battles->last_participated !== $battle_data->ts ) {
      if ( 'wild' !== $battle_data->type ) {
        $user_pokemon->battles->participated++;
      }
      $user_pokemon->battles->last_participated = $battle_data->ts;
    }

  } // If swap move or traditional move.

  // Update and save the battle data, relinquishing the lock on the battle file.
  $battle_data->turn = $opponent_id;
  slackemon_save_battle_data( $battle_data, $battle_hash, 'battle', true );

  // Prepare notice of the previous move (if available) for prepending to this move's notice.
  if ( $options['previous_move_notice'] ) {
    $options['previous_move_notice'] .= "\n\n";
  }

  // Notify the user.
  if ( slackemon_is_user_human( $user_id ) ) {

    $this_move_notice_user = $options['previous_move_notice'] . 'You ' . $move_message;

    $user_message = [
      'attachments'      => (
        slackemon_get_battle_attachments( $battle_hash, $user_id, 'during', $this_move_notice_user )
      ),
      'replace_original' => true,
    ];

  }

  // Notify the opponent.
  if ( slackemon_is_user_human( $opponent_id ) ) {

    $user_first_name = (
      'wild' === $battle_data->type ?
      slackemon_readable( $user_pokemon->name ) :
      slackemon_get_slack_user_first_name( $user_id )
    );

    $this_move_notice_opponent = $options['previous_move_notice'] . $user_first_name . ' ' . $move_message;

    $opponent_message = [
      'attachments' => (
        slackemon_get_battle_attachments(
          $battle_hash,
          $opponent_id,
          $options['is_first_move'] ? 'first' : 'during',
          $this_move_notice_opponent
        )
      ),
    ];

  }
  
  if ( 'p2p' === $battle_data->type ) {

      slackemon_send2slack( $user_message, RESPONSE_URL );

      // Do we already have an existing action response URL for the opponent?
      // If so, use it, if not, it means this is the first move of a new battle, so we create a fresh message instead.
      if ( $battle_data->users->{ $opponent_id }->response_url ) {
        $opponent_message['replace_original'] = true;
        slackemon_send2slack( $opponent_message, $battle_data->users->{ $opponent_id }->response_url );
      } else {
        $opponent_message['channel'] = $opponent_id;
        slackemon_post2slack( $opponent_message );
      }

  } else {

    // This is not a p2p battle, so, we need to determine which user is which, send a response to the human user, and
    // then if it was the human user who just moved, we need to make a move for the opponent.

    if ( slackemon_is_user_human( $user_id ) ) {

      slackemon_send2slack( $user_message, RESPONSE_URL );

      // If neither Pokemon hasn't fainted, go ahead and move!
      if ( $user_pokemon->hp && $opponent_pokemon->hp ) {

        sleep( 2 ); // Wait before the computer moves...

        // Before we move, should we flee?
        if ( slackemon_should_wild_battle_pokemon_flee( $opponent_pokemon ) ) {
          slackemon_do_action_response( slackemon_get_catch_message( $opponent_pokemon->ts, $action, true, 'flee' ) );
          return false;
        }

        $move = slackemon_get_best_move( $opponent_pokemon, $user_pokemon );

        $args = [
          $move->name,
          $battle_hash,
          $action,
          $opponent_id,
          [ 'previous_move_notice' => $this_move_notice_user ],
        ];

        call_user_func_array( __FUNCTION__, $args );

      } // If either pokemon has hp remaining.

    } else {

      // User is not a human, so just send the message to the opponent.
      slackemon_send2slack( $opponent_message, RESPONSE_URL );

    } // If last move was from human user.
  } // If p2p battle / else.
} // Function slackemon_do_battle_move.

function slackemon_is_player_eligible_for_challenge( $challenge_type, $user_id ) {

  $challenge_type_data = slackemon_get_battle_challenge_data( $challenge_type );

  if ( ! $challenge_type_data->enabled ) {
    return false;
  }

  // Check there are no legendaries if the challenge type disallows them.
  if ( ! $challenge_type_data->allow_legendaries && slackemon_is_legendary_in_battle_team( $user_id ) ) {
    return false;
  }

  // Check the lowest level Pokemon is not higher than the allowed level if a level limited challenge.
  if (
    $challenge_type_data->level_limited &&
    slackemon_get_battle_team_lowest_level( $user_id, true ) > $challenge_type[1]
  ) {
    return false;
  }

  return true;

} // Function slackemon_is_player_eligible_for_challenge.

/**
 * Gets the number of Pokemon a user has remaining (i.e. not fainted) in battle. This be used to eg. determine
 * whether they can swap or not, and also whether the battle has ended. Relies on HP to determine whether Pokemon
 * have fainted or not.
 *
 * @param obj  $battle_data
 * @param str  $user_id      The ID of a user participating in the battle.
 * @param bool $skip_current Whether or not to skip counting the current Pokemon in battle. Should usually be true.
 */
function slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id, $skip_current = true ) {

  $remaining_pokemon = 0;

  if ( $skip_current ) {
    $current_pokemon = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
  }

  foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {

    // If Pokemon has no HP, they have fainted.
    if ( ! $_pokemon->hp ) {
      continue;
    }

    // Do we skip the current Pokemon?
    if ( $skip_current && $_pokemon->ts === $current_pokemon->ts ) {
      continue;
    }

    $remaining_pokemon++;

  }

  return $remaining_pokemon;

} // Function slackemon_get_user_remaining_battle_pokemon.

/**
 * Returns the user ID (or spawn timestamp (ts) if a wild Pokemon) of a user's battle opponent.
 *
 * @param obj|str $battle_data The battle data object, or a battle hash.
 * @param str     $user_id
 */
function slackemon_get_battle_opponent_id( $battle_data, $user_id ) {

  // Accept a battle hash being passed through.
  if ( is_string( $battle_data ) ) {
    $battle_data = slackemon_get_battle_data( $battle_data );
  }

  foreach ( $battle_data->users as $_user_id => $_user_data ) {
    if ( $_user_id !== $user_id ) {
      return $_user_id;
    }
  }

} // Function slackemon_get_battle_opponent_id.

/**
 * Returns the Pokemon object that a user is currently using in a battle.
 *
 * @param obj|str $battle_data The battle data object, or a battle hash.
 * @param str     $user_id
 * @return obj|bool Returns the object, or false on failure.
 */
function slackemon_get_battle_current_pokemon( $battle_data, $user_id ) {

  // Accept a battle hash being passed through.
  if ( is_string( $battle_data ) ) {
    $battle_data = slackemon_get_battle_data( $battle_data );
  }

  $user_pokemon = $battle_data->users->{ $user_id }->team;

  foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {
    if ( $_pokemon->ts === $battle_data->users->{ $user_id }->status->current ) {
      return $_pokemon;
    }
  }

  return false;

} // Function slackemon_get_battle_current_pokemon.

/**
 * Advises whether or not a battle is over. This only means that all of one user's Pokemon have fainted; not that
 * the battle complete routines have been invoked yet (if required - which they usually are).
 *
 * Designed to be used while building battle attachments, but can be called any time.
 *
 * @param obj|arr $battle_data_or_attachment_args Either the battle data object, or an array of arguments as provided
 *                                                by slackemon_get_battle_attachments().
 * @param str     $user_id                        The user ID that this function was called on behalf on. Required only
 *                                                if battle_data is supplied as the first argument.
 */
function slackemon_is_battle_over( $battle_data_or_attachment_args, $user_id = null ) {

  // Support both battle_data or attachment_args.
  if ( isset( $battle_data_or_attachment_args['battle_data'] ) ) {
    extract( $battle_data_or_attachment_args );
  } else {
    $battle_data                = $battle_data_or_attachment_args;
    $opponent_id                = slackemon_get_battle_opponent_id( $battle_data, $user_id );
    $opponent_pokemon           = slackemon_get_battle_current_pokemon( $battle_data, $opponent_id );
    $opponent_remaining_pokemon = slackemon_get_user_remaining_battle_pokemon( $battle_data, $opponent_id );
  }

  // Has the calling user won?

  $is_opponent_turn = $battle_data->turn === $opponent_id;
  $has_user_won     = $is_opponent_turn && ! $opponent_pokemon->hp && ! $opponent_remaining_pokemon;

  if ( $has_user_won ) {
    return true;
  }

  // Has the opponent won?

  // If only battle_data was supplied, there's some more data we need to get.
  if ( ! isset( $battle_data_or_attachment_args['battle_data'] ) ) {
    $user_pokemon           = slackemon_get_battle_current_pokemon( $battle_data, $user_id );
    $user_remaining_pokemon = slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id );
  }

  $is_user_turn     = $battle_data->turn === $user_id;
  $has_opponent_won = $is_user_turn && ! $user_pokemon->hp && ! $user_remaining_pokemon;

  if ( $has_opponent_won ) {
    return true;
  }

  return false;

} // Function slackemon_is_battle_over.

/**
 * Determines whether a wild Pokemon should flee from battle. Called before every move and at an attempted catch
 * if the Pokemon has not fainted.
 *
 * Compared to a direct catch, the chance of fleeing is generally cut by two thirds (but this depends on the
 * exact value of SLACKEMON_BATTLE_FLEE_MULTIPLIER), *plus* a further reduced chance based on the Pokemon's
 * HP - the lower the HP, the less chance of fleeing.
 *
 * @param obj $pokemon The object representing the wild Pokemon.
 * @return bool Whether or not the wild Pokemon should flee.
 */
function slackemon_should_wild_battle_pokemon_flee( $pokemon ) {

  $hp_percentage  = $pokemon->hp / $pokemon->stats->hp;
  $hp_percentage  = max( $hp_percentage, 0.001 ); // Prevent us from ending up with 0, which would error next.
  $random_int_max = round( SLACKEMON_BASE_FLEE_CHANCE * SLACKEMON_BATTLE_FLEE_MULTIPLIER / $hp_percentage );

  $should_flee    = random_int( 1, $random_int_max ) === 1;

  return $should_flee;

} // Function slackemon_should_wild_battle_pokemon_flee.

/**
 * Returns the battle challenge types that Slackemon supports, along with the attributes of each one.
 *
 * @return obj
 */
function slackemon_get_battle_challenge_types() {

  // Get local challenge types config.
  $challenge_types = json_decode( file_get_contents( __DIR__ . '/../etc/challenge-types.json' ) );

  return $challenge_types;

} // Function slackemon_get_battle_challenge_types.

/** Returns data on the provided battle challenge type. */
function slackemon_get_battle_challenge_data( $challenge_type ) {

  if ( is_string( $challenge_type ) ) {
    $challenge_type = [ $challenge_type ];
  }

  return slackemon_get_battle_challenge_types()->{ $challenge_type[0] };

} // Function slackemon_get_battle_challenge_data.

function slackemon_is_friendly_battle( $battle_data ) {

  $xp_modifier = (int) slackemon_get_battle_challenge_data( $battle_data->challenge_type[0] )->xp_modifier;

  return 0 === $xp_modifier;

} // Function slackemon_is_friendly_battle.

/**
 * Generates a hash to identify a battle, based on the start time and the two users in the battle. The order of the
 * users does not matter. Usually only used as the unique filename of an in-progress or recently completed battle
 * (as well as the invite/challenge filename before acceptance, if applicable).
 *
 * @param int $ts       The timestamp representing the start of the battle (or invite send, if applicable).
 * @param str $user_id1 One user involved in the battle.
 * @param str $user_id2 The other user involved in the battle.
 * @return str The computed hash.
 */
function slackemon_generate_battle_hash( $ts, $user_id1, $user_id2 ) {

  $battle_hash_parts = [
    $ts,
    $user_id1,
    $user_id2,
  ];

  // Sort the parts so that the order they are provided in never matters.
  asort( $battle_hash_parts );

  $battle_hash = md5( join( '', $battle_hash_parts ) );

  return $battle_hash;

} // Function slackemon_generate_battle_hash.

function slackemon_get_battle_data( $battle_hash, $allow_completed_battle = false, $for_writing = false ) {
  global $data_folder, $_cached_slackemon_battle_data;

  if ( ! $for_writing && isset( $_cached_slackemon_battle_data[ $battle_hash ] ) ) {
    return $_cached_slackemon_battle_data[ $battle_hash ];
  }

  if (
    $allow_completed_battle &&
    slackemon_file_exists( $data_folder . '/battles_complete/' . $battle_hash, 'store' )
  ) {
    $battle_filename = $data_folder . '/battles_complete/' . $battle_hash;
  } else if ( slackemon_file_exists( $data_folder . '/battles_active/' . $battle_hash, 'store' ) ) {
    $battle_filename = $data_folder . '/battles_active/' . $battle_hash;
  } else {
    return false;
  }

  $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store', $for_writing ) );
  $_cached_slackemon_battle_data[ $battle_hash ] = $battle_data;

  return $battle_data;

} // Function slackemon_get_battle_data.

function slackemon_save_battle_data(
  $battle_data, $battle_hash, $battle_stage = 'battle', $relinquish_lock = false, $warn_if_not_locked = true
) {
  global $data_folder, $_cached_slackemon_battle_data;

  switch ( $battle_stage ) {
    case 'battle':   $battle_folder = 'battles_active';   break;
    case 'complete': $battle_folder = 'battles_complete'; break;
    case 'invite':   $battle_folder = 'battles_invites';  break;
  }

  $battle_filename = $data_folder . '/' . $battle_folder . '/' . $battle_hash;

  // Update the in-memory cache.
  $_cached_slackemon_battle_data[ $battle_hash ] = $battle_data;

  $json_options = 'development' === APP_ENV ? JSON_PRETTY_PRINT : 0;

  $return = slackemon_file_put_contents(
    $battle_filename, json_encode( $battle_data, $json_options ), 'store', $warn_if_not_locked
  );

  if ( $relinquish_lock ) {
    slackemon_unlock_file( $battle_filename );
  }

  return $return;

} // Function slackemon_save_battle_data.

/**
 * Records a Pokemon as 'seen' in a user's Pokedex if they see it in battle for the very first time.
 * Will not increment the seen counter if it already exists. This matches Pokemon Go behaviour.
 *
 * @param str     $player_id  The player's user ID (eg. U12345678).
 * @param int|str $pokedex_id The national Pokedex ID of the Pokemon seen in battle. Ideally this should be an integer,
 *                            but we can't rely on that being the case, therefore strict comparison should not be used
 *                            on this value.
 */
function slackemon_maybe_record_battle_seen_pokemon( $player_id, $pokedex_id ) {

  $player_data = slackemon_get_player_data( $player_id );

  // Bow out if the user already has a Pokedex entry for this Pokemon.
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $pokedex_id == $pokedex_entry->id ) {
      return;
    }
  }

  // Get player data again, for writing this time.
  $player_data = slackemon_get_player_data( $player_id, true );

  // First seen - time to create a new entry!
  $player_data->pokedex[] = [
    'id'     => (int) $pokedex_id,
    'seen'   => 1, // Seen in battle - this will stay at 1 until they see again in a spawn or evolve etc.
    'caught' => 0,
  ];

  return slackemon_save_player_data( $player_data, $player_id, true );

} // Function slackemon_maybe_record_battle_seen_pokemon.

function slackemon_get_all_active_battles() {
  global $data_folder;

  $battles = slackemon_get_files_by_prefix( $data_folder . '/battles_active/', 'store' );
  $active_battles = [];

  foreach ( $battles as $battle_filename ) {
    $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store' ) );
    $active_battles[] = $battle_data;
  }

  return $active_battles;

} // Function slackemon_get_all_active_battles.

function slackemon_get_user_active_battles( $user_id = USER_ID ) {
  global $data_folder;

  $battles = slackemon_get_files_by_prefix( $data_folder . '/battles_active/', 'store' );
  $user_battles = [];

  foreach ( $battles as $battle_filename ) {
    $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store' ) );
    if ( isset( $battle_data->users->{ $user_id } ) ) {
      $user_battles[] = $battle_data;
    }
  }

  return $user_battles;

} // Function slackemon_get_user_active_battles.

function slackemon_get_user_complete_battles( $user_id = USER_ID ) {
  global $data_folder;

  $battles = slackemon_get_files_by_prefix( $data_folder . '/battles_complete/', 'store' );
  $user_battles = [];

  foreach ( $battles as $battle_filename ) {
    $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store' ) );
    if ( isset( $battle_data->users->{ $user_id } ) ) {
      $user_battles[] = $battle_data;
    }
  }

  return $user_battles;

} // Function slackemon_get_user_complete_battles.

/**
 * Moves the battle file for completion. Checks that the file exists first, as this function will be run twice
 * in every battle - whichever user triggers it first is the one who will actually move the file.
 *
 * @param str $battle_hash The hash used to identify the battle filename.
 */
function slackemon_move_completed_battle_file( $battle_hash ) {
  global $data_folder;
  
  if ( slackemon_file_exists( $data_folder . '/battles_active/' . $battle_hash, 'store' ) ) {

    return slackemon_rename(
      $data_folder . '/battles_active/'   . $battle_hash,
      $data_folder . '/battles_complete/' . $battle_hash,
      'store'
    );

  }

  // Return false as we didn't move anything.
  return false;

} // Function slackemon_move_completed_battle_file.

function slackemon_get_battle_action_denied_message( $action, $user_id ) {

  $message = $action->original_message->attachments[ $action->attachment_id - 1 ];

  $message->footer = (
    ( slackemon_is_desktop( $user_id ) ? ':exclamation:' : '' ) .
    'Oops! You can\'t do this during a battle.'
  );

  return slackemon_update_triggering_attachment( $message, $action, false );

} // Function slackemon_get_battle_action_denied_message.

function slackemon_battle_debug( $message ) {

  if ( ! SLACKEMON_BATTLE_DEBUG ) {
    return;
  }

  slackemon_error_log( $message );

}

// The end!
