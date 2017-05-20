<?php

// TM 09/05/2015
// Provides the logic for responding to message menu option requests

require_once( __DIR__ . '/init.php' );
header( 'Content-Type: application/json' );

$callback_id  = $options_request->callback_id;
$action_name  = $options_request->name;
$action_value = $options_request->value;

// Handle the request
require( __DIR__ . '/' . $callback_id . '/options-request.php' );

// The end!
