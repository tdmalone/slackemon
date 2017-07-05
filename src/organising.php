<?php
/**
 * Pokemon-organising specific functions for Slackemon.
 *
 * @package Slackemon
 */

// Cronned function (through /slackemon happiness-updates) which should run once a day (probs at midnight)
function slackemon_do_happiness_updates() {

  // Increment friendship value by 1 for those Pokemon in the battle team
  // We'll also increment by 1 for favourite Pokemon, divided by the total number of favourites
  foreach ( slackemon_get_player_ids() as $player_id ) {

    $player_data    = slackemon_get_player_data( $player_id, true );
    $player_pokemon = $player_data->pokemon;

    // Work out our total favourite count, so we can apply happiness increases appropriately
    $total_favourites = 0;
    foreach ( $player_pokemon as $_pokemon ) {
      if ( isset( $_pokemon->is_favourite ) && $_pokemon->is_favourite ) {
        $total_favourites++;
      }
    }

    foreach ( $player_pokemon as $_pokemon ) {

      if ( isset( $_pokemon->is_battle_team ) && $_pokemon->is_battle_team ) {
        $_pokemon->happiness++;
      }

      if ( isset( $_pokemon->is_favourite ) && $_pokemon->is_favourite ) {
        $_pokemon->happiness += 1 / $total_favourites;
      }

      $_pokemon->happiness = min( 255, $_pokemon->happiness ); // Stay within the max bounds

    } // Foreach player_pokemon

    slackemon_save_player_data( $player_data, $player_id, true );

  } // Foreach player
} // Function slackemon_do_happiness_updates

function slackemon_get_pokemon_view_message( $spawn_ts, $action_name, $action, $more_stats = false ) {

  $player_data = slackemon_get_player_data();
  $pokemon     = slackemon_get_player_pokemon_data( $spawn_ts );
  $is_desktop  = 'desktop' === slackemon_get_player_menu_mode();

  // Error out if we didn't find the requested Pokemon
  if ( ! $pokemon ) {
    $message = [
      'text' => (
        '*Oops!* The Pokémon you\'ve tried to view doesn\'t appear to be in your collection.' . "\n" .
        'Please try another Pokémon instead.'
      ),
      'attachments' => [ slackemon_back_to_menu_attachment() ],
    ];
    return $message;
  }

  // Get Pokedex data
  $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
  $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );

  // Description
  foreach ( $species_data->flavor_text_entries as $text ) {
    if ( 'en' === $text->language->name ) {
      $pokemon_description = str_replace( "\n", ' ', $text->flavor_text );
      break;
    }
  }

  // Held item
  if ( isset( $pokemon->held_item ) && $pokemon->held_item ) {
    $pokemon_description .= (
      "\n\n" .
      ':gift: *Holding a ' . slackemon_readable( slackemon_get_item_name( $pokemon->held_item ) ) . '*: ' .
      slackemon_get_item_description( $pokemon->held_item )
    );
  }

  // Generation
  $generation = str_replace( 'GENERATION-', 'Gen ', strtoupper( $species_data->generation->name ) );

  // Genus
  $genus = '';
  foreach ( $species_data->genera as $genus ) {
    if ( 'en' === $genus->language->name ) {
      $genus = $genus->genus;
      break;
    }
  }

  // Is this Pokemon on our battle team?
  $is_battle_team = isset( $pokemon->is_battle_team ) && $pokemon->is_battle_team ? true : false;

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $original_attachment = $message['attachments'][ $action->attachment_id - 1 ];

  $base_stats = slackemon_get_base_stats( $pokemon->pokedex );

  if ( $more_stats ) {

    $pokemon_regions = [];
    foreach ( $species_data->pokedex_numbers as $_pokedex_entry ) {
      if ( 'national' === $_pokedex_entry->pokedex->name ) { continue; }
      if ( false !== strpos( $_pokedex_entry->pokedex->name, 'conquest' ) ) { continue; }
      if ( false !== strpos( $_pokedex_entry->pokedex->name, 'conquest' ) ) { continue; }
      $_region_name = $_pokedex_entry->pokedex->name;
      $_region_name = str_replace( [ 'updated-', 'original-', 'extended-' ], '', $_region_name );
      $pokemon_regions[] = slackemon_readable( $_region_name );
    }
    $pokemon_regions = array_unique( $pokemon_regions );
    asort( $pokemon_regions );

    $pokemon_nature_stats = slackemon_get_nature_stat_modifications( $pokemon->nature );
    $pokemon_growth_rate  = slackemon_readable( $species_data->growth_rate->name );

    $growth_rate_data = slackemon_get_pokemon_growth_rate_data( $pokemon->pokedex );
    $level_up_exp_required = 0;
    foreach ( $growth_rate_data->levels as $_level ) {
      if ( $_level->level == floor( $pokemon->level ) + 1 ) {
        $level_up_exp_required = $_level->experience - $pokemon->xp;
        break;
      }
    }

    $pokemon_evolution  = '';
    $evolution_data     = slackemon_get_pokemon_evolution_data( $pokemon->pokedex );
    $evolution_chain    = slackemon_get_evolution_chain_pokemon( $evolution_data->chain, $pokemon->pokedex );
    $possible_evolution = false;

    foreach ( $evolution_chain->evolves_to as $_evolution ) {
      foreach ( $_evolution->evolution_details as $_evolution_detail ) {

        $possible_evolution = true;

        // Skip this evolution method if it doesn't contain any of our currently available evolution methods
        if (
          ( 'level-up' !== $_evolution_detail->trigger->name && 'use-item' !== $_evolution_detail->trigger->name ) ||
          (
            ! $_evolution_detail->min_level &&
            ! $_evolution_detail->min_happiness &&
            ! $_evolution_detail->item &&
            ! $_evolution_detail->known_move_type &&
            ! $_evolution_detail->known_move
          )
        ) {
          continue;
        }

        $pokemon_evolution .= (
          'Evolves into ' . slackemon_readable( $_evolution->species->name ) . ' ' .
          ( $_evolution_detail->min_level     ? 'at level ' . $_evolution_detail->min_level . ' '         : '' ) .
          ( $_evolution_detail->min_level && $_evolution_detail->min_happiness ? 'and '                   : '' ) .
          ( $_evolution_detail->min_happiness ? 'at happiness ' . $_evolution_detail->min_happiness . ' ' : '' ) .
          (
            $_evolution_detail->min_affection ?
            'at happiness ' . slackemon_affection_to_happiness( $_evolution_detail->min_affection ) . ' '
            : ''
          ) . (
            $_evolution_detail->item ?
            'with a ' . slackemon_readable( $_evolution_detail->item->name ) . ' ' :
            ''
          ) . (
            $_evolution_detail->time_of_day ?
            ( 'day' === $_evolution_detail->time_of_day ? '(during the day)' : '(at night)' ) . ' ' :
            ''
          ) . (
            $_evolution_detail->known_move_type ?
            'if a ' . slackemon_readable( $_evolution_detail->known_move_type->name ) . '-type move is known' :
            ''
          ) . (
            $_evolution_detail->known_move ?
            'if ' . slackemon_readable( $_evolution_detail->known_move->name ) . ' is known' :
            ''
          ) . "\n"
        );

      }
    }

    if ( ! $pokemon_evolution && $possible_evolution ) {
      $pokemon_evolution = '_(evolution method not yet discovered)_';
    }

    $base_stats_attachment = [
      'title' => 'Base Stats',
      'value' => (
        'Attack '  . $base_stats->attack  . ' / ' .
        ( $is_desktop ? '' : 'Sp ' . $base_stats->{'special-attack'} . "\n" ) .
        'Defense ' . $base_stats->defense  . ' / ' .
        ( $is_desktop ? '' : 'Sp ' . $base_stats->{'special-defense'} . "\n" ) .
        'HP '      . $base_stats->hp .
        (
          $is_desktop ?
          "\n" .
          'Sp Att '  . $base_stats->{'special-attack'}  . ' / ' .
          'Sp Def '  . $base_stats->{'special-defense'} :
          ''
        ) . ' / ' . 'Speed '   . $base_stats->speed . "\n"
      ),
      'short' => true,
    ];

    $current_stats_attachment = [
      'title' => 'Current Stats',
      'value' => (
        'Attack '  . $pokemon->stats->attack  . ' / ' .
        ( $is_desktop ? '' : 'Sp ' . $pokemon->stats->{'special-attack'} . "\n" ) .
        'Defense ' . $pokemon->stats->defense . ' / ' .
        ( $is_desktop ? '' : 'Sp ' . $pokemon->stats->{'special-defense'} . "\n" ) .
        'HP '      . $pokemon->stats->hp .
        (
          $is_desktop ?
          "\n" .
          'Sp Att '  . $pokemon->stats->{'special-attack'}  . ' / ' .
          'Sp Def '  . $pokemon->stats->{'special-defense'} :
          ''
        ) . ' / ' . 'Speed '   . $pokemon->stats->speed . "\n" .
        'Level ' . $pokemon->level . ' - ' . $pokemon->cp . ' CP'
      ),
      'short' => true,
    ];

    $_nature_stat_name_increase = slackemon_readable( $pokemon_nature_stats['increase'], true, ! $is_desktop );
    $_nature_stat_name_decrease = slackemon_readable( $pokemon_nature_stats['decrease'], true, ! $is_desktop );

    // Desktop is keeping these values in a two column layout, so we need to shorten a little
    if ( $is_desktop ) {
      $_nature_stat_name_increase = str_replace( 'Special ', 'Sp ', $_nature_stat_name_increase );
      $_nature_stat_name_decrease = str_replace( 'Special ', 'Sp ', $_nature_stat_name_decrease );
    }

    $nature_attachment = [
      'title' => 'Nature',
      'value' => (
        slackemon_readable( $pokemon->nature ) . ' ' .
        slackemon_get_nature_emoji( $pokemon->nature ) .
        ( $is_desktop ? "\n" : ' ' ) .
        '_(' .
        ( $pokemon_nature_stats['increase'] ? $_nature_stat_name_increase : 'no' ) . ' increase / ' .
        ( $pokemon_nature_stats['increase'] ? $_nature_stat_name_decrease : 'no' ) . ' decrease' .
        ')_'
      ),
      'short' => $is_desktop,
    ];

    $growth_rate_attachment = [
      'title' => 'Growth Rate',
      'value' => (
        '<http://bulbapedia.bulbagarden.net/wiki/Experience#' .
        str_replace( ' ', '_', $pokemon_growth_rate ) . '|' .
        $pokemon_growth_rate . '>'
      ),
      'short' => true,
    ];

    $links_attachment = [
      'title' => 'Links',
      'value' => (
        '<http://bulbapedia.bulbagarden.net/wiki/' . slackemon_readable( $pokemon->name ) . '|' .
        slackemon_readable( $pokemon->name ) . ' on Bulbapedia>'
      ),
      'short' => $is_desktop,
    ];

    $attachment_fields = [
      $is_desktop ? [] : '', // Leave a blank line here on desktop
      $current_stats_attachment,
      $is_desktop ? '' : $base_stats_attachment,
      [
        'title' => 'Individual Values',
        'value' => (
          'Attack '  . $pokemon->ivs->attack  . ' / ' .
          'Defense ' . $pokemon->ivs->defense . ' / ' .
          'HP '      . $pokemon->ivs->hp . "\n" .
          'Sp Att '  . $pokemon->ivs->{'special-attack'}  . ' / ' .
          'Sp Def '  . $pokemon->ivs->{'special-defense'} . ' / ' .
          'Speed '   . $pokemon->ivs->speed . "\n" .
          'Total: '  . slackemon_get_iv_percentage( $pokemon->ivs ) . '%'
        ),
        'short' => $is_desktop,
      ],
      $is_desktop ? $base_stats_attachment : '',
      [
        'title' => 'Effort Values',
        'value' => (
          $pokemon->evs ?
          'Attack '  . $pokemon->evs->attack  . ' / ' .
          'Defense ' . $pokemon->evs->defense . ' / ' .
          'HP '      . $pokemon->evs->hp . "\n" .
          'Sp Att '  . $pokemon->evs->{'special-attack'}  . ' / ' .
          'Sp Def '  . $pokemon->evs->{'special-defense'} . ' / ' .
          'Speed '   . $pokemon->evs->speed . "\n" .
          'Total: '  . slackemon_get_combined_evs( $pokemon->evs ) :
          '_(none yet)_'
        ),
        'short' => $is_desktop,
      ],
      $is_desktop ? '' : $nature_attachment,
      [
        'title' => 'Experience',
        'value' => (
          number_format( $pokemon->xp ) . ' (' . number_format( $level_up_exp_required ) . ' to ' .
          ( $is_desktop ? 'Level ' : 'L' ) . ( floor( $pokemon->level ) + 1 ) . ')'
        ),
        'short' => true,
      ], [
        'title' => 'Move Power',
        'value' => slackemon_get_cumulative_move_power( $pokemon->moves, $pokemon->types ),
        'short' => true,
      ], [
        'title' => 'CP Range',
        'value' => (
          slackemon_get_min_cp( $pokemon->pokedex ) . ' - ' .
          slackemon_get_max_cp( $pokemon->pokedex ) . ( $is_desktop ? ' for this species' : ' species' ) . "\n" .
          slackemon_get_min_cp( $pokemon ) . ' - ' .
          slackemon_get_max_cp( $pokemon ) . ( $is_desktop ? ' for this Pokémon' : ' individual' )
        ),
        'short' => true,
      ], [
        'title' => 'Happiness',
        'value' => (
          floor( $pokemon->happiness ) . ' / 255 ' .
          '(' . floor( $pokemon->happiness / 255 * 100 ) . '%)'
        ),
        'short' => true,
      ],
      $is_desktop ? $nature_attachment : '',
      $is_desktop ? $growth_rate_attachment : '',
      [
        'title' => 'Caught',
        'value' => (
          date( 'jS M \'y g:ia', $pokemon->ts ) .
          ( $is_desktop && isset( $pokemon->region ) ? ' in ' . slackemon_readable( $pokemon->region ) : '' )
        ),
        'short' => true,
      ], [
        'title' => 'Last Battle',
        'value' => (
          $pokemon->battles->last_participated ?
          date( 'jS M \'y g:ia', $pokemon->battles->last_participated ) :
          '_(none yet)_'
        ),
        'short' => true,
      ],
      $is_desktop ? '' : $growth_rate_attachment,
      $is_desktop ? '' : $links_attachment,
      [
        'title' => 'Regions',
        'value' => join( ', ', $pokemon_regions ),
        'short' => $is_desktop,
      ],
      $is_desktop ? $links_attachment : '',
      [
        'title' => 'Evolution',
        'value' => $pokemon_evolution ? $pokemon_evolution : '_(does not evolve)_',
        'short' => false,
      ],
    ];

    $attachment_footer = '';

    $attachment_actions = [
      [
        'name'  => 'pokemon/view' . ( 'pokemon/stats/from-battle-menu' === $action_name ? '/from-battle-menu' : '' ),
        'text'  => ':arrow_backward: Back',
        'type'  => 'button',
        'value' => $pokemon->ts,
      ], (
        isset( $pokemon->held_item ) && $pokemon->held_item ?
        [
          'name'  => 'pokemon/return-item',
          'text'  => ':gift: Return Held Item',
          'type'  => 'button',
          'value' => $pokemon->ts,
        ] :
        []
      ),
    ];

  } else { // More stats... else general stats

    // Get all evolution possibilities
    $evolution_possibilities = slackemon_can_user_pokemon_evolve( $pokemon, 'level-up', true );

    $attachment_fields = [
      $is_desktop ? [] : '',
      [
        'title' => 'Type',
        'value' => slackemon_emojify_types( join( '   ' , $pokemon->types ) ),
        'short' => $is_desktop,
      ], [
        'title' => 'Stats',
        'value' => (
          $pokemon->cp . ' CP - ' .
          'Level ' . $pokemon->level . ' - ' .
          'HP ' . floor( $pokemon->hp ) . '/' . $pokemon->stats->hp .
          ( 0 == $pokemon->hp ? ':black_small_square:' : '' ) .
          ( $pokemon->hp > 0 && $pokemon->hp < $pokemon->stats->hp ? ':small_orange_diamond:' : '' )
        ),
        'short' => $is_desktop,
      ], [
        'title' => 'Moves',
        'value' => slackemon_readable_moveset( $pokemon->moves, $pokemon->types, false, true ),
        'short' => $is_desktop,
      ], [
        'title' => 'Base Stats',
        'value' => (
          'Attack '  . $base_stats->attack  . ' / ' .
          'Defense ' . $base_stats->defense . ' / ' .
          'HP '      . $base_stats->hp      . "\n" .
          (
            isset( $pokemon->ivs ) ?
            slackemon_appraise_ivs( $pokemon->ivs ) . ' ' .
            slackemon_get_iv_percentage( $pokemon->ivs ) .'%' :
            ''
          )
        ),
        'short' => $is_desktop,
      ], (
        $is_desktop ?
        [
          'title' => 'Height / Weight',
          'value' => ( $pokemon_data->height / 10 ) . 'm / ' . ( $pokemon_data->weight ) / 10 . 'kg',
          'short' => true,
        ] :
        ''
      ), [
        'title' => 'Nature',
        'value' => slackemon_readable( $pokemon->nature ) . ' ' . slackemon_get_nature_emoji( $pokemon->nature ),
        'short' => true,
      ], (
        $is_desktop ?
        [
          'title' => 'Habitat',
          'value' => isset( $species_data->habitat ) ? slackemon_readable( $species_data->habitat->name ) : 'Unspecified',
          'short' => true,
        ] :
        ''
      ), [
        'title' => 'Happiness',
        'value' => (
          floor( $pokemon->happiness / 255 * 100 ) . '%' . ' ' .
          slackemon_get_happiness_emoji( $pokemon->happiness )
        ),
        'short' => true,
      ], [
        'title' => 'Family',
        'value' => slackemon_get_evolution_chain( $pokemon->pokedex, '_(does not evolve)_' ),
        'short' => false,
      ],
    ];

    $attachment_footer = (
      'Caught ' . slackemon_get_relative_time( $pokemon->ts ) . ' | ' .
      'Won ' . $pokemon->battles->won . ' of ' . $pokemon->battles->participated . ' trainer battles'
    );

    $attachment_actions = [
      [
        'name'  => $pokemon->is_favourite ? 'unfavourite' : 'favourite',
        'text'  => (
          $is_desktop ?
          ( $pokemon->is_favourite ? ':sparkling_heart:' : ':blue_heart:' ) :
          ( $pokemon->is_favourite ? ':sparkling_heart: Unfavourite' : ':blue_heart: Favourite' )
        ),
        'type'  => 'button',
        'value' => $pokemon->ts,
        'style' => $pokemon->is_favourite ? 'primary' : '',
      ], [
        'name'  => 'pokemon/stats' . ( 'pokemon/view/from-battle-menu' === $action_name ? '/from-battle-menu' : '' ),
        'text'  => ':part_alternation_mark: More',
        'type'  => 'button',
        'value' => $pokemon->ts,
      ]
    ];

    if ( 'pokemon/view/from-battle-menu' !== $action_name ) {

      $attachment_actions[] = [
        'name'  => 'transfer',
        'text'  => ':outbox_tray: Transfer',
        'type'  => 'button',
        'value' => $pokemon->ts,
        'confirm' => [
          'title' => 'Are you sure?',
          'text'  => 'Are you sure you want to transfer this Pokémon? This cannot be undone.',
        ],
      ];

      $attachment_actions[] = (
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
      );

      $attachment_actions[] = (
        $is_battle_team || ! slackemon_is_battle_team_full() ?
        [
          'name'  => 'battle-team/' . ( $is_battle_team ? 'remove' : 'add' ),
          'text'  => ':facepunch: Battle Team',
          'type'  => 'button',
          'value' => $pokemon->ts,
          'style' => $is_battle_team ? 'primary' : '',
        ] : []
      );
    
    } // If not viewing from battle menu
  } // If more_stats / else

  $message['attachments'][ $action->attachment_id - 1 ] = [
    'color' => $pokemon->hp >= $pokemon->stats->hp * .1 ? slackemon_get_color_as_hex( $species_data->color->name ) : '',
    'text' => (
      ( SLACKEMON_ENABLE_CUSTOM_EMOJI && $is_desktop ? ':' . $pokemon->name . ': ' : '' ) .
      '*' .
      slackemon_readable( $pokemon->name, false ) .
      slackemon_get_gender_symbol( $pokemon->gender ) .
      ( $is_desktop ? '  •  ' : ' •  ' ) .
      '#' . $pokemon->pokedex .
      '*' .
      (
        $is_desktop ?
        '  •  ' .
        '*' .
        ucfirst( $genus ) . ' Pokémon' . '  •  ' .
        $generation . '    ' .
        ( 0 == $pokemon->hp ? ':skull: ' : '' ) .
        ( slackemon_is_legendary( $pokemon->pokedex ) ? ':star2: ' : '' ) .
        '*' :
        ''
      ) . "\n\n" .
      ( $more_stats ? '' : $pokemon_description )
    ),
    'fields'    => $attachment_fields,
    'footer'    => $attachment_footer,
    'actions'   => $attachment_actions,
    'image_url' => (
      slackemon_get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/' . $pokemon->name . '.gif' )
    ),
  ];

  // If this is being displayed immediately after a battle/catch, remove the spawn data & add a main menu link.
  if ( 'pokemon/view/caught' === $action_name ) {

    array_shift( $message['attachments'] );
    array_shift( $message['attachments'] );
    array_shift( $message['attachments'] );

    $message['attachments'][] = slackemon_back_to_menu_attachment();

  } else if ( 'pokemon/view/battle' === $action_name ) {

    // When viewing after a no-catch battle, no prior attachments need removing. The message text does, though.
    $message['text'] = '';
    $message['attachments'][] = slackemon_back_to_menu_attachment();

  } else if ( 'pokemon/view/caught/battle' === $action_name ) {

    // Only need to cut two attachments here, because the first has already been removed by catching routines.
    array_shift( $message['attachments'] );
    array_shift( $message['attachments'] );
    $message['attachments'][] = slackemon_back_to_menu_attachment();

  }

  return $message;

} // Function slackemon_view_pokemon

function slackemon_has_user_seen_pokemon( $user_id, $pokedex_number ) {

  $player_data = slackemon_get_player_data( $user_id );
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $pokedex_number == $pokedex_entry->id ) {
      return true;
    }
  }

  return false;

} // Function slackemon_has_user_seen_pokemon

function slackemon_has_user_caught_pokemon( $user_id, $pokedex_number ) {

  $player_data = slackemon_get_player_data( $user_id );
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $pokedex_number == $pokedex_entry->id ) {
      if ( $pokedex_entry->caught ) {
        return true;
      } else {
        return false;
      }
    }
  }

  return false;

} // Function slackemon_has_user_caught_pokemon

/** Removes (aka. transfers) Pokemon from a player's collection. */
function slackemon_remove_pokemon( $spawn_timestamps, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  // Turn into an array if a single Pokemon was passed through.
  if ( ! is_array( $spawn_timestamps ) ) {
    $spawn_timestamps = [ $spawn_timestamps ];
  }

  // Make an array of the remaining Pokemon, which we'll only add to if the Pokemon is not being removed.
  $remaining_pokemon = [];
  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( in_array( $_pokemon->ts, $spawn_timestamps ) ) {
      continue;
    }
    $remaining_pokemon[] = $_pokemon;
  }

  $pokemon_removed = count( $player_data->pokemon ) - count( $remaining_pokemon );
  $xp_to_add = 1 === $pokemon_removed ? 10 : 5 * $pokemon_removed; // 10 XP for single transfer; 5 XP each for bulk.
  $player_data->pokemon = $remaining_pokemon;

  $player_data->xp += $xp_to_add;

  if ( slackemon_save_player_data( $player_data, $user_id, true ) ) {
    return $pokemon_removed;
  } else {
    return false;
  }

} // Function slackemon_remove_pokemon

function slackemon_set_player_pokemon_sort_mode( $sort_mode = 'recent', $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );
  $player_data->sort_mode = $sort_mode;

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_set_player_pokemon_sort_mode

function slackemon_set_player_pokemon_type_mode( $type_mode = 'all_types', $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );
  $player_data->type_mode = $type_mode;

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_set_player_pokemon_type_mode

function slackemon_favourite_pokemon( $spawn_ts, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $spawn_ts == $_pokemon->ts ) {
      $_pokemon->is_favourite = true;
    }
  }

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_favourite_pokemon

function slackemon_unfavourite_pokemon( $spawn_ts, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $spawn_ts == $_pokemon->ts ) {
      $_pokemon->is_favourite = false;
    }
  }

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_unfavourite_pokemon

function slackemon_add_to_battle_team( $spawn_ts, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $spawn_ts == $_pokemon->ts ) {
      $_pokemon->is_battle_team = true;
    }
  }

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_add_to_battle_team

function slackemon_remove_from_battle_team( $spawn_ts, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $spawn_ts == $_pokemon->ts ) {
      $_pokemon->is_battle_team = false;
    }
  }

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_remove_from_battle_team

function slackemon_is_battle_team_full( $user_id = USER_ID ) {
  
  $battle_team_count = 0;
  $pokemon_collection = slackemon_get_player_data( $user_id )->pokemon;

  foreach ( $pokemon_collection as $_pokemon ) {
    if ( $_pokemon->is_battle_team ) {
      $battle_team_count++;
    }
  }

  if ( $battle_team_count >= SLACKEMON_BATTLE_TEAM_SIZE ) {
    return true;
  } else {
    return false;
  }

} // Function slackemon_is_battle_team_full

function slackemon_get_battle_team( $user_id = USER_ID, $exclude_fainted = false, $exclude_random_fillers = false ) {

  $pokemon_collection = slackemon_get_player_data( $user_id )->pokemon;
  $battle_team = [];

  // Check whether this user doesn't even have enough Pokemon in their collection to form a team
  if ( count( $pokemon_collection ) < SLACKEMON_BATTLE_TEAM_SIZE ) {
    return $battle_team;
  }

  foreach ( $pokemon_collection as $_pokemon ) {
    if ( isset( $_pokemon->is_battle_team ) && $_pokemon->is_battle_team ) {
      if ( $exclude_fainted && 0 == $_pokemon->hp ) { continue; }
      $battle_team[ $_pokemon->ts ] = $_pokemon;
    }
  }

  // Put the leader first, if there is one.
  $battle_team_leader = slackemon_get_battle_team_leader( $user_id );
  if ( $battle_team_leader ) {
    uksort( $battle_team, function( $key1, $key2 ) use ( $battle_team_leader ) {

      if ( $key1 === $battle_team_leader ) {
        return -1;
      }

      if ( $key2 === $battle_team_leader ) {
        return 1;
      }

      return 0;

    });
  }

  // If our battle team is too big, we need to remove Pokemon from it
  while ( count( $battle_team ) > SLACKEMON_BATTLE_TEAM_SIZE ) {
    array_pop( $battle_team );
  }

  if ( $exclude_random_fillers ) {
    return $battle_team;
  }

  // If our battle team isn't full, we need to fill it with random additions
  $infinite_loop_protection = 0;
  while ( count( $battle_team ) < SLACKEMON_BATTLE_TEAM_SIZE ) {

    if ( $infinite_loop_protection > SLACKEMON_BATTLE_TEAM_SIZE * 100 ) {
      return false;
    }

    $infinite_loop_protection++;

    $random_key = array_rand( $pokemon_collection );
    $_pokemon = $pokemon_collection[ $random_key ];

    if ( ! isset( $battle_team[ $_pokemon->ts ] ) ) { // Ensure we don't add the same Pokemon twice

      if ( $exclude_fainted && 0 == $_pokemon->hp ) {
        continue;
      }

      $battle_team[ $_pokemon->ts ] = $_pokemon;

    }

  }

  return $battle_team;

} // Function slackemon_get_battle_team

/**
 * Returns the ts (spawn timestamp) of the Pokemon that the user has set as their battle team leader.
 *
 * Note that if the leader has been removed from the team and no new leader has beens et, the old leader's ts will
 * still be returned.
 *
 * @param string $user_id
 * @return int|bool The ts of the Pokemon, or false if there is no leader set.
 */
function slackemon_get_battle_team_leader( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  if ( ! isset( $player_data->battle_team_leader ) ) {
    return false;
  }

  return $player_data->battle_team_leader;

} // Function slackemon_get_battle_team_leader

/**
 * Sets the ts (spawn timestamp) of the Pokemon that the user wants as their battle team leader.
 *
 * @param string|int $spawn_ts
 * @return bool Whether or not the action was successful.
 */
function slackemon_set_battle_team_leader( $spawn_ts, $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id, true );

  $player_data->battle_team_leader = (int) $spawn_ts;

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_get_battle_team_leader

function slackemon_get_pokemon_transfer_message( $spawn_ts, $action ) {

  $player_data = slackemon_get_player_data();

  foreach ( $player_data->pokemon as $_pokemon ) {
    if ( $_pokemon->ts == $spawn_ts ) {
      $pokemon = $_pokemon;
    }
  }

  // TODO: If we didn't find the Pokemon, return an error message saying the Pokemon may have already been transferred

  $pokemon_data = slackemon_get_pokemon_data( $pokemon->pokedex );
  $species_data = slackemon_get_pokemon_species_data( $pokemon->pokedex );

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $original_attachment = $message['attachments'][ $action->attachment_id - 1 ];

  $message['attachments'][ $action->attachment_id - 1 ] = [
    'color' => slackemon_get_color_as_hex( $species_data->color->name ),
    'text' => (
      ':white_check_mark: *' . slackemon_readable( $pokemon->name, false ) .
      slackemon_get_gender_symbol( $pokemon->gender ) .
      '* has been transferred ' .
      'to the Professor.' . "\n\n" . 
      '*+10 XP*: Transferred a Pokemon :outbox_tray:'
    ),
    'thumb_url' => slackemon_get_cached_image_url(
      'female' === $pokemon->gender && $pokemon_data->sprites->back_female ?
      $pokemon_data->sprites->back_female :
      $pokemon_data->sprites->back_default
    ),
  ];

  return $message;

} // Function slackemon_get_pokemon_transfer_message

function slackemon_get_bulk_transfer_pokemon( $user_id = USER_ID ) {

  $collection = [];
  $duplicates = slackemon_get_duplicate_pokemon( $user_id );

  foreach ( $duplicates as $pokemon ) {

    // First Pokemon of this species? Create base data
    if ( ! array_key_exists( $pokemon->pokedex, $collection ) ) {

      $evolution_chain = slackemon_get_evolution_chain( $pokemon->pokedex );

      // If this Pokemon has a branched evolution chain, we will skip for now
      // TODO: We should probably use a better way of checking for this, rather than looking for a string in output ;)
      if ( false !== strpos( $evolution_chain, '(' ) ) {
        continue;
      }

      $collection[ $pokemon->pokedex ] = [
        'highest_level'     => 0,
        'highest_weighting' => 0,
        'evolves' => $evolution_chain ? true : false,
        'pokemon' => [],
      ];

    }

    $iv_percentage = slackemon_get_iv_percentage( $pokemon->ivs );
    $move_power    = slackemon_get_cumulative_move_power( $pokemon->moves, $pokemon->types );

    // Weighting

    $weighting = (
      $collection[ $pokemon->pokedex ]['evolves'] ?
      5 * $iv_percentage :
      1.5 * $iv_percentage
    );

    $weighting += $move_power;
    $weighting *= ceil( $pokemon->level / 4 );
    $weighting += $pokemon->level;
    $weighting += $pokemon->happiness;
    $weighting += slackemon_get_combined_evs( $pokemon->evs );
    $weighting += $pokemon->cp;

    // Nerf the weighting if the move power is 0, because that's a fairly big issue
    if ( ! $move_power ) {
      $weighting *= .5;
    }

    // Update the heighest weighting, if applicable
    if ( $weighting > $collection[ $pokemon->pokedex ]['highest_weighting'] ) {
      $collection[ $pokemon->pokedex ]['highest_weighting'] = $weighting;
    }

    // Update the heighest level, if applicable
    if ( $pokemon->level > $collection[ $pokemon->pokedex ]['highest_level'] ) {
      $collection[ $pokemon->pokedex ]['highest_level'] = $pokemon->level;
    }

    // Add this Pokemon's stats to the duplicate collection

    $collection[ $pokemon->pokedex ]['pokemon'][] = [
      'iv'        => $iv_percentage,
      'mp'        => $move_power,
      'weighting' => $weighting,
      'data'      => $pokemon,
    ];

  } // Foreach duplicates

  foreach ( $collection as $pokedex_id => $collection_data ) {
    foreach ( $collection_data['pokemon'] as $key => $pokemon ) {
      if ( $pokemon['weighting'] < $collection_data['highest_weighting'] ) {
        $collection[ $pokedex_id ]['pokemon'][ $key ]['transfer'] = true;
      } else {
        $collection[ $pokedex_id ]['pokemon'][ $key ]['transfer'] = false;
      }
    }
  }

  return $collection;

} // Function slackemon_get_bulk_transfer_pokemon

function slackemon_do_bulk_transfer( $user_id = USER_ID ) {

  $collection = slackemon_get_bulk_transfer_pokemon( $user_id );

  $pokemon_to_transfer = [];
  foreach ( $collection as $collection_data ) {
    foreach ( $collection_data['pokemon'] as $pokemon ) {

      // Don't transfer a favourite or battle team Pokemon
      if ( $pokemon['data']->is_favourite || $pokemon['data']->is_battle_team ) {
        continue;
      }

      // Don't transfer a Pokemon holding an item
      if ( isset( $pokemon['data']->held_item ) && $pokemon['data']->held_item ) {
        continue;
      }

      // Go ahead and transfer a Pokemon marked as such!
      if ( $pokemon['transfer'] ) {
        $pokemon_to_transfer[] = $pokemon['data']->ts;
      }
    }
  }

  $transfer_count = slackemon_remove_pokemon( $pokemon_to_transfer, $user_id );
  return $transfer_count;

} // Function slackemon_do_bulk_transfer

function slackemon_get_duplicate_pokemon( $user_id = USER_ID ) {

  $pokemon_collection = slackemon_get_player_data( $user_id )->pokemon;

  $pokemon_by_id = [];
  $duplicate_pokemon = [];

  foreach ( $pokemon_collection as $pokemon ) {
    if ( ! isset( $pokemon_by_id[ $pokemon->pokedex ] ) ) {
      $pokemon_by_id[ $pokemon->pokedex ] = [];
    }
    $pokemon_by_id[ $pokemon->pokedex ][] = $pokemon;
  }

  foreach ( $pokemon_by_id as $potential_duplicates ) {
    if ( 1 === count( $potential_duplicates ) ) {
      continue;
    }
    foreach ( $potential_duplicates as $pokemon ) {
      $duplicate_pokemon[] = $pokemon;
    }
  }

  return $duplicate_pokemon;

} // Function slackemon_get_duplicate_pokemon

/** Change the item a Pokemon is using. Can also be used to remove a held item by sending null as the item_id. */
function slackemon_change_pokemon_held_item( $item_id, $spawn_ts, $user_id = USER_ID ) {

  $pokemon = slackemon_get_player_pokemon_data( $spawn_ts, null, $user_id );
  
  // Return the old held item back to the bag
  if ( isset( $pokemon->held_item ) && $pokemon->held_item ) {
    slackemon_add_item( $pokemon->held_item, $user_id );
  }

  // Get player data for writing, and re-get the same Pokemon from the new object
  $player_data = slackemon_get_player_data( $user_id, true );
  $pokemon     = slackemon_get_player_pokemon_data( $spawn_ts, $player_data );

  // Update the held item on this Pokemon
  $pokemon->held_item = $item_id;

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_change_pokemon_held_item

/**
 * Sorts a player's Pokemon collection by one or more criteria.
 * Returns a boolean indicating whether the sort was successful.
 */
function slackemon_sort_player_pokemon( &$player_pokemon, $sort_by ) {

  // Accept a string as well as an array
  if ( is_string( $sort_by ) ) {
    $sort_by = [ $sort_by ];
  }

  $result = usort( $player_pokemon, function( $pokemon1, $pokemon2 ) use ( $sort_by ) {
    foreach ( $sort_by as $sort_criteria ) {

      if ( is_bool( $pokemon1->{ $sort_criteria } ) ) {
        if ( $pokemon1->{ $sort_criteria } !== $pokemon2->{ $sort_criteria } ) {
          return $pokemon2->{ $sort_criteria } ? 1 : -1;
        }
      } else if ( is_numeric( $pokemon1->{ $sort_criteria } ) ) {
        return $pokemon1->{ $sort_criteria } < $pokemon2->{ $sort_criteria } ? 1 : -1;
      } else if ( is_string( $pokemon1->{ $sort_criteria } ) ) {
        $compare = strcmp( $pokemon1->{ $sort_criteria }, $pokemon2->{ $sort_criteria } );
        if ( $compare !== 0 ) {
          return $compare > 0 ? 1 : -1;
        }
      }

    } // Foreach sort_criteria
  });

  return $result;

} // Function slackemon_sort_player_pokemon

// The end!
