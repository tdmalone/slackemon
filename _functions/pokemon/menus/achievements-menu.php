<?php

// Chromatix TM 04/04/2017
// Achievements menu for Slackemon Go

function slackemon_get_achievements_menu( $current_page ) {

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  $message = [
    'text' => '',
    'attachments' => [],
  ];

  // Pokedex

  $pokedex = slackemon_get_player_data()->pokedex;
  $pokedex_sorted = [];
  $total_seen = 0;
  $total_caught = 0;

  foreach ( $pokedex as $entry ) {
    $pokedex_sorted[ $entry->id ] = $entry;
    if ( $entry->seen   ) { $total_seen++;   }
    if ( $entry->caught ) { $total_caught++; }
  }

  ksort( $pokedex_sorted );

  // Set up pagination
  $current_page = is_numeric( $current_page ) ? $current_page : 1; // Default to page 1 if no page number
  $total_in_pokedex = count( $pokedex_sorted );
  $total_pages = ceil( $total_in_pokedex / SLACKEMON_POKEDEX_PER_PAGE );
  $current_page = $current_page <= $total_pages ? $current_page : 1; // Default to page 1 if page no. is too big
  $sorted_pokedex_page = array_chunk( $pokedex_sorted, SLACKEMON_POKEDEX_PER_PAGE )[ $current_page - 1 ];

  $message['text'] .= (
    '*Pᴏᴋᴇ́ᴅᴇx*' . "\n" . // Pokedex
    (
      $is_desktop ?
      'You have caught ' . $total_caught . ' unique Pokémon, and seen ' . $total_seen :
      'You have caught ' . $total_caught . ' and seen ' . $total_seen . ' Pokémon'
    ) . ( $total_seen ? ':' : '.' ) . "\n\n"
  );

  foreach( $sorted_pokedex_page as $entry ) {
    $pokemon = slackemon_get_pokemon_data( $entry->id );
    $readable_name = pokedex_readable( $pokemon->name );
    $gender_symbols = [ '♂', '♀' ];
    $message['text'] .= (
      ( $entry->caught ? ':' . $pokemon->name . ':' : ':grey_question:' ) . ' ' .
      '#' . $entry->id . ' - ' . 
      '*' . $readable_name . '*' .
      ( ! $is_desktop && in_array( substr( $readable_name, -1, 1 ), $gender_symbols ) ? '' : ' ' ) . '- ' .
      'Seen ' . $entry->seen . ', Caught ' . $entry->caught .
      "\n"
    );
  }

  // Pagination buttons
  if ( $total_pages > 1 ) {

    // Partial pagination mode, or all pages?
    if ( $total_pages > 5 ) {
      $pagination_actions = [
        [
          'name' => 'achievements',
          'text' => ':rewind:',
          'type' => 'button',
          'value' => '1',
          'style' => 1 == $current_page ? 'primary' : '',
        ], (
          $total_pages == $current_page ?
          [
            'name' => 'achievements',
            'text' => $current_page - 2,
            'type' => 'button',
            'value' => $current_page - 2,
          ] :
          ''
        ), (
          1 == $current_page ?
          '' : [
            'name' => 'achievements',
            'text' => $current_page - 1,
            'type' => 'button',
            'value' => $current_page - 1,
          ]
        ), [
          'name' => 'achievements',
          'text' => $current_page,
          'type' => 'button',
          'value' => $current_page,
          'style' => 'primary',
        ], (
          $total_pages == $current_page ?
          '' : [
            'name' => 'achievements',
            'text' => $current_page + 1,
            'type' => 'button',
            'value' => $current_page + 1,
          ]
        ), (
          1 == $current_page ?
          [
            'name' => 'achievements',
            'text' => $current_page + 2,
            'type' => 'button',
            'value' => $current_page + 2,
          ] : ''
        ), [
          'name' => 'achievements',
          'text' => ':fast_forward:',
          'type' => 'button',
          'value' => $total_pages,
          'style' => $total_pages == $current_page ? 'primary' : '',
        ],
      ];
    } else {
      $pagination_actions = [];
      for ( $i = 1; $i <= $total_pages; $i++ ) {
        $pagination_actions[] = [
          'name' => 'achievements',
          'text' => $i,
          'type' => 'button',
          'value' => $i,
          'style' => $i == $current_page ? 'primary' : '',
        ];
      }
    }

    $message['attachments'][] = [
      'fallback' => 'Page',
      'color' => '#333333',
      'actions' => $pagination_actions,
      'footer' => 'Page ' . $current_page . ' of ' . $total_pages,
    ];

  } // If pagination

  // Leaderboard

  $players = slackemon_get_player_ids();
  usort( $players, function( $player1_id, $player2_id ) {
    $player1_data = slackemon_get_player_data( $player1_id );
    $player2_data = slackemon_get_player_data( $player2_id );
    return $player1_data->xp < $player2_data->xp ? 1 : -1;
  });

  $leaderboard = '*Lᴇᴀᴅᴇʀʙᴏᴀʀᴅ*' . ( $is_desktop ? "\n" : "\n\n" ); // Leaderboard
  $player_count = 0;

  foreach ( $players as $player_id ) {

    $player_data = slackemon_get_player_data( $player_id );
    $player_count++;

    $caught = 0;
    $seen = 0;

    foreach ( $player_data->pokedex as $pokedex_entry ) {
      if ( $pokedex_entry->caught ) { $caught++; }
      if ( $pokedex_entry->seen ) { $seen++; }
    }

    $full_name = get_user_full_name( $player_id );
    $emoji = (
    	slackemon_is_player_active( $player_id ) ?
    	':green_circle:' : (
    		slackemon_is_player_in_battle( $player_id ) ?
    		':yellow_circle:' :
    		':black_circle:'
    	)
    );

    $leaderboard .= (
      $emoji . ' *#' . $player_count . '*. ' .
      '*' . $full_name . '*' .
      ( $is_desktop ? ': ' : "\n" . '             ' ) .
      number_format( $player_data->xp ) . ' XP' .
      ( $is_desktop ? ' - ' : "\n" . '             ' ) .
      $caught . ' caught, ' .
      $seen . ' seen' .
      ( $is_desktop ? ' - ' : "\n" . '             ' ) .
      $player_data->battles->won . ' / ' . $player_data->battles->participated . ' trainer battles won' .
      ( $is_desktop ? "\n" : "\n\n" )
    );

  } // Foreach players

  $leaderboard .= (
    ( $is_desktop ? "\n" : '' ) .
    '_Players in green are online and available to battle!_' . "\n" .
    '_Players in yellow are currently battling._'
  );

  $message['attachments'][] = [
    'pretext' => $leaderboard,
  ];

  $message['attachments'][] = slackemon_back_to_menu_attachment();

  	return $message;

} // Function slackemon_get_achievements_menu

// The end!
