<?php

// Chromatix TM 24/03/2017

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

// Run as a background command
$args = check_subcommands();
run_background_command( 'slackemon/commands.php', $args );

// The end!
