<?php

// TM 09/12/2016
// Main entry point for Slackemon requests

// Before starting, determine if we're receiving an inbound action or option request from our Slack app
$payload = json_decode( file_get_contents( 'php://input' ) );
if ( ! $payload && isset( $_REQUEST['payload'] ) ) {
	$payload = json_decode( $_REQUEST['payload'] );
}

if ( $payload ) {

	// Action, or message menu options request?
	if ( isset( $payload->actions ) && count( $payload->actions ) ) {
		$action = $payload;
		require_once( __DIR__ . '/actions.php' );
		exit();
	} elseif ( isset( $payload->action_ts ) && isset( $payload->callback_id ) ) {
		$options_request = $payload;
		require_once( __DIR__ . '/options-request.php' );
		exit();
	}
}

// Otherwise, let's get going - no-one will get past here now unless they're authorised with a Slack App token
require_once( __DIR__ . '/init.php' );

// Init the once-off, entry-point stuff
define( 'COMMAND', $_POST['command'] );

// Finally, invoke the command by requiring it!

$command_name = substr( COMMAND, 1 );
$default_entry_point = __DIR__ . '/' . $command_name . '/' . $command_name . '.php';

if ( file_exists( $default_entry_point ) ) {

	require( $default_entry_point );

	if ( isset( $_ENV['APP_ENV'] ) && 'testing' === $_ENV['APP_ENV'] ) {
		// We're here to prevent exiting during a unit test
	} else {
		exit();
	}

} else {
	exit( 'Oops! Command instructions could not be found. Please contact <@' . SLACKEMON_MAINTAINER . '> for help.' );
}

// The end!
