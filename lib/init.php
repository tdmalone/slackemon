<?php
/**
 * Initialises the Slackemon environment, including authentication.
 *
 * @package Slackemon
 */

// Activate error logging.
// TODO: This should be defined elsewhere rather than changing at runtime.
ini_set( 'error_reporting', E_ALL );
ini_set( 'log_errors', '1' );
ini_set( 'display_errors', '0' );
ini_set( 'display_startup_errors', '0' );
if ( 'development' === getenv( 'APP_ENV' ) ) {
  ini_set( 'display_errors', '1' );
  ini_set( 'display_startup_errors', '1' );
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

      slackemon_error_log( 'Unauthorised action or options request from ' . slackemon_get_requester_data() );
      slackemon_error_log( $_REQUEST );

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

      $requester_data = slackemon_get_requester_data();

      // We skip logging this request if it comes from Slackbot's link expander, because this occurs everytime
      // a Slackemon deployment notification is posted to a Slack channel.
      if ( false === strpos( $requester_data, 'Slackbot-LinkExpanding' ) ) {
        slackemon_error_log( 'Unauthorised command invocation from ' . $requester_data );
        slackemon_error_log( $_REQUEST );
      }

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
slackemon_change_data_folder( $data_folder );

// Define our constants (slash command invocation mode).
if ( ! defined( 'USER_ID' ) && isset( $_POST['user_id'] ) ) {

  // Set some Slack defaults right away.
  define( 'TEAM_ID',      $_POST['team_id']      );
  define( 'USER_ID',      $_POST['user_id']      );
  define( 'RESPONSE_URL', $_POST['response_url'] );

  // Determine if other custom variables have already been set: if so, assign them, if not, index.php will set them.
  if ( ! defined( 'COMMAND' ) && isset( $_POST['command'] ) ) {
    define( 'COMMAND', $_POST['command'] );
  }
}

// Define our constants (action / options request mode).
if ( ! defined( 'USER_ID' ) && ( isset( $action->user ) || isset( $options_request->user ) ) ) {

  $request = isset( $action ) ? $action : $options_request;

  // Set some Slack defaults right away.
  define( 'TEAM_ID', $request->team->id );
  define( 'USER_ID', $request->user->id );

  if ( isset( $request->response_url ) ) {
    define( 'RESPONSE_URL', $request->response_url );
  }

  define( 'COMMAND', '/' . ( isset( $callback_id ) ? $callback_id[0] : $request->callback_id ) );

}

/**
 * Abstracts error logging functions, in case we need to modify it in the future.
 * Defined here, as it is used in this file before we are able to include other main function files.
 *
 * @param mixed $message A message to send to the logger. Accepts any type; arrays and objects will be print_r()'ed.
 */
function slackemon_error_log( $message ) {

  // Support array or objects being logged
  if ( is_array( $message ) || is_object( $message ) ) {
    $message = print_r( $message, true );
  }

  // Add a prefix we can use to pick up these messages in log searches
  // 'PHP Log: ' fits well with the inbuilt eg. PHP Warning, PHP Notice, etc.
  $message = 'PHP Log: ' . $message;

  error_log( $message );

} // Function slackemon_error_log

/**
 * Returns a basic string of data on the source of the incoming request.
 * Defined here, as it is used in this file for reporting on authorised requests.
 */
function slackemon_get_requester_data() {

  $ip_addresses = slackemon_get_requester_ip_addresses();
  $hostnames = [];

  foreach ( $ip_addresses as $ip_address ) {
    $hostname = gethostbyaddr( $ip_address );
    $hostnames[] = $hostname && $hostname !== $ip_address ? $hostname . ' (' . $ip_address . ')' : $ip_address;
  }

  $requester_data = join( ', ', $hostnames ) . ' / ' . $_SERVER['HTTP_USER_AGENT'];

  return $requester_data;

} // Function slackemon_get_requester_data

/**
 * Attempts to return the requester's IP address, either directly or via a proxy.
 * Returns as an array, because HTTP_X_FORWARDED_FOR can have multiple IPs in it.
 *
 * @return array $ip_addresses
 */
function slackemon_get_requester_ip_addresses() {

  if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && $_SERVER['HTTP_X_FORWARDED_FOR'] ) {

    // Make an array out of the potentially comma-separated list, after removing spaces because some implementations
    // separate with ', ' rather than just ','.
    return explode( ',', preg_replace( '/\s/', '', $_SERVER['HTTP_X_FORWARDED_FOR'] ) );

  }

  return [ $_SERVER['REMOTE_ADDR'] ];

} // Function slackemon_get_requester_ip_addresses

// The end!
