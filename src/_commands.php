<?php
/**
 * Background-handler for incoming /slackemon Slash command invocations.
 *
 * @package Slackemon
 */

// This file gets called directly, so we must set up the Slackemon environment.
require_once( __DIR__ . '/../lib/init.php' );

slackemon_handle_command( $_POST['args'] );

function slackemon_handle_command( $args ) {

  /**
   * Commands usually called by cron.
   */

  if ( isset( $args[0] ) && 'maybe-spawn' === $args[0] ) {

    $spawn_trigger = [
      'type'    => 'cron',
      'user_id' => USER_ID,
    ];

    slackemon_spawn_debug( 'Maybe spawning, please wait...' );
    slackemon_maybe_spawn( $spawn_trigger );

    return slackemon_exit();

  }

  if ( isset( $args[0] ) && 'battle-updates' === $args[0] ) {
    slackemon_do_battle_updates();
    return slackemon_exit();
  }

  if ( isset( $args[0] ) && 'happiness-updates' === $args[0] ) {
    slackemon_do_happiness_updates();
    return slackemon_exit();
  }

  /**
   * Commands for dev/debug use only.
   */

  // Instantly generates a spawn, of a particular Pokedex ID if supplied.
  if ( isset( $args[0] ) && 'spawn' === $args[0] ) {

    $spawn_trigger = [
      'type'    => 'manual',
      'user_id' => USER_ID,
    ];

    $spawn_region     = slackemon_get_player_region();
    $spawn_timestamp  = false;
    $spawn_pokedex_id = isset( $args[1] ) ? $args[1] : false;

    slackemon_spawn_debug( 'Generating a spawn, please wait...' );
    slackemon_spawn( $spawn_trigger, $spawn_region, $spawn_timestamp, $spawn_pokedex_id );

    return slackemon_exit();

  }

  /**
   * Anything else (eg. generally /slackemon being called on its own).
   */

  if ( slackemon_is_player() ) {

    // Force empty the DND cache.
    slackemon_is_player_dnd( USER_ID, true );

    $message = slackemon_get_main_menu();
    $message['channel'] = USER_ID;

    if ( 'directmessage' !== $_POST['channel_name'] ) {
      echo '*Welcome to Slackemon!*' . "\n" . 'See your direct messages for the Slackemon menu. :simple_smile:';
    }

    // We need to use post2slack here so that we have a fully modifiable message we can access via the action payloads'
    // original_message parameter - otherwise, with an emphermal slash command message, we don't have that access.
    slackemon_post2slack( $message );

  } else {

    /**
     * Not a player yet - it's onboarding time!
     */

    $attachments = [
      [
        'text' => (

          ':pokeball: *Welcome to Slackémon!*' . "\n\n" .
          'Slackémon is a Pokémon Go-inspired game for :slack:. Once you start playing, Pokémon randomly appear on ' .
          'Slack - and you\'ll have a short time to catch them before they run away!' . "\n\n" .
          'Using slash commands, you can then manage your Pokémon collection - and even use them in battle against ' .
          'other trainers.'

        ),
        'mrkdwn_in' => [ 'text' ],
      ], [
        'title' => 'So, what are you waiting for?!',
        'thumb_url' => slackemon_get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/ampharos.gif' ),
        'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
        'actions' => [
          [
            'name' => 'onboarding',
            'text' => 'Start playing now!',
            'type' => 'button',
            'value' => 'join',
            'style' => 'primary',
          ]
        ],
      ],
    ];

    slackemon_send2slack( [ 'attachments' => $attachments ] );

  } // If slackemon_is_player / else

  return slackemon_exit();

} // Function slackemon_handle_command

// The end!
