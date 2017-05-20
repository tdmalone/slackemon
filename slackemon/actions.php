<?php

// Chromatix TM 24/03/2017

require_once( __DIR__ . '/../init.php' );
change_data_folder( $data_folder . '/pokedex' );

// Store debugging data
file_put_contents( $data_folder . '/last-inbound-action', json_encode( $action ) );

// Run background action
run_background_action( 'slackemon/actions-do.php', $action, $callback_id );

// The end!
