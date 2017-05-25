<?php

// TM 28/01/2017
// Initialise the Slackemon environment, including authentication

// Define time-based constants
// HT: https://core.trac.wordpress.org/browser/tags/4.7.3/src/wp-includes/default-constants.php#L107
define( 'HALF_A_MINUTE',     30 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS',   60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS',    24 * HOUR_IN_SECONDS   );
define( 'WEEK_IN_SECONDS',    7 * DAY_IN_SECONDS    );
define( 'MONTH_IN_SECONDS',  30 * DAY_IN_SECONDS    );
define( 'YEAR_IN_SECONDS',  365 * DAY_IN_SECONDS    );

// Require local config, or get config from environment variables
if ( file_exists( __DIR__ . '/config.php' ) ) {
	require_once( __DIR__ . '/config.php' );
} else {
	exit( 'Missing local config file. Have you copied the sample config?' );
} else {
	exit( 'Missing local config file. Have you copied the sample config?' );
}

// Set some config variables in stone
date_default_timezone_set( DEFAULT_TIMEZONE );
setlocale( LC_MONETARY, DEFAULT_MONETARY_LOCALE );
$data_folder = DATA_CACHE_FOLDER;

// Check that this request is authorised
// Authorisation can be skipped by the calling file by setting SKIP_AUTH to true - this should only be done if the file
// does its own authorisation!
if ( ! defined( 'SKIP_AUTH' ) || ! SKIP_AUTH ) {

	if ( isset( $action ) || isset( $options_request ) ) {

		// Action or options request auth

		$auth_data   = isset( $action ) ? $action   : $options_request;
		$auth_reason = isset( $action ) ? 'actions' : 'options';

		if (
			! isset( $auth_data->token ) ||
			! isset( $auth_data->team->id ) ||
			! array_key_exists( $auth_data->token, SLACK_TOKENS ) ||
			! isset( SLACK_TOKENS[ $auth_data->token ][ $auth_reason ] ) ||
			! SLACK_TOKENS[ $auth_data->token ][ $auth_reason ] ||
			SLACK_TOKENS[ $auth_data->token ][ 'team_id' ] !== $auth_data->team->id
		) {
			http_response_code( 403 );
			exit(
				'Not authorised for this action or options request. ' .
				'Check that your app token has been configured properly.'
			);
		}

	} else {

		// General slash command auth

		if (
			! isset( $_REQUEST['token'] ) ||
			! array_key_exists( $_REQUEST['token'], SLACK_TOKENS ) ||
			SLACK_TOKENS[ $_REQUEST['token'] ]['team_id'] !== $_REQUEST['team_id'] ||
			(
				isset( SLACK_TOKENS[ $_REQUEST['token'] ]['commands'] ) &&
				! in_array( $_REQUEST['command'], SLACK_TOKENS[ $_REQUEST['token'] ]['commands'] )
			) || (
				isset( SLACK_TOKENS[ $_REQUEST['token'] ]['command'] ) &&
				SLACK_TOKENS[ $_REQUEST['token'] ]['command'] !== $_REQUEST['command']
			)
		) {
			http_response_code( 403 );
			exit(
				'Not authorised for this command invocation. ' .
				'Check that your app token has been configured properly.'
			);
		}
	}
} // If not SKIP_AUTH

// Require all functions
require_once( __DIR__ . '/_functions/functions.php' );

// Make sure data folder exists
change_data_folder( $data_folder );

// Define our constants (slash command invocation mode)
if ( ! defined( 'USER_ID' ) && isset( $_REQUEST['user_id'] ) ) {

	// Set some Slack defaults right away
	define( 'TEAM_ID',      $_REQUEST['team_id']      );
	define( 'USER_ID',      $_REQUEST['user_id']      );
	define( 'RESPONSE_URL', $_REQUEST['response_url'] );

	// Determine if other custom variables have already been set, and if so, assign them, if not, index.php will set them
	if ( ! defined( 'MAINTAINER' ) && isset( $_REQUEST['maintainer'] ) ) {

		if ( isset( SLACK_USERS[ USER_ID ] ) ) {
			define( 'USER', SLACK_USERS[ USER_ID ] );
		}

		define( 'MAINTAINER', $_REQUEST['maintainer'] );
		define( 'COMMAND',    $_REQUEST['command']    );
		
	}

}

// Define our constants (interactive message mode)
if ( ! defined( 'USER_ID' ) && ( isset( $action->user ) || isset( $options_request->user ) ) ) {

	$request = isset( $action ) ? $action : $options_request;

	// Set some Slack defaults right away
	define( 'TEAM_ID',      $request->team->id );
	define( 'USER_ID',      $request->user->id );

	if ( isset( $request->response_url ) ) {
		define( 'RESPONSE_URL', $request->response_url );
	}

	if ( isset( SLACK_USERS[ USER_ID ] ) ) {
		define( 'USER', SLACK_USERS[ USER_ID ] );
	}

	define( 'COMMAND',    '/' . ( isset( $callback_id ) ? $callback_id[0] : $request->callback_id ) );

	define( 'MAINTAINER', ( // Support per-command maintainers, or fallback to global maintainers
		isset( SLASH_COMMANDS[ COMMAND ]['maintainer'][ TEAM_ID ] ) ?
		SLASH_COMMANDS[ COMMAND ]['maintainer'][ TEAM_ID ] :
		GLOBAL_MAINTAINERS[ TEAM_ID ]
	));

}

// The end!
