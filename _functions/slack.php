<?php

// TM 20/03/2017
// Slack API specific functions

/** Send a message to a Slack incoming webhook, given in response to a slash command or action invocation. */
function send2slack( $message, $hook_url = '' ) {
  global $action, $data_folder;

  // Supports either a single string message, or a standard Slack payload array
  $payload = is_array( $message ) ? $message : [ 'text' => $message ];

  // Unlike Slack, default to mrkdwn being turned on if we haven't explicitly turned it off
  $payload['mrkdwn'] = isset( $payload['mrkdwn'] ) ? $payload['mrkdwn'] : true;

  // By default, we don't echo out our result here, we send it to Slack
  // However sometimes, we do need to return it directly to the browser...
  if ( isset( $_POST['special_mode'] ) && 'RETURN' === $_POST['special_mode'] ) {
    if ( ! isset( $payload['channel'] ) ) { // Exception: skip this if a specific channel is set
      echo "\n" . '--------JSON FOLLOWS--------' . "\n";
      echo json_encode( $payload );
      return;
    }
  }

  // Attempt to include a username and icon, if we have one
  // Note that in responses to Slack app response_url's, username and icon replacements are ignored by Slack
  if ( defined( 'COMMAND' ) ) {

    $command_settings = get_command_settings();

    if ( ! isset( $payload['username'] ) && isset( $command_settings['username'] ) ) {
      $payload['username'] = $command_settings['username'];
    }
    
    if ( ! isset( $payload['icon_emoji'] ) && ! isset( $payload['icon_url'] ) && isset( $command_settings['icon'] ) ) {
      
      // Set icon, supporting both emoji and relative _images directory URLs
      if ( preg_match( '/:.*?:/', $command_settings['icon'] ) ) {
        $payload['icon_emoji'] = $command_settings['icon'];
      } else if ( file_exists( __DIR__ . '/../_images/' . $command_settings['icon'] ) ) {
        $payload['icon_url'] = INBOUND_URL . '/_images/' . $command_settings['icon'];
      }

    }
  }

  // If we've been run through cron, modify the payload to send to the correct user
  // We'll also set the default cron username and icon at this point, if one hasn't already been set above
  if ( isset( $_POST['special_mode'] ) && 'AUTORUN' === $_POST['special_mode'] ) {

    // If a channel hasn't been set in our payload, we will work out the best one
    // If this is being called by a service user, send to the debug channel (if defined)
    // Otherwise, send straight back to the user who called it
    if ( ! isset( $payload['channel'] ) ) {
      if (
        isset( SLACK[ TEAM_ID ]['service_user'] ) &&
        isset( SLACK[ TEAM_ID ]['debug_channel'] ) &&
        $_POST['user_id'] === SLACK[ TEAM_ID ]['service_user']
      ) {
        $payload['channel'] = SLACK[ TEAM_ID ]['debug_channel'];
      } else {
        $payload['channel'] = $_POST['user_id'];
      }
    }

    $payload['username'] = isset( $payload['username'] ) ? $payload['username'] : 'Slackémon Cron';

    if ( ! isset( $payload['icon_emoji'] ) && ! isset( $payload['icon_url'] ) ) {
      $payload['icon_url'] = INBOUND_URL . '/_images/cron.png';
    }

  }

  // Hook URL fallbacks, if not supplied
  if ( ! $hook_url ) {

    if ( defined( 'RESPONSE_URL' ) && ! isset( $payload['channel'] ) ) {
      $hook_url = RESPONSE_URL;
    } else {
      return false;
    }  

  }

  $params = 'payload=' . urlencode( json_encode( $payload ) );

  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, $hook_url );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
  $result = curl_exec( $ch );
  curl_close( $ch );

  file_put_contents( $data_folder . '/last-send2slack-result', $result );

  return $result;

} // Function send2slack

/**
 * Send a message to a Slack channel using the Web API's chat.postMessage method, rather than an response_url webhook.
 */
function post2slack( $payload ) {
  global $data_folder;

  $api_base = 'https://slack.com/api';
  $endpoint = $api_base . '/chat.postMessage';

  // Add Slack API token
  $payload['token'] = SERVICES['slack']['key'];

  // Attempt to include a username and icon, if we have one
  // Note that responses to Slack app response_url's, username and icon replacements are ignored
  if ( defined( 'COMMAND' ) ) {

    $command_settings = get_command_settings();

    if ( ! isset( $payload['username'] ) && isset( $command_settings['username'] ) ) {
      $payload['username'] = $command_settings['username'];
    }
    
    if ( ! isset( $payload['icon_emoji'] ) && ! isset( $payload['icon_url'] ) && isset( $command_settings['icon'] ) ) {
      
      // Set icon, supporting both emoji and relative _images directory URLs
      if ( preg_match( '/:.*?:/', $command_settings['icon'] ) ) {
        $payload['icon_emoji'] = $command_settings['icon'];
      } else if ( file_exists( __DIR__ . '/../_images/' . $command_settings['icon'] ) ) {
        $payload['icon_url'] = INBOUND_URL . '/_images/' . $command_settings['icon'];
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

  $response = get_url( $endpoint . '?' . http_build_query( $payload ) );
  file_put_contents( $data_folder . '/last-post2slack-result', $response );

  return $response;

} // Function post2slack

/** Output a JSON payload directly in response to a Slack slash command. */
function output2slack( $payload ) {
  header( 'Content-type: application/json' );
  echo json_encode( $payload );
}

/** Does what it says on the tin. */
function get_user_full_name( $user_id = USER_ID ) {

  $user = get_slack_user( $user_id );
  if ( $user && isset( $user->real_name ) ) {
    return $user->real_name;
  }

  return false;

} // Function get_user_full_name

/** Does what it says on the tin. */
function get_user_first_name( $user_id = USER_ID ) {

  $user_full_name = get_user_full_name( $user_id );

  if ( ! $user_full_name ) {
    return false;
  }

  $user_first_name = substr( $user_full_name, 0, strpos( $user_full_name, ' ' ) );

  return $user_first_name;

} // Function get_user_first_name

/** Does what it says on the tin. */
function get_user_avatar_url( $user_id = USER_ID ) {

  $user = get_slack_user( $user_id );
  if ( $user && isset( $user->profile->image_original ) ) {
    return $user->profile->image_original;
  }

  return false;

} // Function get_user_avatar_url

/**
 * Gets a user's e-mail address, either from the local config if set, or falling back to the Slack API.
 */
function get_user_email_address( $user_id = USER_ID ) {

  if ( defined( 'SLACK_USERS' ) && isset( SLACK_USERS[ $user_id ]['email_address'] ) ) {
    return SLACK_USERS[ $user_id ]['email_address'];
  }

  $user = get_slack_user( $user_id );
  if ( $user && isset( $user->profile->email ) ) {
    return $user->profile->email;
  }

  return false;

} // Function get_user_email_address

/** Gets a Slack user's data from the Slack API. Cached for a day. */
function get_slack_user( $user_id = USER_ID ) {
  global $_cached_slack_user_data;

  if ( isset( $_cached_slack_user_data[ $user_id ] ) ) {
    return $_cached_slack_user_data[ $user_id ];
  }

  $slack_users = get_slack_users();

  foreach( $slack_users as $user ) {
    if ( $user->id === $user_id ) {
      $_cached_slack_user_data[ $user_id ] = $user;
      return $user;
    }
  }

  return false;

} // Function get_slack_user

/** Gets ALL Slack user data from the Slack API. Cached for a day. */
function get_slack_users() {

  if ( ! SERVICES['slack']['key'] ) {
    return [];
  }

  $slack_users = json_decode( get_cached_url(
    'https://slack.com/api/users.list?token=' . SERVICES['slack']['key'],
    [ 'expiry_age' => DAY_IN_SECONDS ]
  ) )->members;

  return $slack_users;

} // Function get_slack_users

// The end!
