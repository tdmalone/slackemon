<?php
/**
 * Onboarding menu for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_onboarding_menu() {

  $message = [
    'attachments' => [
      [
        'text' => (
          ':pikachu_bounce: *Yay! Welcome, new trainer!*' . "\n\n" .
          'Pokémon can appear at _any_ time of day or night - and you\'ll need to be quick to catch them! ' .
          'Don\'t worry though - you won\'t be bothered by Pokémon during your Slack \'do not disturb\' ' .
          'hours.'
        ),
      ], [
        'title' => 'Ooh, what\'s that rustling in the bushes?!',
        'thumb_url' => slackemon_get_cached_image_url( SLACKEMON_INBOUND_URL . '/_images/tree.gif' ),
        'actions' => [
          [
            'name' => 'onboarding',
            'text' => 'Find my first \'mon!',
            'type' => 'button',
            'value' => 'catch',
            'style' => 'primary',
          ],
        ],
      ],
    ],
  ];

  return $message;

} // Function slackemon_get_onboarding_menu

// The end!
