<?php
/**
 * Main entry point for Slackemon requests made by Slack.
 *
 * @package Slackemon
 */

// Before starting, determine if we're receiving an inbound action or option request from Slack app.
$payload = json_decode( file_get_contents( 'php://input' ) );
if ( ! $payload && isset( $_REQUEST['payload'] ) ) {
  $payload = json_decode( $_REQUEST['payload'] );
}

if ( $payload ) {

  // Action, or message menu options request?
  if ( isset( $payload->actions ) && count( $payload->actions ) ) {

    // Set up Slackemon environment.
    $action = $payload;
    require_once( __DIR__ . '/lib/init.php' );

    // Handle the action in the background.
    $callback_id = $action->callback_id;
    slackemon_run_background_action( 'src/_actions.php', $action, $callback_id );

    return slackemon_exit();

  } elseif ( isset( $payload->action_ts ) && isset( $payload->callback_id ) ) {

    // Set up Slackemon environment.
    $options_request = $payload;
    require_once( __DIR__ . '/lib/init.php' );

    // Prepare for JSON output, which will happen within our request handler.
    header( 'Content-Type: application/json' );

    // Handle the options request.
    $options = slackemon_get_slack_message_menu_options( $options_request->name, $options_request->value );

    if ( $options ) {
      echo $options;
    }

    return slackemon_exit();

  }
}

// Otherwise, let's get going - no-one will get past here now unless they're authorised with a Slack App token.
require_once( __DIR__ . '/lib/init.php' );

// Init the once-off, entry-point stuff.
if ( ! defined( 'COMMAND' ) ) {
  define( 'COMMAND', $_POST['command'] );
}

// Run as a background command, as long as this isn't a test run.
$args = check_subcommands();
slackemon_run_background_command( 'src/_commands.php', $args );
return slackemon_exit();

// The end!
