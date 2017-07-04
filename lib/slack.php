<?php
/**
 * Slack API specific functions.
 *
 * @package Slackemon
 */

/** Send a message to a Slack incoming webhook, given in response to a slash command or action invocation. */
function slackemon_send2slack( $payload, $hook_url = '' ) {
  global $action, $data_folder;

  // Supports a single string message, or a standard Slack payload array (incl. expressed as an object)
  if ( is_object( $payload ) ) {
    $payload = (array) $payload;
  } else if ( is_string( $payload ) ) {
    $payload = [ 'text' => $payload ];
  }

  // Unlike Slack, default to mrkdwn being turned on if we haven't explicitly turned it off
  $payload['mrkdwn'] = isset( $payload['mrkdwn'] ) ? $payload['mrkdwn'] : true;

  // Attempt to include a username and icon, if we have one
  // Note that in responses to Slack app response_url's, username and icon replacements are ignored by Slack
  if ( defined( 'COMMAND' ) ) {

    if ( ! isset( $payload['username'] ) ) {
      $payload['username'] = SLACKEMON_USERNAME;
    }

    if ( ! isset( $payload['icon_emoji'] ) && ! isset( $payload['icon_url'] ) ) {
      
      // Set icon, supporting both emoji and relative media directory URLs
      if ( preg_match( '/:.*?:/', SLACKEMON_ICON ) ) {
        $payload['icon_emoji'] = SLACKEMON_ICON;
      } else if ( file_exists( __DIR__ . '/../media/' . SLACKEMON_ICON ) ) {
        $payload['icon_url'] = SLACKEMON_INBOUND_URL . 'media/' . SLACKEMON_ICON;
      }

    }
  }

  // If we've been run through cron, modify the payload to send to the correct user
  if ( isset( $_POST['run_mode'] ) && 'cron' === $_POST['run_mode'] ) {

    // If a channel hasn't been set in our payload, send straight back to the user who called the command
    if ( ! isset( $payload['channel'] ) ) {
      $payload['channel'] = $_POST['user_id'];
    }

  }

  // Hook URL fallbacks, if not supplied
  if ( ! $hook_url ) {

    if ( defined( 'RESPONSE_URL' ) && ! isset( $payload['channel'] ) ) {
      $hook_url = RESPONSE_URL;
    } else {

      // No hook URL is available, so use the chat.postMessage method instead
      slackemon_post2slack( $payload );
      return false;

    }  

  }

  $params = 'payload=' . urlencode( json_encode( $payload ) );

  $curl_options = [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS    => $params,
  ];

  $result = slackemon_get_url(
    $hook_url,
    [
      'curl_options' => $curl_options,
      'skip_error_reporting' => true, // We must skip error reporting, because error sending uses send2slack()!
    ]
  );

  if ( 'development' === APP_ENV ) {
    file_put_contents( $data_folder . '/last-send2slack-result', $result );
  }

  return $result;

} // Function slackemon_send2slack

/**
 * Send a message to a Slack channel using the Web API's chat.postMessage method, rather than an response_url webhook.
 */
function slackemon_post2slack( $payload ) {
  global $data_folder;

  $api_base = 'https://slack.com/api';
  $endpoint = $api_base . '/chat.postMessage';

  // Add Slack API token
  $payload['token'] = SLACKEMON_SLACK_KEY;

  // Attempt to include a username and icon, if we have one
  // Note that responses to Slack app response_url's, username and icon replacements are ignored
  if ( defined( 'COMMAND' ) ) {

    if ( ! isset( $payload['username'] ) ) {
      $payload['username'] = SLACKEMON_USERNAME;
    }
    
    if ( ! isset( $payload['icon_emoji'] ) && ! isset( $payload['icon_url'] ) ) {
      
      // Set icon, supporting both emoji and relative media directory URLs
      if ( preg_match( '/:.*?:/', SLACKEMON_ICON ) ) {
        $payload['icon_emoji'] = SLACKEMON_ICON;
      } else if ( file_exists( __DIR__ . '/../media/' . SLACKEMON_ICON ) ) {
        $payload['icon_url'] = SLACKEMON_INBOUND_URL . 'media/' . SLACKEMON_ICON;
      }

    }
  }

  // Make sure nested arrays are encoded as JSON string values
  $payload = array_map( function( $value ) {
    if ( is_array( $value ) ) {
      return json_encode( $value );
    } else {
      return $value;
    }
  }, $payload );

  $response       = slackemon_get_url( $endpoint . '?' . http_build_query( $payload ) );
  $debug_filename = $data_folder . '/last-post2slack-result';
  $debug_data     = json_encode( $payload ) . PHP_EOL . PHP_EOL . $response;

  if ( 'development' === APP_ENV ) {
    file_put_contents( $debug_filename, $debug_data );
  }

  return $response;

} // Function slackemon_post2slack

/**
 * Assists with long-waiting actions, including while waiting to acquire a file lock, by advising the user and ensuring
 * they don't involve any further actions. Only updates once per session.
 */
function slackemon_send_waiting_message_to_user( $user_id = USER_ID ) {
  global $_slackemon_waiting_message_sent;

  if ( $_slackemon_waiting_message_sent ) {
    return;
  }

  if ( isset( $_REQUEST['action'] ) ) {

    $action  = json_decode( $_REQUEST['action'] );
    $message = $action->original_message;

    foreach ( $message->attachments as $attachment ) {
      $attachment->actions = [];
    }

    $message->attachments[ $action->attachment_id - 1 ]->footer = (
      'Loading... ' . slackemon_get_loading_indicator( $user_id, false )
    );

    slackemon_send2slack( $message );
    $_slackemon_waiting_message_sent = true;

  } // If action
} // Function slackemon_send_waiting_message_to_user

/** Does what it says on the tin. */
function slackemon_get_slack_user_full_name( $user_id = USER_ID ) {

  $user = slackemon_get_slack_user( $user_id );

  if ( ! $user ) {
    return false;
  }

  if ( isset( $user->real_name ) && $user->real_name ) {
    return $user->real_name;
  }

  // If the user hasn't entered their name, fallback to a modification of their username
  return ucwords( str_replace( [ '.', '-' ], ' ', $user->name ) );

} // Function slackemon_get_user_full_name

/** Does what it says on the tin. */
function slackemon_get_slack_user_first_name( $user_id = USER_ID ) {

  $user_full_name = slackemon_get_slack_user_full_name( $user_id );

  if ( ! $user_full_name ) {
    return false;
  }

  if ( false !== strpos( $user_full_name, ' ' ) ) {
    $user_first_name = substr( $user_full_name, 0, strpos( $user_full_name, ' ' ) );
  } else {
    $user_first_name = $user_full_name;
  }

  return $user_first_name;

} // Function get_user_first_name

/** Does what it says on the tin. */
function slackemon_get_slack_user_avatar_url( $user_id = USER_ID ) {

  $user = slackemon_get_slack_user( $user_id );
  if ( $user && isset( $user->profile->image_original ) ) {
    return $user->profile->image_original;
  }

  return false;

} // Function slackemon_get_user_avatar_url

/**
 * Gets a user's e-mail address, either from the local config if set, or falling back to the Slack API.
 */
function slackemon_get_slack_user_email_address( $user_id = USER_ID ) {

  $user = slackemon_get_slack_user( $user_id );
  if ( $user && isset( $user->profile->email ) ) {
    return $user->profile->email;
  }

  return false;

} // Function slackemon_get_user_email_address

/** Gets a Slack user's data from the Slack API. */
function slackemon_get_slack_user( $user_id = USER_ID ) {
  global $_cached_slack_user_data;

  if ( isset( $_cached_slack_user_data[ $user_id ] ) ) {
    return $_cached_slack_user_data[ $user_id ];
  }

  $slack_users = slackemon_get_slack_users();

  foreach( $slack_users as $user ) {
    if ( $user->id === $user_id ) {
      $_cached_slack_user_data[ $user_id ] = $user;
      return $user;
    }
  }

  // If we haven't found the user yet, they must be a new user who signed up within the last day, due to caching.
  // So, let's force a cache refresh and try once more.

  $slack_users = slackemon_get_slack_users( true );

  foreach( $slack_users as $user ) {
    if ( $user->id === $user_id ) {
      $_cached_slack_user_data[ $user_id ] = $user;
      return $user;
    }
  }

  slackemon_error_log( 'Data for Slack user ID ' . $user_id . ' could not be found.' );

  return false;

} // Function slackemon_get_slack_user

/** Gets ALL Slack user data from the Slack API. Cached for a day. */
function slackemon_get_slack_users( $skip_cache = false ) {

  if ( ! SLACKEMON_SLACK_KEY ) {
    return [];
  }

  $slack_users = json_decode( slackemon_get_cached_url(
    'https://slack.com/api/users.list?token=' . SLACKEMON_SLACK_KEY,
     [ 'expiry_age' => $skip_cache ? 1 : DAY_IN_SECONDS ]
  ) )->members;

  return $slack_users;

} // Function slackemon_get_slack_users

// The end!
