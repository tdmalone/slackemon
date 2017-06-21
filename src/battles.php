<?php
/**
 * Battle specific functions for Slackemon.
 *
 * @package Slackemon
 */

// Cronned function (through /slackemon battle-updates) which should run every minute
function slackemon_do_battle_updates() {

  $active_players = slackemon_get_player_ids([ 'active_only' => true ]);
  
  if ( ! $active_players ) {
    return;
  }

  $now = time();
  $one_minute_ago          = $now - MINUTE_IN_SECONDS * 1;
  $two_minutes_ago         = $now - MINUTE_IN_SECONDS * 2;
  $twenty_minutes_ago      = $now - MINUTE_IN_SECONDS * 20;
  $twenty_one_minutes_ago  = $now - MINUTE_IN_SECONDS * 21;
  $twenty_five_minutes_ago = $now - MINUTE_IN_SECONDS * 25;

  // First up, use this opportunity to check if any Pokemon need automatic reviving after battle
  // Pokemon will restore HP at a rate of 5% per minute (by default), after being fainted for 10 minutes
  // (also, because active_only is true, Pokemon will only restore if a user is online and NOT in another battle!)
  foreach ( $active_players as $player_id ) {

    $player_data = slackemon_get_player_data( $player_id );
    $player_pokemon = $player_data->pokemon;
    $changes_made = false;

    foreach ( $player_pokemon as $_pokemon ) {

      // TODO: This currently excludes fainted Pokemon from *battles that started less than 20 minutes ago*, rather
      // than battles that *ended* 10 minutes ago. We need to store battle *end* time with the Pokemon data itself.
      if ( 0 == $_pokemon->hp && $_pokemon->battles->last_participated > $twenty_minutes_ago ) {
        continue;
      }

      if ( $_pokemon->hp < $_pokemon->stats->hp ) {
        $_pokemon->hp += SLACKEMON_HP_RESTORE_RATE * $_pokemon->stats->hp;
        $_pokemon->hp = min( ceil( $_pokemon->hp ), $_pokemon->stats->hp );
        $changes_made = true;
      }

      foreach ( $_pokemon->moves as $_move ) {
        if ( $_move->{'pp-current'} < $_move->pp ) {
          $_move->{'pp-current'} += SLACKEMON_HP_RESTORE_RATE * $_move->pp;
          $_move->{'pp-current'} = min( ceil( $_move->{'pp-current'} ), $_move->pp );
          $changes_made = true;
        }
      }

    }

    if ( $changes_made ) {
      slackemon_save_player_data( $player_data, $player_id );
    }

  } // Foreach player 

  // Check whether there's any new turns to alert players of (within the last 1-2 mins)...
  // ...or turns expiring soon (they expire at 25 minutes, due to Slack 30 minute action response_url timeout)...
  // ...or turns which *have* expired.

  $active_battles = slackemon_get_all_active_battles();

  foreach ( $active_battles as $_battle ) {

    $user_id = $_battle->turn;
    $opponent_id = slackemon_get_battle_opponent_id( $_battle->hash, $user_id );

    // For non P2P battles, we expire a lot sooner (assuming the flee limit is a lot sooner, that is!)
    // We also just do it directly to the user
    if ( 'p2p' !== $_battle->type ) {

      if (
        'U' === substr( $user_id, 0, 1 ) &&
        $_battle->last_move_ts < max( time() - SLACKEMON_FLEE_TIME_LIMIT, $twenty_five_minutes_ago )
      ) {

        send2slack(
          slackemon_get_catch_message( $opponent_id, null, true, 'flee-late', $user_id ),
          $_battle->users->{ $user_id }->response_url
        );
        
      }

      // Don't run the rest of this loop iteration, it's for P2P battles only
      continue;

    } // If not p2p battle

    $opponent_name = slackemon_get_slack_user_first_name( $opponent_id );

    // New turns
    if ( $_battle->last_move_ts < $one_minute_ago && $_battle->last_move_ts > $two_minutes_ago ) {
      
      send2slack([
        'text' => (
          'It\'s your turn in your battle against ' . $opponent_name . '. :simple_smile:'
        ),
        'channel' => $user_id,
      ]);
    }

    // Expiring turns
    if ( $_battle->last_move_ts < $twenty_minutes_ago && $_battle->last_move_ts > $twenty_one_minutes_ago ) {
      send2slack([
        'text' => (
          ':warning: *Heads up - ' . $opponent_name . ' has been waiting for your move for 20 minutes!*' . "\n" .
          'You need to make a move in the *next 5 minutes* to avoid forfeiting the battle. :timer_clock:'
        ),
        'channel' => $user_id,
      ]);
    }

    // Expired turns - battle ends
    if ( $_battle->last_move_ts < $twenty_five_minutes_ago ) {
      slackemon_end_battle( $_battle->hash, 'timeout', $user_id );
    }

  } // Foreach active_battles
} // Function slackemon_do_battle_updates

function slackemon_get_top_pokemon_list( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  $top_pokemon_sorted = $player_data->pokemon;
  usort( $top_pokemon_sorted, function( $pokemon1, $pokemon2 ) {
    return $pokemon1->cp < $pokemon2->cp ? 1 : -1;
  });

  $top_pokemon = [];
  foreach ( $top_pokemon_sorted as $pokemon ) {
    $top_pokemon[] = (
      ':' . $pokemon->name . ': ' .
      slackemon_readable( $pokemon->name ) . ' ' .
      $pokemon->cp . ' CP'
    );
    if ( count( $top_pokemon ) >= 3 ) {
      break;
    }
  }

  return $top_pokemon;

} // Function slackemon_get_top_pokemon_list

function slackemon_send_battle_invite( $invitee_id, $action, $inviter_id = USER_ID ) {

  $inviter_player_data = slackemon_get_player_data( $inviter_id );
  $inviter_user_data   = slackemon_get_slack_user( $inviter_id );
  $is_desktop          = 'desktop' === slackemon_get_player_menu_mode( $inviter_id );

  $invite_ts = time();

  $battle_hash = slackemon_get_battle_hash( $invite_ts, $inviter_id, $invitee_id );
  $invite_data = [
    'ts' => $invite_ts,
    'hash' => $battle_hash,
    'inviter_id' => $inviter_id,
    'invitee_id' => $invitee_id,
  ];

  // Check that either user doesn't have any outstanding invites as either invitee or inviter
  $inviter_invites = slackemon_get_user_outstanding_invites( $inviter_id );
  $invitee_invites = slackemon_get_user_outstanding_invites( $invitee_id );

  if ( count( $inviter_invites ) ) {

    $cancel_verb = $inviter_invites[0]->inviter_id === $inviter_id ? 'cancel' : 'decline';

    $inviter_message = slackemon_update_triggering_attachment(
      ':open_mouth: *Oops! You already have an outstanding battle invite.*' . "\n" .
      'Please ' . $cancel_verb . ' your current invite before sending a new one. :smile:',
      $action,
      false // Don't send now, we'll return below to be sent with our default action response
    );

  } else if ( count( $invitee_invites ) ) {

    $inviter_message = slackemon_update_triggering_attachment(
      ':open_mouth: *Oops! That user already has an outstanding battle invite.*' . "\n" .
      'Please try battling with this user later. :smile:',
      $action,
      false // Don't send now, we'll return below to be sent with our default action response
    );

  } else {

    $attachment = slackemon_get_player_battle_attachment( $inviter_id );
    $attachment['actions'] = [
      [
        'name' => 'battles/accept',
        'text' => 'Accept',
        'type' => 'button',
        'value' => $battle_hash,
        'style' => 'primary',
      ], [
        'name' => 'battles/decline',
        'text' => 'Decline',
        'type' => 'button',
        'value' => $battle_hash,
        'style' => 'danger',
      ]
    ];

    $invitee_message = [
      'text' => (
        ':stuck_out_tongue_closed_eyes: *You have been challenged to a Slackémon battle ' .
        'by ' . slackemon_get_slack_user_first_name( $inviter_id ) . '!*'
      ),
      'attachments' => [
        $attachment,
        (
          slackemon_is_battle_team_full( $invitee_id ) ?
          [] :
          [
            'pretext' => (
              '_You have not yet selected your full battle team of ' . SLACKEMON_BATTLE_TEAM_SIZE . ' Pokémon. You ' .
              'can do so now, before accepting this invitation, by running `/slackemon` and clicking through to ' .
              'your Pokémon list. If you don\'t, you\'ll be battling with a random selection of your Pokémon instead!_'
            ),
            'mrkdwn_in' => [ 'pretext' ],
          ]
        ),
        slackemon_back_to_menu_attachment(),
      ],
      'channel' => $invitee_id,
    ];

    array_unshift(
      $invitee_message['attachments'],
      slackemon_get_battle_team_status_attachment( $invitee_id, 'invitee' )
    );

    slackemon_save_battle_data( $invite_data, $battle_hash, 'invite' );

    if ( slackemon_post2slack( $invitee_message ) ) {
      $invitee_name     = $is_desktop ? slackemon_get_slack_user_full_name( $invitee_id ) : slackemon_get_slack_user_first_name( $invitee_id );
      $inviter_message  = slackemon_update_triggering_attachment(
        ':white_check_mark: An invitation has been sent to *' . $invitee_name . '*.' . "\n" .
        'I\'ll let you know when they respond!',
        $action,
        false
      );
    } else {
      $inviter_message = [
        'text' => ':no_entry: *Oops!* A problem occurred. Please try your last action again.',
        'replace_original' => false,
      ];
    }

  } // If outstanding invites / else

  return $inviter_message;

} // Function slackemon_send_battle_invite

function slackemon_cancel_battle_invite( $battle_hash, $action, $mode = 'inviter' ) {

  $invite_data = slackemon_get_invite_data( $battle_hash, true );

  if ( $invite_data ) {

    switch ( $mode ) {

      case 'inviter':

        // Respond to the invitee first
        slackemon_post2slack([
          'text' => (
            ':disappointed: *Oh! Sorry, ' . slackemon_get_slack_user_first_name( $invite_data->inviter_id ) . ' has cancelled their ' .
            'battle challenge.*' . "\n" .
            'Maybe next time!'
          ),
          'attachments' => [ slackemon_back_to_menu_attachment() ],
          'channel' => $invite_data->invitee_id,
        ]);

        // Inviter response
        $message = slackemon_update_triggering_attachment(
          ':x:  *Ok, your battle invite has been cancelled.*',
          $action,
          false
        );

      break;

      case 'invitee':

        // Respond to the inviter first
        slackemon_post2slack([
          'text' => (
            ':disappointed: *Sorry, ' . slackemon_get_slack_user_first_name( $invite_data->invitee_id ) . ' has declined your ' .
            'battle challenge.*' . "\n" .
            'Maybe next time!'
          ),
          'attachments' => [ slackemon_back_to_menu_attachment() ],
          'channel' => $invite_data->inviter_id,
        ]);

        // Invitee response
        $message = slackemon_update_triggering_attachment(
          ':x: *You have declined ' . slackemon_get_slack_user_first_name( $invite_data->inviter_id ) . '\'s challenge.*' . "\n" .
          'Not ready to battle right now? Send your own challenge later from the Battle screen!',
          $action,
          false
        );

      break;

    } // Switch mode

  } else {

    $message = slackemon_update_triggering_attachment(
      ':no_entry: *Oops!* That battle invite doesn\'t seem to exist anymore.' . "\n" .
      'It may have already been accepted, declined, or cancelled.',
      $action,
      false
    );

  } // If invite_data / else

  return $message;

} // Function slackemon_cancel_battle_invite

function slackemon_get_battle_team_status_attachment( $user_id = USER_ID, $mode = 'inviter' ) {

  $battle_team = slackemon_get_battle_team( $user_id );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $faint_count = 0;
  $low_hp_count = 0;
  $not_max_hp_count = 0;

  foreach ( $battle_team as $pokemon ) {

    if ( 0 == $pokemon->hp ) {
      $faint_count++;
    } else if ( $pokemon->hp < $pokemon->stats->hp * .1 ) {
      $low_hp_count++;
    } else if ( $pokemon->hp < $pokemon->stats->hp ) {
      $not_max_hp_count++;
    }

  }

  if ( 'inviter' === $mode && ! slackemon_is_battle_team_full( $user_id ) ) {
    $pretext = (
      ':medal: Winning Slackémon battles will level-up your Pokémon - ' .
      'making them stronger _and_ getting you closer to evolving them.' . "\n" .
      ':arrow_right: *To send a battle challenge, you first need to choose your Battle Team ' .
      'of ' . SLACKEMON_BATTLE_TEAM_SIZE . '!*'
    );
  } else if ( $faint_count === SLACKEMON_BATTLE_TEAM_SIZE ) {
    $pretext = (
      ':exclamation: *Your battle team has fainted!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your battle team before accepting this challenge - otherwise your team will be chosen at random!' :
        'To challenge someone to a battle, you\'ll need to change up your battle team, or wait for your ' .
        'Pokémon to regain their strength. :facepunch:'
      )
    );
  } else if ( $faint_count ) {
    $pretext = (
      ':exclamation: *' . $faint_count . ' of the Pokémon on your team ' .
      ( 1 === $faint_count ? 'has' : 'have' ) . ' fainted!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge - otherwise your team will be chosen at' .
        'random.' :
        'You should change up your team before your next battle - if not, fainted Pokémon will be replaced ' .
        'randomly from your collection.'
      )
    );
  } else if ( $low_hp_count ) {
    $pretext = (
      ':exclamation: *' . $low_hp_count . ' of the Pokémon on your team ' .
      ( 1 === $low_hp_count ? 'does not have' : 'do not have' ) . ' much health left!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge.' :
        'You should change up your team before your next battle - or wait for your Pokémon to regain their strength.'
      )
    );
  } else if ( $not_max_hp_count ) {
    $pretext = (
      ':warning: *' . $not_max_hp_count . ' of ' .
      ( $is_desktop ? 'the Pokémon on your team' : 'your Pokémon' ) . ' ' .
      ( 1 === $not_max_hp_count ? 'is' : 'are' ) . ' not at full health.*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge.' :
        'You should change up your team before your next battle - or wait for your Pokémon to regain their strength.'
      )
    );
  } else {
    $pretext = ':white_check_mark: Your battle team is ready to go!';
  }

  $attachment = [
    'pretext' => $pretext,
    'mrkdwn_in' => [ 'pretext', 'text', 'fields' ],
  ];

  return $attachment;

} // Function slackemon_get_battle_team_status_attachment

function slackemon_start_battle( $battle_hash, $action ) {

  $invite_data = slackemon_get_invite_data( $battle_hash, true );

  if ( ! $invite_data ) {

    $message = slackemon_update_triggering_attachment(
      ':no_entry: *Oops!* That battle invite doesn\'t seem to exist anymore.' . "\n" .
      'It may have already been accepted, declined, or cancelled.',
      $action
    );

    return;

  }

  $battle_ts  = $invite_data->ts;
  $inviter_id = $invite_data->inviter_id;
  $invitee_id = $invite_data->invitee_id;

  $inviter_battle_team = slackemon_get_battle_team( $inviter_id, true );
  $invitee_battle_team = slackemon_get_battle_team( $invitee_id, true );

  if ( false === $inviter_battle_team || false === $invitee_battle_team ) {

    // Cancel battle - we don't have enough non-fainted Pokemon on at least one of the teams!

    $invitee_fail_to_self = ':open_mouth: *Oops!* You don\'t seem to have enough revived Pokémon to accept this invite!' . "\n" . ':skull: You can see your fainted Pokémon on your Pokémon page from the Main Menu. You may have to wait for them to regain their strength, or catch some more Pokémon. :pokeball:';
    $invitee_fail_to_other = ':slightly_frowning_face: *Oh no!* ' . slackemon_get_slack_user_first_name( $invitee_id ) . ' doesn\'t have enough revived Pokémon to accept your battle invite at the moment.' . "\n" . 'I\'ve sent them a message too. Perhaps try inviting them again later! :slightly_smiling_face:';
    $inviter_fail_to_self = ':open_mouth: *Oops!* You don\'t seem to have enough revived Pokémon to participate in the battle you invited ' . slackemon_get_slack_user_first_name( $invitee_id ) . ' to!' . "\n" . ':skull: You can see your fainted Pokémon on your Pokémon page from the Main Menu. You may have to wait for them to regain their strength, or catch some more Pokémon. :pokeball:';
    $inviter_fail_to_other = ':slightly_frowning_face: *Oh no!* ' . slackemon_get_slack_user_first_name( $inviter_id ) . ' doesn\'t seem to have enough revived Pokémon to participate in this battle!' . "\n" . 'I\'ve sent them a message too. Perhaps they\'ll invite you again soon! :slightly_smiling_face:';

    if ( false === $inviter_battle_team && false === $invitee_battle_team ) {
      $inviter_message = $inviter_fail_to_self;
      $invitee_message = $invitee_fail_to_self;
    } elseif ( false === $inviter_battle_team ) {
      $inviter_message = $inviter_fail_to_self;
      $invitee_message = $inviter_fail_to_other;
    } elseif ( false === $invitee_battle_team ) {
      $inviter_message = $invitee_fail_to_other;
      $invitee_message = $invitee_fail_to_self;
    }

    // Use the built-in action response URL detector to send directly to the invitee (who invoked this action)
    send2slack([
      'text' => $invitee_message,
      'attachments' => [ slackemon_back_to_menu_attachment() ],
      'replace_original' => true,
    ]);

    // Create a new Slackemon message for the inviter
    slackemon_post2slack([
      'text' => $inviter_message,
      'attachments' => [ slackemon_back_to_menu_attachment() ],
      'channel' => $inviter_id,
    ]);

    return false;

  } // If not enough revived Pokemon for either team

  $battle_data = [
    'ts' => $battle_ts,
    'hash' => $battle_hash,
    'type' => 'p2p',
    'users' => [
      $inviter_id => [
        'team' => $inviter_battle_team,
        'status' => [ 'current' => false ],
        'response_url' => '', // Not available until the inviter is given an action to perform
      ],
      $invitee_id => [
        'team' => $invitee_battle_team,
        'status' => [ 'current' => false ],
        'response_url' => RESPONSE_URL,
      ],
    ],
    'last_move_ts' => time(),
    'turn' => $invitee_id,
  ];

  // Start with a random Pokemon from the team, for now (until we code in choosing at the start)
  $inviter_random_key = array_rand( $battle_data['users'][ $inviter_id ]['team'] );
  $invitee_random_key = array_rand( $battle_data['users'][ $invitee_id ]['team'] );
  $inviter_pokemon = $battle_data['users'][ $inviter_id ]['team'][ $inviter_random_key ];
  $invitee_pokemon = $battle_data['users'][ $invitee_id ]['team'][ $invitee_random_key ];

  $battle_data['users'][ $inviter_id ]['status']['current'] = $inviter_pokemon->ts;
  $battle_data['users'][ $invitee_id ]['status']['current'] = $invitee_pokemon->ts;

  // Maybe record these Pokemon as 'seen' in the user's Pokedex?
  slackemon_maybe_record_battle_seen_pokemon( $inviter_id, $invitee_pokemon->pokedex );
  slackemon_maybe_record_battle_seen_pokemon( $invitee_id, $inviter_pokemon->pokedex );

  // Set players in battle
  slackemon_set_player_in_battle( $inviter_id );
  slackemon_set_player_in_battle( $invitee_id );

  // For consistency, turn the whole thing into an object rather than an array
  $battle_data = json_decode( json_encode( $battle_data ) );

  // Save battle data
  slackemon_save_battle_data( $battle_data, $battle_hash );

  // Respond to the invitee
  $inviter_first_name = slackemon_get_slack_user_first_name( $inviter_id );
  if ( send2slack([
    'text' => ':grin: *You have accepted ' . $inviter_first_name . '\'s challenge!*',
    'attachments' => slackemon_get_battle_attachments( $battle_hash, $invitee_id, 'start' ),
    'replace_original' => true,
  ]) ) {

    // Alert the inviter
    slackemon_post2slack([
      'text' => (
        ':laughing: *' . slackemon_get_slack_user_first_name( $invitee_id ) . ' has accepted your battle challenge!*' . "\n" .
        'It\'s their move first - so hang tight just a sec!'
      ),
      'channel' => $inviter_id,
    ]);

    return true;

  } else {

    return false;

  }

} // Function slackemon_start_battle

// Usually means user has surrendered ($reason = 'surrender') but can also be used for timeouts etc.
// Running through this function will result in *only the opponent getting battle experience*
function slackemon_end_battle( $battle_hash, $reason, $user_id = USER_ID ) {

  $loser_id = $user_id;
  $winner_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  $battle_data = slackemon_get_battle_data( $battle_hash );

  switch ( $reason ) {

    case 'timeout':

      // This option is only supported for p2p battles

      if ( 'p2p' === $battle_data->type ) {

        $winner_name = slackemon_get_slack_user_first_name( $winner_id );

        $loser_message = (
          ':exclamation: *Unfortunately, your battle with ' . $winner_name . ' has expired.*' . "\n" .
          'This is because you did not make a move within 25 minutes. ' . $winner_name . ' will receive full ' .
          'experience points for this battle. Perhaps try again later when you have some more time up your ' .
          'sleeve. :slightly_smiling_face:'
        );

        send2slack([
          'text' => $loser_message,
          'channel' => $loser_id,
        ]);

        slackemon_post2slack([
          'text' => (
            ':face_with_rolling_eyes: *Unfortunately, your battle with ' . slackemon_get_slack_user_first_name( $loser_id ) . ' ' .
            'has expired.*' . "\n" .
            slackemon_get_slack_user_first_name( $loser_id ) . ' did not make a move within 25 minutes. You still get full ' .
            'experience points for your part in the battle though - click the _Complete_ button below to receive them!'
          ),
          'attachments' => [
            [
              'fallback' => 'Complete Battle',
              'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
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

      } // If p2p battle

    break; // Case timeout

    case 'surrender':

      switch ( $battle_data->type ) {

        case 'p2p':

          send2slack([
            'text' => 'You have surrended the battle!', // TODO: Expand on this, lol, when surrenders become possible
          ]);

        break; // Case p2p

        case 'wild':

          $user_pokemon = $battle_data->users->{ $user_id }->team[0];
          
          $user_pokemon_message = ':' . $user_pokemon->name . ': ' . slackemon_readable( $user_pokemon->name ) . ' ';
          $user_pokemon_message .= (
            $user_pokemon->battles->last_participated == $battle_data->ts ?
            'has ' . floor( $user_pokemon->hp / $user_pokemon->stats->hp * 100 ) . '% HP remaining' :
            'didn\'t participate in this battle'
          );

          send2slack([
            'text' => (
              ':white_check_mark: *Ok, you got away safely!* :relieved:' . "\n\n" .
              $user_pokemon_message
            ),
            'attachments' => [
              slackemon_back_to_menu_attachment(),
            ],
          ]);

        break; // Case wild

      } // Switch battle type

    break; // Case surrender

  } // Switch reason

  // Complete the battle for the loser now
  // The winner's completion will happen when they follow their battle complete action
  slackemon_complete_battle( 'lost', $battle_hash, $loser_id, false, false );

} // Function slackemon_end_battle

// Tally up and apply the battle stats for the user
function slackemon_complete_battle( $battle_result, $battle_hash, $user_id = USER_ID, $award_xp_to_user = true, $send_response_to_user = true ) {

  $player_data = slackemon_get_player_data( $user_id );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode( $user_id );
  $battle_data = slackemon_get_battle_data( $battle_hash, true ); // 'true' for include getting from a 'complete' file, in case a user has already run this function and the file has been moved as a result

  if ( ! $battle_data ) {
    return slackemon_battle_has_ended_message();
  }

  $pokemon_experience_message = '';

  // Did the user win or lose?
  switch ( $battle_result ) {

    case 'won':

      // What's the experience & effort points gained from the opponent's fainted Pokemon?

      $total_experience_gained = 0;
      $effort_points_gained = [
        'attack'          => 0,
        'defense'         => 0,
        'hp'              => 0,
        'special-attack'  => 0,
        'special-defense' => 0,
        'speed'           => 0
      ];
      $experience_gained_per_pokemon = [];
      $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

      foreach ( $battle_data->users->{ $opponent_id }->team as $_pokemon ) {

        // Skip if this opponent Pokemon didn't faint
        if ( $_pokemon->hp ) {
          continue;
        }

        // Grab the fainted Pokemon's API data
        $_pokemon_data = slackemon_get_pokemon_data( $_pokemon->pokedex );

        // Calculate the experience yielded by this Pokemon
        $experience = (int) slackemon_calculate_battle_experience( $_pokemon );
        $total_experience_gained += $experience;

        $experience_gained_per_pokemon[] = [
          'name'    => $_pokemon->name,
          'pokedex' => $_pokemon->pokedex,
          'level'   => $_pokemon->level,
          'experience_gained' => (int) $experience,
        ];

        // Calculate the effort point gain
        foreach ( $_pokemon_data->stats as $_stat ) {
          if ( ! $_stat->effort ) { continue; }
          $effort_points_gained[ $_stat->stat->name ] += (int) $_stat->effort; break;
        }

      } // Foreach opponent pokemon

      $battle_pokemon_by_ts = [];

      // Apply experience & any other relevant changes to eligible Pokemon
      foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {

        // Skip recalculation if this Pokemon fainted; just add it to the interim collection to save HP/participation
        if ( ! $_pokemon->hp ) {
          $_pokemon->happiness -= 1; // Happiness reduction of 1 due to fainting
          $_pokemon->happiness = max( 0, $_pokemon->happiness ); // Ensure we don't go below 0
          $battle_pokemon_by_ts[ $_pokemon->ts ] = $_pokemon;
          $pokemon_experience_message .= (
            ':' . $_pokemon->name . ': ' . slackemon_readable( $_pokemon->name, false ) . ' ' .
            'fainted :frowning:' . "\n"
          );
          continue;
        }

        // Skip everything if this Pokemon didn't get to participate at all
        if ( $_pokemon->battles->last_participated !== $battle_data->ts ) {
          $pokemon_experience_message .= (
            ':' . $_pokemon->name . ': ' . slackemon_readable( $_pokemon->name, false ) . ' ' .
            'didn\'t participate in this battle' . "\n"
          );
          continue;
        }

        // Experience
        $_pokemon->xp += $total_experience_gained;

        // EVs

        // First, turn it into an object if it was an array - how it is first created at spawn time
        if ( is_array( $_pokemon->evs ) ) {
          $_pokemon->evs = json_decode( json_encode( [
            'attack'  => 0,
            'defense' => 0,
            'hp'      => 0,
            'special-attack'  => 0,
            'special-defense' => 0,
            'speed' => 0,
          ]));
        }

        // Apply EVs from all defeated Pokemon, and ensure it stays below the maximum
        $current_evs = slackemon_get_combined_evs( $_pokemon->evs );
        foreach ( $effort_points_gained as $key => $value ) {

          // Max 510 across all EV stats - reduce the value we're applying if it would otherwise take us over the limit
          if ( $current_evs + $value > 510 ) {
            $value = $current_evs + $value - 510;
          }

          // Apply the value
          $_pokemon->evs->{ $key } += $value;

          // Ensure a max 252 per EV stat
          $_pokemon->evs->{ $key } = min( 252, $_pokemon->evs->{ $key } );

        }

        // Recalculate level
        $old_level = $_pokemon->level; // Store for happiness calculation shortly
        $growth_rate_data = slackemon_get_pokemon_growth_rate_data( $_pokemon->pokedex );
        foreach ( $growth_rate_data->levels as $_level ) {

          // Levels in the API go from 100 down to 1
          // If our experience is greater than the experience required for this level, *this* is our level!
          if ( $_pokemon->xp >= $_level->experience ) {

            // Work out a partial level if we're well on our way to the next
            if ( isset( $next_level_experience ) && $_pokemon->xp > $_level->experience ) {
              $exp_required_to_next_level = $next_level_experience - $_level->experience;
              $exp_gained_towards_next_level = $_pokemon->xp - $_level->experience;
              $_pokemon->level = $_level->level + ( $exp_gained_towards_next_level / $exp_required_to_next_level );
            } else {
              $_pokemon->level = $_level->level;
            }

            // Limit levels to 1 decimal point
            $_pokemon->level = round( $_pokemon->level, 1, PHP_ROUND_HALF_DOWN );

            break;

          }

          // Pass experience required down to previous level as the loop continues
          $next_level_experience = $_level->experience;
        }

        // Recalculate happiness as a result of levelling up
        // +5 per additional level if happiness 0-99; +3 if happiness 100-199; or +2 if 200-255
        // Reference: http://bulbapedia.bulbagarden.net/wiki/Friendship#In_Generation_I
        for ( $i = floor( $old_level ); $i < floor( $_pokemon->level ); $i++ ) {
          if ( $_pokemon->happiness < 100 ) {
            $_pokemon->happiness += 5;
          } else if ( $_pokemon->happiness < 200 ) {
            $_pokemon->happiness += 3;
          } else {
            $_pokemon->happiness += 2;
          }
        }

        // Ensure we don't exceed the 255 happiness cap
        $_pokemon->happiness = min( 255, $_pokemon->happiness );

        // Recalculate stats
        $_pokemon->stats->attack  = slackemon_calculate_stats( 'attack',  $_pokemon );
        $_pokemon->stats->defense = slackemon_calculate_stats( 'defense', $_pokemon );
        $_pokemon->stats->hp      = slackemon_calculate_stats( 'hp',      $_pokemon );
        $_pokemon->stats->speed   = slackemon_calculate_stats( 'speed',   $_pokemon );
        $_pokemon->stats->{'special-attack'}  = slackemon_calculate_stats( 'special-attack',  $_pokemon );
        $_pokemon->stats->{'special-defense'} = slackemon_calculate_stats( 'special-defense', $_pokemon );

        // Recalculate CP
        $_pokemon->cp = slackemon_calculate_cp( $_pokemon->stats );

        // Modify trainer battle stats
        if ( 'wild' !== $battle_data->type ) {
          $_pokemon->battles->won++;
          $_pokemon->battles->last_won = $battle_data->ts;
        }

        // Add Pokemon to interim collection
        $battle_pokemon_by_ts[ $_pokemon->ts ] = $_pokemon;

      } // Foreach player battle team Pokemon

      // Apply new Pokemon data to the player's collection
      foreach ( $player_data->pokemon as $_pokemon ) {
        if ( isset( $battle_pokemon_by_ts[ $_pokemon->ts ] ) ) {

          // Did this Pokemon participate and not faint? It will have an XP difference if so - that's how we detect it
          if ( $_pokemon->xp != $battle_pokemon_by_ts[ $_pokemon->ts ]->xp ) {

            $_pokemon_intro = ':' . $_pokemon->name . ': ' . slackemon_readable( $_pokemon->name, false );

            if ( $battle_pokemon_by_ts[ $_pokemon->ts ]->level > $_pokemon->level ) {

              $pokemon_experience_message .= (
                $_pokemon_intro . ' has levelled up ' .
                ( $is_desktop ? 'from level ' . $_pokemon->level . ' ' : '' ) .
                'to ' . $battle_pokemon_by_ts[ $_pokemon->ts ]->level . '!' . "\n"
              );

              if ( slackemon_can_user_pokemon_evolve( $battle_pokemon_by_ts[ $_pokemon->ts ] ) ) {
                $pokemon_experience_message .= '*' . $_pokemon_intro . ' is ready to evolve!! :open_mouth:*' . "\n";
              }

              $happiness_percent_old = floor( $_pokemon->happiness / 255 * 100 );
              $happiness_percent_new = floor( $battle_pokemon_by_ts[ $_pokemon->ts ]->happiness / 255 * 100 );

              if ( $happiness_percent_new > $happiness_percent_old ) {

                $pokemon_experience_message .= (
                  $_pokemon_intro . '\'s happiness has increased ' .
                  ( $is_desktop ? 'from ' . $happiness_percent_old . '% ' : '' ) .
                  'to ' . $happiness_percent_new . '%' . "\n"
                );

              } // If happiness increase

              if ( $battle_pokemon_by_ts[ $_pokemon->ts ]->cp > $_pokemon->cp ) {
                $pokemon_experience_message .= (
                  $_pokemon_intro . '\'s CP has increased ' .
                  ( $is_desktop ? 'from ' . $_pokemon->cp . ' ' : '' ) .
                  'to ' . $battle_pokemon_by_ts[ $_pokemon->ts ]->cp . "\n"
                );
              }

              if ( $battle_pokemon_by_ts[ $_pokemon->ts ]->stats->hp > $_pokemon->stats->hp ) {
                $pokemon_experience_message .= (
                  $_pokemon_intro . '\'s HP has increased ' .
                  ( $is_desktop ? 'from ' . $_pokemon->stats->hp . ' ' : '' ) .
                  'to ' . $battle_pokemon_by_ts[ $_pokemon->ts ]->stats->hp . "\n"
                );
              }

            } else {

              $pokemon_experience_message .= $_pokemon_intro . ' gained experience from this battle' . "\n";

            } // If level increase / else
          } // If Pokemon has XP difference

          $_pokemon->hp        = $battle_pokemon_by_ts[ $_pokemon->ts ]->hp;
          $_pokemon->xp        = $battle_pokemon_by_ts[ $_pokemon->ts ]->xp;
          $_pokemon->cp        = $battle_pokemon_by_ts[ $_pokemon->ts ]->cp;
          $_pokemon->evs       = $battle_pokemon_by_ts[ $_pokemon->ts ]->evs;
          $_pokemon->moves     = $battle_pokemon_by_ts[ $_pokemon->ts ]->moves;
          $_pokemon->level     = $battle_pokemon_by_ts[ $_pokemon->ts ]->level;
          $_pokemon->stats     = $battle_pokemon_by_ts[ $_pokemon->ts ]->stats;
          $_pokemon->battles   = $battle_pokemon_by_ts[ $_pokemon->ts ]->battles;
          $_pokemon->happiness = $battle_pokemon_by_ts[ $_pokemon->ts ]->happiness;

        } // If pokemon was in this battle
      } // Foreach player_data pokemon

      // Add player XP
      if ( $award_xp_to_user ) {

        if ( 'wild' === $battle_data->type ) {
          $xp_to_add = 175 + $total_experience_gained;
        } else {
          $xp_to_add = 500 + $total_experience_gained;
        }

        slackemon_add_xp( $xp_to_add, $user_id );

      }

      // Modify trainer battle stats
      if ( 'wild' !== $battle_data->type ) {
        $player_data->battles->won++;
        $player_data->battles->last_won = $battle_data->ts;
      }

      if ( $award_xp_to_user ) { // This should always be true for the winner!

        $xp_gain_message = '';

        foreach( $experience_gained_per_pokemon as $_pokemon ) {

          $xp_gain_message .= (
            ( $_pokemon['experience_gained'] < 10 ?  '   ' : '' ) . // Spacing
            ( $_pokemon['experience_gained'] < 100 ? '   ' : '' ) . // More spacing
            '*+' . $_pokemon['experience_gained'] . ' XP*: Defeated a ' .
            'level ' . $_pokemon['level'] . ' :' . $_pokemon['name'] . ': ' .
            slackemon_readable( $_pokemon['name'] ) .
            (
              SLACKEMON_EXP_GAIN_MODIFIER > 1 ?
              ' :low_brightness:' . ( $is_desktop ? '*x' . SLACKEMON_EXP_GAIN_MODIFIER . '*' : '' ) :
              ''
            ) .
            "\n"
          );

        } // For each experience_gained_per_pokemon
      } // If award_xp_to_user

      // Put message together
      $message = [
        'text' => (
          '*Your Pokémon:*' . "\n" .
          $pokemon_experience_message . "\n" .
          (
            'wild' === $battle_data->type ?
            '*+175 XP*: Won a wild battle' :
            '*+500 XP*: Won a trainer battle! :tada:'
          ) . "\n" .
          ( $award_xp_to_user ? $xp_gain_message : '' )
        ),
      ];

    break;

    case 'lost':

      $battle_pokemon_by_ts = [];

      // Save HP, happiness and participation data
      foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {

        if ( ! $_pokemon->hp ) {
          $_pokemon->happiness -= 1; // Happiness reduction of 1 due to fainting
          $_pokemon->happiness = max( 0, $_pokemon->happiness ); // Ensure we don't go below 0
        }

        $battle_pokemon_by_ts[ $_pokemon->ts ] = $_pokemon;

      }

      // Apply new Pokemon data to the player's collection
      foreach ( $player_data->pokemon as $_pokemon ) {
        if ( isset( $battle_pokemon_by_ts[ $_pokemon->ts ] ) ) {

          $_pokemon_intro = ':' . $_pokemon->name . ': ' . slackemon_readable( $_pokemon->name );

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

        } // If pokemon was in this battle
      } // Foreach player_data pokemon

      // Add player XP
      if ( $award_xp_to_user ) {
        slackemon_add_xp( 25, $user_id );
      }

      // Put message together
      $message = [
        'text' => (
          '*Your Pokémon:*' . "\n" .
          $pokemon_experience_message . "\n" .
          ( $award_xp_to_user ? '*+25 XP*: Participated in a battle' : '' )
        ),
      ];

    break;

  } // Switch battle_result (win/lose)

  // Move the battle file for completion, if it hasn't been done already by the user that ran this function first
  global $data_folder;
  if ( slackemon_file_exists( $data_folder . '/battles_active/' . $battle_hash, 'store' ) ) {

    slackemon_rename(
      $data_folder . '/battles_active/' . $battle_hash,
      $data_folder . '/battles_complete/' . $battle_hash,
      'store'
    );

  }

  // Modify trainer battle stats
  if ( 'wild' !== $battle_data->type ) {
    $player_data->battles->participated++;
  }
  $player_data->battles->last_participated = $battle_data->ts;

  slackemon_set_player_not_in_battle( $user_id );
  slackemon_save_player_data( $player_data, $user_id );

  if ( $send_response_to_user ) {
    $message['attachments'][] = slackemon_back_to_menu_attachment();
    send2slack( $message );
  }

  return $message;

} // Function slackemon_complete_battle

function slackemon_offer_battle_swap( $battle_hash, $user_id, $return_full_message = false, $action = null ) {

  $battle_data     = slackemon_get_battle_data( $battle_hash );
  $current_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
  $battle_team     = $battle_data->users->{ $user_id }->team;
  $is_desktop      = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $swap_actions = [];

  foreach ( $battle_team as $pokemon ) {
    if ( ! $pokemon->hp ) { continue; }
    if ( $pokemon->ts === $current_pokemon->ts ) { continue; }
    $swap_actions[] = [
      'name' => 'battles/swap/do',
      'text' => (
        ( $is_desktop ? ':' . $pokemon->name . ': ' : '' ) .
        slackemon_readable( $pokemon->name ) . ' (' . $pokemon->cp . ' CP)'
      ),
      'type' => 'button',
      'value' => $battle_hash . '/' . $pokemon->ts,
    ];
  }

  $swap_attachment = [
    'text' => (
      '*Who would you like to send into battle' .
      ( $is_desktop ? ' to replace ' . slackemon_readable( $current_pokemon->name ) : '' ) . '?*'
    ),
    'color' => '#333333',
    'actions' => $swap_actions,
    'mrkdwn_in' => [ 'text' ],
    'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
  ];

  if ( $return_full_message ) {
    $message = [ 'attachments' => $action->original_message->attachments ];
    $message['attachments'][ $action->attachment_id - 1 ] = $swap_attachment;
    return $message;
  } else {
    return $swap_attachment;
  }

} // Function slackemon_offer_battle_swap

function slackemon_do_battle_move( $move_name, $battle_hash, $action, $first_move = false, $user_id = USER_ID ) {

  $battle_data = slackemon_get_battle_data( $battle_hash );

  if ( ! $battle_data || $battle_data->last_move_ts < time() - MINUTE_IN_SECONDS * 25 ) {
    return slackemon_battle_has_ended_message();
  }

  // In case timeouts have allowed a user to move twice, we need to make sure it's this user's turn
  if ( $battle_data->turn !== $user_id ) {
    return;
  }

  $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  $battle_data->last_move_ts = time();

  if ( 'p2p' === $battle_data->type || 'U' === substr( $user_id, 0, 1 ) ) {
    $battle_data->users->{ $user_id }->response_url = RESPONSE_URL;
  }

  // Is this move a swap to another Pokemon ts?
  if ( is_numeric( $move_name ) ) {

    $new_pokemon_ts = $move_name;
    $old_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
    $battle_team = $battle_data->users->{ $user_id }->team;

    foreach ( $battle_team as $_pokemon ) {
      if ( $_pokemon->ts == $new_pokemon_ts ) {
        $new_pokemon = $_pokemon;
        break;
      }
    }

    // Set the new current Pokemon for this user
    $battle_data->users->{ $user_id }->status->current = $new_pokemon->ts;
    slackemon_maybe_record_battle_seen_pokemon( $opponent_id, $new_pokemon->pokedex );

    $move_message = (
      'swapped ' . slackemon_readable( $old_pokemon->name ) . ' for ' . slackemon_readable( $new_pokemon->name ) . '! ' .
      ucfirst( slackemon_get_gender_pronoun( $new_pokemon->gender ) ) . ' has ' . $new_pokemon->cp . ' CP.'
    );

    $user_pokemon     = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
    $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $opponent_id );

  // This move is a traditional battle move
  } else {

    $user_pokemon     = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
    $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $opponent_id );

    // Get the move from the Pokemon's known moves
    foreach ( $user_pokemon->moves as $_move ) {
      if ( $_move->name === $move_name ) {
        $move = $_move;
      }
    }

    // If move was not found or has no PP, we resort to our backup move instead (usually Struggle)
    if ( ! isset( $move ) || ! $move->{'pp-current'} ) {
      $move = slackemon_get_backup_move();
    }

    // Get the move data
    $move_data = slackemon_get_move_data( $move->name );

    // Calculate the move's damage
    $damage = slackemon_calculate_move_damage( $move, $user_pokemon, $opponent_pokemon );

    // Do the damage!
    $opponent_pokemon->hp -= $damage->damage;
    $opponent_pokemon->hp = max( 0, $opponent_pokemon->hp ); // Ensure the HP doesn't go below 0

    // Update the PP
    $move->{'pp-current'}--;

    // Deal with special effects of the move
    // TODO - This needs to be expanded a lot more!

    $meta_message = '';

    if ( $move_data->meta->drain ) {

      // Drain is expressed as a percentage of the damage done, and can be negative if the attack caused recoil
      $drain_amount     = ( $move_data->meta->drain / 100 ) * $damage->damage;
      $drain_percentage = floor( $drain_amount / $user_pokemon->stats->hp * 100 );

      $user_pokemon->hp += $drain_amount;
      $user_pokemon->hp = max( 0, $user_pokemon->hp ); // Ensure the HP doesn't go below 0
      $user_pokemon->hp = min( $user_pokemon->stats->hp, $user_pokemon->hp ); // Ensure the HP doesn't go above the max

      if ( $move_data->meta->drain > 0 ) {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' drained ' .
          $drain_percentage . '% HP from ' . slackemon_readable( $opponent_pokemon->name ) . '!_' . "\n"
        );
      } else {
        $meta_message .= (
          '_The recoil damaged ' . slackemon_readable( $user_pokemon->name ) . ' ' .
          'by ' . abs( $drain_percentage ) . '%!_' . "\n"
        );
      }

    } // If drain

    if ( $move_data->meta->healing ) {

      // Healing is expressed as a percentage of the user's maximum HP, and can be negative if they hurt themselves
      $healing_amount     = $move_data->meta->healing / 100 * $user_pokemon->stats->hp;
      $healing_percentage = floor( $healing_amount / $user_pokemon->stats->hp * 100 );

      $user_pokemon->hp += $healing_amount;
      $user_pokemon->hp = max( 0, $user_pokemon->hp ); // Ensure the HP doesn't go below 0
      $user_pokemon->hp = min( $user_pokemon->stats->hp, $user_pokemon->hp ); // Ensure the HP doesn't go above the max

      if ( $move_data->meta->healing > 0 ) {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' got healed ' .
          'by ' . $healing_percentage . '%!_' . "\n"
        );
      } else {
        $meta_message .= (
          '_' . slackemon_readable( $user_pokemon->name ) . ' hurt ' .
          ( 'male' === $user_pokemon->gender ? 'him' : 'her' ) . 'self, ' .
          'causing ' . abs( $healing_percentage ) . '% damage!_' . "\n"
        );
      }

    } // If healing

    $move_message = 'used *' . slackemon_readable( $move->name ) . '*. ';

    if ( $damage->damage ) {
      $move_message .= (
        'It ' . ( 1 == $damage->damage_percentage ? 'only ' : '' ) .
        'did ' . $damage->damage_percentage . '% damage' .
        ( 1 != $damage->damage_percentage && $damage->damage_percentage < 10 ? '.' : '!' ) . ' ' .
        $damage->type_message . "\n" .
        $meta_message
      );
    } else {
      $move_message .= $meta_message;
    }

    // Did the opponent faint?
    if ( ! $opponent_pokemon->hp ) {
      $move_message .= "\n" . '*' . slackemon_readable( $opponent_pokemon->name ) . ' has fainted!*';
    }

    // Make sure the Pokemon gets credit if this was its first move in this battle
    if ( $user_pokemon->battles->last_participated !== $battle_data->ts ) {
      if ( 'wild' !== $battle_data->type ) {
        $user_pokemon->battles->participated++;
      }
      $user_pokemon->battles->last_participated = $battle_data->ts;
    }

  } // If swap move or traditional move

  // Update and save the battle data
  $battle_data->turn = $opponent_id;
  slackemon_save_battle_data( $battle_data, $battle_hash );

  // Notify the user
  $last_move_notice = 'You ' . $move_message;
  $user_message = [
    'attachments' => slackemon_get_battle_attachments( $battle_hash, $user_id, 'during', $last_move_notice ),
    'replace_original' => true,
  ];

  // Notify the opponent
  $user_first_name = (
    'wild' === $battle_data->type ?
    slackemon_readable( $user_pokemon->name ) :
    slackemon_get_slack_user_first_name( $user_id )
  );
  $last_move_notice = $user_first_name . ' ' . $move_message;
  $opponent_message = [
    'attachments' => (
      slackemon_get_battle_attachments(
        $battle_hash,
        $opponent_id,
        $first_move ? 'first' : 'during',
        $last_move_notice
      )
    ),
  ];
  
  if ( 'p2p' === $battle_data->type ) {

      send2slack( $user_message, RESPONSE_URL );

      // Do we already have an existing action response URL for the opponent?
      // If so, use it, if not, it means this is the first move of a new battle, so we create a fresh message instead
      if ( $battle_data->users->{ $opponent_id }->response_url ) {
        $opponent_message['replace_original'] = true;
        send2slack( $opponent_message, $battle_data->users->{ $opponent_id }->response_url );
      } else {
        $opponent_message['channel'] = $opponent_id;
        slackemon_post2slack( $opponent_message );
      }

  } else {

    // This is not a p2p battle, so, we need to determine which user is which, send a response to the human user, and
    // then if it was the human user who just moved, we need to make a move for the opponent.

    if ( 'U' === substr( $user_id, 0, 1 ) ) {

      send2slack( $user_message, RESPONSE_URL );

      // If neither Pokemon hasn't fainted, go ahead and move!
      if ( $user_pokemon->hp && $opponent_pokemon->hp ) {

        sleep( 2 ); // Wait before the computer moves...

        // Before we move, should we flee?
        // This doubles the chance of staying compared to a standard catch, plus increases more depending on
        // how much HP the wild Pokemon has left.
        $hp_percentage_integer = $opponent_pokemon->hp / $opponent_pokemon->stats->hp;
        $is_staying = (
          random_int( 1, SLACKEMON_BASE_FLEE_CHANCE * SLACKEMON_BATTLE_FLEE_MULTIPLIER / $hp_percentage_integer ) > 1
        );

        if ( ! $is_staying ) {
          slackemon_do_action_response( slackemon_get_catch_message( $opponent_pokemon->ts, $action, true, 'flee' ) );
          return false;
        }

        $move = slackemon_get_best_move( $opponent_pokemon, $user_pokemon );
        slackemon_do_battle_move( $move->name, $battle_hash, $action, false, $opponent_id );

      } // If either pokemon has hp

    } else {

      send2slack( $opponent_message, RESPONSE_URL );

    } // If last move was from human user
  } // If p2p battle / else
} // Function slackemon_do_battle_move

function slackemon_get_battle_attachments( $battle_hash, $user_id, $battle_stage, $last_move_notice = '' ) {

  $is_desktop  = 'U' === substr( $user_id, 0, 1 ) && 'desktop' === slackemon_get_player_menu_mode( $user_id );
  $player_data = 'U' === substr( $user_id, 0, 1 ) ? slackemon_get_player_data( $user_id ) : false;

  $battle_data = slackemon_get_battle_data( $battle_hash );
  $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  $user_pokemon     = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
  $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $opponent_id );

  $opponent_first_name = (
    'wild' === $battle_data->type ?
    slackemon_readable( $opponent_pokemon->name ) :
    slackemon_get_slack_user_first_name( $opponent_id )
  );

  $user_pokemon->moves = slackemon_sort_battle_moves( $user_pokemon->moves, $user_pokemon->types );

  // Do we have a swap available?
  $user_swaps_available = 0;
  foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {
    if ( ! $_pokemon->hp ) { continue; }
    if ( $_pokemon->ts === $user_pokemon->ts ) { continue; }
    $user_swaps_available++;
  }
  $opponent_swaps_available = 0;
  foreach ( $battle_data->users->{ $opponent_id }->team as $_pokemon ) {
    if ( ! $_pokemon->hp ) { continue; }
    if ( $_pokemon->ts === $opponent_pokemon->ts ) { continue; }
    $opponent_swaps_available++;
  }

  $opponent_pretext = '';
  $user_pretext = '';
  switch ( $battle_stage ) {

    case 'start': // Brand new battle is starting
    case 'first': // First move has been made by the battle invitee

      if ( 'wild' === $battle_data->type ) {

        $opponent_pretext = (
          '*' . slackemon_readable( $opponent_pokemon->name ) . '* is up for a battle! ' .
          ucfirst( slackemon_get_gender_pronoun( $opponent_pokemon->gender ) ) . ' gets to go first.' . "\n" .
          'Take care - a wild Pokémon could flee at any time.'
        );

        $user_pretext = (
          'You have chosen *' . slackemon_readable( $user_pokemon->name ) . '*, with ' .
          '*' . $user_pokemon->cp . ' CP*.'
        );

      } else {

        $opponent_pretext = (
          $opponent_first_name . ' has chosen *' . slackemon_readable( $opponent_pokemon->name ) . '*! ' .
          ucfirst( slackemon_get_gender_pronoun( $opponent_pokemon->gender ) ) . ' has ' .
          '*' . $opponent_pokemon->cp . ' CP*.'
        );

        $user_pretext = (
          'Your first Pokémon up is *' . slackemon_readable( $user_pokemon->name ) . '*, with ' .
          '*' . $user_pokemon->cp . ' CP*.'
        );

      }

    break;

  } // Switch battle_stage

  // Put actions together

  $actions = [];

  if ( $battle_data->turn === $user_id ) {

    // It's the user's turn - they can make a move, use an item, swap, flee/surrender...

    $item_option_groups = [];

    if ( 'wild' === $battle_data->type ) {
      $item_option_groups['pokeballs'] = [
        'text' => 'Pokéballs',
        'options' => [
          [
            'text'  => 'Pokéball' . ( $is_desktop ? ' :pokeball:' : '' ),
            'value' => 'pokeball/' . $opponent_id,
          ],
        ],
      ];
    }

    if ( $player_data ) {
      $available_items = [];
      foreach ( $player_data->items as $item ) {

        $item_data = slackemon_get_item_data( $item->id );
        $item_attributes = [];

        foreach ( $item_data->attributes as $_attribute ) {
          $item_attributes[] = $_attribute->name;
        }

        if ( in_array( 'usable-in-battle', $item_attributes ) ) {
          if ( ! isset( $available_items[ 'item' . $item->id ] ) ) {

            $available_items[ 'item' . $item->id ] = [
              'id'       => $item->id,
              'count'    => 0,
              'name'     => $item_data->name,
              'category' => $item_data->category->name,
            ];

          }

          $available_items[ 'item' . $item->id ]['count']++;

        } // If usable-in-battle
      } // Foreach items

      usort( $available_items, function( $item1, $item2 ) {
        $cat_compare  = strcmp( $item1['category'], $item2['category'] );
        $item_compare = strcmp( $item1['name'],     $item2['name']     );
        if ( $cat_compare !== 0 ) {
          return $cat_compare  > 0 ? 1 : -1;
        } else {
          return $item_compare > 0 ? 1 : -1;
        }
      });

      foreach( $available_items as $item ) {

        // Combine all the Pokeballs together at the top
        if ( 'special-balls' === $item['category'] || 'standard-balls' === $item['category'] ) {
          $item['category'] = 'pokeballs';
        }

        if ( ! isset( $item_option_groups[ $item['category'] ] ) ) {
          $item_option_groups[ $item['category'] ] = [
            'text'    => slackemon_readable( $item['category'] ),
            'options' => [],
          ];
        }

        $item_option_groups[ $item['category'] ]['options'][] = [
          'text'  => slackemon_readable( $item['name'] ) . ' (' . $item['count'] . ')',
          'value' => $item['id'],
        ];

      } // Foreach available_items
    } // If player_data

    $move_options = [];
    $available_moves = [];

    foreach ( $user_pokemon->moves as $_move ) {
      if ( $_move->{'pp-current'} ) {
        $available_moves[] = $_move;
      }
    }

    // If there are moves available, we resort to our backup move instead (usually Struggle)
    if ( ! count( $available_moves ) ) {
      $available_moves[] = slackemon_get_backup_move();
    }

    foreach ( $available_moves as $_move ) {

      $_move_data = slackemon_get_move_data( $_move->name );

      $damage_class_readable = (
        999 != $_move->{'pp-current'} ? // 999 is used (interally, not in the API) for moves like Struggle
        ucfirst( substr( $_move_data->damage_class->name, 0, 2 ) ) : // Eg. Ph, Sp, St
        ''
      );

      $move_options[] = [
        'text' => (
          $damage_class_readable . '  ' .
          ( $is_desktop ? slackemon_emojify_types( ucfirst( $_move_data->type->name ), false ) . ' ' : '' ) .
          ( 999 != $_move->{'pp-current'} ? $_move->{'pp-current'} . '/' . $_move->pp . ' • ' : '' ) .
          slackemon_readable( $_move->name ) . ' x' . ( $_move_data->power ? $_move_data->power : 0 ) .
          ( $is_desktop ? '' : ' (' . ucfirst( $_move_data->type->name ) . ')' )
        ),
        'value' => $battle_hash . '/' . $_move->name . '/' . ( 'start' === $battle_stage ? 'first' : '' ),
      ];

    }

    if ( $user_swaps_available ) {

      $move_options[] = [
        'text' => ( $is_desktop ? ':twisted_rightwards_arrows: ' : '' ) . 'Swap Pokémon',
        'value' => $battle_hash . '//swap',
      ];

    }

    $actions[] = [
      'name' => 'battles/move',
      'text' => 'Make a Move',
      'type' => 'select',
      'options' => $move_options,
    ];

    $actions[] = [
      'name' => 'battles/item',
      'text' => 'Use Item',
      'type' => 'select',
      'option_groups' => $item_option_groups,
    ];

    // TODO: At the moment, flee is only available for wild battles
    // Surrender option will be available for non-wild battles later
    if ( 'wild' === $battle_data->type ) {

      $verb  = 'wild' === $battle_data->type ? 'flee' : 'surrender';
      $emoji = 'flee' === $verb ? ':runner:' : ':waving_white_flag:'; 

      $actions[] = [
        'name' => 'battles/surrender',
        'text' => $emoji . ' ' . ucfirst( $verb ),
        'type' => 'button',
        'value' => $battle_hash,
        'style' => 'danger',
        'confirm' => [
          'title' => 'Are you sure?',
          'text' => (
            'Are you sure you want to ' . $verb . '? ' .
            (
              'surrender' === $verb ?
              'The other player will get experience for their part in the battle, but you will not.' :
              'Your Pokémon will not gain any experience from this battle.'
            )
          ),
        ],
      ];

    } // If wild battle

  } else if ( $opponent_pokemon->hp || $opponent_swaps_available ) {

    // It's the opponent's turn

  } else {

    // The user won!

    if ( 'wild' === $battle_data->type ) {
      $actions[] = [
        'name' => 'catch/end-battle',
        'text' => ':pokeball: Throw Pokéball',
        'type' => 'button',
        'value' => $opponent_id,
        'style' => 'primary',
      ];
    }

    $actions[] = [
      'name' => 'battles/complete',
      'text' => ':white_check_mark: Complete Battle',
      'type' => 'button',
      'value' => $battle_hash . '/won',
      'style' => 'wild' === $battle_data->type ? '' : 'primary',
    ];

  }

  $attachments = [

    // Opponent's Pokemon
    slackemon_get_battle_pokemon_attachment( $opponent_pokemon, $opponent_id, $battle_hash, 'opponent', $opponent_pretext ),

    // User's Pokemon
    slackemon_get_battle_pokemon_attachment( $user_pokemon, $user_id, $battle_hash, 'user', $user_pretext ),

    // Last move notice (if applicable)
    (
      $last_move_notice ?
      [
        'text' => $last_move_notice,
        'color' => '#333333',
        'mrkdwn_in' => [ 'text' ],
      ] :
      []
    ),

    // User's options

    (
      $user_pokemon->hp ?
      [
        'text' => (
          $battle_data->turn === $user_id ?
          '*It\'s your move' . ( 'start' === $battle_stage ? ' first' : '' ) . '.*' .
          ( $is_desktop ? "\n" . 'What would you like to do?' : '' ) :
          (
            $opponent_pokemon->hp || $opponent_swaps_available ?
            '*It\'s ' . $opponent_first_name . '\'s move' .
            ( 'p2p' === $battle_data->type ? '.' : '... :loading:' ) .
            '*' :
            (
              'wild' === $battle_data->type ?
              ':tada: *You won the battle!* :party_parrot:' :
              ':tada: *Cᴏɴɢʀᴀᴛᴜʟᴀᴛɪᴏɴs! You won the battle!!* :party_parrot: :party_parrot:' . "\n" . // Congratulations
              'Click the _Complete_ button to get your XP bonus and power up your Pokémon! :100:'
            )
          )
        ),
        'color' => (
          $battle_data->turn !== $user_id && ! $opponent_pokemon->hp && ! $opponent_swaps_available ? // User won battle
          'good' :
          '#333333'
        ),
        'mrkdwn_in' => [ 'text' ],
        'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
        'actions' => $actions,
      ] :
      // User's current Pokemon has fainted - do we offer a swap, or was that their last mon??
      (
        $user_swaps_available ?
        slackemon_offer_battle_swap( $battle_hash, $user_id ) :
        [
          'text' => (
            ':expressionless: *Nooo... you lost the battle!*' . "\n" .
            'Click the _Complete_ button to get your XP bonus and see your Pokémon.'
          ),
          'mrkdwn_in' => [ 'text' ],
          'color' => 'danger',
          'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
          'actions' => [
            /*[ // TODO ?
              'name' => 'battles/send-congratulations',
              'text' => 'Send Congratulations',
            ],*/ [
              'name' => 'battles/complete',
              'text' => 'Complete Battle',
              'type' => 'button',
              'value' => $battle_hash . '/lost',
              'style' => 'primary',
            ],
          ],
        ]
      )
    ),
  ];

  return $attachments;

} // Function slackemon_get_battle_attachments

function slackemon_get_battle_pokemon_attachment( $pokemon, $player_id, $battle_hash, $player_type, $pretext = '' ) {

  $user_id    = 'user' === $player_type ? $player_id : slackemon_get_battle_opponent_id( $battle_hash, $player_id );
  $is_desktop = 'U' === substr( $user_id, 0, 1 ) && 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $battle_data = slackemon_get_battle_data( $battle_hash );
  $hp_percentage = min( 100, floor( $pokemon->hp / $pokemon->stats->hp * 100 ) );
  $hp_color = '';
  $hp_emoji = '';

  if ( $hp_percentage >= 50 ) {
    $hp_color = 'good';
    $hp_emoji .= str_repeat( ':green_circle:', floor( $hp_percentage / 10 ) );
    $hp_emoji .= str_repeat( ':yellow_circle:', $hp_percentage % 10 ? 1 : 0 );
    $hp_emoji .= str_repeat( ':white_circle:', floor( 10 - $hp_percentage / 10 ) );
  } else if ( $hp_percentage >= 30 ) {
    $hp_color = 'warning';
    $hp_emoji .= str_repeat( ':yellow_circle:', floor( $hp_percentage / 10 ) );
    $hp_emoji .= str_repeat( ':red_circle:', $hp_percentage % 10 ? 1 : 0 );
    $hp_emoji .= str_repeat( ':white_circle:', floor( 10 - $hp_percentage / 10 ) );
  } else if ( $hp_percentage >= 1 ) {
    $hp_color = 'danger';
    $hp_emoji .= str_repeat( ':red_circle:', floor( $hp_percentage / 10 ) );
    $hp_emoji .= str_repeat( ':black_circle:', $hp_percentage % 10 ? 1 : 0 );
    $hp_emoji .= str_repeat( ':white_circle:', floor( 10 - $hp_percentage / 10 ) );
  } else if ( ! $hp_percentage ) {
    $hp_color = '';
    $hp_emoji .= str_repeat( ':white_circle:', 10 );
  }

  $player_battle_team = $battle_data->users->{ $player_id }->team;
  $player_battle_team_readable = [ 'fainted' => '', 'known' => '', 'unknown' => '' ];

  foreach ( $player_battle_team as $_pokemon ) {
    if (
      $_pokemon->battles->last_participated !== $battle_data->ts && // Pokemon hasn't participated in this battle yet
      $pokemon->ts !== $_pokemon->ts && // Pokemon is not the Pokemon we're sending through now
      $player_type === 'opponent' // This player is the opponent, not the user owning this Pokemon attachment
    ) {
      $player_battle_team_readable['unknown'] .= ':grey_question:';
    } else if ( ! $_pokemon->hp ) {
      $player_battle_team_readable['fainted'] .= ':heavy_multiplication_x: ';
    } else {
      if ( $player_battle_team_readable['known'] ) { $player_battle_team_readable['known'] .= '  '; }
      $player_battle_team_readable['known'] .= ':' . $_pokemon->name . ':';
    }
  }

  // Determine which sprite to show
  // If Pokemon hasn't fainted, show the animated sprite
  // If it has fainted, show the front static sprite if a wild battle (because it's catchable), otherwise back static
  if ( $pokemon->hp ) {
    $image_url = SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/' . $pokemon->name . '.gif';
  } else if ( 'wild' === $battle_data->type ) {
    $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
    $image_url = (
      'female' === $pokemon->gender && $pokemon_data->sprites->front_female ?
      $pokemon_data->sprites->front_female :
      $pokemon_data->sprites->front_default
    );
  } else {
    $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
    $image_url = (
      'female' === $pokemon->gender && $pokemon_data->sprites->back_female ?
      $pokemon_data->sprites->back_female :
      $pokemon_data->sprites->back_default
    );
  }

  $attachment = [
    'pretext' => $pretext,
    'fallback' => $pretext,
    'text' => (
      (
        'wild' === $battle_data->type && 'opponent' === $player_type ?
        '' :
        (
          'wild' === $battle_data->type ?
          '' : 
          ':bust_in_silhouette: ' .
          slackemon_get_slack_user_first_name( $player_id ) . '    ' .
          $player_battle_team_readable['fainted'] . $player_battle_team_readable['known'] . $player_battle_team_readable['unknown'] . "\n\n"
        )
      ) .
      '*' .
      slackemon_readable( $pokemon->name, false ) .
      slackemon_get_gender_symbol( $pokemon->gender ) .
      ( $is_desktop ? '        ' : '    L' . $pokemon->level . '   ' ) .
      $pokemon->cp . ' CP' .
      ( $is_desktop ? '            ' : '      ' ) .
      slackemon_emojify_types( join( ' ' , $pokemon->types ), false ) .
      '*' . "\n" .
      $hp_emoji . ( $is_desktop ? '' : '   ' . $hp_percentage . '%' )
    ),
    'footer' => $is_desktop ? $hp_percentage . '% HP' . '  •  ' . 'Level ' . $pokemon->level : '',
    'color' => $hp_color,
    'image_url' => slackemon_get_cached_image_url( $image_url ),
    'mrkdwn_in' => [ 'pretext', 'text' ],
  ];

  return $attachment;

} // Function slackemon_get_battle_pokemon_attachment

function slackemon_get_battle_opponent_id( $battle_hash, $user_id ) {

  $battle_data = slackemon_get_battle_data( $battle_hash );

  foreach ( $battle_data->users as $_user_id => $_user_data ) {
    if ( $_user_id !== $user_id ) {
      return $_user_id;
    }
  }

} // Function slackemon_get_battle_opponent_id

function slackemon_get_battle_current_pokemon( $battle_hash, $user_id ) {

  $battle_data = slackemon_get_battle_data( $battle_hash );
  $user_pokemon = $battle_data->users->{ $user_id }->team;

  foreach ( $battle_data->users->{ $user_id }->team as $_pokemon ) {
    if ( $_pokemon->ts === $battle_data->users->{ $user_id }->status->current ) {
      return $_pokemon;
    }
  }

  return false;

} // Function slackemon_get_battle_current_pokemon

function slackemon_get_battle_hash( $ts, $user_id1, $user_id2 ) {

  $battle_hash_parts = [ $ts, $user_id1, $user_id2 ];
  asort( $battle_hash_parts );

  $battle_hash = md5( join( '', $battle_hash_parts ) );

  return $battle_hash;

} // Function slackemon_get_battle_hash

function slackemon_get_battle_data( $battle_hash, $allow_completed_battle = false ) {
  global $data_folder, $_cached_slackemon_battle_data;

  if ( isset( $_cached_slackemon_battle_data[ $battle_hash ] ) ) {
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

  $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store' ) );
  $_cached_slackemon_battle_data[ $battle_hash ] = $battle_data;

  return $battle_data;

} // Function slackemon_get_battle_data

function slackemon_get_invite_data( $battle_hash, $remove_invite = false ) {
  global $data_folder;

  if ( slackemon_file_exists( $data_folder . '/battle-invites/' . $battle_hash, 'store' ) ) {
    $invite_filename = $data_folder . '/battle-invites/' . $battle_hash;
  } else {
    return false;
  }

  $invite_data = json_decode( slackemon_file_get_contents( $invite_filename, 'store' ) );

  if ( $remove_invite ) {
    slackemon_unlink( $invite_filename, 'store' );
  }

  return $invite_data;

} // Function slackemon_get_invite_data

function slackemon_save_battle_data( $battle_data, $battle_hash, $battle_stage = 'battle' ) {
  global $data_folder, $_cached_slackemon_battle_data;

  switch ( $battle_stage ) {
    case 'battle':   $battle_folder = 'battles_active';   break;
    case 'complete': $battle_folder = 'battles_complete'; break;
    case 'invite':   $battle_folder = 'battle-invites';   break;
  }

  $battle_filename = $data_folder . '/' . $battle_folder . '/' . $battle_hash . '.' . $battle_stage;

  $_cached_slackemon_battle_data[ $battle_hash ] = $battle_data;
  return slackemon_file_put_contents( $battle_filename, json_encode( $battle_data ), 'store' );

} // Function slackemon_get_battle_data

function slackemon_maybe_record_battle_seen_pokemon( $player_id, $pokedex_id ) {

  $player_data = slackemon_get_player_data( $player_id );

  // Bow out if the user already has a Pokedex entry for this Pokemon
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $pokedex_id == $pokedex_entry->id ) {
      return;
    }
  }

  // First seen - time to create a new entry!
  $player_data->pokedex[] = [
    'id' => (int) $pokedex_id,
    'seen' => 1, // Seen in battle - this will stay at 1 until they see again in a spawn or evolve etc.
    'caught' => 0,
  ];

  return slackemon_save_player_data( $player_data, $player_id );

} // Function slackemon_maybe_record_battle_seen_pokemon

function slackemon_get_all_active_battles() {
  global $data_folder;

  $battles = slackemon_get_files_by_prefix( $data_folder . '/battles_active/', 'store' );
  $active_battles = [];

  foreach ( $battles as $battle_filename ) {
    $battle_data = json_decode( slackemon_file_get_contents( $battle_filename, 'store' ) );
    $active_battles[] = $battle_data;
  }

  return $active_battles;

} // Function slackemon_get_all_active_battles

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

} // Function slackemon_get_user_active_battles

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

} // Function slackemon_get_user_complete_battles

function slackemon_get_user_outstanding_invites( $user_id = USER_ID ) {
  global $data_folder;

  $invites = slackemon_get_files_by_prefix( $data_folder . '/battles_invites/', 'store' );
  $user_invites = [];

  foreach ( $invites as $invite_filename ) {
    $invite_data = json_decode( slackemon_file_get_contents( $invite_filename, 'store' ) );
    if ( $user_id === $invite_data->inviter_id || $user_id === $invite_data->invitee_id ) {
      $user_invites[] = $invite_data;
    }
  }

  return $user_invites;

} // Function slackemon_get_user_outstanding_invites

function slackemon_battle_has_ended_message() {

  return send2slack([
    'text' => (
      ':open_mouth: *Oops! It appears this battle may have ended!*' . "\n" .
      'If this doesn\'t seem right to you, check with your battle opponent. If you think something may be wrong ' .
      'with Slackémon, please chat to <@' . SLACKEMON_MAINTAINER . '>.'
    ),
    'attachments' => [
      slackemon_back_to_menu_attachment()
    ],
  ]);

} // Function slackemon_battle_has_ended_message

function slackemon_battle_debug( $message ) {

  if ( ! SLACKEMON_BATTLE_DEBUG ) {
    return;
  }

  slackemon_error_log( $message );

}

// The end!
