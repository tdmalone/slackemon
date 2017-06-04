<?php

// Chromatix TM 24/03/2017

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

// Run as a background command, unless this is a test run
$args = check_subcommands();
if ( isset( $args[0] ) && 'test' === $args[0] ) {
	echo 'Test run.';
} else {
	run_background_command( 'slackemon/commands.php', $args );
}

// The end!
