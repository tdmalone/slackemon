<?php
/**
 * Battle menu for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_battle_menu() {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => (
      '*Bᴀᴛᴛʟᴇs*' . "\n" // Battles
    ),
    'attachments' => [],
  ];

  $message['attachments'] = slackemon_get_battle_menu_attachments();
  $message['attachments'][] = slackemon_back_to_menu_attachment();

  return $message;

} // Function slackemon_get_battle_menu

function slackemon_get_battle_menu_attachments( $user_id = USER_ID ) {

  $attachments = [];
  $battle_team = slackemon_get_battle_team( $user_id, false, true );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode();

  $faint_count = 0;

  foreach ( $battle_team as $pokemon ) {

    $attachments[] = slackemon_get_battle_menu_pokemon_attachment( $pokemon );

    if ( 0 == $pokemon->hp ) {
      $faint_count++;
    }

  } // Foreach battle_team pokemon

  // Add attachments to add new Pokemon to the team
  if ( count( $battle_team ) < SLACKEMON_BATTLE_TEAM_SIZE ) {
    for ( $i = count( $battle_team ); $i < SLACKEMON_BATTLE_TEAM_SIZE; $i++ ) {

      if ( SLACKEMON_BATTLE_TEAM_SIZE - 1 === $i ) {
        $count_helper = 'one more';
      } else if ( $i > count( $battle_team ) ) {
        $count_helper = 'another';
      } else {
        $count_helper = 'a';
      }

      $attachments[] = slackemon_get_battle_menu_add_attachment( $count_helper );

    }
  }

  // Add the battle team status attachment at the start
  array_unshift( $attachments, slackemon_get_battle_team_status_attachment() );

  $online_players      = slackemon_get_player_ids([ 'active_only' => true, 'skip_current_user' => true ]);
  $outstanding_invites = slackemon_get_user_outstanding_invites();

  if ( $faint_count === SLACKEMON_BATTLE_TEAM_SIZE && ! count( $outstanding_invites ) ) {
    return $attachments;
  }

  // Don't allow the player to proceed with sending an invite if their battle team isn't full
  if ( ! slackemon_is_battle_team_full( $user_id, false, true ) && ! count( $outstanding_invites ) ) {
    return $attachments;
  }

  if ( slackemon_is_player_in_battle() ) {

    // TODO: Show stats on current battle, include links to get back to it, stop it, etc.

    $attachments[] = [
      'text' => ':exclamation: _You can\'t send a new battle invite until your current battle has finished._',
      'color' => '#333333',
    ];

  } else if ( count( $outstanding_invites ) ) {

    if ( $user_id === $outstanding_invites[0]->inviter_id ) {

      $opponent_id = $outstanding_invites[0]->invitee_id;
      $battle_hash = $outstanding_invites[0]->hash;

      $attachment = slackemon_get_player_battle_attachment( $opponent_id );
      $_first_name = slackemon_get_slack_user_first_name( $opponent_id );

      $attachment['pretext'] = (
        slackemon_get_loading_indicator( $user_id, false ) . ' ' .
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
      $_first_name = slackemon_get_slack_user_first_name( $opponent_id );
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

    // Line spacer
    $attachments[] = [
      'pretext' => ' ' . "\n" . ' ',
    ];

    $attachments[] = [
      'pretext' => ':arrow_right: *Please choose your opponent:*',
      'color' => '#333333',
    ];

    foreach ( $online_players as $player_id ) {

      $attachment = slackemon_get_player_battle_attachment( $player_id );

      $attachment['actions'] = [
        [
          'name' => 'battles/invite',
          'text' => 'Challenge ' . slackemon_get_slack_user_first_name( $player_id ) . '!',
          'type' => 'button',
          'value' => $player_id,
          'style' => 'primary',
        ],
      ];

      $attachments[] = $attachment;

    }

  } else {

    // Line spacer
    $attachments[] = [
      'pretext' => ' ' . "\n" . ' ',
    ];

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
  $player_user_data = slackemon_get_slack_user( $player_id );
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $battles_won  = $player_data->battles->won;
  $battles_lost = $player_data->battles->participated - $player_data->battles->won;

  $attachment = [
    'text' => '*' . slackemon_get_slack_user_full_name( $player_id ) . ' - ' . number_format( $player_data->xp ) . ' XP*',
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
      'These are ' . slackemon_get_slack_user_first_name( $player_id ) . '\'s top Pokémon by CP, but not necessarily ' .
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

function slackemon_get_battle_menu_pokemon_attachment( $pokemon ) {

  $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );
  $combined_evs = slackemon_get_combined_evs( $pokemon->evs );
  $is_desktop   = 'desktop' === slackemon_get_player_menu_mode();

  $attachment = [
    'text' => (
      '*' .
      ( 0 == $pokemon->hp ? ':skull: ' : '' ) .
      ( slackemon_is_legendary( $pokemon->pokedex ) ? ':star2: ' : '' ) .
      slackemon_readable( $pokemon->name, false ) .
      slackemon_get_gender_symbol( $pokemon->gender ) .
      ( $is_desktop ? '  •  ' : ' •  ' ) .
      $pokemon->cp . ' CP' . '  •  ' .
      'L ' . $pokemon->level .
      ( $is_desktop ? '  •  ' . $combined_evs . ' EV' . ( 1 === $combined_evs ? '' : 's' ) : '' ) . '     ' .
      slackemon_emojify_types( join( ' ' , $pokemon->types ), false ) .
      '*' .
      ( $is_desktop ? "\n" : "\n\n" ) .
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
      ( $is_desktop ? ' • ' : "\n" ) .
      'Sp Attk '    . $pokemon->stats->{'special-attack'}  . ' • ' .
      'Sp Defense ' . $pokemon->stats->{'special-defense'} . ' • ' .
      'Speed '      . $pokemon->stats->speed
    ),
    'actions' => [
      [
        'name'  => 'pokemon/view/from-battle-menu',
        'text'  => ':eye: View Info',
        'type'  => 'button',
        'value' => $pokemon->ts,
      ], [
        'name'  => 'battle-team/remove/from-battle-menu',
        'text'  => ':x: Remove',
        'type'  => 'button',
        'value' => $pokemon->ts,
      ], (
        slackemon_get_player_battle_team_leader() === $pokemon->ts ?
        [] :
        [
          'name'  => 'battle-team/set-leader',
          'text'  => ':smiling_imp: Promote to Leader',
          'type'  => 'button',
          'value' => $pokemon->ts,
        ]
      ),
    ],
    'color' => $pokemon->hp >= $pokemon->stats->hp * .1 ? slackemon_get_color_as_hex( $species_data->color->name ) : '',
    'thumb_url' => (
      $is_desktop ?
      slackemon_get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/' . $pokemon->name . '.gif' ) :
      ''
    ),
  ];

  return $attachment;

} // Function slackemon_get_battle_menu_pokemon_attachment

function slackemon_get_battle_menu_add_attachment( $count_helper = 'a' ) {

  $player_data = slackemon_get_player_data();
  slackemon_sort_player_pokemon( $player_data->pokemon, [ 'name', 'is_favourite', 'level', 'cp', 'ts' ] );

  // Prepare message menu options - if we have more than 100 Pokemon, we need to set up an interactive search to
  // prevent Slack from cutting the additional Pokemon off.
  if ( count( $player_data->pokemon ) > 100 ) {
    $message_menu_options = [
      'data_source' => 'external',
      'min_query_length' => 1,
    ];
  } else {
    $message_menu_options = [
      'options' => array_map(
        function( $_pokemon ) {

          // Ensure current members of the battle team don't get listed again
          if ( $_pokemon->is_battle_team ) {
            return false;
          }

          return slackemon_get_battle_menu_add_option( $_pokemon );

        },
        $player_data->pokemon
      ),
    ];
  }

  $attachment = [
    'text'      => '*Select ' . $count_helper . ' Pokémon to add to your battle team:*',
    'color'     => '#333333',
    'mrkdwn_in' => [ 'text' ],
    'actions'   => [
      array_merge(
        [
          'name' => 'battle-team/add/from-battle-menu',
          'text' => 'Choose a Pokémon...',
          'type' => 'select',
        ],
        $message_menu_options
      ),
    ],
  ];

  return $attachment;

} // Function slackemon_get_battle_menu_add_attachment

/**
 * Abstracts the formatting of option values for adding to battle teams within the battle menu.
 * Can really be used elsewhere as well.
 */
function slackemon_get_battle_menu_add_option( $_pokemon ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $option = [
    'text' => (
      ( SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ? ':' . $_pokemon->name . ': ' : '' ) .
      slackemon_readable( $_pokemon->name ) .
      ' (L' . floor( $_pokemon->level ) .
      ')' .
      ( $is_desktop   && $_pokemon->is_favourite ? ' :sparkling_heart:' : '' ) .
      ( ! $is_desktop && $_pokemon->is_favourite ? ' *'                 : '' )
    ),
    'value' => $_pokemon->ts,
  ];

  return $option;

} // Function slackemon_get_battle_menu_add_option

// The end!
