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
      [
        'text' => (
          ':outbox_tray: *Bulk Transfer Tool*' . "\n" .
          'Provides a list of your duplicate Pokémon, and based on evolution possibilities, level, movesets, IVs, EVs and happiness level, allows you to transfer the less \'favourable\' Pokémon all at once.'
        ),
        'color' => 'warning',
        'actions' => [
          [
            'name' => 'tools',
            'text' => 'See My Duplicates',
            'type' => 'button',
            'value' => 'bulk-transfer',
            'style' => 'primary',
          ],
        ],
      ], [
        'text' => (
          ':radioactive_sign: *Move Deleter*' . "\n" .
          'Allows you to have your Pokémon \'forget\' certain moves so they can be taught new ones. Some moves, however, might never be able to be learnt again, so choose wisely!' . "\n" .
          '*_Coming soon._*'
        ),
        'color' => 'warning',
      ],
      slackemon_back_to_menu_attachment(),
    ],
  ];

  return $message;

} // Function slackemon_get_tools_menu

function slackemon_get_bulk_transfer_menu( $do_transfers = false ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => '*Bulk Transfer Tool*' . "\n",
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
      'Pokémon have been evaluated by their *level*, *movesets*, *IVs*, *EVs* and *happiness*, and given a weighting. Moveset weightings take into account the Same Type Attack Bonus if applicable. Pokémon that evolve have had a higher weighting given to their IVs.' . "\n\n" .
      'Pokemon with branched evolutions (eg. :eevee: Eevee and :poliwag: Poliwag) are currently not handled by this tool.' . "\n\n" :
      ''
    ) .
    '*This tool will _not_ transfer favourite, battle team, or item-holding Pokémon.* ' .
    ':sparkling_heart: :facepunch: :gift:' . "\n" .
    'When using this tool you will receive *half the normal XP* for each transfer.' . "\n\n"
  );

  foreach ( $collection as $pokedex_id => $collection_data ) {

    $pokemon_name = $collection_data['pokemon'][0]['data']->name;

    $message['text'] .= (
      ':' . $pokemon_name . ': *' . slackemon_readable( $pokemon_name ) . '* ' .
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
    'name' => 'tools',
    'text' => ':outbox_tray: Do Transfers',
    'type' => 'button',
    'value' => 'bulk-transfer/do',
    'style' => 'danger',
    'confirm' => [
      'title' => 'Are you sure?',
      'text' => (
        'Are you sure you want to transfer ' . $transfer_count . ' Pokémon? This cannot be undone. ' .
        'You will earn half the normal XP for each transfer.'
      ),
    ],
  ]);

  return $message;

} // Function slackemon_get_bulk_transfer_menu

// The end!
