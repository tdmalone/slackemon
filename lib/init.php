<?php
/**
 * Initialises the Slackemon environment, including authentication.
 *
 * @package Slackemon
 */

// Activate error logging.
// TODO: This should be defined elsewhere rather than changing at runtime.
if ( 'development' === getenv( 'APP_ENV' ) ) {
  ini_set( 'log_errors', '1' );
  ini_set( 'error_log', __DIR__ . '/../error_log' );
}

// Define time-based constants.
// HT: https://core.trac.wordpress.org/browser/tags/4.7.3/src/wp-includes/default-constants.php#L107.
define( 'HALF_A_MINUTE',     30 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS',   60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS',    24 * HOUR_IN_SECONDS   );
define( 'WEEK_IN_SECONDS',    7 * DAY_IN_SECONDS    );
define( 'MONTH_IN_SECONDS',  30 * DAY_IN_SECONDS    );
define( 'YEAR_IN_SECONDS',  365 * DAY_IN_SECONDS    );

// Get config from environment variables.
require_once( __DIR__ . '/../config.php' );

// Set some config variables in stone.
date_default_timezone_set( SLACKEMON_TIMEZONE );
setlocale( LC_MONETARY, SLACKEMON_MONETARY_LOCALE );
$data_folder = __DIR__ . '/../' . SLACKEMON_DATA_FOLDER;

// Check that this request is authorised.
// Authorisation can be skipped by the calling file by setting SKIP_AUTH to true - this should only be done if the file
// does its own authorisation!
if ( ! defined( 'SKIP_AUTH' ) || ! SKIP_AUTH ) {

  if ( isset( $action ) || isset( $options_request ) ) {

    // Action or options request auth.
    $auth_data   = isset( $action ) ? $action   : $options_request;
    $auth_reason = isset( $action ) ? 'actions' : 'options';

    if (
      ! isset( $auth_data->token    ) ||
      ! isset( $auth_data->team->id ) ||
      SLACKEMON_SLACK_TOKEN   !== $auth_data->token ||
      SLACKEMON_SLACK_TEAM_ID !== $auth_data->team->id
    ) {
      http_response_code( 403 );
      exit(
        'Not authorised for this action or options request. ' .
        'Check that your app token has been configured properly.'
      );
    }

  } else {

    // General slash command auth.

    if (
      ! isset( $_POST['token'] ) ||
      SLACKEMON_SLACK_TOKEN   !== $_POST['token'] ||
      SLACKEMON_SLACK_TEAM_ID !== $_POST['team_id']
    ) {
      http_response_code( 403 );
      exit(
        'Not authorised for this command invocation. ' .
        'Check that your app token has been configured properly.'
      );
    }
  }
} // If not SKIP_AUTH

// Require the app.
require_once( __DIR__ . '/../src/_app.php' );

// Make sure data folder exists.
change_data_folder( $data_folder );

// Define our constants (slash command invocation mode).
if ( ! defined( 'USER_ID' ) && isset( $_POST['user_id'] ) ) {

  // Set some Slack defaults right away.
  define( 'TEAM_ID',      $_POST['team_id']      );
  define( 'USER_ID',      $_POST['user_id']      );
  define( 'RESPONSE_URL', $_POST['response_url'] );

  // Determine if other custom variables have already been set: if so, assign them, if not, index.php will set them.
  if ( ! defined( 'COMMAND' ) && isset( $_POST['command'] ) ) {
    define( 'COMMAND',    $_POST['command'] );
  }
}

// Define our constants (action / options request mode).
if ( ! defined( 'USER_ID' ) && ( isset( $action->user ) || isset( $options_request->user ) ) ) {

  $request = isset( $action ) ? $action : $options_request;

  // Set some Slack defaults right away.
  define( 'TEAM_ID',      $request->team->id );
  define( 'USER_ID',      $request->user->id );

  if ( isset( $request->response_url ) ) {
    define( 'RESPONSE_URL', $request->response_url );
  }

  define( 'COMMAND',    '/' . ( isset( $callback_id ) ? $callback_id[0] : $request->callback_id ) );

}

// The end!
