<?php
/**
 * Item specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_item_spawn( $trigger = [], $region = false, $timestamp = false ) {

  // Get total item count, choose a random one, and grab its data
  $items_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/item/' ) );
  $random_item = random_int( 1, $items_data->count );
  $item_data = slackemon_get_item_data( $random_item );

  $unsupported_items = [
    'safari-ball',  // We don't have the Great Marsh location set up for it
    'sport-ball',   // We don't have the mechanic for this
    'dive-ball',    // We don't have the mechanic for this
    'pure-incense', // We don't have the mechanic for this
    'cleanse-tag',  // We don't have the mechanic for this
    'smoke-ball',   // We don't have the mechanic for this
    'poke-ball',    // We treat this as having an unlimited supply for now anyway
  ];

  $unsupported_categories = [

    // To be implemented shortly
    'flutes', 'stat-boosts',
    'effort-drop', 'in-a-pinch', 'medicine', 'other', 'picky-healing', 'type-protection',
    'healing', 'pp-recovery', 'revival', 'status-cures', 'vitamins',
    'effort-training', 'held-items', 'jewels',
    'plates', 'species-specific', 'training', 'type-enhancement',
    'special-balls', 'standard-balls',

    //'unused',            // These items have no use
    'bad-held-items',    // These items aren't really useful
    'all-mail',          // We don't send mail to other trainers
    'mulch',             // We have no way to grow berries
    'plot-advancement',  // We don't have a plot like the main games ;)
    'gameplay',          // Same as above
    'apricorn-box',      // We have no mechanics to deal with these
    'apricorn-balls',    // Same as above
    'baking-only',       // Same as above
    'data-cards',        // Same as above
    'xy-unknown',        // Same as above
    'miracle-shooter',   // Same as above
    'dex-completion',    // Same as above
    'event-items',       // Same as above
    'scarves',           // Same as above

    // These are not in the game yet, but might be in the near future
    //'loot',         // Not really useful until we can sell them in stores
    //'collectibles', // Same as above
    'choice',       // Needs specific move choice / stat mechanics
    'mega-stones',  // Needs mechanic for mega-evolution during battle

    // Other
    'spelunking',   // We could implement this....
  ];

  // Try again if...
  if (
    ! $item_data || // We didn't pick a valid item for some reason
    ! isset( $item_data->name ) // We didn't pick a valid item for some reason
  ) {
    slackemon_spawn_debug( 'Not spawning item ' . $random_item . ' as it doesn\'t appear to be valid.' );
    return slackemon_item_spawn( $trigger, $region, $timestamp );
  }

  // Try again if...
  if (
    //! $item_data->cost || // Item is priceless, and therefore probably quite valuable
    in_array( $item_data->name, $unsupported_items ) ||
    in_array( $item_data->category->name, $unsupported_categories )
  ) {
    slackemon_spawn_debug( 'Not spawning ' . slackemon_readable( $item_data->name ) . '; its category is ' . slackemon_readable( $item_data->category->name ) . '.' );
    return slackemon_item_spawn( $trigger, $region, $timestamp );
  }

  // In addition to the above, we assign a certain rarity to some categories by making a chance that we'll respawn...

  if ( 'tms' === $item_data->category->name ) {
    if ( random_int( 1, 2 ) > 1 ) {
      slackemon_spawn_debug( 'Not spawning ' . slackemon_readable( $item_data->name ) . '; random chance says to skip it make it rarer this time.' );
      return slackemon_item_spawn( $trigger, $region, $timestamp );
    }
  }

  if ( 'hms' === $item_data->category->name ) {
    if ( random_int( 1, 5 ) > 1 ) {
      slackemon_spawn_debug( 'Not spawning ' . slackemon_readable( $item_data->name ) . '; random chance says to skip it make it rarer this time.' );
      return slackemon_item_spawn( $trigger, $region, $timestamp );
    }
  }

  $description = slackemon_get_item_description( $item_data->id );

  if ( ! $description ) {
    slackemon_spawn_debug( 'Not spawning ' . slackemon_readable( $item_data->name ) . ' because we could not find a suitable description for it.' );
    return slackemon_item_spawn( $trigger, $region, $timestamp );
  }

  slackemon_spawn_debug( 'Ok, will spawn a ' . slackemon_readable( $item_data->name ) . '.' );

  // Store details of the item spawn
  $spawn = [
    'id'          => $item_data->id,
    'ts'          => $timestamp,
    'region'      => $region,
    'trigger'     => $trigger,
    'description' => $description,
    'users'       => new stdClass(),
  ];

  if ( slackemon_save_spawn_data( $spawn ) ) {
    slackemon_notify_item_spawn( $spawn );
  }

  return $spawn;

} // Function slackemon_item_spawn

function slackemon_notify_item_spawn( $spawn ) {
  global $data_folder;

  $item_data = slackemon_get_item_data( $spawn['id'] );

  // Let's have a little fun with where this item might be found...

  $item_locations = [
    'on the ground!',
    'in the kitchen! :fork_and_knife:',
    'in the garden! :house_with_garden:',
    'under your desk!',
    'under [someone]\'s desk!',
    'in your car! :car:',
    'in [someone]\'s car! :blue_car:',
  ];

  $random_location = $item_locations[ array_rand( $item_locations ) ];

  $message = [
    'attachments' => [
      [
        'pretext'   => 'Oh! You found a *' . slackemon_readable( $item_data->name ) . '* ' . $random_location,
        'fallback'  => 'You found a ' . slackemon_readable( $item_data->name ) . '!',
        'text'      => $spawn['description'],
        'color'     => '#333333',
        'mrkdwn_in' => [ 'pretext', 'text' ],
        'fields'    => [
          [
            'title' => 'Value',
            'value' => slackemon_get_item_cost( $spawn['id'] ),
            'short' => true,
          ], [
            'title' => 'Item Category',
            'value' => slackemon_readable( $item_data->category->name ),
            'short' => true,
          ]
        ],
        'thumb_url' => slackemon_get_cached_image_url( $item_data->sprites->default ),
      ], [
        'title' => 'What would you like to do?',
        'color' => '#333333',
        'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
        'actions' => [
          [
            'name'  => 'items/pick-up',
            'text'  => ':gift: Pick Up Item',
            'type'  => 'button',
            'value' => $spawn['ts'],
            'style' => 'primary',
          ], [
            'name'  => 'mute',
            'text'  => ':mute: Go Offline',
            'type'  => 'button',
            'value' => 'mute',
            'style' => 'danger',
          ],
        ],
      ],
    ],
  ];

  $slack_users = slackemon_get_slack_users();

  // Notify active users in the region
  foreach ( slackemon_get_player_ids([ 'active_only' => true, 'region' => $spawn['region'] ]) as $player_id ) {

    $this_message = $message;

    // Continue having a little fun with the item locations
    if ( false !== strpos( $this_message['attachments'][0]['pretext'], '[someone]' ) ) {

      $_slack_users = array_filter( $slack_users, function( $user ) use ( $player_id ) {
        if ( $user->is_bot || $user->deleted || 'slackbot' === $user->name || $user->id === $player_id ) {
          return false;
        }
        return true;
      });

      $random_person = $_slack_users[ array_rand( $_slack_users ) ];

      $this_message['attachments'][0]['pretext'] = str_replace(
        '[someone]',
        $random_person->profile->first_name,
        $this_message['attachments'][0]['pretext']
      );

    }

    $this_message['channel'] = $player_id;
    $response = slackemon_post2slack( $this_message );

    if ( 'development' === APP_ENV ) {
      file_put_contents( $data_folder . '/last-spawn-notification', $response );
    }

  } // Foreach active players in the region

  return $spawn;

} // Function slackemon_item_spawn

function slackemon_pick_up_item( $spawn_ts, $user_id = USER_ID ) {

  $spawn_data = slackemon_get_spawn_data( $spawn_ts, slackemon_get_player_region( $user_id ), $user_id );

  // We don't need these anymore
  unset( $spawn_data->region );
  unset( $spawn_data->trigger );
  unset( $spawn_data->description );
  unset( $spawn_data->users );

  return slackemon_add_item( $spawn_data, $user_id );

} // Function slackemon_pick_up_item

function slackemon_get_item_pick_up_message( $spawn_ts, $action, $user_id = USER_ID ) {

  $spawn_data = slackemon_get_spawn_data( $spawn_ts, slackemon_get_player_region( $user_id ), $user_id );
  $item_data  = slackemon_get_item_data( $spawn_data->id );

  $message = [];
  $message['attachments'] = $action->original_message->attachments;

  $message['attachments'][0]->pretext = '';
  $message['attachments'][ $action->attachment_id - 1 ] = slackemon_back_to_menu_attachment();

  if ( $spawn_ts >= time() - SLACKEMON_FLEE_TIME_LIMIT ) {

    slackemon_pick_up_item( $spawn_ts );

    $message['attachments'][ $action->attachment_id - 1 ]['text'] = (
      ':white_check_mark: *Excellent!* Your new *' . slackemon_readable( $item_data->name ) . '* ' .
      'has been packed safely in your bag.'
    );

    array_unshift(
      $message['attachments'][ $action->attachment_id - 1 ]['actions'],
      [
        'name'  => 'items/view-from-pickup',
        'text'  => ':eye: View Item',
        'type'  => 'button',
        'value' => $spawn_data->id,
        'style' => 'primary',
      ], [
        'name'  => 'items',
        'text'  => ':handbag: View Bag',
        'type'  => 'button',
        'value' => 'main',
      ]
    );

  } else {

    // Make the message a little bit more fun
    $flying_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/type/flying/' ) );
    $flying_pokemon = array_filter( $flying_data->pokemon, function( $_pokemon ) {
      $_pokedex_id = (int) basename( $_pokemon->pokemon->url );
      if ( ! $_pokedex_id || $_pokedex_id > 250 ) {
        return false;
      }
      return true;
    });
    $random_pokemon = $flying_pokemon[ array_rand( $flying_pokemon ) ];
    $random_pokemon_name = $random_pokemon->pokemon->name;

    $message['attachments'][ $action->attachment_id - 1 ]['text'] = (
      ':open_mouth: *Oh no!* A wild :' . $random_pokemon_name . ': ' . slackemon_readable( $random_pokemon_name ) . ' ' .
      'flew off with your *' . slackemon_readable( $item_data->name ) . '*!' . "\n" .
      'Try to pick up items within about ' . round( SLACKEMON_FLEE_TIME_LIMIT / 60 ) . ' minutes, before ' .
      'they get stolen!'
    );

    array_shift( $message['attachments'] ); // Remove the first attachment - the main item data

  } // If picked in time / else

  return $message;

} // Function slackemon_get_item_pick_up_message

function slackemon_get_item_view_message( $item_id, $action, $action_name, $user_id = USER_ID ) {

  $items = slackemon_get_player_data( $user_id )->items;
  $item_data = slackemon_get_item_data( $item_id );

  $item = [ 'id' => $item_id, 'count' => 0 ];

  foreach ( $items as $_item ) {
    if ( $_item->id == $item['id'] ) {
      $item['count']++;
    }
  }

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  $item_attachment = slackemon_get_item_attachment( $item, 'items/cancel-action' !== $action_name );

  $message['attachments'][ $action->attachment_id - 1 ] = $item_attachment;

  // If we're viewing directly from a pick-up message, we need a few additional adjustments
  if ( 'items/view-from-pickup' === $action_name ) {
    array_shift( $message['attachments'] ); // Remove the original first attachment
    $back_to_menu_attachment = slackemon_back_to_menu_attachment();
    array_unshift(
      $back_to_menu_attachment['actions'],
      [
        'name' => 'items',
        'text' => ':handbag: View Bag',
        'type' => 'button',
        'value' => 'main',
      ]
    );
    $message['attachments'][] = $back_to_menu_attachment;
  }

  return $message;

} // Function slackemon_get_item_view_message

function slackemon_get_item_action_message( $method, $item_id, $action, $user_id = USER_ID ) {

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  $cancellation_message = '';

  // Set the neccessary language
  switch ( $method ) {
    case 'give':  $question = 'hold this item';     $finish_this_sentence = 'Give to...';  break;
    case 'use':   $question = 'use this item on';   $finish_this_sentence = 'Use on...';   break;
    case 'teach': $question = 'teach this move to'; $finish_this_sentence = 'Teach to...'; break;
  }

  // Because building the list of Pokemon that can be taught a move can be time-consuming, we'll do this now and 
  // cache it for the options request to pick up.
  if ( 'teach' === $method ) {

    $message['attachments'][ $action->attachment_id - 1 ]->footer = (
      'Checking who can learn this move... :loading:'
    );

    $message['attachments'][ $action->attachment_id - 1 ]->actions = [];

    // Send our loading message
    slackemon_do_action_response( $message );

    // Create/update the cache, which will then be requested by the external options source below
    $move_name = slackemon_get_machine_move_data( $item_id, true );
    $teachable_pokemon = slackemon_get_user_teachable_pokemon( $move_name, '', $user_id );

    if ( ! count( $teachable_pokemon ) ) {
      $cancellation_message = (
        'Sorry, you don\'t have any Pokémon who can learn ' . slackemon_readable( $move_name ) . '!'
      );
    }

  }

  $message['attachments'][ $action->attachment_id - 1 ]->footer = (
    $cancellation_message ? $cancellation_message : 'Who would you like to ' . $question . '?'
  );

  $message['attachments'][ $action->attachment_id - 1 ]->actions = [
    (
      $cancellation_message ?
      [] :
      [

        'name' => 'items/' . $method . '/' . $item_id,
        'text' => $finish_this_sentence,
        'type' => 'select',
        'data_source' => 'external',

        // Because 'give' will use the full Pokemon collection, we should require at least one character to be entered
        // before querying, otherwise Slack will cut us off at 100 anyway
        'min_query_length' => 'give' === $method ? 1 : 0,

      ]
    ), [
      'name'  => 'items/cancel-action',
      'text'  => $cancellation_message ? 'Okay' : 'Cancel',
      'type'  => 'button',
      'value' => $item_id,
    ],
  ];

  return $message;

} // Function slackemon_get_item_action_message

function slackemon_get_item_give_do_message( $item_id, $spawn_ts, $action, $user_id = USER_ID ) {

  // Add the item to the Pokemon, and remove it from the item collection
  slackemon_change_pokemon_held_item( $item_id, $spawn_ts, $user_id );
  slackemon_remove_item( $item_id, $user_id );

  $player_data = slackemon_get_player_data( $user_id );
  $item_data = slackemon_get_item_data( $item_id );

  // Find the correct Pokemon
  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      $pokemon = $_pokemon;
    }
  }

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  $message['attachments'][ $action->attachment_id - 1 ]->footer = (
    ':white_check_mark: ' . slackemon_readable( $pokemon->name ) . ' is now holding ' .
    'your ' . slackemon_readable( $item_data->name ) . '.'
  );

  $message['attachments'][ $action->attachment_id - 1 ]->actions = [];

  return $message;

} // Function slackemon_get_item_give_do_message

function slackemon_get_item_return_message( $spawn_ts, $action, $user_id = USER_ID ) {

  // Remove the item from the Pokemon, and return it to the collection

  $player_data = slackemon_get_player_data( $user_id );

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      $pokemon = $_pokemon;
    }
  }

  $item_id = $pokemon->held_item;
  slackemon_add_item( $item_id, $user_id );
  unset( $pokemon->held_item );

  slackemon_save_player_data( $player_data );

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $original_attachment = $message['attachments'][ $action->attachment_id - 1 ];

  $original_attachment->footer = (
    'Your ' . slackemon_readable( slackemon_get_item_name( $item_id ) ) . ' has been returned to your bag.'
  );

  // Remove the action button that called this action
  foreach ( $original_attachment->actions as $key => $_action_button ) {
    if ( 'pokemon/return-item' === $_action_button->name ) {
      unset( $original_attachment->actions[ $key ] );
    }
  }

  $message['attachments'][ $action->attachment_id - 1 ] = $original_attachment;

  return $message;

} // Function slackemon_get_item_return_message

function slackemon_get_item_use_do_message( $item_id, $spawn_ts, $action, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );
  $item_data   = slackemon_get_item_data( $item_id );

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  // Find the correct Pokemon
  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      $pokemon = $_pokemon;
    }
  }

  // Evolution items
  if ( 'evolution' === $item_data->category->name ) {

    // Get the ID of the Pokemon we should be evolving to with this item
    $evolve_to_id = slackemon_can_user_pokemon_evolve( $pokemon, 'use-item', false, $item_data->name );

    if ( $evolve_to_id ) {

      // Time to evolve!
      slackemon_start_evolution_message( $spawn_ts, $action, $user_id, true );
      if ( slackemon_evolve_user_pokemon( $spawn_ts, $evolve_to_id, $user_id ) ) {

        slackemon_remove_item( $item_id, $user_id ); // All evolution items are consumable
        slackemon_end_evolution_message( $spawn_ts, $action, $user_id, true );

        // We have to ensure we don't return something that can construed as a message here, because our function
        // above handles the evolution message completely, and we don't want it replaced.
        return null;

      } else {
        $message = slackemon_get_evolution_error_message( $spawn_ts, $action );
      }

    } else {

      $message = slackemon_get_evolution_error_message( $spawn_ts, $action );

    }

  } else {

    // TODO fallback if item use isn't handled

  }

  return $message;

} // Function slackemon_get_item_use_do_message

function slackemon_get_item_teach_do_message( $item_id, $spawn_ts, $action, $user_id = USER_ID ) {
  
  $player_data = slackemon_get_player_data( $user_id );
  $pokemon     = slackemon_get_player_pokemon_data( $spawn_ts, $player_data );
  $item_data   = slackemon_get_item_data( $item_id );
  $move_name   = slackemon_get_machine_move_data( $item_id, true );

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  // Find the correct Pokemon
  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      $pokemon = $_pokemon;
    }
  }

  // Final confirmation that the Pokemon doesn't know this move
  foreach ( $pokemon->moves as $_move ) {
    if ( $_move->name === $move_name ) {

      $message['attachments'][ $action->attachment_id - 1 ]->footer = (
        ':no_mouth: Oops! ' . slackemon_readable( $pokemon->name ) . ' already knows ' .
        slackemon_readable( $move_name ) . '! You can\'t teach the same move again.'
      );

      return $message;

    }
  }

  // Final confirmation that the Pokemon doesn't already know four moves
  if ( count( $pokemon->moves ) >= SLACKEMON_MAX_KNOWN_MOVES ) {

    $pronoun = 'male' === $pokemon->gender ? 'he' : 'she';

    $message['attachments'][ $action->attachment_id - 1 ]->footer = (
      ':no_mouth: Oops! ' . slackemon_readable( $pokemon->name ) . ' knows too many ' .
      'moves! You\'ll need to delete one first.'
    );

    return $message;

  }

  // Add move to the Pokemon, and if the item is a TM, remove it from the player's collection
  $move_data = slackemon_get_move_data( $move_name );
  $new_move  = [ 'name' => $move_data->name, 'pp' => $move_data->pp, 'pp-current' => $move_data->pp ];
  $pokemon->moves[] = json_decode( json_encode( $new_move ) );

  if ( 'tms' === $item_data->category->name ) {
    slackemon_remove_item( $item_id, $user_id );
  }

  slackemon_save_player_data( $player_data );

  $message['attachments'][ $action->attachment_id - 1 ]->footer = (
    ':tada: Congratulations! ' . slackemon_readable( $pokemon->name ) . ' now knows ' .
    slackemon_readable( $move_data->name ) . '!'
  );

  $message['attachments'][ $action->attachment_id - 1 ]->actions = [];

  return $message;

} // Function slackemon_get_item_teach_do_message

function slackemon_get_item_discard_message( $item_id, $action, $user_id = USER_ID ) {

  $items = slackemon_get_player_data( $user_id )->items;
  $item_data = slackemon_get_item_data( $item_id );

  $item = [ 'id' => $item_id, 'count' => 0 ];

  foreach ( $items as $_item ) {
    if ( $_item->id == $item['id'] ) {
      $item['count']++;
    }
  }

  // Remove one, because we're discarding one
  $item['count']--;

  $message = [
    'text' => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  $item_attachment = slackemon_get_item_attachment( $item );

  // Do we still have other items left of the same type?
  if ( $item['count'] ) {

    $item_attachment['footer'] = (
      '1 x ' . slackemon_readable( $item_data->name ) . ' has been discarded - ' .
      'you have ' . $item['count'] . ' left.'
    );

  } else {

    $item_attachment['footer'] = 'Your ' . slackemon_readable( $item_data->name ) . ' has been discarded.';
    $item_attachment['thumb_url'] = '';

  }

  $message['attachments'][ $action->attachment_id - 1 ] = $item_attachment;
  return $message;

} // Function slackemon_get_item_discard_message

function slackemon_add_item( $item_id, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  if ( is_numeric( $item_id ) ) {
    $player_data->items[] = [ 'id' => (int) $item_id ];
  } else if ( is_object( $item_id ) || is_array( $item_id ) ) {
    $player_data->items[] = $item_id;
  } else {
    return false;
  }

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_add_item

function slackemon_remove_item( $item_id, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  $remaining_items = [];
  $found_item = false;
  foreach ( $player_data->items as $_item ) {
    if ( $_item->id == $item_id && ! $found_item ) {
      $found_item = true;
      continue;
    }
    $remaining_items[] = $_item;
  }

  $player_data->items = $remaining_items;

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_remove_pokemon

function slackemon_get_item_attachment( $item, $expanded = false ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();
  $item_data  = slackemon_get_item_data( $item['id'] );

  $fields = [];
  $description = '';
  $effect_text = '';
  $flavour_text = '';

  // Get description - for machines, the flavour text shows the effect of the move rather than the item
  $effect_text  = slackemon_get_effect_text( $item_data );
  $flavour_text = slackemon_get_flavour_text( $item_data );

  if ( in_array( $item_data->original_category_name, slackemon_get_effect_text_categories() ) ) {
    $description = $effect_text;
  } else {
    $description = $flavour_text;
  }

  if ( $expanded ) {

    if ( $is_desktop ) {
      $fields[] = []; // Line break
    }

    if ( 'all-machines' === $item_data->original_category_name ) {

      $move_data = slackemon_get_machine_move_data( $item['id'] );

      if ( $move_data ) {

        $fields[] = [
          'title' => 'Move Effect',
          'value' => str_replace( "\n", ' ', slackemon_get_flavour_text( $move_data ) ),
          'short' => false,
        ];

        $fields[] = [
          'title' => 'Machine Value',
          'value' => slackemon_get_item_cost( $item['id'] ),
          'short' => true,
        ];
        $fields[] = [
          'title' => 'Move PP',
          'value' => $move_data->pp,
          'short' => true,
        ];
        $fields[] = [
          'title' => 'Type',
          'value' => slackemon_emojify_types( slackemon_readable( $move_data->type->name ) ),
          'short' => true,
        ];
        $fields[] = [
          'title' => 'Power',
          'value' => $move_data->power ? $move_data->power : '0',
          'short' => true,
        ];
        $fields[] = [
          'title' => 'Accuracy',
          'value' => $move_data->accuracy ? $move_data->accuracy : 'n/a',
          'short' => true,
        ];
        $fields[] = [
          'title' => 'Damage Type',
          'value' => slackemon_readable( $move_data->damage_class->name ),
          'short' => true,
        ];

      } // If move data

    } else {

      $effect_text = slackemon_clean_item_description( $effect_text );
      $effect_text_split = preg_split( '/((?:Held|Used).*?):/', $effect_text, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

      // If we found multiple effects based on hold status, we'll split them up into separate fields
      // preg_split will return the title and the text in separate array elements, so we must combine them in our loop
      if ( is_array( $effect_text_split ) && count( $effect_text_split ) > 1 ) {
        $_title = '';
        foreach ( $effect_text_split as $_text ) {
          if ( ! $_title ) { $_title = $_text; continue; }

          // Many Pokemon names come from the API here in lowercase for some reason
          $_text = preg_replace_callback( '/( an? )([a-z\-\s]*?) into ([a-z\-\s]*?[,\.])/', function( $matches ) {
            return $matches[1] . ucwords( $matches[2] ) . ' into ' . ucwords( $matches[3] );
          }, trim( $_text ) );
          $_text = preg_replace_callback( '/ evolves into ([a-z\-\s]*?) when /', function( $matches ) {
            return ' evolves into ' . ucwords( $matches[1] ) . ' when ';
          }, trim( $_text ) );
          
          $fields[] = [
            'title' => strtotitle( $_title ),
            'value' => $_text,
            'short' => false,
          ];

          $_title = ''; // Reset the title so the next iteration starts again

        }
      } else if ( $effect_text ) {
        $fields[] = [
          'title' => 'Item Effect',
          'value' => $effect_text,
          'short' => false,
        ];
      }

      $fields[] = [
        'title' => 'Item Value',
        'value' => slackemon_get_item_cost( $item['id'] ),
        'short' => true,
      ];

    } // If all-machines / else
  } // If expanded / else

  // Clean the description
  $description = slackemon_clean_item_description( $description, true );

  $attributes = [];
  foreach ( $item_data->attributes as $_attribute ) {
    $attributes[] = $_attribute->name;
  }

  // How this item interacts with Pokemon
  // Because the API is missing a lot of item data, we need to assume some attributes based on categories

  $is_holdable = (
    in_array( 'holdable', $attributes )         ||
    in_array( 'holdable-active', $attributes )  ||
    in_array( 'holdable-passive', $attributes ) ||
    'jewels'     === $item_data->original_category_name ||
    'plates'     === $item_data->original_category_name ||
    'held-items' === $item_data->original_category_name ||
    'other'      === $item_data->original_category_name || // Used for 'Other berries'
    'type-protection' === $item_data->original_category_name ||
    ( 'evolution' === $item_data->original_category_name && false !== strpos( $effect_text, 'Held by' ) )
  );

  if (
    'healing' === $item_data->original_category_name
  ) {
    $is_holdable = false;
  }

  $is_usable = (
    in_array( 'useable-overworld', $attributes )      ||
    'vitamins'     === $item_data->original_category_name ||
    'healing'      === $item_data->original_category_name ||
    'medicine'     === $item_data->original_category_name ||
    'status-cures' === $item_data->original_category_name ||
    'pp-recovery'  === $item_data->original_category_name ||
    'effort-drop'  === $item_data->original_category_name ||
    ( 'evolution' === $item_data->original_category_name && false !== strpos( $effect_text, 'Used on' ) )
  );

  $is_teachable = (
    'all-machines' === $item_data->original_category_name
  );

  $is_consumable = (
    in_array( 'consumable', $attributes ) ||
    'tms' === $item_data->category->name  ||
    'vitamins'     === $item_data->original_category_name ||
    'medicine'     === $item_data->original_category_name ||
    'status-cures' === $item_data->original_category_name ||
    'evolution'    === $item_data->original_category_name ||
    'effort-drop'  === $item_data->original_category_name
  );

  if ( $item['count'] ) {
    $actions = [
      (
        $expanded ?
        [] :
        [
          'name'  => 'items/view',
          'text'  => ':eye: View',
          'type'  => 'button',
          'value' => $item['id'],
        ]
      ), (
        $is_holdable ?
        [
          'name'  => 'items/give',
          'text'  => ':gift: Give (1)',
          'type'  => 'button',
          'value' => $item['id'],
          'style' => 'primary',
        ] :
        []
      ), (
        $is_usable ?
        [
          'name'  => 'items/use',
          'text'  => ':crystal_ball: Use' . ( $is_consumable ? ' (1)' : '' ),
          'type'  => 'button',
          'value' => $item['id'],
          'style' => 'primary',
        ] :
        []
      ), (
        $is_teachable ?
        [
          'name'  => 'items/teach',
          'text'  => ':trophy: Teach' . ( $is_consumable ? ' (1)' : '' ),
          'type'  => 'button',
          'value' => $item['id'],
          'style' => 'primary',
        ] :
        []
      ), [
        'name'  => 'items/discard',
        'text'  => ':heavy_multiplication_x: Discard (1)',
        'type'  => 'button',
        'value' => $item['id'],
        'style' => 'danger',
      ]
    ];
  } else {
    $actions = [];
  }

  $attachment = [
    'text' => (
      '*' . slackemon_readable( $item_data->name ) . '* (' . $item['count'] . ')' . "\n" .
      $description
    ),
    'fields'    => $fields,
    'mrkdwn_in' => [ 'text' ],
    'color'     => '#333333',
    'thumb_url' => slackemon_get_cached_image_url( $item_data->sprites->default ),
    'actions'   => $actions,
  ];

  return $attachment;

} // Function slackemon_get_item_attachment

function slackemon_get_item_cost( $item_id ) {

  $item_data = slackemon_get_item_data( $item_id );

  if ( $item_data->cost ) {
    $item_cost = '$' . str_replace( '.00', '', number_format( $item_data->cost, 2 ) );
  } else {
    $item_cost = 'Priceless';
  }

  return $item_cost;

} // Function slackemon_get_item_cost

function slackemon_clean_item_description( $text, $extended_clean = false ) {

  $text = str_replace([
    "\n:",
    "\n",
    '  ',
    '  ',
    '  ',
    '[SLACKEMON_NEWLINE]',
  ], [
    ': ',
    ' ',
    ' ',
    ' ',
    ' ',
    "\n",
  ], $text );

  // Ensure move names for machines are in uppercase, and bold them while we're at it
  $text = preg_replace_callback( '/Teaches (.*?) to a/', function( $matches ) {
    return 'Teaches *' . ucwords( $matches[1] ) . '* to a';
  }, $text );

  // Clean up machine descriptions where they refer to older generations
  $text = preg_replace( '/ \(Gen .*?\)/', '', $text );

  // Other minor replacements
  $text = preg_replace([
    '/SolarBeam/',
    '/\bSp\b\.?/',
    '/\bDef\b\.?/',
    '/\bAt{1,2}k\b\.?/',
    '/\bExp\b\.?/',
  ],[
    'Solar Beam',
    'Special',
    'Defense',
    'Attack',
    'Experience',
  ], $text );

  if ( $extended_clean ) {

    $text = str_replace([
      'It’s a ',
      'An item to be held by a Pokémon. ',
      'An item for use on a Pokémon. ',
      'A Berry to be consumed by Pokémon. ',
      //'It ',
    ], [
      'A ',
      '',
    ], $text );

    $text = ucfirst( $text );

  }

  return $text;

} // Function slackemon_clean_item_description

function slackemon_get_item_category_emoji( $category_name, $include_space = 'after' ) {

  $emoji = [
    'hms' => ':dvd:',
    'tms' => ':cd:',
    'vitamins' => ':pill:',
    'stat-boosts' => ':chart_with_upwards_trend:',
    'jewels' => ':gem:',
    'standard-balls' => ':pokeball:',
    'healing' => ':syringe:',
  ];

  if ( isset( $emoji[ $category_name ] ) ) {

    return (
      ( 'before' === $include_space ? ' ' : '' ) .
      $emoji[ $category_name ] .
      ( 'after' === $include_space ? ' ' : '' )
    );

  } else {
    return '';
  }

} // Function slackemon_get_item_category_emoji

/**
 * Rewrites API category names into our own alternative names - sometimes further splitting categories up.
 * This function takes care of returning both sides of the rewrite - from the item perspective, and from the category
 * list perspective. So, when item data is passed in, a single category is returned. When item data is not passed in,
 * an array of potential replacement categories are returned.
 */
function slackemon_rewrite_item_category( $category_name, $item_data = null ) {

  $category_names = [ $category_name ];

  switch ( $category_name ) {

    case 'all-machines':
      if ( $item_data ) {
        switch ( substr( $item_data->name, 0, 2 ) ) {
          case 'tm': $category_name = "tms"; break;
          case 'hm': $category_name = "hms"; break;
          default:   $category_name = "other-machines"; break;
        }
      } else {
        $category_names = [ 'tms', 'hms', 'other-machines' ];
      }
    break;

  }

  if ( $item_data ) {
    return $category_name;
  } else {
    return $category_names;
  }

} // Function slackemon_rewrite_item_category

/**
 * Gets a list of category names (BEFORE rewriting) that use the effect text from the API, rather than the flavour
 * text, when constructing a description of the item.
 */
function slackemon_get_effect_text_categories(){
  return [
    'all-machines',
  ];
}

function slackemon_get_item_name( $item_id ) {

  return slackemon_get_item_data( $item_id )->name;

} // Function slackemon_get_item_name

function slackemon_get_item_description( $item_name_or_id ) {

  $item_data = slackemon_get_item_data( $item_name_or_id );

  // For all items, use the flavour text, except for TM/HMs, use the effect text (not the short one)
  // This is because for machines, the flavour text shows the *effect* of the move rather than the move taught

  if ( in_array( $item_data->original_category_name, slackemon_get_effect_text_categories() ) ) {
    $description = slackemon_get_effect_text( $item_data );
  } else {
    $description = slackemon_get_flavour_text( $item_data );
  }

  if ( $description ) {
    return slackemon_clean_item_description( $description );
  } else {
    return false;
  }

} // Function slackemon_get_item_description

/** Attempts to return the data or just the system name of a move taught by a TM/HM. */
function slackemon_get_machine_move_data( $item_id, $name_only = false ) {

  $item_data   = slackemon_get_item_data( $item_id );
  $effect_text = slackemon_get_effect_text( $item_data );

  if ( preg_match( '/Teaches (.*?) to a/', $effect_text, $matches ) ) {

    $move_name = strtolower( str_replace( ' ', '-', $matches[1] ) );

    // This move is referred to incorrectly in the item API
    if ( $move_name === 'solarbeam' ) {
      $move_name = 'solar-beam';
    }

    if ( $name_only ) {
      return $move_name;
    }

    $move_data = slackemon_get_move_data( $move_name );

    if ( $move_data && ! isset( $move_data->detail ) ) { // The 'detail' property is used to say 'Not found'
      return $move_data;
    }

  } // If preg_match

  return false;

} // Function slackemon_get_machine_move_data

// The end!
