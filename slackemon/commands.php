<?php

// Chromatix TM 24/03/2017

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

$args = $_POST['args'];

// For cron
if ( isset( $args[0] ) && 'maybe-spawn' === $args[0] ) {
  slackemon_spawn_debug( 'Maybe spawning, please wait...' );
  slackemon_maybe_spawn([ 'type' => 'cron', 'user_id' => USER_ID ]);
  exit();
}
if ( isset( $args[0] ) && 'battle-updates' === $args[0] ) {
  slackemon_do_battle_updates();
  exit();
}
if ( isset( $args[0] ) && 'happiness-updates' === $args[0] ) {
  slackemon_do_happiness_updates();
  exit();
}

// For dev/debug only - instantly generate a spawn, including a particular Pokedex ID if desired
if ( isset( $args[0] ) && 'spawn' === $args[0] ) {
  slackemon_spawn_debug( 'Generating a spawn, please wait...' );
  slackemon_spawn([ 'type' => 'manual', 'user_id' => USER_ID ], slackemon_get_player_region(), false, isset( $args[1] ) ? $args[1] : false );
  exit();
}

if ( slackemon_is_player() ) {

  // Force empty the DND cache
  slackemon_is_player_dnd( USER_ID, true );

  $message = slackemon_get_main_menu();
  $message['channel'] = USER_ID;

  if ( 'directmessage' !== $_POST['channel_name'] ) {
    echo '*Welcome to Slackemon!*' . "\n" . 'See your direct messages for the Slackemon menu. :simple_smile:';
  }

  // We need to use post2slack here so that we have a fully modifiable message we can access via the action payloads'
  // original_message parameter - otherwise, with an emphermal slash command message, we don't have that access.
  post2slack( $message );

} else {

  // Not a player yet - onboarding time!

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
      'thumb_url' => get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/ampharos.gif' ),
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

  send2slack([ 'attachments' => $attachments ]);

} // If slackemon_is_player / else

// The end!
