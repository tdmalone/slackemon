<?php
/**
 * Tools menu for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_tools_menu() {

  $message = [
    'text' => '*Tᴏᴏʟs*', // Tools
    'attachments' => [
      slackemon_get_bulk_transfer_attachment(),
      slackemon_get_move_deleter_attachment(),
      slackemon_back_to_menu_attachment(),
    ],
  ];

  return $message;

} // Function slackemon_get_tools_menu

function slackemon_get_bulk_transfer_attachment() {

  $attachment = [
    'text' => (
      ':outbox_tray: *Bulk Transfer Tool*' . "\n" .
      'Provides a list of your duplicate Pokémon, and based on evolution possibilities, level, movesets, ' .
      'IVs, EVs and happiness level, allows you to transfer the less \'favourable\' Pokémon all at once.'
    ),
    'color' => 'warning',
    'actions' => [
      [
        'name'  => 'tools',
        'text'  => 'See My Duplicates',
        'type'  => 'button',
        'value' => 'bulk-transfer',
        'style' => 'primary',
      ],
    ],
  ];

  return $attachment;

} // Function slackemon_get_bulk_transfer_attachment

function slackemon_get_move_deleter_attachment() {

  // TODO: Rather than re-doing most of the code that is used in slackemon_get_battle_menu_add_attachment(),
  //       we should instead abstract the sorting and constructing of a Pokemon drop-down menu. Can probably
  //       also be used for items as well (move teaching, item use and item giving).

  $player_data = slackemon_get_player_data();
  slackemon_sort_player_pokemon( $player_data->pokemon, [ 'name', 'is_favourite', 'level', 'cp', 'ts' ] );

  // Prepare message menu options - if we have more than 100 Pokemon, we need to set up an interactive search to
  // prevent Slack from cutting the additional Pokemon off.
  if ( count( $player_data->pokemon ) > 100 ) {
    $message_menu_options = [
      'data_source'      => 'external',
      'min_query_length' => 1,
    ];
  } else {
    $message_menu_options = [
      'options' => array_map(
        function( $_pokemon ) {

          // Use the same funciton that the battle menu uses.
          return slackemon_get_battle_menu_add_option( $_pokemon );

        },
        $player_data->pokemon
      ),
    ];
  }

  $attachment = [
    'text' => (
      ':radioactive_sign: *Move Deleter*' . "\n" .
      'Allows you to have your Pokémon \'forget\' certain moves so they can be taught new ones. Some ' .
      'moves, however, might never be able to be learnt again, so choose wisely!'
    ),
    'actions' => [
      array_merge(
        [
          'name' => 'tools/move-deleter',
          'text' => 'Choose a Pokémon...',
          'type' => 'select',
        ],
        $message_menu_options
      )
    ],
    'color' => 'warning',
  ];

  return $attachment;

} // Function slackemon_get_move_deleter_attachment

function slackemon_bulk_transfer_tool( $do_transfers = false ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => ':outbox_tray: *Bᴜʟᴋ Tʀᴀɴsғᴇʀ Tᴏᴏʟ*' . "\n", // Bulk Transfer Tool
    'attachments' => [
      slackemon_back_to_menu_attachment(),
    ],
  ];

  $collection = slackemon_get_bulk_transfer_pokemon();
  $total_count = 0;
  $transfer_count = 0;

  if ( ! count( $collection ) ) {
    $message['text'] .= 'Looks like you have no duplicate Pokémon at the moment! Go catch some more. :wink:';
    return $message;
  }

  if ( $do_transfers ) {
    if ( $transfer_count = slackemon_do_bulk_transfer() ) {
      $message['text'] .= (
        $transfer_count . ' duplicate Pokémon ' . ( 1 === $transfer_count ? 'has' : 'have' ) . ' been transferred ' .
        'to the Professor.' . "\n\n" .
        '*+' . ( $transfer_count * 5 ) . ' XP*: Bulk transferred ' . $transfer_count . ' Pokémon :outbox_tray:'
      );
    } else {
      $message['text'] .= (
        ':worried: *Oops! The bulk transfer of your Pokémon appears to have failed.*' . "\n" .
        'Please check your Pokémon list and try again.'
      );
    }
    return $message;
  }

  $message['text'] .= (
    ( $is_desktop ? 'Please review this list of duplicate Pokémon carefully.' . "\n" : "\n" ) .
    'Click _Do Transfers_ below if you are ready to transfer all Pokémon marked with an :x:.' . "\n\n" .
    (
      $is_desktop ?
      'Pokémon have been evaluated by their *level*, *movesets*, *IVs*, *EVs* and *happiness*, and given a ' .
      'weighting. Moveset weightings take into account the Same Type Attack Bonus if applicable. Pokémon that ' .
      'evolve have had a higher weighting given to their IVs.' . "\n\n" .
      'Pokemon with branched evolutions (eg. :eevee: Eevee and :poliwag: Poliwag) are currently not handled by ' .
      'this tool.' . "\n\n" :
      ''
    ) .
    '*This tool will _not_ transfer favourite, battle team, or item-holding Pokémon.* ' .
    ':sparkling_heart: :facepunch: :gift:' . "\n" .
    'When using this tool you will receive *half the normal XP* for each transfer.' . "\n\n"
  );

  foreach ( $collection as $pokedex_id => $collection_data ) {

    $pokemon_name = $collection_data['pokemon'][0]['data']->name;

    $message['text'] .= (
      ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $pokemon_name . ': ' : '' ) .
      '*' . slackemon_readable( $pokemon_name ) . '* ' .
      ( $collection_data['evolves'] ? '_(evolves)_' : '_(does not evolve)_' ) .
      "\n"
    );

    foreach ( $collection_data['pokemon'] as $pokemon ) {

      $total_count++;

      if ( $pokemon['transfer'] ) {
        if ( $pokemon['data']->is_favourite ) {
          $_emoji = ':sparkling_heart:';
        } else if ( $pokemon['data']->is_battle_team ) {
          $_emoji = ':facepunch:';
        } else if ( isset( $pokemon['data']->held_item ) && $pokemon['data']->held_item ) {
          $_emoji = ':gift:';
        } else {
          $_emoji = ':x:';
          $transfer_count++; // We actually will only transfer if the above conditions aren't true
        }
      } else {
        $_emoji = ':white_check_mark:';
      }

      $message['text'] .= (
        ( $is_desktop ? '        ' : '' ) .
        $_emoji . ' ' .
        ( $is_desktop ? slackemon_get_gender_symbol( $pokemon['data']->gender, 'after' ) : '' ) .
        ( $is_desktop ? 'Caught ' . slackemon_get_relative_time( $pokemon['data']->ts, false ) . ' • ' : '' ) .
        $pokemon['data']->cp . ' CP' . ' • ' .
        'Level ' .
        ( ! $is_desktop && $collection_data['highest_level'] >= 10 && $pokemon['data']->level < 10 ? '  ' : '' ) .
        ( ! $is_desktop && $collection_data['highest_level'] >= 100 && $pokemon['data']->level < 100 ? '  ' : '' ) .
        ( $is_desktop ? $pokemon['data']->level : floor( $pokemon['data']->level ) ) . ' • ' .
        ( ! $is_desktop && $pokemon['mp'] < 10 ? '  ' : '' ) .
        ( ! $is_desktop && $pokemon['mp'] < 100 ? '  ' : '' ) .
        $pokemon['mp'] . ' ' . ( $is_desktop ? 'move power' : 'MP' ) . ' • ' .
        $pokemon['iv'] . '% IVs' .
        (
          $is_desktop ?
          ' • ' .
          slackemon_get_combined_evs( $pokemon['data']->evs ) . ' EVs • ' .
          floor( $pokemon['data']->happiness / 255 * 100 ) . '% happiness' :
          ''
        )
      );

      $message['text'] .= "\n";

    }

    if ( ! $is_desktop ) {
      $message['text'] .= "\n";
    }

  } // Foreach collection

  array_unshift( $message['attachments'][0]['actions'], [
    'name'  => 'tools',
    'text'  => ':outbox_tray: Do Transfers',
    'type'  => 'button',
    'value' => 'bulk-transfer/do',
    'style' => 'danger',
    'confirm' => [
      'title' => 'Are you sure?',
      'text'  => (
        'Are you sure you want to transfer ' . $transfer_count . ' Pokémon? This cannot be undone. ' .
        'You will earn half the normal XP for each transfer.'
      ),
    ],
  ]);

  return $message;

} // Function slackemon_bulk_transfer_tool

function slackemon_move_deleter_tool( $spawn_ts, $move_just_deleted = '', $move_deletion_successful = null ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => ':radioactive_sign: *Mᴏᴠᴇ Dᴇʟᴇᴛᴇʀ*' . "\n", // Move Deleter
  ];

  $pokemon      = slackemon_get_player_pokemon_data( $spawn_ts );
  $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );

  $message['text'] .= (
    'Select a move for ' .
    ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':' . $pokemon->name . ': ' : '' ) .
    '*' . slackemon_readable( $pokemon->name ) . '* to forget.' . "\n" .
    $pokemon->cp . ' CP • ' .
    'L' . floor( $pokemon->level ) . ' • ' .
    slackemon_appraise_ivs( $pokemon->ivs, false ) . ' (' . slackemon_get_iv_percentage( $pokemon->ivs ) .'%)' .
    ( $is_desktop ? ' • ' : "\n" ) .
    slackemon_get_evolution_chain( $pokemon->pokedex, '_(does not evolve)_' )
  );

  $message['attachments'] = [];
  $final_actions = [];

  if ( $move_just_deleted ) {
    $message['attachments'][] = [
      'color' => '#333333',
      'text'  => (
        $move_deletion_successful ?
        ':white_check_mark: *' . slackemon_readable( $pokemon->name ) . ' has ' .
        'forgotten how to use ' . slackemon_readable( $move_just_deleted ) . '.*' :
        ':exclamation: *Sorry, an error occurred getting ' . slackemon_readable( $pokemon->name ) . ' to ' .
        'forget ' . slackemon_readable( $move_just_deleted ) . '.*'
      ),
    ];
  }

  foreach ( $pokemon->moves as $move ) {

    $move_data     = slackemon_get_move_data( $move->name );
    $learn_methods = [];

    foreach ( $pokemon_data->moves as $potential_move ) {
      if ( $potential_move->move->name === $move->name ) {
        foreach ( $potential_move->version_group_details as $version_group_details ) {
          $learn_methods[] = $version_group_details->move_learn_method->name;
        }
        break;
      }
    }

    $attachment = [
      'color' => slackemon_get_type_color( $move_data->type->name ),
      'title' => slackemon_readable( $move->name ) . ' (x' . ( $move_data->power ? $move_data->power : '0' ) . ')',
      'text'  => (
        slackemon_get_flavour_text( $move_data ) . ' ' .
        (
          in_array( 'machine', $learn_methods ) ?
          'This move may _possibly_ be taught again by using a machine.' :
          '_It may not be possible to learn this move again._'
        ) . "\n\n" .
        '' . slackemon_emojify_types( slackemon_readable( $move_data->type->name ) ) . ' • ' .
        slackemon_readable( $move_data->damage_class->name ) . ' • ' .
        $move_data->pp . ' PP' .
        ( $move_data->accuracy ? ' • ' . $move_data->accuracy . ' accuracy' : '' )
      ),
    ];

    $action = [
      'name'  => 'tools/move-deleter/do',
      'text'  => 'Forget ' . slackemon_readable( $move->name ),
      'value' => $spawn_ts . '/' . $move->name,
      'type'  => 'button',
      'style' => 'danger',
      'confirm' => [
        'title' => 'Are you sure?',
        'text'  => (
          'Are you sure you want ' . slackemon_readable( $pokemon->name ) . ' ' .
          'to forget ' . slackemon_readable( $move->name ) . '? This cannot be undone.'
        ),
      ],
    ];

    if ( count( $pokemon->moves ) > 1 ) {

      // Display all actions together on desktop, otherwise with each attachment on mobile.
      if ( $is_desktop ) {
        $final_actions[] = $action;
      } else {
        $attachment['actions'] = [ $action ];
      }

    } else {
      $attachment['text'] .= (
        "\n\n" .
        '_' . slackemon_readable( $pokemon->name ) . ' cannot forget ' . slackemon_readable( $move->name ) . ', ' .
        'as it is the only move ' . slackemon_get_gender_pronoun( $pokemon->gender ) . ' knows!_'
      );
    }

    $message['attachments'][] = $attachment;

  } // Foreach move

  if ( count( $final_actions ) ) {
    $message['attachments'][] = [
      'fallback' => 'Move removal action buttons',
      'actions'  => $final_actions,
      'color'    => '#333333',
    ];
  }

  $message['attachments'][] = slackemon_back_to_menu_attachment( [ 'tools', 'main' ] );

  return $message;

} // Function slackemon_move_deleter_tool

// The end!
