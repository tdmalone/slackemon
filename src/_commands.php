<?php
/**
 * Background-handler for incoming /slackemon Slash command invocations.
 *
 * @package Slackemon
 */

// This file gets called directly, so we must set up the Slackemon environment.
require_once( __DIR__ . '/../lib/init.php' );

// Run the below function right away
slackemon_handle_command( $_POST['args'] );

function slackemon_handle_command( $args ) {

  /**
   * Running some commands directly can be powerful (and can mean cheating!) so we must disable them in
   * live environments if they are not being run by cron. To get past here, run_mode must be set and must
   * be 'cron', or APP_ENV must be set and must NOT be 'live'.
   */

  if (
    isset( $args[0] ) &&
    (
      ( isset( $_POST['run_mode'] ) && 'cron' === $_POST['run_mode'] ) ||
      ( APP_ENV && 'live' !== APP_ENV )
    )
  ) {

    switch( $args[0] ) {

      /**
       * Commands usually called by cron.
       */

      case 'maybe-spawn':

        $spawn_trigger = [
          'type'    => isset( $_POST['run_mode'] ) && $_POST['run_mode'] ? $_POST['run_mode'] : 'manual',
          'user_id' => USER_ID,
        ];

        slackemon_spawn_debug( 'Maybe spawning, please wait...' );
        slackemon_maybe_spawn( $spawn_trigger );

        return slackemon_exit();

      break;

      case 'battle-updates':
        slackemon_do_battle_updates();
        return slackemon_exit();
      break;

      case 'happiness-updates':
        slackemon_do_happiness_updates();
        return slackemon_exit();
      break;

      case 'clean-up':
        slackemon_clean_up();
        return slackemon_exit();
      break;

      /**
       * Commands for dev/debug use only.
       */

      // Instantly generates a spawn, of a particular Pokedex ID if supplied (also accepts 'item:x' for an item ID
      // or just 'item' for a definite random item spawn.
      case 'spawn':

        $spawn_trigger = [
          'type'    => isset( $_POST['run_mode'] ) && $_POST['run_mode'] ? $_POST['run_mode'] : 'manual',
          'user_id' => USER_ID,
        ];

        $spawn_region         = slackemon_get_player_region();
        $spawn_timestamp      = false;
        $spawn_specific_id    = isset( $args[1] ) ? $args[1] : false;
        $spawn_specific_level = isset( $args[2] ) ? $args[2] : false;

        slackemon_spawn_debug( 'Generating a spawn in ' . ucfirst( $spawn_region ) . ', please wait...' );
        slackemon_spawn( $spawn_trigger, $spawn_region, $spawn_timestamp, $spawn_specific_id, $spawn_specific_level );

        return slackemon_exit();

      break;

      // Scaffolds a test player file with the requested number of random spawns. May take some time to complete.
      // Side effect: runs clean-up (up to the second) so it can be assured of unique spawn timestamps.
      case 'scaffold':

        $spawn_count = isset( $args[1] ) ? $args[1] : 10;

        slackemon_send2slack( 'Scaffolding ' . $spawn_count . ' spawns...' );

        if ( slackemon_scaffold_player_file( $spawn_count ) ) {
          slackemon_send2slack( 'Your scaffold is complete.' );
        }

        // Intentionally don't exit here - it can be useful to go straight to the main menu to show the
        // new Pokemon count.

      break;

    } // Switch $args[0]
  } // If cron or not live

  /**
   * Anything else (eg. generally /slackemon being called on its own, or if it is called with anything not
   * supported above).
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

    if ( slackemon_is_player_dnd( USER_ID, true ) ) {

      $attachments = [
        [
          'text' => (
            ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':pokeball: ' : '' ) . 
            '*Welcome to Slackémon!*' . "\n\n" .
            'It looks like you are currently in :no_entry: *Do Not Disturb* mode. Slackémon can only notify ' .
            'you of nearby Pokémon when you are fully online.'
          ),
          'mrkdwn_in' => [ 'text' ],
        ], [
          'text' => (
            'Please switch off DND mode by typing `/dnd off`, then try typing ' .
            '`' . SLACKEMON_SLASH_COMMAND . '` again. I\'ll be waiting here for you! :innocent:'
          ),
          'mrkdwn_in' => [ 'text' ],
        ]
      ];

    } else {

      $attachments = [
        [
          'text' => (
            ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':pokeball: ' : '' ) . 
            '*Welcome to Slackémon!*' . "\n\n" .
            'Slackémon is a Pokémon Go-inspired game for :slack:. Once you start playing, Pokémon randomly appear ' .
            'on Slack - and you\'ll have a short time to catch them before they run away!' . "\n\n" .
            'Using slash commands, you can then manage your Pokémon collection - and even use them in battle ' .
            'against other trainers.'
          ),
          'mrkdwn_in' => [ 'text' ],
        ], [
          'title' => 'So, what are you waiting for?!',
          'thumb_url' => (
            slackemon_get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/pikachu-cosplay.gif' )
          ),
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

    }

    slackemon_send2slack( [ 'attachments' => $attachments ] );

  } // If slackemon_is_player / else

  return slackemon_exit();

} // Function slackemon_handle_command

// The end!
