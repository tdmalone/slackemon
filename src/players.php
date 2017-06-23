<?php
/**
 * Player specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_is_player( $user_id = USER_ID ) {
  global $data_folder;
  $player_filename = $data_folder . '/players/' . $user_id;

  if ( ! slackemon_file_exists( $player_filename, 'store' ) ) {
    return false;
  }

  return true;

} // Function slackemon_is_player

function slackemon_register_player( $user_id = USER_ID ) {

  // Set up player with blank data
  $player_data = [
    'registered' => time(),
    'user_id'    => $user_id,
    'team_id'    => defined( 'TEAM_ID' ) ? TEAM_ID : '',
    'status'     => 1, // 1 == Active, 2 == Muted, 3 == In Battle
    'xp'         => 0,
    'region'     => SLACKEMON_DEFAULT_REGION,
    'pokemon'    => [],
    'pokedex'    => [],
    'battles'    => [
      'won'               => 0,
      'participated'      => 0,
      'last_won'          => false,
      'last_participated' => false,
    ],
    'items'      => [],
    'version'    => SLACKEMON_VERSION,
  ];

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_register_player

function slackemon_save_player_data( $player_data, $user_id = USER_ID ) {
  global $data_folder, $_cached_slackemon_player_data;

  $player_filename = $data_folder . '/players/' . $user_id;

  $_cached_slackemon_player_data[ $user_id ] = $player_data;
  return slackemon_file_put_contents( $player_filename, json_encode( $player_data ), 'store' );

} // Function slackemon_save_player_data

function slackemon_get_player_data( $user_id = USER_ID ) {
  global $data_folder, $_cached_slackemon_player_data;

  if ( isset( $_cached_slackemon_player_data[ $user_id ] ) ) {
    return $_cached_slackemon_player_data[ $user_id ];
  }

  $player_filename = $data_folder . '/players/' . $user_id;

  // If we couldn't find the player file, store a trace to discover how we got here
  if ( ! slackemon_file_exists( $player_filename, 'store' ) ) {
    slackemon_error_log( $data_folder . '/backtrace-' . $user_id, print_r( debug_backtrace(), true ) );
    return false;
  }

  $player_data = json_decode( slackemon_file_get_contents( $player_filename, 'store' ) );

  // If our player data doesn't exist or is somehow corrupted (eg. JSON file in the middle of being written to),
  // we need to error out right away.
  if ( ! $player_data ) {

    send2slack([
      'text' => (
        ':exclamation: *Oops!* An error occurred accessing your player data. Please try your last action again.' . "\n" .
        'If this problem persists, talk to <@' . SLACKEMON_MAINTAINER . '>.'
      ),
      'channel' => $user_id, // Sending the channel through forces a new message to be sent, rather than replacing
                             // whichever one the user actioned from.
    ]);

    slackemon_error_log( 'Player data file for ' . $user_id . ' could not be accessed - potentially corrupted?' );
    slackemon_exit();

  }

  $_cached_slackemon_player_data[ $user_id ] = $player_data;

  // Ensure player is not caught in a cancelled region if the available regions change
  $regions = slackemon_get_regions();
  if ( ! array_key_exists( $player_data->region, $regions ) ) {
    $player_data->region = SLACKEMON_DEFAULT_REGION;
    slackemon_save_player_data( $player_data, $user_id );
  }

  // Version migrations for player data

  // v0.0.36
  // - Now that spawned Pokemon are correctly saved with their species name rather than variety name, fix any previously
  //   caught Deoxys
  if ( '0.0.36' !== $player_data->version ) {

    $player_data->version = '0.0.36';

    foreach ( $player_data->pokemon as $_pokemon ) {

      if ( 'deoxys-normal' === $_pokemon->name ) {
        $_pokemon->name    = 'deoxys';
        $_pokemon->variety = 'deoxys-normal';
      }

    }

    slackemon_save_player_data( $player_data, $user_id );

  } // If not version

  return $player_data;

} // Function slackemon_get_player_data

function slackemon_search_player_pokemon( $search_string, $user_id = USER_ID ) {

  $player_data    = slackemon_get_player_data( $user_id );
  $player_pokemon = $player_data->pokemon;

  if ( $search_string ) {
    $player_pokemon = array_filter( $player_pokemon, function( $_pokemon ) use ( $search_string ) {
      if ( $search_string === substr( $_pokemon->name, 0, strlen( $search_string ) ) ) {
        return true;
      }
    });
  }

  return $player_pokemon;

} // Function slackemon_search_player_pokemon

function slackemon_get_player_pokemon_data( $spawn_ts, $player_data = null, $user_id = USER_ID ) {

  if ( ! $player_data ) {
    $player_data = slackemon_get_player_data( $user_id );
  }

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      return $_pokemon;
    }
  }

  // If the Pokemon wasn't found and returned, return false
  return false;

} // Function slackemon_get_player_pokemon_data

function slackemon_add_xp( $xp, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  $player_data->xp += $xp;

  $player_data->xp = floor( $player_data->xp );

  if ( slackemon_save_player_data( $player_data, $user_id ) ) {
    return $player_data->xp;
  } else {
    return false;
  }

} // Function slackemon_add_xp

function slackemon_get_player_ids( $options = [] ) {
  global $data_folder;
  $players = slackemon_get_files_by_prefix( $data_folder . '/players/', 'store' );

  // No players at all?
  if ( ! count( $players ) ) {
    return false;
  }

  // Set default options
  if ( ! isset( $options['active_only']           ) ) { $options['active_only']           = false; }
  if ( ! isset( $options['active_or_battle_only'] ) ) { $options['active_or_battle_only'] = false; }
  if ( ! isset( $options['skip_current_user']     ) ) { $options['skip_current_user']     = false; }
  if ( ! isset( $options['region']                ) ) { $options['region']                = false; }

  // Turn into player IDs
  $players = array_map( function( $path ) {
    return pathinfo( $path, PATHINFO_FILENAME );
  }, $players );

  // Skip the current user? Useful for battle challenges :)
  if ( $options['skip_current_user'] ) {
    $players = array_filter( $players, function( $user_id ) {
      if ( USER_ID === $user_id ) {
        return false;
      } else {
        return true;
      }
    });
  }

  // Only active players? Counts out those on mute & in battle; particularly useful for spawn notifications
  if ( $options['active_only'] ) {
    $players = array_filter( $players, function( $user_id ) {
      if ( slackemon_is_player_active( $user_id ) ) {
        return true;
      }
      return false;
    });
  }

  // Only active players incl. those in battle? Useful for online status checks
  if ( $options['active_or_battle_only'] ) {
    $players = array_filter( $players, function( $user_id ) {
      if ( slackemon_is_player_active( $user_id ) || slackemon_is_player_in_battle( $user_id ) ) {
        return true;
      }
      return false;
    });
  }

  // Only players in a particular region? Particularly useful for spawn notifications
  if ( $options['region'] ) {
    $players = array_filter( $players, function( $user_id ) use ( $options ) {
      if ( $options['region'] === slackemon_get_player_region( $user_id ) ) {
        return true;
      }
      return false;
    });
  }

  return $players;

} // Function slackemon_get_player_ids

function slackemon_cancel_player( $user_id = USER_ID ) {
  global $data_folder;

  return slackemon_unlink( $data_folder . '/players/' . $user_id, 'store' );

} // Function slackemon_cancel_player

function slackemon_mute_player( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  if ( 1 !== $player_data->status ) { return false; } // Prevent muting when another status is current
  $player_data->status = 2;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_mute_player

function slackemon_unmute_player( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  if ( 2 !== $player_data->status ) { return false; } // Prevent unmuting when another status is current
  $player_data->status = 1;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_unmute_player

function slackemon_is_player_muted( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  // Protect against status somehow not being set at all, by setting it to not-muted as default
  if ( ! isset( $player_data->status ) ) {
    $player_data->status = 1;
    return slackemon_save_player_data( $player_data, $user_id );
  }

  if ( 2 === $player_data->status ) {
    return true;
  }

  return false;

} // Function slackemon_is_player_muted

function slackemon_set_player_in_battle( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  $player_data->status = 3;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_set_player_in_battle

function slackemon_set_player_not_in_battle( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  if ( 3 !== $player_data->status ) { return false; }
  $player_data->status = 1;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_set_player_not_in_battle

function slackemon_is_player_in_battle( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  if ( 3 === $player_data->status ) {
    return true;
  }

  return false;

} // Function slackemon_is_player_in_battle

function slackemon_is_player_active( $user_id = USER_ID ) {

  if (
    slackemon_is_player_muted( $user_id ) ||
    slackemon_is_player_in_battle( $user_id ) ||
    slackemon_is_player_dnd( $user_id )
  ) {
    return false;
  }

  return true;

} // Function slackemon_is_player_active

function slackemon_is_player_dnd( $user_id = USER_ID, $skip_cache = false ) {
  global $_cached_slackemon_dnd;

  if ( ! isset( $_cached_slackemon_dnd[ $user_id ] ) ) {

    $api_base = 'https://slack.com/api';
    $endpoint = $api_base . '/dnd.info';

    $payload = [
      'token' => SLACKEMON_SLACK_KEY,
      'user' => $user_id,
    ];

    $cache_options = [
      'expiry_age' => ( $skip_cache ? 1 : MINUTE_IN_SECONDS * 5 ), // 1 second if skipping cache, so that it still saves
    ];

    $url = $endpoint . '?' . http_build_query( $payload );
    $dnd_data = json_decode( slackemon_get_cached_url( $url, $cache_options ) );

    // Assume the player isn't DND if we didn't get a response from Slack, because... not much else we can do!
    if ( ! $dnd_data ) {
      return false;
    }

    if ( $dnd_data->dnd_enabled && $dnd_data->next_dnd_start_ts < time() && $dnd_data->next_dnd_end_ts > time() ) {
      $_cached_slackemon_dnd[ $user_id ] = true;
    } else {
      $_cached_slackemon_dnd[ $user_id ] = false;
    }

  }

  return $_cached_slackemon_dnd[ $user_id ];

} // Function slackemon_is_player_dnd

function slackemon_get_player_menu_mode( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  if ( ! isset( $player_data->menu_mode ) ) {
    slackemon_set_player_menu_mode( 'desktop', $user_id );
    return 'desktop';
  }

  return $player_data->menu_mode;

} // Function slackemon_get_player_menu_mode

function slackemon_set_player_menu_mode( $menu_mode, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  $player_data->menu_mode = $menu_mode;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_set_player_menu_mode

// The end!
