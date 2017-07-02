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

  // Remove any open file locks
  slackemon_remove_file_locks();

  // Attempt to close database connection if the database file was included.
  if ( function_exists( 'slackemon_pg_close' ) ) {
    slackemon_pg_close();
  }

  // Just return the status message if we're running unit tests, as we don't want to exit from those.
  if ( 'testing' === APP_ENV ) {
    return $status;
  }

  // TODO: Are there alternatives to exiting here? Exiting is untestable.
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
      }
      
      header( 'Content-type: application/json' );
      return slackemon_exit( json_encode( $welcome_message ) );

    }
  }

  return $args;
  
} // Function check_subcommands

/** Run a command in the background while the main file returns a response to Slack. */
function slackemon_run_background_command( $path, $args ) {

  $command_data = [

    // Pass through all the usual expected data.
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
    'run_mode'     => isset( $_POST['run_mode'] ) ? $_POST['run_mode'] : '',
    
  ];

  return slackemon_run_in_background( $command_data, $path );

} // Function run_background_command

/** Run an action in the background while the main file returns a response to Slack. */
function slackemon_run_background_action( $path, $action, $callback_id ) {

  $action_data = [
    'action'      => json_encode( $action ),
    'callback_id' => $callback_id,
  ];

  return slackemon_run_in_background( $action_data, $path );

} // Function run_background_action

/** Abstracts the 'run in background' logic used for both background commands and actions. */
function slackemon_run_in_background( $data, $path ) {

  $url   = slackemon_build_background_url( $path );
  $query = http_build_query( $data );

  // Just return the full URL if we're running unit tests, as we don't want to actually invoke commands from those.
  if ( 'testing' === APP_ENV ) {
    return $url . '?' . $query;
  }

  $curl_options = [
    CURLOPT_FRESH_CONNECT => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS    => $query,
    CURLOPT_TIMEOUT       => SLACKEMON_CURL_TIMEOUT,
  ];

  return slackemon_get_url( $url, [ 'curl_options' => $curl_options ] );

} // Function slackemon_run_in_background

/** Builds URLs for background command and action runs, where Slackemon basically calls itself. */
function slackemon_build_background_url( $path ) {

  $background_url = SLACKEMON_LOCAL_URL . $path;

  return $background_url;

} // Function slackemon_build_background_url

/** Returns a printable version string for output in menus etc. */
function slackemon_get_version_string() {

  $version_string = SLACKEMON_ACTION_CALLBACK_ID . ' v' . SLACKEMON_VERSION;

  if ( APP_ENV && 'live' !== APP_ENV ) {
   $version_string .= ' ' . strtoupper( str_replace( 'development', 'dev', APP_ENV ) );
  }
  
  if ( getenv( 'HEROKU_RELEASE_VERSION' ) ) {
    $version_string .= ' ' . 'build ' . preg_replace( '/[^0-9]/', '', getenv( 'HEROKU_RELEASE_VERSION' ) );
  }

  if ( APP_ENV && 'development' === APP_ENV ) {
    $git_head_file = __DIR__ . '/../.git/HEAD';

    if ( file_exists( $git_head_file ) ) {
      $git_current_head   = trim( file_get_contents( $git_head_file ) );
      $git_current_branch = substr( $git_current_head, strrpos( $git_current_head, '/' ) + 1 );
      $version_string    .= ' ' . '(' . $git_current_branch . ')';
    }
  }

  return $version_string;

} // Function slackemon_get_version_string

/** An easy way to quickly truncate long strings, eg. task titles. */
function maybe_truncate( $string = '', $max_chars = 100 ) {

  if ( strlen( (string) $string ) > (int) $max_chars ) {
    return trim( substr( $string, 0, $max_chars - 3 ) ) . '...';
  } else {
    return $string;
  }

} // Function maybe_truncate

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

/**
 * Checks if an IP address is from a recognised private range.
 *
 * @link https://stackoverflow.com/a/13818126/1982136
 */
function slackemon_is_ip_private( $ip_address ) {

  $private_ranges = [
    '10.0.0.0|10.255.255.255',      // Single class A network
    '172.16.0.0|172.31.255.255',    // 16 contiguous class B network
    '192.168.0.0|192.168.255.255',  // 256 contiguous class C network
    '169.254.0.0|169.254.255.255',  // Link-local address aka Automatic Private IP Addressing
    '127.0.0.0|127.255.255.255'     // Localhost
  ];

  $long_ip = ip2long( $ip_address );

  if ( $long_ip && -1 !== $long_ip ) {

    foreach ( $private_ranges as $range ) {
      list ( $start, $end ) = explode( '|', $range );

      if ( $long_ip >= ip2long( $start ) && $long_ip <= ip2long( $end ) ) {
        return true;
      }
    }
  }

  return false;

} // Function slackemon_is_ip_private

/** Call this function from the top of a function that is deprecated and should no longer be used. */
function __slackemon_deprecated_function( $function, $version ) {

  slackemon_error_log(
    'WARNING: Using ' . $function . ' which was deprecated in v' . $version . '.' . PHP_EOL .
    slackemon_debug_backtrace( 4, true )
  );

} // Function slackemon_deprecated_function

/** Wraps PHP's built-in debug_backtrace with some default arguments to make it easier to call. */
function slackemon_debug_backtrace( $levels = 3, $skip_first = false, $ignore_args = true ) {

  // Do a backtrace with the requested number of levels plus one.
  // We then drop the trace level that was generated by this current function.
  $backtrace = debug_backtrace( $ignore_args ? DEBUG_BACKTRACE_IGNORE_ARGS : null, $levels + 1 );
  array_shift( $backtrace );

  // Allow another calling function to skip an additional level of tracing.
  if ( $skip_first ) {
    array_shift( $backtrace );
  }

  // Return as a readable dump.
  return print_r( $backtrace, true );

} // Function slackemon_debug_backtrace

/**
 * Cleans up stale files. Generally run regularly by cron.
 * TODO: Could directly access the database if that is the data store to run much more efficient commands here.
 */
function slackemon_clean_up( $age_limit = HOUR_IN_SECONDS * 24 ) {
  global $data_folder;

  $folders_to_clean = [
    'battles_active',
    'battles_complete',
    'battles_invites',
    'moves',
    'spawns',
  ];

  foreach ( $folders_to_clean as $folder ) {

    $files = slackemon_get_files_by_prefix( $data_folder . '/' . $folder . '/', 'store' );

    foreach ( $files as $file ) {
      if ( slackemon_filemtime( $file, 'store' ) < time() - $age_limit ) {
        slackemon_unlink( $file, 'store' );
      }
    }

  } // Foreach folder
} // Function slackemon_clean_up

// The end!
