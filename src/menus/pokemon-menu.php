<?php

// Chromatix TM 04/04/2017
// Pokemon organising menu options for Slackemon Go

function slackemon_get_pokemon_menu( $sort_page_value ) {

  $player_data = slackemon_get_player_data();
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  if ( ! count( $player_data->pokemon ) ) {

    $message = [
        'text' => (
          ':open_mouth: *Oh! You haven\'t caught any Pokémon yet!*' . "\n" . 
          'Hang out a little longer and one will appear soon!'
        ),
        'attachments' => [ slackemon_back_to_menu_attachment() ],
      ];

    return $message;

  }

  $message = [];

  // Set up sorting
  $valid_sorting_values = [
    'recent'      => ( $is_desktop ? ':clock2: '                   : '' ) . 'Recent',
    'number'      => ( $is_desktop ? ':hash: '                     : '' ) . 'Number',
    'name'        => ( $is_desktop ? ':a: '                        : '' ) . 'Name',
    'level'       => ( $is_desktop ? ':part_alternation_mark: '    : '' ) . 'Level',
    'favourite'   => ( $is_desktop ? ':sparkling_heart: '          : '' ) . 'Favourite',
    'battle-team' => ( $is_desktop ? ':facepunch: '                : '' ) . 'Battle Team',
    'cp'          => ( $is_desktop ? ':chart_with_upwards_trend: ' : '' ) . 'Combat Power (CP)',
    'move-power'  => ( $is_desktop ? ':bow_and_arrow: '            : '' ) . 'Move Power (MP)',
    'hp'          => ( $is_desktop ? ':pill: '                     : '' ) . 'Hit Points (HP)',
    'ivs'         => ( $is_desktop ? ':reminder_ribbon: '          : '' ) . 'Individual Values (IVs)',
    'evs'         => ( $is_desktop ? ':sports_medal: '             : '' ) . 'Effort Values (EVs)',
    'happiness'   => ( $is_desktop ? ':grinning: '                 : '' ) . 'Happiness',
    'held-item'   => ( $is_desktop ? ':gift: '                     : '' ) . 'Held Item',
  ];
  $valid_types = [ 'all-types' => 'All types' ];
  $_types = json_decode( get_cached_url( 'http://pokeapi.co/api/v2/type/' ) );
  foreach ( $_types->results as $_type ) {
    if ( trim( basename( $_type->url ), '/' ) > 1000 ) { continue; } // Skip non-standard types (large IDs)
    if ( $is_desktop ) {
      $valid_types[ $_type->name ] = slackemon_emojify_types( ucwords( $_type->name ), true, 'before' );
    } else {
      $valid_types[ $_type->name ] = ucwords( $_type->name );
    }
  }
  ksort( $valid_types );

  // Get the player's current set sort modes if they still exist as valid modes, or fallback to defaults
  $sort_mode = (
    isset( $player_data->sort_mode ) && array_key_exists( $player_data->sort_mode, $valid_sorting_values ) ?
    $player_data->sort_mode :
    'recent'
  );
  $type_mode = (
    isset( $player_data->type_mode ) && array_key_exists( $player_data->type_mode, $valid_types )  ?
    $player_data->type_mode :
    'all-types'
  );

  if ( array_key_exists( $sort_page_value, $valid_sorting_values ) ) {

    // Set sort mode based on sort_page_value, and persist it until the player changes it again
    $sort_mode = $sort_page_value;
    slackemon_set_player_pokemon_sort_mode( $sort_mode );

  } else if ( array_key_exists( $sort_page_value, $valid_types ) ) {

    $type_mode = $sort_page_value;
    slackemon_set_player_pokemon_type_mode( $type_mode );

  }

  // Set up pagination
  // Default to page 1 if a page number wasn't sent through
  $current_page = is_numeric( $sort_page_value ) ? $sort_page_value : 1;

  $message['text'] = (
    '*Pᴏᴋᴇ́ᴍᴏɴ*' . "\n" . // Pokemon
    'You have *' . count( $player_data->pokemon ) . '* Pokémon.'
  );

  $message['attachments'] = [];

  // Sort/types/search menus
  $sort_menu_options = [];
  foreach ( $valid_sorting_values as $value => $text ) {
    $sort_menu_options[] = [ 'text' => $text, 'value' => $value ];
  }
  $type_menu_options = [];
  foreach ( $valid_types as $value => $text ) {
    $type_menu_options[] = [ 'text' => $text, 'value' => $value ];
  }
  $message['attachments'][] = [
    'fallback' => 'Sort by',
    'color' => '#333333',
    'actions' => [
      [
        'name' => 'pokemon/list',
        'text' => 'Sort by...',
        'type' => 'select',
        'options' => $sort_menu_options,
        'selected_options' => [
          [
            'text'  => 'Sorting by ' . preg_replace( '/:.*?: /', '', $valid_sorting_values[ $sort_mode ] ),
            'value' => $sort_mode,
          ],
        ],
      ], [
        'name' => 'pokemon/list',
        'text' => 'Show type...',
        'type' => 'select',
        'options' => $type_menu_options,
        'selected_options' => [
          [
            'text' => (
              $type_mode === 'all-types' ?
              $valid_types[ $type_mode ] :
              preg_replace( '/:.*?: /', '', $valid_types[ $type_mode ] ) . ' types'
            ),
            'value' => $type_mode,
          ],
        ],
      ], /*[ // TODO
        'name' => 'pokemon/search',
        'text' => 'Search...',
        'type' => 'select',
        'data_source' => 'external',
      ],*/
    ],
  ];

  // JSON clone trick so we don't modify the original Pokemon object collection
  $sorted_pokemon = json_decode( json_encode( $player_data->pokemon ) );

  // Exclude non-selected types, if relevant
  if ( $type_mode && 'all-types' !== $type_mode ) {
    $sorted_pokemon = array_filter( $sorted_pokemon, function( $_pokemon ) use ( $type_mode ) {
      if ( in_array( ucfirst( $type_mode ), $_pokemon->types ) ) {
        return true;
      } else {
        return false;
      }
    });
  }

  // Do sorting
  switch ( $sort_mode ) {
    case 'recent':

      // Spawn timestamp, descending
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        return $pokemon1->ts < $pokemon2->ts ? 1 : -1;
      });

    break;
    case 'number':

      // Number, ascending, falling back to recent
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->pokedex != $pokemon2->pokedex ) {
          return $pokemon1->pokedex > $pokemon2->pokedex ? 1 : -1;
        } else {
          return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent fallback
        }
      });

    break;
    case 'name':

      // Name, ascending, falling back to recent
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        $compare = strcmp( $pokemon1->name, $pokemon2->name );
        if ( $compare !== 0 ) {
          return $compare > 0 ? 1 : -1; // Name
        } else {
          return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent fallback
        }
      });

    break;
    case 'cp':

      // CP, descending, falling back to recent
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->cp !== $pokemon2->cp ) {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP
        } else {
          return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent fallback
        }
      });

    break;
    case 'favourite':

      // Favourite Pokemon first, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->is_favourite !== $pokemon2->is_favourite ) {
          return $pokemon2->is_favourite ? 1 : -1; // Is favourite
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'battle-team':

      // Battle team members first, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->is_battle_team !== $pokemon2->is_battle_team ) {
          return $pokemon2->is_battle_team ? 1 : -1; // Is on battle team
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'level':

      // Level, descending, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->level !== $pokemon2->level ) {
          return $pokemon1->level < $pokemon2->level ? 1 : -1; // Level
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'move-power':

      // Move Power, descending, 0falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        $pokemon1_mp = slackemon_get_cumulative_move_power( $pokemon1->moves, $pokemon1->types );
        $pokemon2_mp = slackemon_get_cumulative_move_power( $pokemon2->moves, $pokemon2->types );
        if ( $pokemon1_mp !== $pokemon2_mp ) {
          return $pokemon1_mp < $pokemon2_mp ? 1 : -1; // Move Power
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'hp':

      // HP, descending, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->hp !== $pokemon2->hp ) {
          return $pokemon1->hp < $pokemon2->hp ? 1 : -1; // HP
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'ivs':

      // IVs, descending, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        $pokemon1_ivs = slackemon_get_iv_percentage( $pokemon1->ivs );
        $pokemon2_ivs = slackemon_get_iv_percentage( $pokemon2->ivs );
        if ( $pokemon1_ivs !== $pokemon2_ivs ) {
          return $pokemon1_ivs < $pokemon2_ivs ? 1 : -1; // IV percentage
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'evs':

      // EVs, descending, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        $pokemon1_evs = slackemon_get_combined_evs( $pokemon1->evs );
        $pokemon2_evs = slackemon_get_combined_evs( $pokemon2->evs );
        if ( $pokemon1_evs !== $pokemon2_evs ) {
          return $pokemon1_evs < $pokemon2_evs ? 1 : -1; // Combined EV total
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'happiness':

      // Happiness, descending, falling back to CP
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if ( $pokemon1->happiness !== $pokemon2->happiness ) {
          return $pokemon1->happiness < $pokemon2->happiness ? 1 : -1; // Happiness
        } else {
          return $pokemon1->cp < $pokemon2->cp ? 1 : -1; // CP fallback
        }
      });

    break;
    case 'held-item':

      // Held item, ascending, falling back to recent
      usort( $sorted_pokemon, function( $pokemon1, $pokemon2 ) {
        if (
          isset( $pokemon1->held_item ) && $pokemon1->held_item &&
          isset( $pokemon2->held_item ) && $pokemon2->held_item
        ) {
          $pokemon1_item_name = slackemon_get_item_data( $pokemon1->held_item )->name;
          $pokemon2_item_name = slackemon_get_item_data( $pokemon2->held_item )->name;
          $compare = strcmp( $pokemon1_item_name, $pokemon2_item_name );
          if ( $compare !== 0 ) {
            return $compare > 0 ? 1 : -1; // Item name
          } else {
            return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent fallback
          }
        } else if ( isset( $pokemon1->held_item ) && $pokemon1->held_item ) {
          return -1;
        } else if ( isset( $pokemon2->held_item ) && $pokemon2->held_item ) {
          return 1;
        } else {
          return $pokemon1->ts < $pokemon2->ts ? 1 : -1; // Recent fallback
        }
      });

    break;
  } // Switch sort_mode

  // Do pagination
  $sorted_pokemon_page = slackemon_paginate( $sorted_pokemon, $current_page, SLACKEMON_POKEMON_PER_PAGE );

  // Output Pokemon attachments
  foreach ( $sorted_pokemon_page as $pokemon ) {

    $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
    $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );

    // Is this Pokemon on our battle team?
    $is_battle_team = isset( $pokemon->is_battle_team ) && $pokemon->is_battle_team ? true : false;

    // Emoji'fied types
    $emojified_types = slackemon_emojify_types( join( ' ' , $pokemon->types ), false );

    // Get all evolution possibilities
    $evolution_possibilities = slackemon_can_user_pokemon_evolve( $pokemon, 'level-up', true );

    // Build footer info

    $footer = $is_desktop ? 'Caught ' . get_relative_time( $pokemon->ts, false ) . ' - ' : '';
    $footer .= slackemon_condensed_moveset( $pokemon->moves, $pokemon->types, ! $is_desktop );

    switch ( $sort_mode ) {

      case 'recent':
        if ( $is_desktop ) {
          $footer .=  ' - ' . slackemon_appraise_ivs( $pokemon->ivs, false, ! $is_desktop );
        } else {
          $footer = 'Caught ' . get_relative_time( $pokemon->ts, false ) . ' - ' . $footer;
        }
      break;

      case 'move-power':
        $footer .= ' (' . slackemon_get_cumulative_move_power( $pokemon->moves, $pokemon->types ) . ' MP)';
      break;

      case 'ivs':
        $footer .= (
          ' - ' . 
          slackemon_appraise_ivs( $pokemon->ivs, false, ! $is_desktop ) . ' ' .
          '(' . slackemon_get_iv_percentage( $pokemon->ivs ) . '%)'
        );
      break;

      case 'evs':
        $footer .= ' - ' . slackemon_get_combined_evs( $pokemon->evs ) . ' EVs';
      break;

      default:
        $footer .= ' - ' . slackemon_appraise_ivs( $pokemon->ivs, false, ! $is_desktop );
      break;

    } // Switch sort_mode

    $message['attachments'][] = [
      'text' => (
        ( $is_desktop ? ':' . $pokemon->name . ': ' : '' ) .
        '*' .
        slackemon_readable( $pokemon->name, false ) .
        slackemon_get_gender_symbol( $pokemon->gender ) . ' ' .
        slackemon_get_happiness_emoji( $pokemon->happiness ) .
        ( 'happiness' === $sort_mode ? ' ' . floor( $pokemon->happiness / 255 * 100 ) . '%' . '  ' : '' ) .
        ( 0 == $pokemon->hp ? ':skull:' : '' ) .
        ( slackemon_is_legendary( $pokemon->pokedex ) ? ':star2:' : '' ) .
        ( $is_desktop ? '' : '   ' . $emojified_types ) .
        '*' .
        (
          isset( $pokemon->held_item ) ?
          '   :gift: _' . slackemon_readable( slackemon_get_item_data( $pokemon->held_item )->name ) . '_' :
          ''
        ) .
        ( $is_desktop ? "\n" : "\n\n" ) .
        (
          $is_desktop || ( 'level' !== $sort_mode && 'hp' !== $sort_mode ) ?
          '#' . $pokemon->pokedex . '  •  ' :
          ''
        ) .
        $pokemon->cp . ' CP' . '  •  ' .
        (
          'level' === $sort_mode ?
          'L ' . $pokemon->level :
          'L' . floor( $pokemon->level )
        ) . '  •  ' . (
          'hp' === $sort_mode ? // We'll show the full HP numbers when specifically sorting by HP
          $pokemon->hp . '/' . $pokemon->stats->hp . ' HP' :
          floor( $pokemon->hp / $pokemon->stats->hp * 100 ) . '% HP'
        ) .
        ( $is_desktop ? '     ' . $emojified_types : '' )
      ),
      'actions' => [
        [
          'name'  => $pokemon->is_favourite ? 'unfavourite' : 'favourite',
          'text'  => (
            $is_desktop ?
            ( $pokemon->is_favourite ? ':sparkling_heart:' : ':blue_heart:' ) :
            ( $pokemon->is_favourite ? ':sparkling_heart: Favourite' : ':blue_heart: Favourite' )
          ),
          'type'  => 'button',
          'value' => $pokemon->ts,
          'style' => $pokemon->is_favourite ? 'primary' : '',
        ], [
          'name'  => 'pokemon/view',
          'text'  => ':eye: View',
          'type'  => 'button',
          'value' => $pokemon->ts,
        ], [
          'name'  => 'transfer',
          'text'  => ':outbox_tray: Transfer',
          'type'  => 'button',
          'value' => $pokemon->ts,
          'confirm' => [
            'title' => 'Are you sure?',
            'text'  => 'Are you sure you want to transfer this Pokémon? This cannot be undone.',
          ],
        ], (
          count( $evolution_possibilities ) ?
          [
            'name' => 'evolve',
            'text' => (
              ':arrow_double_up: Evolve' .
              (
                count( $evolution_possibilities ) > 1 ?
                ' (' . count( $evolution_possibilities ) . ')' :
                ''
              )
            ),
            'type'  => 'button',
            'value' => $pokemon->ts,
          ] : []
        ), (
          $is_battle_team || ! slackemon_is_battle_team_full() ?
          [
            'name'  => 'battle-team/' . ( $is_battle_team ? 'remove' : 'add' ),
            'text'  => ':facepunch: Battle Team',
            'type'  => 'button',
            'value' => $pokemon->ts,
            'style' => $is_battle_team ? 'primary' : '',
          ] : []
        ),
      ],
      'footer' => $footer,
      'color'  => (
        $pokemon->hp >= $pokemon->stats->hp * .1 ? slackemon_get_color_as_hex( $species_data->color->name ) : ''
      ),
      'thumb_url' => get_cached_image_url(
        'female' === $pokemon->gender && $pokemon_data->sprites->front_female ?
        $pokemon_data->sprites->front_female :
        $pokemon_data->sprites->front_default
      ),
    ];

  } // Foreach sorted_pokemon

  $message['attachments'][] = slackemon_get_pagination_attachment(
    $sorted_pokemon, $current_page, 'pokemon/list', SLACKEMON_POKEMON_PER_PAGE
  );

  $message['attachments'][] = slackemon_back_to_menu_attachment();

  return $message;

} // Function slackemon_get_pokemon_menu

function slackemon_get_favourite_message( $action ) {

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  // Loop through each attachment. If the current attachment, find the 'favourite' button, & change it to 'unfavourite'.
  foreach ( $message['attachments'] as $outer_key => $attachment ) {
    if ( $outer_key === $action->attachment_id - 1 ) {
      foreach ( $attachment->actions as $inner_key => $action_button ) {
        if ( $action_button->name === 'favourite' ) {
          $action_button->name = 'unfavourite';
          $action_button->text = ':sparkling_heart:' . ( $is_desktop ? '' : ' Favourite' );
          $action_button->style = 'primary';
          $attachment->actions[ $inner_key ] = $action_button;
        }
      }
    }
    $message['attachments'][ $outer_key ] = $attachment;
  }

  return $message;

} // Function slackemon_get_favourite_message

function slackemon_get_unfavourite_message( $action ) {

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  // Like adding, loop through each attachment and change the 'unfavourite' button to 'favourite' :)
  foreach ( $message['attachments'] as $outer_key => $attachment ) {
    if ( $outer_key === $action->attachment_id - 1 ) {
      foreach ( $attachment->actions as $inner_key => $action_button ) {
        if ( 'unfavourite' === $action_button->name ) {
          $action_button->name = 'favourite';
          $action_button->text = ':blue_heart:' . ( $is_desktop ? '' : ' Favourite' );
          $action_button->style = '';
          $attachment->actions[ $inner_key ] = $action_button;
        }
      }
    }
    $message['attachments'][ $outer_key ] = $attachment;
  }

  return $message;

} // Function slackemon_get_unfavourite_message

function slackemon_get_battle_team_add_message( $action, $action_name = '' ) {

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  // When adding at the Battle menu, we just need to reload the battle menu
  if ( 'battle-team/add/from-battle-menu' === $action_name ) {
    return slackemon_get_battle_menu();
  }

  // Otherwise, when adding at the Pokemon menu:
  // Now this is slightly complicated - we loop through each attachment, determining what to do with it.
  // If it's the current attachment, we loop through to find the 'add' button, and change it to 'remove'.
  // If it's not the current attachment and the battle team is now full, we loop through to find the 'add' button
  // and actually just remove it.
  foreach ( $message['attachments'] as $outer_key => $attachment ) {
    if ( $outer_key === $action->attachment_id - 1 ) {
      foreach ( $attachment->actions as $inner_key => $action_button ) {
        if ( $action_button->name === 'battle-team/add' ) {
          $action_button->name = 'battle-team/remove';
          $action_button->style = 'primary';
          $attachment->actions[ $inner_key ] = $action_button;
        }
      }
    } else if ( slackemon_is_battle_team_full() ) {
      if ( isset( $attachment->actions ) ) {
        foreach ( $attachment->actions as $inner_key => $action_button ) {
          if ( $action_button->name === 'battle-team/add' ) {
            $action_button = [];
            $attachment->actions[ $inner_key ] = $action_button;
          }
        }
      }
    }
    $message['attachments'][ $outer_key ] = $attachment;
  }

  return $message;

} // Function slackemon_get_battle_team_add_message

function slackemon_get_battle_team_remove_message( $action, $action_name = '' ) {

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  // When removing at the Battle menu, we just need to reload the battle menu
  if ( 'battle-team/remove/from-battle-menu' === $action_name ) {
    return slackemon_get_battle_menu();
  }

  // Otherwise, when removing at the Pokemon menu:
  // Like adding, this is also complicated, although a little more so. Again, we loop through each attachment.
  // If it's the current attachment, we loop through to find the 'remove' button, and change it to 'add'.
  // If it's not the current attachment and the battle team is now not full, we loop through to find the transfer
  // button, and grab the Pokemon's spawn_ts from it. If we find an 'add' or 'remove' button already here,
  // we then skip. Otherwise, we add an 'add' button.
  foreach ( $message['attachments'] as $outer_key => $attachment ) {
    if ( $outer_key === $action->attachment_id - 1 ) {
      foreach ( $attachment->actions as $inner_key => $action_button ) {
        if ( 'battle-team/remove' === $action_button->name ) {
          $action_button->name = 'battle-team/add';
          $action_button->style = '';
          $attachment->actions[ $inner_key ] = $action_button;
        }
      }
    } else if ( ! slackemon_is_battle_team_full() && isset( $attachment->actions ) ) {
      foreach ( $attachment->actions as $inner_key => $action_button ) {
        $spawn_ts = 0;
        if ( 'transfer' === $action_button->name ) {
          $spawn_ts = $action_button->value;
        } else if ( 'battle-team/remove' === $action_button->name || 'battle-team/add' === $action_button->name ) {
          continue 2;
        }
      }
      if ( isset( $spawn_ts ) && $spawn_ts ) {
        $attachment->actions[] = [
          'name' => 'battle-team/add',
          'text' => ':facepunch: Battle Team',
          'type' => 'button',
          'value' => $spawn_ts,
          'style' => '',
        ];
      }
    }
    $message['attachments'][ $outer_key ] = $attachment;
  }

  return $message;

} // Function slackemon_get_battle_team_remove_message

// The end!
