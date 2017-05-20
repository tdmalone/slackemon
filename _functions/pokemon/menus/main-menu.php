<?php

// Chromatix TM 04/04/2017
// Main menu functions for Slackemon Go

function slackemon_get_main_menu() {

  $player_data = slackemon_get_player_data();
  $latest_news = slackemon_get_latest_news();
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();
  $pokemon_array_keys = array_keys( $player_data->pokemon );

  $most_recent_pokemon = (
    count( $pokemon_array_keys ) ?
    $player_data->pokemon[ array_pop( $pokemon_array_keys ) ] :
    false
  );

  if ( $most_recent_pokemon ) {
    $most_recent_species_data = slackemon_get_pokemon_species_data( $most_recent_pokemon->pokedex );
  }

  $unique_caught = 0;
  $total_caught = 0;

  foreach ( $player_data->pokedex as $entry ) {
    if ( $entry->caught ) {
      $unique_caught++;
      $total_caught += $entry->caught;
    }
  }

  $players_online = count( slackemon_get_player_ids([ 'active_or_battle_only' => true ]) );
  $weather = slackemon_get_weather();
  $weather_condition = slackemon_get_weather_condition();

  if ( $weather_condition ) {
    switch ( $weather_condition ) {
      case 'Sunny':       $weather_emoji = ':sunny:';      break; // Day version of 'Clear'
      case 'Clear':       $weather_emoji = ':sparkles:';   break; // Night version of 'Clear'
      case 'Cloudy':      $weather_emoji = ':cloud:';      break;
      case 'Windy';       $weather_emoji = ':dash:';       break;
      case 'Raining':     $weather_emoji = ':rain_cloud:'; break;
      case 'Stormy':      $weather_emoji = ':lightning:';  break;
      case 'Snowing':     $weather_emoji = ':snowflake:';  break;
      case 'Hailing';     $weather_emoji = ':snowflake:';  break;
      case 'Cold';        $weather_emoji = ':droplet:';    break;
      case 'Hot';         $weather_emoji = ':fire:';       break;
      default:
        if (
          false !== strpos( $weather_condition, 'Fog' ) ||
          false !== strpos( $weather_condition, 'Mist' ) ||
          false !== strpos( $weather_condition, 'Haze' ) ||
          false !== strpos( $weather_condition, 'Smoke' )
        ) {
          $weather_emoji = ':fog:';
        } else {
          $weather_emoji = '';
        }
      break;
    }
  }

  $message = [
    'text' => (
      ':pokeball: *Welcome to Slackémon Go, ' . get_user_first_name() . '!*'. "\n" .
      ':part_alternation_mark: ' . number_format( $player_data->xp ) . ' XP     ' .
      ( $is_desktop ? '' : '     ' ) .
      ':world_map: ' . pokedex_readable( slackemon_get_player_region() ) .
      ( $is_desktop ? '     ' : "\n" ) .
      (
        $weather_condition ?
        $weather_emoji . ' ' . $weather_condition . ', ' . round( $weather->main->temp ) . '°' :
        ''
      ) . '     ' . (
        slackemon_is_daytime() ?
        ':sun_with_face: Daytime' . ( $weather ? ' til ' . date( 'g:ia', $weather->sys->sunset ) : '' ) :
        ':new_moon_with_face: Night-time' . ( $weather ? ' til ' . date( 'g:ia', $weather->sys->sunrise ) : '' )
      )
    ),
    'attachments' => [
      [
        'text' => (
          'You have *' . count( $player_data->pokemon ) . ' Pokémon* on your team' . "\n" .
          'You have won *' . $player_data->battles->won . ' trainer battles*' .
          (
            $is_desktop ? 
            ' (participated in ' . $player_data->battles->participated . ')' :
            ''
          ) . "\n" .
          'You have caught *' . $unique_caught . ' unique Pokémon*' .
          ( $is_desktop ? ' (' . $total_caught . ' total)' : '' )
        ),
        'color' => 'good',
        'mrkdwn_in' => [ 'text' ],
      ], [
        'text' => (
          slackemon_is_player_muted() ?
          (
            ':mute: *You are currently set as offline.*' . "\n" .
            'You can still use Slackémon, but you will not see nearby Pokémon or get battle invites.'
          ) : (
            slackemon_is_player_dnd() ?
            (
              ':no_entry: *Your Slack account is set to _Do Not Disturb_.*' . "\n" .
              'You can still use Slackémon, but you will not appear as online nor get any notifications. '
            ) : (
              slackemon_is_player_in_battle() ?
              (
                ':no_entry: *You are currently in battle mode.*' . "\n" .
                'You can still use the menu, but you will not see nearby Pokémon until the battle is over.'
              ) : (
                $most_recent_pokemon ?
                'Your last catch was ' .
                ':' . $most_recent_pokemon->name . ': ' .
                '*' . pokedex_readable( $most_recent_pokemon->name, false ) .
                slackemon_get_gender_symbol( $most_recent_pokemon->gender ) . '*' .
                (
                  $is_desktop ? ' ' .
                  get_relative_time( $most_recent_pokemon->ts ) :
                  ''
                ) :
                ''
              )
            )
          )
        ),
        'color' => (
          slackemon_is_player_muted() || slackemon_is_player_dnd() || slackemon_is_player_in_battle() ?
          'danger' :
          ( $most_recent_pokemon ? slackemon_get_color_as_hex( $most_recent_species_data->color->name ) : '' )
        ),
        'mrkdwn_in' => [ 'text' ],
      ], (
        count( $latest_news ) ?
        [
          'color' => '#333333',
          'text' => '*Lᴀᴛᴇsᴛ Nᴇᴡs*' . "\n" . join( "\n", $latest_news ), // Latest News
          'mrkdwn_in' => [ 'text' ],
        ] :
        []
      ), [
        'text' => '*Mᴀɪɴ Mᴇɴᴜ*', // Main Menu
        'mrkdwn_in' => [ 'text' ],
        'color' => '#333333',
        'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
        'actions' => [
          [
            'name' => 'pokemon/list',
            'text' => ( $is_desktop ? ':pika2:' : ':monkey:' ) . ' Pokémon',
            'type' => 'button',
            'value' => 'main',
          ], (
            count( $player_data->items ) ?
            [
              'name' => 'items',
              'text' => ':handbag: Bag',
              'type' => 'button',
              'value' => 'main',
            ] :
            []
          ), [
            'name' => 'battles',
            'text' => ':facepunch: Battles',
            'type' => 'button',
            'value' => 'main',
          ], [
            'name' => 'travel',
            'text' => ':world_map: Travel',
            'type' => 'button',
            'value' => 'main',
          ], [
            'name' => 'achievements',
            'text' => ':sports_medal: Achievements',
            'type' => 'button',
            'value' => 'main',
          ],
        ],
      ], [
        'fallback' => SLACKEMON_ACTION_CALLBACK_ID,
        'footer' => (
          SLACKEMON_ACTION_CALLBACK_ID . ' v' . SLACKEMON_VERSION . ' - ' .
          $players_online . ' player' . ( 1 === $players_online ? '' : 's' ) . ' online'
        ),
        'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
        'actions' => [
          (
            slackemon_is_player_in_battle() ?
            [] :
            [
              'name' => 'mute',
              'text' => slackemon_is_player_muted() ? ':mute: Offline' : ':loud_sound: Online',
              'type' => 'button',
              'value' => slackemon_is_player_muted() ? 'unmute' : 'mute',
              'style' => slackemon_is_player_muted() ? 'danger' : 'primary',
            ]
          ), [
            'name' => 'menu_mode',
            'text' => $is_desktop ? ':desktop_computer: Mobile Off' : ':iphone: Mobile On',
            'type' => 'button',
            'value' => $is_desktop ? 'mobile' : 'desktop',
            'style' => $is_desktop ? '' : 'primary',
          ], [
            'name' => 'tools',
            'text' => ':hammer: Tools',
            'type' => 'button',
            'value' => 'main',
          ],
        ],
      ],
    ],
  ];

  return $message;

} // Function slackemon_get_main_menu

function slackemon_back_to_menu_attachment() {

  $attachment = [
    'fallback' => 'Back to Menu',
    'color' => '#333333',
    'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
    'actions' => [
      [
        'name' => 'menu',
        'text' => ':leftwards_arrow_with_hook: Main Menu',
        'type' => 'button',
        'value' => 'main',
      ],
    ],
  ];

  return $attachment;

} // Function slackemon_back_to_menu_attachment

function slackemon_get_latest_news() {

  $latest_news = [
    
  ];

  return array_merge( SLACKEMON_ADDITIONAL_NEWS, $latest_news );

} // Function slackemon_get_latest_news

// The end!
