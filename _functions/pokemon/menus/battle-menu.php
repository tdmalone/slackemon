<?php

// Chromatix TM 04/04/2017
// Battle menu for Slackemon Go

function slackemon_get_battle_menu() {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => (
      '*Bᴀᴛᴛʟᴇs*' . "\n" // Battles
    ),
    'attachments' => [],
  ];

  if ( slackemon_is_battle_team_full() ) {

    $message['attachments'] = slackemon_get_battle_menu_attachments();

  } else {

    $message['text'] .= (
      ':medal: Winning Slackémon battles will level-up your Pokémon - ' .
      'making them stronger _and_ getting you closer to evolving them.' . "\n" .
      ':arrow_right: *To send a battle challenge, you need to first choose your Battle Team ' .
      'of ' . SLACKEMON_BATTLE_TEAM_SIZE . ': visit your Pokémon page from the main menu!*'
    );

  }

  $message['attachments'][] = slackemon_back_to_menu_attachment();

  return $message;

} // Function slackemon_get_battle_menu

function slackemon_get_battle_menu_attachments() {

  $attachments = [];
  $battle_team = slackemon_get_battle_team();
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode();

  $faint_count = 0;

  foreach ( $battle_team as $pokemon ) {

    $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );

    $attachments[] = [
      'text' => (
        '*' .
        ( 0 == $pokemon->hp ? ':skull: ' : '' ) .
        ( pokedex_is_legendary( $pokemon->pokedex ) ? ':star2: ' : '' ) .
        pokedex_readable( $pokemon->name, false ) .
        slackemon_get_gender_symbol( $pokemon->gender ) .
        ( $is_desktop ? '  •  ' : ' •  ' ) .
        $pokemon->cp . ' CP' . '  •  ' .
        'L ' . $pokemon->level . '     ' .
        slackemon_emojify_types( join( ' ' , $pokemon->types ), false ) .
        '*' . "\n" .
        (
          $is_desktop ?
          'Won ' . $pokemon->battles->won . ' of ' . $pokemon->battles->participated . ' trainer battles' . "\n" :
          "\n"
        ) .
        slackemon_readable_moveset( $pokemon->moves, $pokemon->types, true, true )
      ),
      'footer' => (
        'Attack ' . $pokemon->stats->attack . ' • ' .
        'Defense ' . $pokemon->stats->defense . ' • ' .
        'HP ' . floor( $pokemon->hp ) . '/' . $pokemon->stats->hp .
        (
          0 == $pokemon->hp ?
          ( $is_desktop ? ':black_small_square:' : ' !!!' ) :
          ''
        ) . (
          $pokemon->hp > 0 && $pokemon->hp < $pokemon->stats->hp ?
          ( $is_desktop ? ':small_orange_diamond:' : ' !!!' ) :
          ''
        ) .
        ( $is_desktop ? '' : ' • Won ' . $pokemon->battles->won . ' of ' . $pokemon->battles->participated )
      ),
      'color' => $pokemon->hp >= $pokemon->stats->hp * .1 ? slackemon_get_color_as_hex( $species_data->color->name ) : '',
      'thumb_url' => (
        $is_desktop ?
        get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/' . $pokemon->name . '.gif' ) :
        ''
      ),
    ];

    if ( 0 == $pokemon->hp ) {
      $faint_count++;
    }

  }

  array_unshift( $attachments, slackemon_get_battle_team_status_attachment() );

  $online_players      = slackemon_get_player_ids([ 'active_only' => true, 'skip_current_user' => true ]);
  $outstanding_invites = slackemon_get_user_outstanding_invites();

  if ( $faint_count === SLACKEMON_BATTLE_TEAM_SIZE && ! count( $outstanding_invites ) ) {
    return $attachments;
  }

  if ( slackemon_is_player_in_battle() ) {

    // TODO: Show stats on current battle, include links to get back to it, stop it, etc.

    $attachments[] = [
      'text' => ':exclamation: _You can\'t send a new battle invite until your current battle has finished._',
      'color' => '#333333',
    ];

  } else if ( count( $outstanding_invites ) ) {

    if ( USER_ID === $outstanding_invites[0]->inviter_id ) {

      $opponent_id = $outstanding_invites[0]->invitee_id;
      $battle_hash = $outstanding_invites[0]->hash;

      $attachment = slackemon_get_player_battle_attachment( $opponent_id );
      $_first_name = get_user_first_name( $opponent_id );

      $attachment['pretext'] = (
        ( $is_desktop ? ':loading: ' : '' ) .
        '*Waiting for ' . $_first_name . '\'s response to your ' . ( $is_desktop ? 'battle ' : '' ) . 'invite...*'
      );

      $attachment['actions'] = [
        [
          'name' => 'battles/cancel',
          'text' => 'Cancel Invitation',
          'type' => 'button',
          'value' => $battle_hash,
          'style' => 'danger',
        ]
      ];

      $attachments[] = $attachment;

    } else {

      $opponent_id = $outstanding_invites[0]->inviter_id;
      $battle_hash = $outstanding_invites[0]->hash;

      $attachment = slackemon_get_player_battle_attachment( $opponent_id );
      $_first_name = get_user_first_name( $opponent_id );
      $attachment['pretext'] = ':arrow_right: *You have an outstanding battle invite from ' . $_first_name . ':*';

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

      $attachments[] = $attachment;

    }

  } else if ( count( $online_players ) ) {

    $attachments[] = [
      'pretext' => ':arrow_right: *Please choose your opponent:*',
      'color' => '#333333',
    ];

    foreach ( $online_players as $player_id ) {

      $attachment = slackemon_get_player_battle_attachment( $player_id );

      $attachment['actions'] = [
        [
          'name' => 'battles/invite',
          'text' => 'Challenge ' . get_user_first_name( $player_id ) . '!',
          'type' => 'button',
          'value' => $player_id,
          'style' => 'primary',
        ],
      ];

      $attachments[] = $attachment;

    }

  } else {

    $attachments[] = [
      'pretext' => (
        ':disappointed: *There are no players available to battle' .
        ( $is_desktop ? ' at the moment' : '' ) . '.*' . "\n" .
        'Please try again later' .
        ( $is_desktop ? ' - or ask another player to come online' : '' ) . '!'
      ),
    ];

  } // If online_players / else

  return $attachments;

} // Function slackemon_get_battle_menu_attachments

function slackemon_get_player_battle_attachment( $player_id, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $player_id );
  $player_user_data = get_slack_user( $player_id );
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $battles_won  = $player_data->battles->won;
  $battles_lost = $player_data->battles->participated - $player_data->battles->won;

  $attachment = [
    'text' => '*' . get_user_full_name( $player_id ) . ' - ' . number_format( $player_data->xp ) . ' XP*',
    'fields' => [
      [
        'title' => 'Battles Won',
        'value' => 0 == $battles_won ? '(none)' : $battles_won,
        'short' => true,
      ], [
        'title' => 'Battles Lost',
        'value' => 0 == $battles_lost ? '(none)' : $battles_lost,
        'short' => true,
      ], [
        'title' => 'Top Pokémon',
        'value' => join( $is_desktop ? '   ' : "\n", slackemon_get_top_pokemon_list( $player_id ) ),
        'short' => false,
      ],
    ],
    'footer' => (
      'These are ' . get_user_first_name( $player_id ) . '\'s top Pokémon by CP, but not necessarily ' .
      'their battle team!' . ( $is_desktop ? "\n" : ' ' ) .
      'That remains a secret until your battle starts.'
    ),
    'thumb_url' => $player_user_data->profile->image_192,
    'color' => $player_user_data->color,
    'mrkdwn_in' => [ 'pretext', 'text', 'fields', 'footer' ],
    'callback_id' => SLACKEMON_ACTION_CALLBACK_ID
  ];

  return $attachment;

} // Function slackemon_get_player_battle_attachment

// The end!
