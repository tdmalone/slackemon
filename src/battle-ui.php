<?php
/**
 * Battle specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_top_pokemon_list( $user_id = USER_ID, $include_legendaries = false ) {

  $player_data = slackemon_get_player_data( $user_id );

  $top_pokemon_sorted = $player_data->pokemon;

  usort( $top_pokemon_sorted, function( $pokemon1, $pokemon2 ) {
    return $pokemon1->cp < $pokemon2->cp ? 1 : -1;
  });

  $top_pokemon = [];

  foreach ( $top_pokemon_sorted as $pokemon ) {

    if ( ! $include_legendaries && slackemon_is_legendary( $pokemon->pokedex ) ) {
      continue;
    }

    $top_pokemon[] = (
      ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $pokemon->name . ': ' : '' ) .
      slackemon_readable( $pokemon->name ) . ' ' .
      $pokemon->cp . ' CP'
    );

    if ( count( $top_pokemon ) >= 3 ) {
      break;
    }

  }

  return $top_pokemon;

} // Function slackemon_get_top_pokemon_list.

function slackemon_get_battle_team_status_attachment( $user_id = USER_ID, $mode = 'inviter' ) {

  $battle_team = slackemon_get_battle_team( $user_id );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $faint_count = 0;
  $low_hp_count = 0;
  $not_max_hp_count = 0;

  foreach ( $battle_team as $pokemon ) {

    if ( 0 == $pokemon->hp ) {
      $faint_count++;
    } else if ( $pokemon->hp < $pokemon->stats->hp * .1 ) {
      $low_hp_count++;
    } else if ( $pokemon->hp < $pokemon->stats->hp ) {
      $not_max_hp_count++;
    }

  }

  if ( 'inviter' === $mode && ! slackemon_is_battle_team_full( $user_id ) ) {
    $pretext = (
      ':medal: Winning Slackémon battles will level-up your Pokémon - ' .
      'making them stronger _and_ getting you closer to evolving them.' . "\n" .
      ':arrow_right: *To send a battle challenge, you first need to choose your Battle Team ' .
      'of ' . SLACKEMON_BATTLE_TEAM_SIZE . '!*'
    );
  } else if ( $faint_count === SLACKEMON_BATTLE_TEAM_SIZE ) {
    $pretext = (
      ':exclamation: *Your battle team has fainted!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your battle team before accepting this challenge - otherwise your team will be ' .
        'chosen at random!' :
        'To challenge someone to a battle, you\'ll need to change up your battle team, or wait for your ' .
        'Pokémon to regain their strength. :facepunch:'
      )
    );
  } else if ( $faint_count ) {
    $pretext = (
      ':exclamation: *' . $faint_count . ' of the Pokémon on your team ' .
      ( 1 === $faint_count ? 'has' : 'have' ) . ' fainted!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge - otherwise your team will be chosen ' .
        'at random.' :
        'You should change up your team before your next battle - if not, fainted Pokémon will be replaced ' .
        'randomly from your collection.'
      )
    );
  } else if ( $low_hp_count ) {
    $pretext = (
      ':exclamation: *' . $low_hp_count . ' of the Pokémon on your team ' .
      ( 1 === $low_hp_count ? 'does not have' : 'do not have' ) . ' much health left!*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge.' :
        'You should change up your team before your next battle - or wait for your Pokémon to regain their strength.'
      )
    );
  } else if ( $not_max_hp_count ) {
    $pretext = (
      ':warning: *' . $not_max_hp_count . ' of ' .
      ( $is_desktop ? 'the Pokémon on your team' : 'your Pokémon' ) . ' ' .
      ( 1 === $not_max_hp_count ? 'is' : 'are' ) . ' not at full health.*' . "\n" .
      (
        'invitee' === $mode ?
        'You should change up your team before accepting this challenge.' :
        'You should change up your team before your next battle - or wait for your Pokémon to regain their strength.'
      )
    );
  } else {
    $pretext = ':white_check_mark: Your battle team is ready to go!';
  }

  $attachment = [
    'pretext' => $pretext,
    'mrkdwn_in' => [ 'pretext', 'text', 'fields' ],
  ];

  return $attachment;

} // Function slackemon_get_battle_team_status_attachment.

/** Battle swaps are either offered when a user's Pokemon faints, or when the user specifically requests a swap. */
function slackemon_offer_battle_swap( $battle_hash, $user_id, $user_initiated = false, $action = null ) {

  $battle_data     = slackemon_get_battle_data( $battle_hash );
  $current_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $user_id );
  $battle_team     = $battle_data->users->{ $user_id }->team;
  $is_desktop      = 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $swap_actions = [];

  foreach ( $battle_team as $pokemon ) {

    if ( ! $pokemon->hp ) {
      continue;
    }

    if ( $pokemon->ts === $current_pokemon->ts ) {
      continue;
    }

    $swap_actions[] = [
      'name' => 'battles/swap/do' . ( $user_initiated ? '/user_initiated' : '' ),
      'text' => (
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ? ':' . $pokemon->name . ': ' : '' ) .
        slackemon_readable( $pokemon->name ) . ' (' . $pokemon->cp . ' CP)'
      ),
      'type'  => 'button',
      'value' => $battle_hash . '/' . $pokemon->ts,
    ];

  }

  // Add a button to cancel the swap, in case it was accidentally selected by the user.
  if ( $user_initiated ) {
    $swap_actions[] = [
      'name'  => 'battles/swap/cancel',
      'text'  => 'Cancel',
      'type'  => 'button',
      'value' => $battle_hash,
      'style' => 'danger',
    ];
  }

  $swap_attachment = [
    'text' => (
      '*Who would you like to send into battle' .
      ( $is_desktop ? ' to replace ' . slackemon_readable( $current_pokemon->name ) : '' ) . '?*'
    ),
    'color'       => '#333333',
    'actions'     => $swap_actions,
    'mrkdwn_in'   => [ 'text' ],
    'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
  ];

  // If the user requested the swap, we need to return the entire message as an action response.
  // Otherwise, all we're returning is a collection of actions to include in an already constructed message.
  if ( $user_initiated ) {
    $message = [ 'attachments' => $action->original_message->attachments ];
    $message['attachments'][ $action->attachment_id - 1 ] = $swap_attachment;
    return $message;
  } else {
    return $swap_attachment;
  }

} // Function slackemon_offer_battle_swap.

/**
 * Gets the in-battle message attachments for a user.
 *
 * Note that this function can currently also be called on behalf of a wild Pokemon, who is technically masquerading
 * as a user in battle. This is a TODO - it should be more efficient so we can avoid these calls, but keep that in
 * mind if operating on the player data. You can use `'U' === substr( $user_id, 0, 1 )` to check if it is a real user
 * or not; some functions dealing with player data will do this for you.
 *
 * @param str $battle_hash
 * @param str $user_id
 * @param str $battle_stage
 * @param str $this_move_notice
 */
function slackemon_get_battle_attachments( $battle_hash, $user_id, $battle_stage, $this_move_notice = '' ) {

  // First, get and massage all the data we'll need, preparing it to be passed to our other functions.
  $battle_data = slackemon_get_battle_data( $battle_hash );
  $opponent_id = slackemon_get_battle_opponent_id( $battle_data, $user_id );
  $args = [
    'is_desktop'                 => slackemon_is_desktop( $user_id ),
    'player_data'                => slackemon_get_player_data( $user_id ),
    'battle_data'                => $battle_data,
    'battle_stage'               => $battle_stage,
    'this_move_notice'           => $this_move_notice,
    'user_id'                    => $user_id,
    'user_pokemon'               => slackemon_get_battle_current_pokemon( $battle_data, $user_id ),
    'user_remaining_pokemon'     => slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id ),
    'opponent_id'                => $opponent_id,
    'opponent_pokemon'           => slackemon_get_battle_current_pokemon( $battle_data, $opponent_id ),
    'opponent_remaining_pokemon' => slackemon_get_user_remaining_battle_pokemon( $battle_data, $opponent_id ),
  ];

  // Get attachments.
  $user_pokemon_attachments     = slackemon_get_battle_pokemon_attachments( $args, 'user' );
  $opponent_pokemon_attachments = slackemon_get_battle_pokemon_attachments( $args, 'opponent' );
  $general_attachments          = slackemon_get_battle_general_attachment( $args );

  return array_merge( $opponent_pokemon_attachments, $user_pokemon_attachments, $general_attachments );

} // Function slackemon_get_battle_attachments.

/**
 * Advises whether or not a battle is over. This only means that all of one user's Pokemon have fainted; not that
 * the battle complete routines have been invoked yet (if required - which they usually are).
 *
 * Designed to be used while building battle attachments.
 *
 * @param arr $args An array of arguments as provided by slackemon_get_battle_attachments().
 */
function slackemon_is_battle_over( $args ) {

  $is_user_turn     = $args['battle_data']->turn === $args['user_id'];
  $is_opponent_turn = $args['battle_data']->turn === $args['opponent_id'];

  $has_user_won     = $is_opponent_turn && ! $args['opponent_pokemon']->hp && ! $args['opponent_remaining_pokemon'];
  $has_opponent_won = $is_user_turn     && ! $args['user_pokemon']->hp     && ! $args['user_remaining_pokemon'];

  if ( $has_user_won || $has_opponent_won ) {
    return true;
  }

  return false;

} // Function slackemon_is_battle_over.

/**
 * Returns the general attachment displayed below active battles - could be either a complete/success message, action
 * buttons/menus, or a waiting message, depending on the state of the battle and the requesting user.
 *
 * @param arr $args An array of arguments as provided by slackemon_get_battle_attachments().
 */
function slackemon_get_battle_general_attachment( $args ) {

  $battle_actions      = slackemon_get_battle_actions( $args );
  $opponent_first_name = slackemon_get_battle_opponent_first_name( $args );
  $celebration_emoji   = ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ' :party_parrot:' : '' );  

  // For friendly battles, we will end the battle now, as there's nothing else for the user to do or acknowledge.
  if ( slackemon_is_friendly_battle( $args['battle_data'] ) && slackemon_is_battle_over( $args ) )
    slackemon_set_player_not_in_battle( $args['user_id'] );
  }

  $attachments = [];
  $pretext     = $args['this_move_notice'] . "\n\n";

  if ( $args['user_pokemon']->hp ) {

    $is_opponent_turn = $args['battle_data']->turn === $args['opponent_id'];
    $has_user_won     = $is_opponent_turn && ! $args['opponent_pokemon']->hp && ! $args['opponent_remaining_pokemon'];

    if ( $has_user_won ) {

      if ( 'wild' === $battle_data->type ) {
        $pretext .= ':tada: *You won the battle!*' . $celebration_emoji;
      } else {

        // We celebrate a bit more if it was a trainer battle :).

        $pretext .= (
          ':tada: *Cᴏɴɢʀᴀᴛᴜʟᴀᴛɪᴏɴs! You won the battle!!*' . // Congratulations.
          $celebration_emoji . $celebration_emoji . "\n"
        );

        if ( slackemon_is_friendly_battle( $args['battle_data'] ) ) {
          $pretext .= 'TODO';
        } else {
          $pretext .= 'Click the _Complete_ button to get your XP bonus and power up your Pokémon! :100:';
        }

      }

    } else if ( $is_opponent_turn ) {

      $pretext .= '*It\'s ' . $opponent_first_name . '\'s move';

      // Add a loading indicator if this is not a p2p battle, as we'll be waiting for the computer to make a move.
      if ( 'p2p' === $args['battle_data']->type ) {
        $pretext .= '.';
      } else {
        $pretext .= '... ' . slackemon_get_loading_indicator( $user_id, false );
      }

      $pretext .= '*';

    }

    $attachments[] = [
      'pretext'     => $pretext,
      'color'       => $user_has_won ? 'good' : '#333333',
      'mrkdwn_in'   => [ 'text', 'pretext' ],
      'actions'     => $battle_actions,
      'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,
    ];

  } else if ( $args['user_remaining_pokemon'] ) {

    $attachments[] = slackemon_offer_battle_swap( $args['battle_data']->hash, $args['user_id'] );

  } else {

    $pretext .= ':expressionless: *Nooo... you lost the battle!*' . "\n";

    if ( slackemon_is_friendly_battle( $args['battle_data'] ) ) {

      $pretext .= 'TODO';


    } else {

      $pretext .= 'Click the _Complete_ button to get your XP bonus and see your Pokémon.';

    }

    $attachments[] = [

      'pretext'     => $pretext,
      'mrkdwn_in'   => [ 'text', 'pretext' ],
      'color'       => 'danger',
      'callback_id' => SLACKEMON_ACTION_CALLBACK_ID,

      'actions' => [
        'name'  => 'battles/complete',
        'text'  => 'Complete Battle',
        'type'  => 'button',
        'value' => $args['battle_data']->hash . '/lost',
        'style' => 'primary',
      ],

    ];

  }

  return [ $attachment ];

} // Function slackemon_get_battle_general_attachment.

function slackemon_get_battle_actions( $battle_data, $battle_stage, $user_id, $user_pokemon ) {

  $is_desktop  = 'U' === substr( $user_id, 0, 1 ) && 'desktop' === slackemon_get_player_menu_mode( $user_id );
  $player_data = 'U' === substr( $user_id, 0, 1 ) ? slackemon_get_player_data( $user_id ) : false;

  $opponent_id = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

  $opponent_pokemon = slackemon_get_battle_current_pokemon( $battle_hash, $opponent_id );

  $user_remaining_pokemon = slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id );

  $actions = [];

  if ( $battle_data->turn === $user_id ) {

    // It's the user's turn - they can make a move, use an item, swap, flee/surrender...

    $actions[] = [
      'name'          => 'battles/move',
      'text'          => 'Make a Move',
      'type'          => 'select',
      'options'       => (
        slackemon_get_battle_move_options( $battle_data, $battle_stage, $user_id, $user_pokemon )
      ),
    ];

    $actions[] = [
      'name'          => 'battles/item',
      'text'          => 'Use Item',
      'type'          => 'select',
      'option_groups' => (
        slackemon_get_battle_item_option_groups( $battle_data, $battle_stage, $user_id, $user_pokemon )
      ),
    ];

    // TODO: At the moment, flee is only available for wild battles.
    // Surrender option will be available for non-wild battles later.
    if ( 'wild' === $battle_data->type ) {

      $verb  = 'wild' === $battle_data->type ? 'flee' : 'surrender';
      $emoji = 'flee' === $verb ? ':runner:' : ':waving_white_flag:';

      $actions[] = [
        'name'  => 'battles/surrender',
        'text'  => $emoji . ' ' . ucfirst( $verb ),
        'type'  => 'button',
        'value' => $battle_data->hash,
        'style' => 'danger',
        'confirm' => [
          'title' => 'Are you sure?',
          'text'  => (
            'Are you sure you want to ' . $verb . '? ' .
            (
              'surrender' === $verb ?
              'The other player will get experience for their part in the battle, but you will not.' :
              'Your Pokémon will not gain any experience from this battle.'
            )
          ),
        ],
      ];

    } // If wild battle.

  } else if ( $opponent_pokemon->hp || $opponent_remaining_pokemon ) {

    // It's the opponent's turn, so no actions will be added here.

  } else {

    // If we've got here, the user won!

    if ( 'wild' === $battle_data->type ) {

      $actions[] = [
        'name'  => 'catch/end-battle',
        'text'  => ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':pokeball:' : ':volleyball:' ) . ' Throw Pokéball',
        'type'  => 'button',
        'value' => $opponent_id,
        'style' => 'primary',
      ];

    }

    $actions[] = [
      'name'  => 'battles/complete',
      'text'  => ':white_check_mark: Complete Battle',
      'type'  => 'button',
      'value' => $battle_data->hash . '/won',
      'style' => 'wild' === $battle_data->type ? '' : 'primary',
    ];

  }

  return $actions;

} // Function slackemon_get_battle_actions.

function slackemon_get_battle_move_options( $battle_data, $battle_stage, $user_id, $user_pokemon ) {

  $is_desktop      = slackemon_is_desktop( $user_id );
  $move_options    = [];
  $available_moves = [];

  $user_remaining_pokemon = slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id );
  $user_pokemon->moves = slackemon_sort_battle_moves( $user_pokemon->moves, $user_pokemon->types );

  foreach ( $user_pokemon->moves as $_move ) {
    if ( floor( $_move->{'pp-current'} ) ) {
      $available_moves[] = $_move;
    }
  }

  // If there are no moves available, we resort to our backup move instead (usually Struggle).
  if ( ! count( $available_moves ) ) {
    $available_moves[] = slackemon_get_backup_move();
  }

  foreach ( $available_moves as $_move ) {

    $_move_data = slackemon_get_move_data( $_move->name );

    $damage_class_readable = (
      999 != $_move->{'pp-current'} ? // 999 is used (interally, not in the API) for moves like Struggle.
      ucfirst( substr( $_move_data->damage_class->name, 0, 2 ) ) : // Eg. Ph, Sp, St.
      ''
    );

    $move_options[] = [
      'text' => (
        $damage_class_readable . '  ' .
        (
          SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ?
          slackemon_emojify_types( ucfirst( $_move_data->type->name ), false ) . ' ' :
          ''
        ) .
        ( 999 != $_move->{'pp-current'} ? floor( $_move->{'pp-current'} ) . '/' . $_move->pp . ' • ' : '' ) .
        slackemon_readable( $_move->name ) . ' x' . ( $_move_data->power ? $_move_data->power : 0 ) .
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ? '' : ' (' . ucfirst( $_move_data->type->name ) . ')' )
      ),
      'value' => $battle_data->hash . '/' . $_move->name . '/' . ( 'start' === $battle_stage ? 'first' : '' ),
    ];

  }

  // Are swaps available?
  if ( $user_remaining_pokemon && $battle_data->users->{ $user_id }->status->swaps_remaining ) {

    $move_options[] = [
      'text'  => (
        ( $is_desktop ? ':twisted_rightwards_arrows: ' : '' ) .
        'Swap Pokémon ' .
        '(' . $battle_data->users->{ $user_id }->status->swaps_remaining . '/' . SLACKEMON_BATTLE_SWAP_LIMIT . ')'
      ),
      'value' => $battle_data->hash . '//swap', // Double slash is intentional - there is no 'name' for this 'move'.
    ];

  }

  return $move_options;

} // Function slackemon_get_battle_move_options.

/** Returns item option groups for use within in-battle message menus. */
function slackemon_get_battle_item_option_groups( $battle_data, $battle_stage, $user_id, $user_pokemon ) {

  $is_desktop   = slackemon_is_desktop( $user_id );
  $item_options = [];

  // For wild battles, allow a Pokeball to be used to catch the 'opponent'.
  if ( 'wild' === $battle_data->type ) {

    $opponent_id  = slackemon_get_battle_opponent_id( $battle_hash, $user_id );

    $item_options['pokeballs'] = [
      'text'    => 'Pokéballs',
      'options' => [
        [
          'text'  => 'Pokéball' . ( SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ? ' :pokeball:' : '' ),
          'value' => 'pokeball/' . $opponent_id,
        ],
      ],
    ];

  }

  // Bow out now if we couldn't access the player data for some reason.
  if ( ! $player_data ) {
    return $item_options;
  }

  $available_items = [];

  foreach ( $player_data->items as $item ) {

    $item_data = slackemon_get_item_data( $item->id );
    $item_attributes = [];

    foreach ( $item_data->attributes as $_attribute ) {
      $item_attributes[] = $_attribute->name;
    }

    if ( in_array( 'usable-in-battle', $item_attributes ) ) {
      if ( ! isset( $available_items[ 'item' . $item->id ] ) ) {

        $available_items[ 'item' . $item->id ] = [
          'id'       => $item->id,
          'count'    => 0,
          'name'     => $item_data->name,
          'category' => $item_data->category->name,
        ];

      }

      $available_items[ 'item' . $item->id ]['count']++;

    } // If usable-in-battle
  } // Foreach items

  usort( $available_items, function( $item1, $item2 ) {
    $cat_compare  = strcmp( $item1['category'], $item2['category'] );
    $item_compare = strcmp( $item1['name'],     $item2['name']     );
    if ( $cat_compare !== 0 ) {
      return $cat_compare  > 0 ? 1 : -1;
    } else {
      return $item_compare > 0 ? 1 : -1;
    }
  });

  foreach( $available_items as $item ) {

    // Combine all the Pokeballs together at the top
    if ( 'special-balls' === $item['category'] || 'standard-balls' === $item['category'] ) {
      $item['category'] = 'pokeballs';
    }

    if ( ! isset( $item_options[ $item['category'] ] ) ) {
      $item_options[ $item['category'] ] = [
        'text'    => slackemon_readable( $item['category'] ),
        'options' => [],
      ];
    }

    $item_options[ $item['category'] ]['options'][] = [
      'text'  => slackemon_readable( $item['name'] ) . ' (' . $item['count'] . ')',
      'value' => $item['id'],
    ];

  } // Foreach available_items

  return $item_options;

} // Function slackemon_get_battle_item_option_groups.

function slackemon_get_battle_pokemon_attachments( $pokemon, $player_id, $battle_hash, $player_type, $pretext = '' ) {

  $user_id    = 'user' === $player_type ? $player_id : slackemon_get_battle_opponent_id( $battle_hash, $player_id );
  $is_desktop = 'U' === substr( $user_id, 0, 1 ) && 'desktop' === slackemon_get_player_menu_mode( $user_id );

  $opponent_pretext = slackemon_get_battle_opponent_pretext( $args );

  $battle_data = slackemon_get_battle_data( $battle_hash );
  $hp_percentage = min( 100, floor( $pokemon->hp / $pokemon->stats->hp * 100 ) );
  $hp_color = '';
  $hp_emoji = '';

  if ( $hp_percentage >= 100 ) {
    $hp_color = 'good';
    $hp_emoji .= ':hp_left_green:';
    $hp_emoji .= str_repeat( ':hp_green:', 8 );
    $hp_emoji .= ':hp_right_green:';
  } else if ( $hp_percentage >= 50 ) {
    $hp_color = 'good';
    $hp_emoji .= ':hp_left_green:';
    $hp_emoji .= str_repeat( ':hp_green:', max( floor(      $hp_percentage / 10 ) - 1, 0 ) );
    $hp_emoji .= str_repeat( ':hp_white:',      floor( 10 - $hp_percentage / 10 )          );
    $hp_emoji .= ':hp_right_white:';
  } else if ( $hp_percentage >= 20 ) {
    $hp_color = 'warning';
    $hp_emoji .= ':hp_left_yellow:';
    $hp_emoji .= str_repeat( ':hp_yellow:', max( floor(      $hp_percentage / 10 ) - 1, 0 ) );
    $hp_emoji .= str_repeat( ':hp_white:',       floor( 10 - $hp_percentage / 10 )          );
    $hp_emoji .= ':hp_right_white:';
  } else if ( $hp_percentage >= 1 ) {
    $hp_color = 'danger';
    $hp_emoji .= ':hp_left_red:';
    $hp_emoji .= str_repeat( ':hp_red:',   max( floor(      $hp_percentage / 10 ) - 1, 0 ) );
    $hp_emoji .= str_repeat( ':hp_white:',      floor( 10 - $hp_percentage / 10 )          );
    $hp_emoji .= ':hp_right_white:';
  } else if ( ! $hp_percentage ) {
    $hp_color = '';
    $hp_emoji .= ':hp_left_white:';
    $hp_emoji .= str_repeat( ':hp_white:', 8 );
    $hp_emoji .= ':hp_right_white:';
  }

  if ( ! SLACKEMON_ENABLE_CUSTOM_EMOJI ) {
    $hp_emoji = '';
  }

  $player_battle_team = $battle_data->users->{ $player_id }->team;
  $player_battle_team_readable = [ 'fainted' => '', 'known' => '', 'unknown' => '' ];

  foreach ( $player_battle_team as $_pokemon ) {

    if (
      $_pokemon->battles->last_participated !== $battle_data->ts && // Pokemon hasn't participated in this battle yet
      $pokemon->ts !== $_pokemon->ts && // Pokemon is not the Pokemon we're sending through now
      $player_type === 'opponent' // This player is the opponent, not the user owning this Pokemon attachment
    ) {

      $player_battle_team_readable['unknown'] .= ':grey_question:';

    } else if ( ! $_pokemon->hp ) {

      $player_battle_team_readable['fainted'] .= ':heavy_multiplication_x: ';

    } else {

      if ( $player_battle_team_readable['known'] ) {
        $player_battle_team_readable['known'] .= '  ';
      }

      if ( SLACKEMON_ENABLE_CUSTOM_EMOJI ) {
        $player_battle_team_readable['known'] .= ':' . $_pokemon->name . ':';
      } else {
        $player_battle_team_readable['known'] .= ':smiling_imp:';
      }

    }
  }

  // Determine which sprite to show
  // If Pokemon hasn't fainted, show the animated sprite
  // If it has fainted, show the front static sprite if a wild battle (because it's catchable), otherwise back static
  if ( $pokemon->hp ) {

    $image_url = (
      SLACKEMON_ANIMATED_GIF_BASE .
      '/ani-' . ( 'opponent' === $player_type ? 'front' : 'back' ) . '/' .
      $pokemon->name . '.gif'
    );

  } else if ( 'wild' === $battle_data->type ) {
    $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
    $image_url = (
      'female' === $pokemon->gender && $pokemon_data->sprites->front_female ?
      $pokemon_data->sprites->front_female :
      $pokemon_data->sprites->front_default
    );
  } else {
    $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
    $image_url = (
      'female' === $pokemon->gender && $pokemon_data->sprites->back_female ?
      $pokemon_data->sprites->back_female :
      $pokemon_data->sprites->back_default
    );
  }

  $status_attachment = [
    'pretext'  => $pretext,
    'fallback' => $pretext,
    'text' => (
      (
        'wild' === $battle_data->type && 'opponent' === $player_type ?
        '' :
        (
          'wild' === $battle_data->type ?
          '' : 
          ':bust_in_silhouette: ' .
          slackemon_get_slack_user_first_name( $player_id ) . '    ' .
          $player_battle_team_readable['fainted'] . $player_battle_team_readable['known'] . $player_battle_team_readable['unknown'] . "\n\n"
        )
      ) .
      '*' .
      slackemon_readable( $pokemon->name, false ) .
      slackemon_get_gender_symbol( $pokemon->gender ) .
      '*' . '     ' .
      (
        isset( $pokemon->flags ) && in_array( 'hide_stats', $pokemon->flags ) ?
        '???' . '     ' . '???' :
        'L' . $pokemon->level . '     ' . $pokemon->cp . ' CP'
      ) . '       ' .
      slackemon_emojify_types( join( ' ' , $pokemon->types ), false ) . "\n" .
      $hp_percentage . '%' . $hp_emoji
    ),
    'color' => $hp_color,
    'mrkdwn_in' => [ 'pretext', 'text' ],
  ];

  $image_attachment = [
    'fallback'  => $pretext,
    'text'      => ' ',
    'image_url' => slackemon_get_cached_image_url( $image_url ),
  ];

  if ( 'opponent' === $player_type ) {
    return [ $status_attachment, $image_attachment ];
  }

  return [ $image_attachment, $status_attachment ];

} // Function slackemon_get_battle_pokemon_attachments.

function slackemon_get_battle_opponent_pretext( $battle_data, $opponent_pokemon ) {
    
  switch ( $battle_stage ) {

    case 'start': // Brand new battle is starting.
    case 'first': // First move has been made by the battle invitee.

      if ( 'wild' === $battle_data->type ) {

        $opponent_pretext = (
          '*' . slackemon_readable( $opponent_pokemon->name ) . '* is up for a battle! ' .
          ucfirst( slackemon_get_gender_pronoun( $opponent_pokemon->gender ) ) . ' gets to go first.' . "\n" .
          'Take care - a wild Pokémon could flee at any time.'
        );

      } else {

        $opponent_pretext = (
          $opponent_first_name . ' has chosen *' . slackemon_readable( $opponent_pokemon->name ) . '*! ' .
          ucfirst( slackemon_get_gender_pronoun( $opponent_pokemon->gender ) ) . ' has ' .
          '*' . $opponent_pokemon->cp . ' CP*.'
        );

      }

    break;

  } // Switch battle_stage.

  return $opponent_pretext;

} // Function slackemon_get_battle_opponent_pretext.

/**
 * Gets the first name of a battle opponent - either a wild Pokemon's name, or another player's first name.
 *
 * Designed to be used while building battle attachments.
 *
 * @param arr $args An array of arguments as provided by slackemon_get_battle_attachments().
 */
function slackemon_get_battle_opponent_first_name( $args ) {

  if ( 'wild' === $args['battle_data']->type ) {
    return slackemon_readable( $args['opponent_pokemon']->name );
  }
    
  return slackemon_get_slack_user_first_name( $args['opponent_id'] );

} // Function slackemon_get_battle_opponent_first_name.

/** Sometimes a user may try to use a battle that has already ended - this returns an appropriate error message. */
function slackemon_battle_has_ended_message() {

  // Ensure the user is out of battle mode, to try to prevent them being caught in it.
  slackemon_set_player_not_in_battle();

  return slackemon_send2slack([
    'text' => (
      ':open_mouth: *Oops! It appears this battle may have ended!*' . "\n" .
      'If this doesn\'t seem right to you, check with your battle opponent.' . "\n" . 
      'If you think something may be wrong with Slackémon, please chat to <@' . SLACKEMON_MAINTAINER . '>.'
    ),
    'attachments' => [
      slackemon_back_to_menu_attachment()
    ],
  ]);

} // Function slackemon_battle_has_ended_message

function slackemon_readable_challenge_type( $challenge_type ) {
  return slackemon_readable( join( ' ', $challenge_type ) );
}

// The end!
