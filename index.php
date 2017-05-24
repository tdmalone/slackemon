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
define( 'COMMAND', ( // Support a command alias, or fallback to the directly defined command
	defined( 'SLASH_COMMANDS' ) && isset( SLASH_COMMANDS[ $_REQUEST['command'] ]['alias_of'] ) ?
	SLASH_COMMANDS[ $_REQUEST['command'] ]['alias_of'] :
	$_REQUEST['command']
));
define( 'MAINTAINER', ( // Support per-command maintainers, or fallback to global maintainers
	defined( 'SLASH_COMMANDS' ) && isset( SLASH_COMMANDS[ COMMAND ]['maintainer'][ TEAM_ID ] ) ?
	SLASH_COMMANDS[ COMMAND ]['maintainer'][ TEAM_ID ] :
	GLOBAL_MAINTAINERS[ TEAM_ID ]
));

// Get the settings for this command
$command_settings = get_command_settings();

// Finally, check that we have an entry point defined for the command (or whether the default entry point exists),
// and invoke it by requiring it

$command_name = substr( COMMAND, 1 );
$default_entry_point = __DIR__ . '/' . $command_name . '/' . $command_name . '.php';

if ( isset( $command_settings['entry_point'] ) && file_exists( $command_settings['entry_point'] ) ) {
	require( $command_settings['entry_point'] );
	exit();
} elseif ( file_exists( $default_entry_point ) ) {
	require( $default_entry_point );
	exit();
} else {
	exit( 'Oops! Command instructions could not be found. Please contact <@' . MAINTAINER . '> for help.' );
}

// The end!
