<?php
/**
 * Provides the logic for responding to message menu option requests.
 *
 * @package Slackemon
 */

// Set up Slackemon environment.
require_once( __DIR__ . '/init.php' );

// Prepare for JSON output, which will happen within our request handler.
header( 'Content-Type: application/json' );

$callback_id  = $options_request->callback_id;
$action_name  = $options_request->name;
$action_value = $options_request->value;

// Handle the request.
require( __DIR__ . '/' . $callback_id . '/options-request.php' );
