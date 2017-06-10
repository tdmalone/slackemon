<?php
/**
 * Handles incoming action notifications from the Slack message buttons (https://api.slack.com/docs/message-buttons).
 * Called directly by index.php if action conditions exist - $action will exist with the action details.
 *
 * @package Slackemon
 */

$callback_id = $action->callback_id;
require_once( __DIR__ . '/init.php' );

// Handle the action.
require( __DIR__ . '/' . $callback_id . '/actions.php' );

// The end!
