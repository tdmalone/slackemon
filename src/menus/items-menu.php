<?php
/**
 * Items ('Bag') menu for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_items_menu( $category_name = '', $page_number = 1 ) {

  $player_data = slackemon_get_player_data();
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  // This cannot exceed 5 for desktop, otherwise Slack will not show the additional action buttons
  $categories_per_pocket = $is_desktop ? 4 : 100;

  $items = [];
  $pockets = [];
  $categories = [];
  $attachments = [];

  // Loop through each item, and get the count of each plus category unique counts
  foreach ( $player_data->items as $item ) {

    $item_data = slackemon_get_item_data( $item->id );

    if ( ! isset( $items[ 'item' . $item->id ] ) ) {

      $items[ 'item' . $item->id ] = [
        'id'       => $item->id,
        'count'    => 0,
        'name'     => $item_data->name,
        'category' => $item_data->category->name,
      ];

      if ( ! isset( $categories[ $item_data->category->name ] ) ) {
        $categories[ $item_data->category->name ] = [
          'unique_count' => 0,
          'total_count'  => 0,
          'first_image'  => slackemon_get_cached_image_url( $item_data->sprites->default ),
        ];
      }

      $categories[ $item_data->category->name ]['unique_count']++;

    }

    $items[ 'item' . $item->id ]['count']++;
    $categories[ $item_data->category->name ]['total_count']++;

  } // Foreach items

  // Did we request a particular category?
  if ( $category_name ) {
    $items = array_filter( $items, function( $item ) use ( $category_name ) {
      return $item['category'] === $category_name;
    });
    return slackemon_get_item_category_menu( $category_name, $items, $page_number );
  }

  $pockets_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/item-pocket/' ) )->results;

  foreach ( $pockets_data as $pocket ) {

    $pocket_data = json_decode( slackemon_get_cached_url( $pocket->url ) );

    $pocket_categories = [];
    foreach ( $pocket_data->categories as $category ) {

      if ( isset( $categories[ $category->name ] ) ) {
        $pocket_categories[] = $category->name;
      }

      // Support rewritten category names
      $rewritten_category_names = slackemon_rewrite_item_category( $category->name );
      if ( $rewritten_category_names[0] !== $category->name || count( $rewritten_category_names ) > 1 ) {
        foreach ( $rewritten_category_names as $_cat ) {
          if ( isset( $categories[ $_cat ] ) ) {
            $pocket_categories[] = $_cat;
          }
        }
      }

    }

    if ( count( $pocket_categories ) ) {
      asort( $pocket_categories );
      $pockets[ $pocket->name ] = $pocket_categories;
    }

  }

  ksort( $pockets );
  $additional_pockets = 0;
  foreach ( $pockets as $pocket_name => $pocket_categories ) {

    $chunk_count = 0;
    foreach ( array_chunk( $pocket_categories, $categories_per_pocket ) as $category_chunk ) {
      $chunk_count++;

      $actions = [];
      $options = [];

      foreach ( $category_chunk as $category_name ) {

        if ( $is_desktop ) {

          $actions[] = [
            'name'  => 'items/category',
            'text'  => (
              slackemon_get_item_category_emoji( $category_name ) .
              slackemon_readable( $category_name ) . ' ' .
              '(' . $categories[ $category_name ]['total_count'] . ')'
            ),
            'type'  => 'button',
            'value' => $category_name,
          ];

        } else {

          $options[] = [
            'text'  => (
              slackemon_readable( $category_name ) . ' ' .
              '(' . $categories[ $category_name ]['total_count'] . ')'
            ),
            'value' => $category_name,
          ];

        }

      } // Foreach category_chunk

      if ( ! $is_desktop ) {
        $actions[] = [
          'name'    => 'items/category',
          'text'    => 'Select category...',
          'type'    => 'select',
          'options' => $options,
        ];
      }

      $attachments[] = [
        'text'      => (
          '*' .
          slackemon_readable( $pocket_name ) .
          ( count( $pocket_categories ) > $categories_per_pocket ? ' #' . $chunk_count : '' ) .
          '*'
        ),
        'actions'   => $actions,
        'color'     => '#333333',
        'thumb_url' => $is_desktop ? $categories[ $category_chunk[ array_rand( $category_chunk ) ] ]['first_image'] : '',
      ];

    } // Foreach category_chunk

    if ( $chunk_count > 1 ) {
      $additional_pockets += $chunk_count - 1;
    }

  } // Foreach pocket

  $attachments[] = slackemon_back_to_menu_attachment();

  $message = [
    'text' => (
      '*Bᴀɢ*' . "\n" .
      'You have ' . count( $player_data->items ) . ' item' .
      ( 1 === count( $player_data->items ) ? '' : 's' ) .
      ( 1 === count( $pockets ) ? '.' : ' in your bag\'s ' . ( count( $pockets ) + $additional_pockets ) . ' pockets.' )
    ),
    'attachments' => $attachments,
  ];

  return $message;

} // Function slackemon_get_items_menu

function slackemon_get_item_category_menu( $category_name, $items, $page_number ) {

  // Sort alphabetically by name
  usort( $items, function( $item1, $item2 ) {
    return strcmp( $item1['name'], $item2['name'] ) > 0 ? 1 : -1;
  });

  $category_header = '';

  switch ( $category_name ) {

    case 'hms':
      $category_header = 'Hidden Machines teach high-value moves that can be rare to learn otherwise. These machines do not expire after use, and can be used again and again on any compatible Pokémon.';
    break;

    case 'tms':
      $category_header = 'Technical Machines teach a range of useful moves. *They expire after use*, so choose your compatible Pokémon carefully!';
    break;

  }

  $attachments = [];

  // Do pagination
  $paged_items = slackemon_paginate( $items, $page_number, SLACKEMON_ITEMS_PER_PAGE );

  foreach ( $paged_items as $item ) {
    $attachments[] = slackemon_get_item_attachment( $item );
  }

  $attachments[] = slackemon_get_pagination_attachment(
    $items, $page_number, 'items/category', SLACKEMON_ITEMS_PER_PAGE, $category_name . '/'
  );

  $menu_attachment = slackemon_back_to_menu_attachment();
  array_unshift( $menu_attachment['actions'], [
    'name'  => 'items',
    'text'  => ':handbag: Back to Bag',
    'type'  => 'button',
    'value' => 'main',
  ]);
  $attachments[] = $menu_attachment;

  $message = [
    'text' => '*Bᴀɢ - ' . slackemon_readable( $category_name ) . '*' . "\n" . $category_header,
    'attachments' => $attachments,
  ];

  return $message;

} // Function slackemon_get_item_category_menu

// The end!
