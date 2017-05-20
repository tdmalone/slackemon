<?php

// Chromatix TM 24/03/2017

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

$args = check_subcommands();
$allowed_subcommands = [ 'spawn', 'maybe-spawn', 'battle-updates', 'happiness-updates' ];

if (
  slackemon_is_player() ||
  ( isset( $args[0] ) && in_array( $args[0], $allowed_subcommands ) )
) {

  // Run background command
  run_background_command( 'slackemon/commands.php', $args );
  exit();

}

// Otherwise assume onboarding

$attachments = [
  [
    'text' => (

      ':pokeball: *Welcome to Slackémon Go!*' . "\n\n" .
      'Slackémon Go is a Pokémon Go-inspired game for :slack:. Once you start playing, Pokémon randomly appear on ' .
      'Slack - and you\'ll have a short time to catch them before they run away!' . "\n\n" .
      'Using slash commands, you can then manage your Pokémon collection - and even use them in battle against ' .
      'other trainers.'

    ),
    'mrkdwn_in' => [ 'text' ],
  ], [
    'title' => 'So, what are you waiting for?!',
    'thumb_url' => get_cached_image_url( INBOUND_URL . '/_images/slackemon-ampharos.gif' ),
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

output2slack([ 'attachments' => $attachments ]);

// The end!
