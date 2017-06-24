<?php
/**
 * Functions that handle automation of Slackemon, generally via cron.
 *
 * @package Slackemon
 */

function slackemon_run_automated_command( $command, $user_id, $team_id, $options = [] ) {

  // Prepare the command and its arguments
  $command_parts = explode( ' ', $command );
  $command = $command_parts[0];
  $text = isset( $command_parts[1] ) ? join( ' ', array_slice( $command_parts, 1 ) ) : '';

  // Get the team ID & command token
  $token = SLACKEMON_SLACK_TOKEN;

  // Prepare options
  $options['run_mode'] = isset( $options['run_mode'] ) ? $options['run_mode'] : 'unknown';

  // Put payload together
  $params = [

    // The usual expected data
    // Reference: https://api.slack.com/slash-commands#triggering_a_command
    'token'        => $token,
    'team_id'      => $team_id,
    'team_domain'  => '', // Unknown when autorun
    'channel_id'   => '', // Unknown when autorun
    'channel_name' => '', // Unknown when autorun
    'user_id'      => $user_id,
    'user_name'    => '', // Unknown when autorun
    'command'      => $command,
    'text'         => $text,
    'response_url' => false,

    // Our own custom data

    // Send through the run mode, for logging and (minimal) access control purposes
    'run_mode' => $options['run_mode'],

  ];

  // Run the command
  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, SLACKEMON_INBOUND_URL );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
  $result = curl_exec( $ch );

  // Return the initial result of the automated command to the caller
  return $result;

} // Function slackemon_run_automated_command

function slackemon_check_cron_value( $requested, $current ) {

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

} // Function slackemon_check_cron_value

// The end!
