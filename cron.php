<?php
/**
 * Enables commands to be simulated via a scheduled cron.
 * This file requires cronning by either the local system via CLI, or externally passing the SLACKEMON_CRON_TOKEN.
 *
 * @package Slackemon
 */

define( 'SKIP_AUTH', true );
require_once( __DIR__ . '/lib/init.php' );

// AUTH: Check if the cron token was set - if running over the web from a non-local IP address.
if (
  'cli' !== php_sapi_name() &&
  ! slackemon_is_ip_private( $_SERVER['REMOTE_ADDR'] ) &&
  ( ! isset( $_REQUEST['token'] ) || SLACKEMON_CRON_TOKEN !== $_REQUEST['token'] )
) {
  http_response_code( 403 );
  slackemon_error_log( 'Unauthorised cron request.' );
  return slackemon_exit( 'Not authorised for this cron request.' );
}

// Cron schedule
// The format is pretty much just like normal crons, including support for * / and - values.
define(
  'SLACKEMON_CRON_SCHEDULE', [
    [ '*', '*', '*', '*', '*', '/slackemon maybe-spawn'       ], // Runs every minute.
    [ '*', '*', '*', '*', '*', '/slackemon battle-updates'    ], // Runs every minute.
    [ '1', '1', '*', '*', '*', '/slackemon happiness-updates' ], // Runs once a day.
  ]
);

// Set the current time and date parameters.
define( 'MINUTE', (int) date( 'i' ) );
define( 'HOUR',   (int) date( 'G' ) );
define( 'DATE',   (int) date( 'j' ) );
define( 'MONTH',  (int) date( 'n' ) );
define( 'DAY',    (int) date( 'w' ) );

// Output the current time and date.
echo MINUTE . ' ' . HOUR . ' ' . DATE . ' ' . MONTH . ' ' . DAY;

// Check schedule, and run commands if it's time.
foreach ( SLACKEMON_CRON_SCHEDULE as $item ) {

  // Decide whether to skip this item if it doesn't match every condition.
  if (
    ! slackemon_check_cron_value( $item[0], MINUTE ) ||
    ! slackemon_check_cron_value( $item[1], HOUR   ) ||
    ! slackemon_check_cron_value( $item[2], DATE   ) ||
    ! slackemon_check_cron_value( $item[3], MONTH  ) ||
    ! slackemon_check_cron_value( $item[4], DAY    )
  ) {
    continue;
  }

  // Prepare data.
  $command = $item[5];
  $user_id = SLACKEMON_MAINTAINER;
  $team_id = SLACKEMON_SLACK_TEAM_ID;

  // Run the command, and output the initial result back to the cron caller.
  $result = slackemon_run_automated_command( $command, $user_id, $team_id, [ 'run_mode' => 'cron' ] );
  echo $result;

} // Foreach SLACKEMON_CRON_SCHEDULE $item

return slackemon_exit();

// The end!
