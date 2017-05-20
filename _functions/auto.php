<?php

// TM 28/01/2017
// Functions that handle automation of Slackemon, generally via cron

function run_automated_slashie( $command, $user_id, $options = [] ) {

	// Prepare the command and its arguments
	$command_parts = explode( ' ', $command );
	$command = $command_parts[0];
	$text = isset( $command_parts[1] ) ? join( ' ', array_slice( $command_parts, 1 ) ) : '';

	// Get the team ID & command token
	$team_id = SLACK_USERS[ $user_id ]['team_id'];
	$token = SLACK_TOKENS_BY_COMMAND[ $team_id ][ $command ];

	// Prepare options
	$options['return_result'] = isset( $options['return_result'] ) ? $options['return_result'] : false;
	$options['run_mode']      = isset( $options['run_mode'] )      ? $options['run_mode']      : 'unknown';

	// Put payload together
	$params = [

		// The usual expected data
		// Reference: https://api.slack.com/slash-commands#triggering_a_command
		'token'		 	 	 => $token,
		'team_id' 		 => $team_id,
		'team_domain'  => '', // Unknown when autorun
		'channel_id' 	 => '', // Unknown when autorun
		'channel_name' => '', // Unknown when autorun
		'user_id' 		 => $user_id,
		'user_name' 	 => '', // Unknown when autorun
		'command' 		 => $command,
		'text' 				 => $text,
		'response_url' => GENERIC_WEBHOOKS[ $team_id ],

		// Our own custom data

		// Instructs send2slack() to either send the response to the user_id channel (AUTORUN), or return it
		'special_mode' => $options['return_result'] ? 'RETURN' : 'AUTORUN',

		// Send through the run mode, for logging purposes
		'run_mode' => $options['run_mode'],

	];

	// Run the command - requires INBOUND_URL to be defined in config.php
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, INBOUND_URL );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	$result = curl_exec( $ch );

	// DEBUG ONLY - potential security risk as the token will be exposed
	//preint( $params );

	// Return the initial result of the automated command to the caller
	return $result;

} // Function run_automated_slashie

function check_cron_value( $requested, $current ) {

	// An asterisk always returns true
	if ( '*' === $requested ) {
		return true;
	}

	// If the values are the same, return true
	if ( $current == $requested ) {
		return true;
	}

	// If the requested value contains a comma, check that the current value is one of the options
	if ( false !== strpos( $requested, ',' ) ) {
		$options = explode( ',', $requested );
		if ( in_array( $current, $options ) ) {
			return true;
		} else {
			return false;
		}
	}

	// If the requested value contains a hyphen, check that the current value is in the range
	if ( preg_match( '/^(\d{1,2})\-(\d{1,2})$/', $requested, $matches ) ) {
		if ( $current >= $matches[1] && $current <= $matches[2] ) {
			return true;
		} else {
			return false;
		}
	}

	// If the requested value contains a forward slash & asterisk, check that the current value is divisible
	// by the requested amount
	if ( preg_match( '/^\*\/(\d{1,2})$/', $requested, $matches ) ) {
		if ( $current % $matches[1] == 0 ) {
			return true;
		} else {
			return false;
		}
	}

	// If we've got here, it's not a match, so return false
	return false;

} // Function check_cron_value

// The end!
