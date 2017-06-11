<?php
/**
 * Entry point for the /slackemon Slash command.
 *
 * @package Slackemon
 */

// Set up the Slackemon environment.
require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

// Run as a background command, as long as this isn't a test run.
$args = check_subcommands();
if ( ! isset( $args[0] ) || 'unit-tests' !== $args[0] ) {
  run_background_command( 'slackemon/commands.php', $args );
}

// The end!
