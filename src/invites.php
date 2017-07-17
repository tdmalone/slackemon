<?php
/**
 * Battle invite (challenge) specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_send_battle_invite( $invitee_id, $action, $challenge_type, $inviter_id = USER_ID ) {

  $is_desktop = slackemon_is_desktop( $inviter_id );

  // Bow out early if the challenge type the user selected is unavailable at the moment (the menu will send through
  // 'unavailable' if this is so).
  if ( 'unavailable' === $challenge_type[0] ) {

    $invite_attachment = $action->original_message->attachments[ $action->attachment_id - 1 ];

    $invite_attachment->footer = (
      ( $is_desktop ? ':no_entry_sign: ' : '' ) .
      'Sorry, that challenge type is not available for your current battle team. Please adjust your team to ensure ' .
      'it meets the rules for the challenge, or try another challenge type.'
    );

    $message = slackemon_update_triggering_attachment( $invite_attachment, $action, false );

    return $message;

  }

  $inviter_player_data = slackemon_get_player_data( $inviter_id );
  $inviter_user_data   = slackemon_get_slack_user( $inviter_id );

  $invite_ts   = time();
  $battle_hash = slackemon_generate_battle_hash( $invite_ts, $inviter_id, $invitee_id );

  $invite_data = [
    'ts'             => $invite_ts,
    'hash'           => $battle_hash,
    'challenge_type' => $challenge_type,
    'inviter_id'     => $inviter_id,
    'invitee_id'     => $invitee_id,
  ];

  // Check that either user doesn't have any outstanding invites as either invitee or inviter.
  $inviter_invites = slackemon_get_user_outstanding_invites( $inviter_id );
  $invitee_invites = slackemon_get_user_outstanding_invites( $invitee_id );

  if ( count( $inviter_invites ) ) {

    $cancel_verb = $inviter_invites[0]->inviter_id === $inviter_id ? 'cancel' : 'decline';

    $inviter_message = slackemon_update_triggering_attachment(
      ':open_mouth: *Oops! You already have an outstanding battle challenge.*' . "\n" .
      'Please ' . $cancel_verb . ' your current challenge before sending a new one. :smile:',
      $action,
      false // Don't send now, we'll return below to be sent with our default action response.
    );

    return $inviter_message;

  } else if ( count( $invitee_invites ) ) {

    $inviter_message = slackemon_update_triggering_attachment(
      ':open_mouth: *Oops! That user already has an outstanding battle challenge.*' . "\n" .
      'Please try challenging this user later. :smile:',
      $action,
      false // Don't send now, we'll return below to be sent with our default action response.
    );

    return $inviter_message;

  }

  $invitee_message = [
    'text' => (
      ':stuck_out_tongue_closed_eyes: *You have been challenged ' .
      'to a ' . slackemon_readable_challenge_type( $challenge_type ) . ' Slackémon Battle ' .
      slackemon_get_battle_challenge_emoji( $challenge_type ) . ' ' .
      'by ' . slackemon_get_slack_user_first_name( $inviter_id ) . '!*'
    ),
    'attachments' => slackemon_get_battle_invite_attachments( $invite_data ),
    'channel'     => $invitee_id,
  ];

  // Save invite data without warning about it not being locked, since it is a new file.
  slackemon_save_battle_data( $invite_data, $battle_hash, 'invite', false, false );

  if ( slackemon_post2slack( $invitee_message ) ) {

    $inviter_message = slackemon_get_battle_menu();

  } else {

    $inviter_message = [
      'text'             => ':no_entry: *Oops!* A problem occurred. Please try your last action again.',
      'replace_original' => false,
    ];

  }

  return $inviter_message;

} // Function slackemon_send_battle_invite.

function slackemon_get_battle_invite_attachments( $invite_data, $context = 'invite' ) {

  $invite_attachment = slackemon_get_battle_invite_attachment( $invite_data, $context );
  $status_attachment = slackemon_get_invite_status_attachment( $invite_data );
  $menu_attachment   = slackemon_back_to_menu_attachment();

  // Add pretext if coming from the battle menu.
  if ( 'battle-menu' === $context ) {

    $challenge_type = $invite_data->challenge_type;

    $invite_attachment['pretext'] = (
      ':arrow_right: *You have a ' . slackemon_readable_challenge_type( $challenge_type ) . ' Battle Challenge ' .
      slackemon_get_battle_challenge_emoji( $challenge_type ) . ' ' .
      'from ' . slackemon_get_slack_user_first_name( $invite_data->inviter_id ) . ':*'
    );

  }

  // Adjust the options if we have a non-blocking status attachment, so it's clearer to the user what they should do.
  if ( $status_attachment && 'warning' === $status_attachment['color'] ) {

    $invite_attachment['actions'][0]['style']   = 'default';

    $invite_attachment['actions'][0]['confirm'] =  [
      'title' => 'Are you sure?',
      'text'  => 'Are you sure you\'re ready to start this battle? Your team is not fully ready yet!',
    ];

    // Add additional menu option for the battle menu.
    $menu_attachment = slackemon_back_to_menu_attachment([ 'battles', 'main' ]);
    $menu_attachment['actions'][0]['style'] = 'primary';

  }

  return [
    $invite_attachment,
    $status_attachment,
    'invite' === $context ? $menu_attachment : [],
  ];

} // Function slackemon_get_battle_invite_attachments.

function slackemon_get_battle_invite_attachment( $invite_data, $context = 'invite' ) {

  // Accept either an array or object.
  if ( is_array( $invite_data ) ) {
    $invite_data = json_decode( json_encode( $invite_data ) );
  }

  $inviter_id = $invite_data->inviter_id;
  $invitee_id = $invite_data->invitee_id;

  $invite_attachment  = slackemon_get_player_battle_attachment( $inviter_id, $invitee_id );
  $is_player_eligible = slackemon_is_player_eligible_for_challenge( $invite_data->challenge_type, $invitee_id );

  // The accept action will be a link to the Battles menu instead if the player isn't eligible yet.
  if ( $is_player_eligible ) {

    $accept_action = [
      'name'  => 'battles/accept',
      'text'  => 'Accept',
      'type'  => 'button',
      'value' => $invite_data->hash,
      'style' => 'primary',
    ];

  } else if ( 'battle-menu' !== $context ) {

    $accept_action = slackemon_back_to_menu_attachment( ['battles'], 'actions' )[0];
    $accept_action['text']  = ':facepunch: Change Team';
    $accept_action['style'] = 'primary';

  }

  $invite_attachment['actions'] = [
    isset( $accept_action ) ? $accept_action : [],
    [
      'name'  => 'battles/decline',
      'text'  => 'Decline',
      'type'  => 'button',
      'value' => $invite_data->hash,
      'style' => 'danger',
    ]
  ];

  return $invite_attachment;

} // Function slackemon_get_battle_invite_attachment.

function slackemon_get_invite_status_attachment( $invite_data ) {

  // Accept either an array or object.
  if ( is_array( $invite_data ) ) {
    $invite_data = json_decode( json_encode( $invite_data ) );
  }

  $inviter_id = $invite_data->inviter_id;
  $invitee_id = $invite_data->invitee_id;

  $is_player_eligible = slackemon_is_player_eligible_for_challenge( $invite_data->challenge_type, $invitee_id );

  // If the player is already eligible, get a generic battle team status message, to advise if they should make
  // changes to eg. fainted Pokemon before accepting.
  if ( $is_player_eligible ) {

    $status_options = [
      'perspective'      => 'invitee',
      'challenge_type'   => $invite_data->challenge_type,
      'quiet_on_success' => true,
    ];

    $status_attachment = slackemon_get_battle_team_status_attachment( $invitee_id, $status_options );

    // Move the pretext to the main text. Because we've said 'quiet_on_success' above, if there is a message, we know
    // it's not a success one.
    if ( $status_attachment ) {
      $status_attachment['color']   = 'warning';
      $status_attachment['text']    = $status_attachment['pretext'];
      $status_attachment['pretext'] = '';
    }

  } else {

    // Set up our own status attachment if the player is not eligible. Accept button will have already been removed.
    $status_attachment = [
      'color' => 'danger',
      'text'  => (
        ':exclamation: *Your current battle team isn\'t eligible for this challenge.*' . "\n" .
        'You\'ll need to swap your team before you can accept.'
      ),
    ];

  }

  return $status_attachment;

} // Function slackemon_get_invite_status_attachment.

function slackemon_cancel_battle_invite( $battle_hash, $action, $mode = 'inviter' ) {

  $invite_data = slackemon_get_invite_data( $battle_hash, true );

  if ( $invite_data ) {

    switch ( $mode ) {

      case 'inviter':

        // Respond to the invitee first.
        slackemon_post2slack([
          'text' => (
            ':disappointed: *Oh! Sorry, ' . slackemon_get_slack_user_first_name( $invite_data->inviter_id ) . ' ' .
            'has cancelled their battle challenge.*' . "\n" .
            'Maybe next time!'
          ),
          'attachments' => [ slackemon_back_to_menu_attachment() ],
          'channel' => $invite_data->invitee_id,
        ]);

        // Inviter response.
        $message = slackemon_get_battle_menu();

      break;

      case 'invitee':

        // Respond to the inviter first.
        slackemon_post2slack([
          'text' => (
            ':disappointed: *Sorry, ' . slackemon_get_slack_user_first_name( $invite_data->invitee_id ) . ' ' .
            'has declined your battle challenge.*' . "\n" .
            'Maybe next time!'
          ),
          'attachments' => [ slackemon_back_to_menu_attachment() ],
          'channel' => $invite_data->inviter_id,
        ]);

        // Invitee response.
        $message = slackemon_update_triggering_attachment(
          ':x: *You have declined ' . slackemon_get_slack_user_first_name( $invite_data->inviter_id ) . '\'s ' .
          'challenge.*' . "\n" .
          'Not ready to battle right now? Send your own challenge later from the Battle menu.',
          $action,
          false
        );

      break;

    } // Switch mode.

  } else {

    $message = slackemon_update_triggering_attachment(
      ':no_entry: *Oops!* That battle challenge doesn\'t seem to exist anymore.' . "\n" .
      'It may have already been accepted, declined, or cancelled.',
      $action,
      false
    );

  } // If invite_data / else.

  return $message;

} // Function slackemon_cancel_battle_invite.

function slackemon_get_invite_data( $battle_hash, $remove_invite = false ) {
  global $data_folder;

  if ( slackemon_file_exists( $data_folder . '/battles_invites/' . $battle_hash, 'store' ) ) {
    $invite_filename = $data_folder . '/battles_invites/' . $battle_hash;
  } else {
    return false;
  }

  $invite_data = json_decode( slackemon_file_get_contents( $invite_filename, 'store' ) );

  if ( $remove_invite ) {
    slackemon_unlink( $invite_filename, 'store' );
  }

  return $invite_data;

} // Function slackemon_get_invite_data.

function slackemon_get_user_outstanding_invites( $user_id = USER_ID ) {
  global $data_folder;

  $invites      = slackemon_get_files_by_prefix( $data_folder . '/battles_invites/', 'store' );
  $user_invites = [];

  foreach ( $invites as $invite_filename ) {

    $invite_data = json_decode( slackemon_file_get_contents( $invite_filename, 'store' ) );
    
    if ( $user_id === $invite_data->inviter_id || $user_id === $invite_data->invitee_id ) {
      $user_invites[] = $invite_data;
    }
  }

  return $user_invites;

} // Function slackemon_get_user_outstanding_invites.

/**
 * Returns whether or not the user has an outstanding invite - either as an invitee or inviter, or both.
 *
 * @param str $user_id The user ID, of course.
 * @param str $mode    Whether to look for invites sent by 'inviter', received by 'invitee', or 'either'. Defaults
 *                     to 'either'.
 * @return bool
 */
function slackemon_does_user_have_outstanding_invite( $user_id, $mode = 'either' ) {

  $outstanding_invites = slackemon_get_user_outstanding_invites( $user_id );

  if ( ! count( $outstanding_invites ) ) {
    return false;
  }

  if ( 'either' === $mode ) {
    return true;
  }

  foreach ( $outstanding_invites as $invite ) {

    if ( 'inviter' === $mode && $user_id === $invite->inviter_id ) {
      return true;
    }

    if ( 'invitee' === $mode && $user_id === $invite->invitee_id ) {
      return true;
    }

  }

  return false;

} // Function slackemon_does_user_have_outstanding_invite.

/** Returns responses to be sent to users due to invite cancellation. */
function slackemon_get_invite_cancellation_responses( $inviter_name, $invitee_name ) {

  $responses = [

    'team_size' => [
      'inviter' => [
        'self' => (
          'You don\'t have enough revived Pokémon to participate in the battle you ' .
          'challenged ' . $invitee_name . ' to!' . "\n" .
          ':skull: You can see your fainted Pokémon on your Pokémon page from the Main Menu. You may have to wait ' .
          'for them to regain their strength, or catch some more Pokémon.' .
          ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ' :pokeball:' : '' )
        ),
        'other' => $inviter_name . ' doesn\'t have enough revived Pokémon to participate in this battle!',
      ],
      'invitee' => [
        'self' => (
          'You don\'t have enough revived Pokémon to accept this challenge!' . "\n" .
          ':skull: You can see your fainted Pokémon on your Pokémon page from the Main Menu. You may have to wait ' .
          'for them to regain their strength, or catch some more Pokémon.' .
          ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ' :pokeball:' : '' )
        ),
        'other' => $invitee_name . ' doesn\'t have enough revived Pokémon to accept your battle challenge right now.',
      ],
    ],

    'eligibility' => [
      'inviter' => [
        'self' => (
          'Your battle team is no longer eligible for the challenge you invited ' . $invitee_name . ' to!' . "\n" .
          'Please check your team, then try sending your challenge again.'
        ),
        'other' => $inviter_name . '\'s current battle team is no longer eligible for this challenge!',
      ],
      'invitee' => [
        'self' => (
          'Your current battle team is not eligible to participate in this challenge.' . "\n" .
          'Please check your team, then try sending a challenge back to ' . $inviter_name . '.'
        ),
        'other' => $invitee_name . '\'s current battle team is not eligible for your challenge.',
      ],
    ],

  ];

  // Massage messages to add common prefixes/suffixes as needed, so they only need to be repeated once.
  foreach ( $responses as $error_type => &$outer ) {
    foreach ( $outer as $msg_from => &$inner ) {
      foreach( $inner as $msg_to => &$message ) {

        if ( 'self' === $msg_to ) {
          $message = ':open_mouth: *Oops!* ' . $message;
        }

        if ( 'other' === $msg_to ) {

          $message = (
            ':slightly_frowning_face: *Oh no!* ' . $message . "\n" .
            'I\'ve sent them a message too. Perhaps ' .
            ( 'invitee' === $msg_from ? 'try challenging them again later' : 'they\'ll challenge you again soon' ) .
            '! :slightly_smiling_face:'
          );

        }

      }
    }
  }

  return $responses;

} // Function slackemon_get_invite_cancellation_responses.

// The end!
