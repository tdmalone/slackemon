<?php
/**
 * Spawn specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_maybe_spawn( $trigger = [] ) {

  $spawn_chance_rate = ceil( MINUTE_IN_SECONDS / SLACKEMON_HOURLY_SPAWN_RATE );
  $spawn_randomizer  = random_int( 1, $spawn_chance_rate );
  $should_spawn      = 1 === $spawn_randomizer;

  if ( ! $should_spawn ) {

    slackemon_spawn_debug(
      'No spawn this time (looking for 1 from 1 to ' . $spawn_chance_rate . ', got ' . $spawn_randomizer . ').'
    );
    
    return false;

  }

  $timestamp = time();
  slackemon_spawn_debug( 'Spawning...' );

  foreach ( slackemon_get_regions() as $region ) {
    slackemon_spawn( $trigger, $region['name'], $timestamp );
  }

  return true;

} // Function slackemon_maybe_spawn

function slackemon_spawn(
  $trigger = [], $region = false, $timestamp = false, $specific_id = false, $specific_level = false
) {

  // Default region
  if ( ! $region ) {
    $region = SLACKEMON_DEFAULT_REGION;
  }

  // Set timestamp if it wasn't passed thru - this will how we identify unique spawn times
  if ( ! $timestamp ) {
    $timestamp = time();
  }

  // No active players in the selected region?
  if ( ! slackemon_get_player_ids([ 'active_only' => true, 'region' => $region ]) ) {
    slackemon_spawn_debug( 'No active players in ' . slackemon_readable( $region ) . ', so not spawning there.' );
    return;
  }

  // Should this be an item spawn, or a Pokemon spawn?
  if ( 'onboarding' !== $trigger['type'] && ( ! $specific_id || 'item' === substr( $specific_id, 0, 4 ) ) ) {
    $item_spawn = 'item' === substr( $specific_id, 0, 4 ) || random_int( 1, 100 ) <= SLACKEMON_ITEM_SPAWN_CHANCE;
    if ( $item_spawn ) {
      slackemon_spawn_debug( 'This will be an item spawn...' );
      return slackemon_item_spawn( $trigger, $region, $timestamp, substr( $specific_id, 5 ) );
    }
  }

  // Allow IV range to be modified based on events
  $min_ivs = SLACKEMON_MIN_IVS;
  $max_ivs = SLACKEMON_MAX_IVS;

  // If a specific Pokedex ID has not been passed through, use that now.
  if ( $specific_id ) {
    $pokedex_id = (int) $specific_id;
  } else {

    // Get region data
    $region_data = slackemon_get_regions()[ $region ];

    // Generate spawned Pokemon by region-specific Pokedex ID.
    // We then get the Pokemon ID from the URL specified in the region Pokedex.
    $region_pokedex = $region_data['region_pokedex'];
    $pokedex_id = trim( basename( $region_pokedex[ array_rand( $region_pokedex ) ]->pokemon_species->url ), '/' );

  }

  // Get Pokemon data
  $pokemon = slackemon_get_pokemon_data( $pokedex_id );

  // Put types together
  $types = [];
  foreach ( $pokemon->types as $type ) {
    $types[] = slackemon_readable( $type->type->name );
  }

  // In some weather situations, there's a chance we will skip this Pokemon if it's not the right type
  // If our randomizer decides to do this, we will intentionally select a random Pokemon of the correct type
  if ( ! $specific_id ) {

    $weather_condition = slackemon_get_weather_condition([ 'expiry_age' => MINUTE_IN_SECONDS * 15 ]);
    $weather_spawn = false; // For now... this might change in a sec!

    $weather_types = [
      'Windy'   => 'Flying',
      'Hot'     => 'Fire',
      'Raining' => 'Water',
      'Stormy'  => 'Electric',
      'Snowing' => 'Ice',
      'Hailing' => 'Ice',
      'Cold'    => 'Ice',
      'Sunny'   => 'Grass',
    ];

    if ( array_key_exists( $weather_condition, $weather_types ) ) {
      if ( ! in_array( $weather_types[ $weather_condition ], $types ) ) {

        // We don't want to be exclusive based on the weather - just have an increased chance
        // So, what should we do this time?
        $weather_randomizer = random_int( 1, max( 2, ceil( MINUTE_IN_SECONDS / SLACKEMON_HOURLY_SPAWN_RATE ) / 2 ) );
        $weather_spawn = 1 !== $weather_randomizer;

        if ( $weather_spawn ) {

          slackemon_spawn_debug(
            'Deciding not to spawn ' . join( ', ', $types ) . ' type ' .
            'while the weather is ' . $weather_condition . ' ' .
            '(looking for ' . $weather_types[ $weather_condition ] . ' type)'
          );

          // Let's look for a Pokemon of the specific type we're after!
          // We need to do a bit of API wrangling for this

          // Get all types, then get a collection of Pokemon for the specific type we're after
          $_all_types = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/type/' ) );
          $_desired_type = strtolower( $weather_types[ $weather_condition ] );
          foreach ( $_all_types->results as $_type ) {
            if ( $_desired_type === $_type->name ) {
              $_weather_type_collection = json_decode( slackemon_get_cached_url( $_type->url ) )->pokemon;
              break;
            }
          }

          // Filter the collection to ensure it's not from the wrong region
          $_weather_type_collection = array_filter(
            $_weather_type_collection,
            function( $_pokemon ) use ( $region_pokedex ) {

              $_pokedex_id = trim( basename( $_pokemon->pokemon->url ), '/' );

              foreach ( $region_pokedex as $region_pokemon ) {
                if ( $_pokedex_id == trim( basename( $region_pokemon->pokemon_species->url ), '/' ) ) {
                  return true;
                }
              }

              return false;

          });

          // Get a random Pokemon, then Pokedex ID, Pokemon data, and process the type(s) of our new Pokemon selection.
          // From here, we'll then continue as normal (incl. checking for Pokemon exclusions, evolution chains etc.).
          $_random_of_type = $_weather_type_collection[ array_rand( $_weather_type_collection ) ];
          $pokedex_id = trim( basename( $_random_of_type->pokemon->url ), '/' ); // ID is only available from the URL.
          $pokemon = slackemon_get_pokemon_data( $pokedex_id );
          $types = [];
          foreach ( $pokemon->types as $type ) {
            $types[] = slackemon_readable( $type->type->name );
          }

          // Because this Pokemon likes the weather, it's gonna have higher IVs - so we cut the range in half
          $min_ivs = ( $min_ivs + $max_ivs ) / 2;

        } else {

          slackemon_spawn_debug(
            'The weather is ' . $weather_condition . ' so I\'d prefer ' . $weather_types[ $weather_condition ] . ' ' .
            'type, but I will still allow a spawn of ' . join( ', ', $types ) . ' type this time.'
          );

        } // If weather_spawn / else
      } // If type matches weather_condition
    } // If weather_condition exists
  } // If not specific_id

  // Make sure we don't have an excluded Pokemon
  if ( ! $specific_id && in_array( $pokedex_id, explode( '|', SLACKEMON_EXCLUDED_POKEMON ) ) ) {
    slackemon_spawn_debug( 'Can\'t spawn ' . slackemon_readable( $pokemon->name ) . '; it is specifically excluded.' );
    return call_user_func_array( __FUNCTION__, func_get_args() );
  }

  // If legendaries are excluded, make sure we don't have one (unless the weather is favourable)
  if ( ! $specific_id && slackemon_is_legendary( $pokedex_id ) ) {
    if ( SLACKEMON_EXCLUDE_LEGENDARIES && ( ! SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS || ! $weather_spawn ) ) {
      slackemon_spawn_debug( 'Can\'t spawn ' . slackemon_readable( $pokemon->name ) . ' as it is legendary.' );
      return call_user_func_array( __FUNCTION__, func_get_args() );
    }
  }

  // If it's daytime, make sure we're not spawning a Ghost or Dark Pokemon (or Zubat or Abra)
  // And at night, no Grass Pokemon or Normal+Flying Pokemon (because birds don't fly at night)
  // (we also double check to ensure that eg. a Grass+Ghost/Dark type wouldn't be excluded completely!)
  if ( ! $specific_id && SLACKEMON_EXCLUDE_ON_TIME_OF_DAY && slackemon_is_daytime() ) {
    if ( in_array( 'Ghost', $types ) || in_array( 'Dark', $types ) ) {
      slackemon_spawn_debug(
        'Can\'t spawn Ghost or Dark type during the day (' . slackemon_readable( $pokemon->name ) . ').'
      );
      return call_user_func_array( __FUNCTION__, func_get_args() );
    } else if ( 41 == $pokedex_id || 63 == $pokedex_id ) {
      slackemon_spawn_debug(
        'Can\'t spawn Zubat or Abra during the day (' . slackemon_readable( $pokemon->name ) . ').'
      );
      return call_user_func_array( __FUNCTION__, func_get_args() );
    }
  } else if ( ! $specific_id && SLACKEMON_EXCLUDE_ON_TIME_OF_DAY ) {
    if ( in_array( 'Grass', $types ) && ! in_array( 'Ghost', $types ) && ! in_array( 'Dark', $types )  ) {
      slackemon_spawn_debug( 'Can\'t spawn Grass type at night (' . slackemon_readable( $pokemon->name ) . ').' );
      return call_user_func_array( __FUNCTION__, func_get_args() );
    } else if ( in_array( 'Normal', $types ) && in_array( 'Flying', $types ) ) {
      slackemon_spawn_debug(
        'Can\'t spawn Normal & Flying type at night (' . slackemon_readable( $pokemon->name ) . ').'
      );
      return call_user_func_array( __FUNCTION__, func_get_args() );
    }
  }

  // Do Pokemon species query
  // Provides evolution chain URL, color, generation, description, genus, habitat, egg groups, and baby & gender status
  $species = slackemon_get_pokemon_species_data( $pokedex_id );

  // Make sure we're only spawning from the first in an evolution chain
  if ( ! $specific_id && SLACKEMON_EXCLUDE_EVOLUTIONS && $species->evolves_from_species ) {
    slackemon_spawn_debug(
      'Can\'t spawn ' . slackemon_readable( $pokemon->name ) . ' as it is not the first in an evolution chain.'
    );
    return call_user_func_array( __FUNCTION__, func_get_args() );
  }

  // Make sure we're not spawning a baby - they'll only be available in eggs
  if ( ! $specific_id && SLACKEMON_EXCLUDE_BABIES && $species->is_baby ) {
    slackemon_spawn_debug( 'Can\'t spawn ' . slackemon_readable( $pokemon->name ) . ' as it is a baby Pokémon.' );
    return call_user_func_array( __FUNCTION__, func_get_args() );
  }

  slackemon_spawn_debug( 'Ok, will spawn a ' . slackemon_readable( $pokemon->name ) . '.' );

  // Determine nature
  $natures = slackemon_get_natures();
  $nature  = $natures[ array_rand( $natures ) ];

  // Put stats together, including IVs
  $level = $specific_level ? (int) $specific_level : 1;
  $xp    = $specific_level ? (int) slackemon_get_xp_for_level( $pokedex_id, $specific_level ) : 0;
  $evs   = [];
  $ivs   = [
    'attack'  => random_int( $min_ivs, $max_ivs ),
    'defense' => random_int( $min_ivs, $max_ivs ),
    'hp'      => random_int( $min_ivs, $max_ivs ),
    'speed'   => random_int( $min_ivs, $max_ivs ),
    'special-attack'  => random_int( $min_ivs, $max_ivs ),
    'special-defense' => random_int( $min_ivs, $max_ivs ),
  ];
  $stats = [
    'attack'  => slackemon_calculate_stats( 'attack',  $pokedex_id, $level, $ivs, $evs, $nature ),
    'defense' => slackemon_calculate_stats( 'defense', $pokedex_id, $level, $ivs, $evs, $nature ),
    'hp'      => slackemon_calculate_stats( 'hp',      $pokedex_id, $level, $ivs, $evs, $nature ),
    'speed'   => slackemon_calculate_stats( 'speed',   $pokedex_id, $level, $ivs, $evs, $nature ),
    'special-attack'  => slackemon_calculate_stats( 'special-attack',  $pokedex_id, $level, $ivs, $evs, $nature ),
    'special-defense' => slackemon_calculate_stats( 'special-defense', $pokedex_id, $level, $ivs, $evs, $nature ),
  ];

  // Grab a couple of moves, ensuring uniqueness
  $moves = slackemon_get_random_moves( $pokemon->moves, [], 2 );

  // PokeAPI expresses gender as -1 if genderless, or the chance of a Pokemon being female in eighths
  // So, a value of 0 means no chance of Female, which makes it Male
  // A value of 8 means it's always Female
  // Anything in between is a chance... which is expressed in the random number generator and 'chance check' below
  if ( $species->gender_rate < 0 ) {
    $gender = false; // Genderless Pokemon
  } else {
    $gender = random_int( 1, 100 );
    if ( $gender <= floor( $species->gender_rate / 8 * 100 ) ) {
      $gender = 'female';
    } else {
      $gender = 'male';
    }
  }

  $spawn = [
    'pokedex'   => $pokedex_id,
    'ts'        => $timestamp,
    'region'    => $region,
    'trigger'   => $trigger,
    'name'      => $species->name,
    'types'     => $types,
    'gender'    => $gender,
    'is_shiny'  => false,
    'form'      => '',
    'nature'    => $nature,
    'moves'     => $moves,
    'abilities' => [],
    'stats'     => $stats,
    'ivs'       => $ivs,
    'evs'       => $evs,
    'level'     => $level,
    'cp'        => slackemon_calculate_cp( $stats ),
    'hp'        => $stats['hp'],
    'xp'        => $xp,
    'happiness' => $species->base_happiness,
    'battles'   => [
      'won'               => 0,
      'participated'      => 0,
      'last_won'          => false,
      'last_participated' => false,
    ],
    'flags'     => [],
    'users'     => new stdClass(),
  ];

  // Support different Pokemon varieties - eg. Deoxys/Wormadam
  if ( $pokemon->name !== $species->name ) {
    $spawn['variety'] = $pokemon->name;
  }

  if ( 'scaffold' !== $trigger['type'] && slackemon_save_spawn_data( $spawn ) ) {
    slackemon_notify_spawn( $spawn, $specific_level );
  }

  return $spawn;

} // Function slackemon_spawn

function slackemon_save_spawn_data( $spawn_data ) {
  global $data_folder, $_cached_slackemon_spawn_data;

  $spawn_id = $spawn_data['ts'] . '-' . $spawn_data['region'];
  $spawn_filename = $data_folder . '/spawns/' . $spawn_id;

  // Update the in-memory cache.
  $_cached_slackemon_spawn_data[ $spawn_id ] = $spawn_data;

  $json_options = 'development' === APP_ENV ? JSON_PRETTY_PRINT : 0;

  $return = slackemon_file_put_contents(
    $spawn_filename, json_encode( $spawn_data, $json_options ), 'store', false
  );

  return $return;

} // Function slackemon_save_spawn_data

function slackemon_get_spawn_data( $spawn_ts, $spawn_region, $user_id = USER_ID ) {
  global $data_folder, $_cached_slackemon_spawn_data;

  $spawn_id = $spawn_ts . '-' . $spawn_region;

  if ( isset( $_cached_slackemon_spawn_data[ $spawn_id . '-' . $user_id ] ) ) {
    return $_cached_slackemon_spawn_data[ $spawn_id . '-' . $user_id ];
  }

  $spawn_filename = $data_folder . '/spawns/' . $spawn_id;

  $spawn_data = json_decode( slackemon_file_get_contents( $spawn_filename, 'store' ) );

  // Assign user specific variables to the data
  if ( isset( $spawn_data->users->{ $user_id } ) ) {
    foreach ( $spawn_data->users->{ $user_id } as $key => $value ) {
      $spawn_data->{ $key } = $value;
    }
  }

  $_cached_slackemon_spawn_data[ $spawn_id . '-' . $user_id ] = $spawn_data;

  return $spawn_data;

} // Function slackemon_get_spawn_data

function slackemon_notify_spawn( $spawn, $specific_level = false ) {
  global $data_folder;

  $species_data = slackemon_get_pokemon_species_data( $spawn['pokedex'] );

  $message = [
    'attachments' => [
      [
        'pretext' => (
          ( slackemon_is_legendary( $spawn['pokedex'] ) ? ':star2: ' : '' ) .
          'A wild *' . slackemon_readable( $spawn['name'], false ) .
          slackemon_get_gender_symbol( $spawn['gender'] ) .
          '* has just appeared! [SEEN_CAUGHT]'
        ),
        'fallback' => (
          'A wild ' . slackemon_readable( $spawn['name'], false ) .
          slackemon_get_gender_symbol( $spawn['gender'] ) .
          ' has just appeared! [SEEN_CAUGHT]'
        ),
        'color' => slackemon_get_color_as_hex( $species_data->color->name ),
        'image_url' => slackemon_get_cached_image_url(
          SLACKEMON_ANIMATED_GIF_BASE . '/ani-front/' . $spawn['name'] . '.gif'
        ),
      ], [
        'color' => slackemon_get_color_as_hex( $species_data->color->name ),
        'fields' => [
          [
            'title' => 'Type',
            'value' => slackemon_emojify_types( join( '   ' , $spawn['types'] ) ),
            'short' => true,
          ], [
            'title' => 'CP',
            'value' => ( // WARNING: This attachment & field key may be changed below if user level decides
              $spawn['cp'] . ' (Level ' . $spawn['level'] . ')'
            ),
            'short' => true,
          ], [
            'title' => 'Moves',
            'value' => slackemon_readable_moveset( $spawn['moves'], $spawn['types'] ),
            'short' => true, // WARNING: This attachment & field key will be set false below if not on desktop mode
          ], [
            'title' => 'Stats',
            'value' => (
              'Attack '    . $spawn['stats']['attack']          . ' / ' .
              'Defense '   . $spawn['stats']['defense']         . ' / ' .
              'HP '        . $spawn['stats']['hp']              . "\n"  .
              'Sp Attk '   . $spawn['stats']['special-attack']  . ' / ' .
              'Sp Def '    . $spawn['stats']['special-defense'] . ' / ' .
              'Speed '     . $spawn['stats']['speed']           . "\n"  .
              slackemon_appraise_ivs( $spawn['ivs'] ) . ' ' .
              slackemon_get_iv_percentage( $spawn['ivs'] ) .'%'
            ),
            'short' => true, // WARNING: This attachment & field key will be set false below if not on desktop mode
          ],
        ],
      ], [
        'title' => 'What would you like to do?',
        'color' => '#333333',
        'actions' => [
          [
            'name'  => 'catch',
            'text'  => ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':pokeball:' : ':volleyball:' ) .' Throw Pokéball',
            'type'  => 'button',
            'value' => $spawn['ts'],
          ], [ // WARNING: This array key will be cleared below if the user doesn't have an available battle team
            'name'  => 'catch/start-battle',
            'text'  => ':facepunch: Battle',
            'type'  => 'button',
            'value' => $spawn['ts'],
          ], [
            'name'  => 'mute',
            'text'  => ':mute: Go Offline',
            'type'  => 'button',
            'value' => 'mute',
            'style' => 'danger',
          ],
        ],
      ],
    ], // Attachments
  ]; // $message

  // Just before we loop through each player, let's find the minimum level this Pokemon might evolve at.
  // That is, unless we have forced this spawn to always happen at a specific level.
  if ( ! $specific_level ) {
    $evolution_data  = slackemon_get_pokemon_evolution_data( $spawn['pokedex'] );
    $evolution_chain = slackemon_get_evolution_chain_pokemon( $evolution_data->chain, $spawn['pokedex'] );
    $evolves_at_level = 0;
    foreach ( $evolution_chain->evolves_to as $_evolution ) {
      foreach ( $_evolution->evolution_details as $_evolution_detail ) {
        if (
          $_evolution_detail->min_level && // This evolution requires a minimum level, AND
          (
            ! $evolves_at_level || // This is the first such evolution we've come across, OR
            $_evolution_detail->min_level < $evolves_at_level // This evolution happens earlier than a previous one.
          )
        ) {
          $evolves_at_level = $_evolution_detail->min_level;
          $pokemon_evolves_into     = basename( $_evolution->species->url ); // Gets the Pokedex ID from the URL.
        }
      }
    }
  }

  $player_args = [
    'active_only' => true,
    'region'      => $spawn['region'],
  ];

  foreach ( slackemon_get_player_ids( $player_args ) as $player_id ) {

    $this_message = $message;
    $this_spawn   = $spawn;
    $is_desktop   = 'desktop' === slackemon_get_player_menu_mode( $player_id );

    $seen   = slackemon_has_user_seen_pokemon(   $player_id, $this_spawn['pokedex'] );
    $caught = slackemon_has_user_caught_pokemon( $player_id, $this_spawn['pokedex'] );

    if ( $caught ) {
      $seen_caught_text = '';
    } else if ( $seen ) {
      $seen_caught_text = (
        "\n" .
        'You haven\'t caught a ' . slackemon_readable( $this_spawn['name'] ) . ' yet - good luck!' .
        ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ' :fingers_crossed:' : '' )
      );
    } else {
      $seen_caught_text = 'You\'ve never seen one before!';
    }

    $this_message['attachments'][0]['pretext'] =
      str_replace(
        '[SEEN_CAUGHT]',
        $seen_caught_text,
        $this_message['attachments'][0]['pretext']
      );

    $this_message['attachments'][0]['fallback'] =
      str_replace(
        [ '[SEEN_CAUGHT]', ' :fingers_crossed:', "\n" ],
        [ $seen_caught_text, '' ],
        $this_message['attachments'][0]['fallback']
      );

    // If this user has no battle team, don't show the Battle option set above,
    if ( ! slackemon_get_battle_team( $player_id, true ) ) {
      $this_message['attachments'][ count( $this_message['attachments'] ) - 1 ]['actions'][1] = [];
    }

    // If the user is not on desktop mode, create some more space for some of the fields,
    if ( ! $is_desktop ) {
      $this_message['attachments'][1]['fields'][2]['short'] = false;
      $this_message['attachments'][1]['fields'][3]['short'] = false;
    }

    // If the user has higher level Pokemon in their battle team, potentially adjust this Pokemon's level
    // (unless of course we have asked for a specific level already).
    if ( ! $specific_level ) {

      $highest_level = slackemon_get_battle_team_highest_level( $player_id, true );
      $highest_level_original = $highest_level;

      if ( $highest_level && $highest_level > 1 ) {

        // Protect some of the gameplay by ensuring we don't spawn a level that's too close to evolving.
        // If the user doesn't have the evolved form yet, we'll make sure we're even further from making it too easy.
        if ( $evolves_at_level ) {
          if ( slackemon_has_user_caught_pokemon( $player_id, $pokemon_evolves_into ) ) {

            // User already has evolved form.

            $highest_level = min( $highest_level, $evolves_at_level * .75 );

            slackemon_spawn_debug(
              'This spawn will be up to level ' . $highest_level . ' for ' . $player_id . ' - ' .
              'their battle team highest is ' . $highest_level_original . ' but we need to ensure ' .
              'the Pokemon isn\'t ready to evolve into #' . $pokemon_evolves_into . ' right-away.'
            );

          } else {

            // User DOESN'T have evolved form yet.

            $highest_level = min( $highest_level, $evolves_at_level * .5 );

            slackemon_spawn_debug(
              'This spawn will be up to level ' . $highest_level . ' for ' . $player_id . ' - ' .
              'their battle team highest is ' . $highest_level_original . ' but they don\'t ' .
              'have evolution Pokemon #' . $pokemon_evolves_into . ' yet.'
            );

          }

        } else {

          slackemon_spawn_debug(
            'This spawn will be up to level ' . $highest_level . ' for ' . $player_id . ' - ' .
            'the highest level in their battle team.'
          );

        }

        $random_level = random_int( 1, floor( $highest_level ) );

        foreach ( $this_spawn['stats'] as $key => $value ) {

          $this_spawn['stats'][ $key ] = slackemon_calculate_stats(
            $key,
            $this_spawn['pokedex'],
            $random_level,
            $this_spawn['ivs'],
            $this_spawn['evs'],
            $this_spawn['nature']
          );

        }

        $this_spawn['level'] = $random_level;
        $this_spawn['cp']    = slackemon_calculate_cp( $this_spawn['stats'] );
        $this_spawn['xp']    = slackemon_get_xp_for_level( $this_spawn['pokedex'], $this_spawn['level'] );

        $this_message['attachments'][1]['fields'][1]['value'] = (
          $this_spawn['cp'] . ' (Level ' . $this_spawn['level'] . ')'
        );

      } else {

        slackemon_spawn_debug(
          'User ' . $player_id . ' does not have any Pokemon over level 1 in their battle team, OR ' .
          'does not have enough Pokemon to battle. So, this spawn will be at level 1 for them.'
        );

      } // If highest_level > 1 / else.
    } // If not specific_level.

    // If the CP is higher than any this player has seen before, hide it to increase the mystique. :)

    $player_top_pokemon = slackemon_get_top_player_pokemon([ 'user_id' => $player_id ]);

    if ( $player_top_pokemon->cp < $this_spawn['cp'] ) {

      $this_spawn['flags'][] = 'hide_stats';

      $this_message['attachments'][1]['fields'][1]['value'] = '???';

      $this_message['attachments'][1]['fields'][3]['value'] = (
        slackemon_appraise_ivs( $this_spawn['ivs'] ) . ' ' .
        slackemon_get_iv_percentage( $this_spawn['ivs'] ) .'%'
      );

    }

    // Make sure we go to the correct player.
    $this_message['channel'] = $player_id;

    if ( slackemon_record_spawn_for_user( $player_id, $this_spawn ) ) {

      $response = slackemon_post2slack( $this_message );

      // If in development mode, store the spawn notification for debugging purposes.
      if ( 'development' === APP_ENV ) {
        file_put_contents( $data_folder . '/last-spawn-notification', $response );
      }
      
    }

  } // Foreach active players in the region

  return true;

} // Function slackemon_notify_spawn

function slackemon_record_spawn_for_user( $user_id, $spawn ) {
  global $data_folder;

  $player_data = slackemon_get_player_data( $user_id, true );

  // Can we increment the 'seen' value on an existing spawn?
  $found_entry = false;
  foreach ( $player_data->pokedex as $pokedex_entry ) {
    if ( $spawn['pokedex'] == $pokedex_entry->id ) {
      $pokedex_entry->seen++;
      $found_entry = true;
      break;
    }
  }

  // First time seen - time to create a new Pokedex entry!
  if ( ! $found_entry ) {
    $player_data->pokedex[] = [
      'id'     => (int) $spawn['pokedex'],
      'seen'   => 1,
      'caught' => 0,
    ];
  }

  // Store the calculated stats for this user
  $spawn_filename = $data_folder . '/spawns/' . $spawn['ts'] . '-' . $spawn['region'];
  $spawn_data = json_decode( slackemon_file_get_contents( $spawn_filename, 'store' ) );
  $spawn_data->users->{ $user_id } = [
    'stats'   => $spawn['stats'],
    'level'   => $spawn['level'],
    'xp'      => $spawn['xp'],
    'cp'      => $spawn['cp'],
    'hp'      => $spawn['stats']['hp'],
    'flags'   => $spawn['flags'],
  ];
  slackemon_file_put_contents( $spawn_filename, json_encode( $spawn_data ), 'store', false );

  return slackemon_save_player_data( $player_data, $user_id, true );

} // Function slackemon_record_spawn

function slackemon_spawn_debug( $message ) {

  if ( ! SLACKEMON_SPAWN_DEBUG ) {
    return;
  }

  slackemon_error_log( $message );

}

// The end!
