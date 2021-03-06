<?php
/**
 * Background-handler for for interactive message actions in Slackemon.
 *
 * @package Slackemon
 */

// This file gets called directly, so we must set up the Slackemon environment.
$action = json_decode( $_REQUEST['action'] ); // Required to be set now to successfully init an action.
require_once( __DIR__ . '/../lib/init.php' );

slackemon_handle_action( $action );

function slackemon_handle_action( $action ) {

  $action_name  = $action->actions[0]->name;

  // We support both message buttons, and the newer message menus.
  $action_value = (
    isset( $action->actions[0]->value ) ?
    $action->actions[0]->value :                    // Message button value response.
    $action->actions[0]->selected_options[0]->value // Message menu value response.
  );

  switch ( $action_name ) {

    case 'onboarding':
      switch ( $action_value ) {

        case 'join':
          $message = slackemon_get_onboarding_menu();
          slackemon_register_player();
        break;

        case 'catch':

          $message = [
            'attachments' => [
              [
                'text' => (
                  '*You found a Pokémon!*' . "\n" .
                  'See your direct messages to continue.'
                ),
              ],
            ],
          ];

          // A new player has joined - everyone gets a spawn!
          $spawn_trigger = [
            'type'    => 'onboarding',
            'user_id' => USER_ID,
          ];
          slackemon_spawn( $spawn_trigger );

        break; // Case catch.

      } // Switch action_value
    break; // Case onboarding.

    case 'menu':
      $message = slackemon_get_main_menu();
    break;

    case 'pokemon/list':
      $sort_page_value = $action_value;
      $message = slackemon_get_pokemon_menu( $sort_page_value );
    break;

    case 'pokemon/view': // Viewing a Pokemon's data from the Pokemon menu.
    case 'pokemon/view/caught': // Viewing a Pokemon's data immediately after catching it.
    case 'pokemon/view/battle': // Viewing a Pokemon's data after a battle that didn't result in a catch.
    case 'pokemon/view/caught/battle': // Viewing a Pokemon's data immediately after a catch after a battle...whew!
    case 'pokemon/view/from-battle-menu':
      $spawn_ts = $action_value;
      $message  = slackemon_get_pokemon_view_message( $spawn_ts, $action_name, $action );
    break;

    case 'pokemon/stats':
    case 'pokemon/stats/from-battle-menu':
      $message = slackemon_get_pokemon_view_message( $action_value, $action_name, $action, true );
    break;

    case 'pokemon/return-item':
      $spawn_ts = $action_value;
      $message  = slackemon_get_item_return_message( $spawn_ts, $action );
    break;

    case 'items':
      $message = slackemon_get_items_menu();
    break;

    case 'items/category':

      $action_value   = explode( '/', $action_value );
      $category_name  = $action_value[0];
      $page_number    = isset( $action_value[1] ) ? $action_value[1] : 1;

      $message = slackemon_get_items_menu( $category_name, $page_number );

    break;

    case 'items/pick-up':
      $spawn_ts = $action_value;
      $message  = slackemon_get_item_pick_up_message( $spawn_ts, $action );
    break;

    case 'items/view':
    case 'items/view-from-pickup':
    case 'items/cancel-action':
      $item_id = $action_value;
      $message = slackemon_get_item_view_message( $item_id, $action, $action_name );
    break;

    case 'items/give':
    case 'items/use':
    case 'items/teach':
      $method  = explode( '/', $action_name )[1];
      $item_id = $action_value;
      $message = slackemon_get_item_action_message( $method, $item_id, $action );
    break;

    case 'items/discard':
      $item_id = $action_value;
      $message = slackemon_get_item_discard_message( $item_id, $action );
      slackemon_remove_item( $item_id );
    break;

    case 'travel':
      if ( 'main' === $action_value ) {
        $message = slackemon_get_travel_menu();
      } else {

        // Travelling to a specific region.
        $region = $action_value;
        $message = slackemon_get_region_message( $region );
        slackemon_set_player_region( $region );

      }
    break;

    case 'battles':
      $message = slackemon_get_battle_menu();
    break;

    case 'battles/cancel':
      $battle_hash = $action_value;
      $message     = slackemon_cancel_battle_invite( $battle_hash, $action, 'inviter' );
    break;

    case 'battles/accept':
      $battle_hash = $action_value;
      slackemon_start_battle( $battle_hash, $action );
    break;

    case 'battles/decline':
      $battle_hash = $action_value;
      $message     = slackemon_cancel_battle_invite( $battle_hash, $action, 'invitee' );
    break;

    case 'battles/item':

      $action_value = explode( '/', $action_value );
      $item_type    = $action_value[0];

      switch ( $item_type ) {
        case 'pokeball':
          $spawn_ts = $action_value[1];
          $message  = slackemon_get_catch_message( $spawn_ts, $action, true );
        break;
      }

    break;

    case 'battles/move':

      $action_value = explode( '/', $action_value );
      $battle_hash  = $action_value[0];
      $move_name    = $action_value[1];
      $move_type    = $action_value[2];

      // If the user has requested a swap, we need to provide the swap menu.
      if ( 'swap' === $move_type ) {

        $user_initiated = true;
        $message        = slackemon_offer_battle_swap( $battle_hash, USER_ID, $user_initiated, $action );

      } else {

        $options = [
          'is_first_move' => 'first' === $move_type,
        ];

        slackemon_do_battle_move( $move_name, $battle_hash, $action, USER_ID, $options );

      }

    break; // Case battles/move.

    case 'battles/swap/do':
    case 'battles/swap/do/user_initiated':

      $action_value   = explode( '/', $action_value );
      $battle_hash    = $action_value[0];
      $new_pokemon_ts = $action_value[1];

      $options = [
        'is_swap'                => true,
        'is_user_initiated_swap' => 'battles/swap/do/user_initiated' === $action_name,
      ];

      slackemon_do_battle_move( $new_pokemon_ts, $battle_hash, $action, USER_ID, $options );

    break;

    case 'battles/swap/cancel':

      $battle_hash = $action_value;
      $message     = [
        'attachments' => slackemon_get_battle_attachments( $battle_hash, USER_ID, 'during' ),
      ];

    break;

    case 'battles/surrender':
      $battle_hash = $action_value;
      slackemon_end_battle( $battle_hash, 'surrender' );
    break;

    case 'battles/complete': // Tally up battle stats etc. for the user.

      $action_value  = explode( '/', $action_value );
      $battle_hash   = $action_value[0];
      $battle_result = $action_value[1];

      slackemon_complete_battle( $battle_result, $battle_hash );

    break;

    case 'achievements':
      $message = slackemon_get_achievements_menu( $action_value );
    break;

    case 'mute':

      switch ( $action_value ) {

        case 'mute':
          slackemon_mute_player();
        break;

        case 'unmute':
          slackemon_unmute_player();
        break;

      }

      $message = slackemon_get_main_menu();

    break;

    case 'menu_mode':
      slackemon_set_player_menu_mode( $action_value );
      $message = slackemon_get_main_menu();
    break;

    case 'catch':
      $spawn_ts = $action_value;
      $message  = slackemon_get_catch_message( $spawn_ts, $action );
    break;

    case 'catch/start-battle':
      $spawn_ts = $action_value;
      slackemon_start_catch_battle( $spawn_ts, $action );
    break;

    case 'catch/end-battle':
      $spawn_ts = $action_value;
      $message  = slackemon_get_catch_message( $spawn_ts, $action, true, 'catch' );
    break;

    case 'transfer':
      $spawn_ts = $action_value;
      $message  = slackemon_get_pokemon_transfer_message( $spawn_ts, $action );
      slackemon_remove_pokemon( $spawn_ts );
    break;

    case 'favourite':
      $spawn_ts = $action_value;
      slackemon_favourite_pokemon( $spawn_ts );
      $message = slackemon_get_favourite_message( $action );
    break;

    case 'unfavourite':
      $spawn_ts = $action_value;
      slackemon_unfavourite_pokemon( $spawn_ts );
      $message = slackemon_get_unfavourite_message( $action );
    break;

    case 'battle-team/add':
    case 'battle-team/add/from-battle-menu':
      $spawn_ts      = $action_value;
      $is_successful = slackemon_add_to_battle_team( $spawn_ts );
      $message       = slackemon_get_battle_team_add_message( $action, $action_name, $is_successful );
    break;

    case 'battle-team/remove':
    case 'battle-team/remove/from-battle-menu':
      $spawn_ts      = $action_value;
      $is_successful = slackemon_remove_from_battle_team( $spawn_ts );
      $message       = slackemon_get_battle_team_remove_message( $action, $action_name, $is_successful );
    break;

    case 'battle-team/set-leader':
      $spawn_ts = $action_value;
      slackemon_set_battle_team_leader( $spawn_ts );
      $message = slackemon_get_battle_menu();
    break;

    case 'evolve':

      $spawn_ts = $action_value;

      // TODO: Prevent this from happening if the user is in battle (slackemon_is_player_in_battle()), in case they
      //       evolve a Pokemon that they're using to battle... which will be overridden when the battle completes!

      slackemon_start_evolution_message( $spawn_ts, $action );

      if ( slackemon_evolve_user_pokemon( $spawn_ts ) ) {
        slackemon_end_evolution_message( $spawn_ts, $action );
      } else {
        $message = slackemon_get_evolution_error_message( $spawn_ts, $action );
      }

    break;

    case 'tools':
      switch ( $action_value ) {

        case 'main':
          $message = slackemon_get_tools_menu();
        break;

        case 'bulk-transfer':
          $message = slackemon_bulk_transfer_tool();
        break;

        case 'bulk-transfer/do':
          $message = slackemon_bulk_transfer_tool( true );
        break;

      }
    break;

    case 'tools/move-deleter':
      $spawn_ts = $action_value;
      $message  = slackemon_move_deleter_tool( $spawn_ts );
    break;

    case 'tools/move-deleter/do':
      $action_value = explode( '/', $action_value );
      $spawn_ts     = $action_value[0];
      $move_name    = $action_value[1];
      $result       = slackemon_delete_user_pokemon_move( $spawn_ts, $move_name );
      $message      = slackemon_move_deleter_tool( $spawn_ts, $move_name, $result );
    break;

    default:
      $no_match = true;
    break;

  } // Switch action_name

  // If there was no match, we might need to dig deeper into a dynamic action_name.
  if ( isset( $no_match ) ) {

    $action_name = explode( '/', $action_name );

    switch ( $action_name[0] ) {

      // Item give/use/teach requests, coming through after an options request.
      case 'items':

        $method   = $action_name[1];
        $item_id  = $action_name[2];
        $spawn_ts = $action_value;

        switch ( $method ) {

          case 'give':
            $message = slackemon_get_item_give_do_message( $item_id, $spawn_ts, $action );
          break;

          case 'use':
            $message = slackemon_get_item_use_do_message( $item_id, $spawn_ts, $action );
          break;

          case 'teach':
            $message = slackemon_get_item_teach_do_message( $item_id, $spawn_ts, $action );
          break;

        }

      break; // Case 'items'.

      // Battle invite requests, coming through after an options request.
      case 'battles':

        switch( $action_name[1] ) {

          case 'invite':

            $invitee_id     = $action_name[2];
            $challenge_type = explode( '/', $action_value );

            $message        = slackemon_send_battle_invite( $invitee_id, $action, $challenge_type );

          break;

        }

      break; // Case 'battles'.

    } // Switch action_name 0.
  } // If no_match

  // If a message has been set, send it back to the user!
  if ( isset( $message ) ) {
    slackemon_do_action_response( $message );
  }

  return slackemon_exit();

} // Function slackemon_handle_action

// The end!
