<?php
/**
 * Generic helper functions for Slackemon.
 *
 * @package Slackemon
 */

// Other helper function files
require_once( __DIR__ . '/apis.php'  );
require_once( __DIR__ . '/auto.php'  );
require_once( __DIR__ . '/color.php' );
require_once( __DIR__ . '/filesystem.php' );
require_once( __DIR__ . '/time.php'  );

/** A quick function to change the data folder, and create it if it doesn't exist. */
function slackemon_change_data_folder( $new_data_folder ) {

  global $data_folder;
  $data_folder = $new_data_folder;

  if ( ! is_dir( $data_folder ) ) {
    mkdir( $data_folder, 0777, true );
  }

}

/**
 * Like exit(), but ensures first that any open connections are closed and locks removed.
 * Also takes care of avoiding completely exiting during unit tests.
 *
 * MUST be called at the end of every script run.
 *
 * Call like this to ensure that during unit tests, the calling function doesn't run any further:
 *     return slackemon_exit();
 *
 * @param string|int $status Passed directly through to PHP's exit() function.
 * @link http://php.net/exit
 */
function slackemon_exit( $status = '' ) {
  
  // Attempt to close database connection if the database file was included.
  if ( function_exists( 'slackemon_pg_close' ) ) {
    slackemon_pg_close();
  }

  // Just return the status message if we're running unit tests, as we don't want to exit from those.
  if ( 'testing' === getenv( 'APP_ENV' ) ) {
    return $status;
  }

  exit( $status );

} // Function slackemon_exit

/**
 * A quick function to check whether a valid subcommand has been provided, returning the exploded arguments.
 * If a welcome message is also provided, that will be shown and processing will be exited IF subcommand is not valid.
 */
function check_subcommands( $allowed_subcommands = [], $welcome_message = '' ) {

  // Convert the arguments to lowercase & remove excess spaces
  $args = explode( ' ', strtolower( preg_replace( '/\s+/', ' ', $_POST['text'] ) ) );

  if ( $welcome_message ) {
    if ( ! count( $args ) || ! $args[0] || ! in_array( $args[0], $allowed_subcommands ) ) {
      if ( is_string( $welcome_message ) ) {
        return slackemon_exit( $welcome_message );
      } else {
        header( 'Content-type: application/json' );
        return slackemon_exit( json_encode( $welcome_message ) );
      }
    }
  }

  return $args;
  
} // Function check_subcommands

/** Run subcommand in the background while the main command returns a waiting response to Slack. */
function slackemon_run_background_command( $path, $args, $additional_fields = [], $additional_fields_as_json = false ) {

  // Build command URL
  $command_url = 'http://' . $_SERVER['SERVER_NAME'];
  $command_url .= 80 != $_SERVER['SERVER_PORT'] && 443 != $_SERVER['SERVER_PORT'] ? ':' . $_SERVER['SERVER_PORT'] : '';
  $command_url .= str_replace( basename( $_SERVER['SCRIPT_NAME'] ), '', $_SERVER['SCRIPT_NAME'] );
  $command_url .= $path;

  // Should we timeout quickly? Because Slack requires a 3-second response, this is the default. However, custom
  // implementations outside of Slack may want to wait and get the output.
  if ( isset( $_POST['special_mode'] ) && 'RETURN' === $_POST['special_mode'] ) {
    $timeout = false;
  } else {
    $timeout = SLACKEMON_CURL_TIMEOUT;
  }

  // Build command data
  $command_data = [

    // Pass through all the usual expected data
    // Reference: https://api.slack.com/slash-commands#triggering_a_command
    'token'        => SLACKEMON_SLACK_TOKEN,
    'team_id'      => TEAM_ID,
    'team_domain'  => $_POST['team_domain'],
    'channel_id'   => $_POST['channel_id'],
    'channel_name' => $_POST['channel_name'],
    'user_id'      => USER_ID,
    'user_name'    => $_POST['user_name'],
    'command'      => COMMAND,
    'text'         => $_POST['text'],
    'response_url' => RESPONSE_URL,
    
    // Pass through our own custom data
    'args'         => $args,
    'maintainer'   => SLACKEMON_MAINTAINER,
    'special_mode' => isset( $_POST['special_mode'] ) ? $_POST['special_mode'] : '', // For cron runs
    
  ];

  // Hook in any additional fields
  if ( $additional_fields_as_json ) {
    $command_data['additional_fields'] = json_encode( $additional_fields );
  } else {
    $command_data = array_merge( $command_data, $additional_fields );
  }

  // Prepare and send the command

  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, $command_url );
  curl_setopt( $ch, CURLOPT_FRESH_CONNECT, true );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $command_data ) );

  if ( $timeout ) {
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
  }

  // Just return the curl object if we're running unit tests, as we don't want to actually invoke commands from those.
  if ( 'testing' === getenv( 'APP_ENV' ) ) {
    curl_close( $ch );
    return $ch;
  }

  $result = curl_exec( $ch );
  curl_close( $ch );

} // Function run_background_command

/** Run action in the background while the main file returns a waiting response to Slack. */
function slackemon_run_background_action( $path, $action, $callback_id ) {

  // Build action URL
  $action_url = 'http://' . $_SERVER['SERVER_NAME'];
  $action_url .= 80 != $_SERVER['SERVER_PORT'] && 443 != $_SERVER['SERVER_PORT'] ? ':' . $_SERVER['SERVER_PORT'] : '';
  $action_url .= str_replace( basename( $_SERVER['SCRIPT_NAME'] ), '', $_SERVER['SCRIPT_NAME'] );
  $action_url .= $path;

  // Curl timeout - this is our trick to make PHP sort-of async
  $timeout = SLACKEMON_CURL_TIMEOUT;

  // Prepare and send the action
  $post_data = [ 'action' => json_encode( $action ), 'callback_id' => $callback_id ];
  
  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, $action_url );
  curl_setopt( $ch, CURLOPT_FRESH_CONNECT, true );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $post_data ) );

  if ( $timeout ) {
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
  }

  // Just return the curl object if we're running unit tests, as we don't want to actually invoke actions from those.
  if ( 'testing' === getenv( 'APP_ENV' ) ) {
    curl_close( $ch );
    return $ch;
  }

  $result = curl_exec( $ch );
  curl_close( $ch );

} // Function run_background_action

/** An easy way to quickly truncate long strings, eg. task titles. */
function maybe_truncate( $string = '', $max_chars = 100 ) {
  if ( strlen( (string) $string ) > (int) $max_chars ) {
    return trim( substr( $string, 0, $max_chars - 3 ) ) . '...';
  } else {
    return $string;
  }
}

// Converts $title to Title Case, and returns the result. 
// HT: https://www.sitepoint.com/title-case-in-php/
function strtotitle( $title ) {

  // Our array of 'small words' which shouldn't be capitalised if they aren't the first word
  $smallwordsarray = [
    'of', 'a', 'the', 'and', 'an', 'or', 'nor', 'but', 'is', 'if', 'then', 'else', 'when',
    'at', 'from', 'by', 'on', 'off', 'for', 'in', 'out', 'over', 'to', 'into', 'with'
  ];

  // Split the string into separate words
  $words = explode( ' ', $title );

  // If this word is the first, or it's not one of our small words, capitalise it
  foreach ( $words as $key => $word) {
    if ( 0 === $key or ! in_array( $word, $smallwordsarray ) ) {
      $words[ $key ] = ucwords( $word );
    }
  }

  // Join the words back into a string
  $newtitle = implode( ' ', $words );

  return $newtitle;

} // Function strtotitle

// The end!
