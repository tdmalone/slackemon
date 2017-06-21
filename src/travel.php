<?php
/**
 * Region travel specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_player_region( $user_id = USER_ID ) {

  $player_data = slackemon_get_player_data( $user_id );

  if ( isset( $player_data->region ) ) {
    return $player_data->region;
  } else {
    return SLACKEMON_DEFAULT_REGION;
  }

} // Function slackemon_get_player_region

function slackemon_set_player_region( $region = false, $user_id = USER_ID ) {
  
  // Default region
  if ( ! $region ) {
    $region = SLACKEMON_DEFAULT_REGION;
  }

  $player_data = slackemon_get_player_data( $user_id );
  $player_data->region = $region;

  if ( ! isset( $player_data->regions ) ) {
    $player_data->regions = [];
  }

  // Have we visited this region before?
  foreach ( $player_data->regions as $_region ) {
    if ( $_region->name === $region ) {
      return slackemon_save_player_data( $player_data, $user_id );
    }
  }

  // If we've got here, it's our first time in this region!
  // (if the default region, we should set the visit time as when we registered)
  $player_data->regions[] = [
    'name' => $region,
    'first_visit' => $region === SLACKEMON_DEFAULT_REGION ? $player_data->registered : time(),
  ];

  // XP bonus for first visit to a new region
  if ( $region !== SLACKEMON_DEFAULT_REGION ) {
    slackemon_add_xp( 1000 );
  }

  return slackemon_save_player_data( $player_data, $user_id );

} // Function slackemon_set_player_region

function slackemon_get_regions() {
  global $_cached_slackemon_regions;

  if (
    isset( $_cached_slackemon_regions ) &&
    is_array( $_cached_slackemon_regions ) &&
    count( $_cached_slackemon_regions )
  ) {
    return $_cached_slackemon_regions;
  }

  $region_list = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/region/' ) )->results;

  // Define region descriptions, since they aren't in the API
  // Also doubles as an excluder - leave a description blank or don't include a region, and it will be skipped
  $region_descriptions = [
    'kanto' => 'The Kanto region is home to a lot of Pokémon and has a rich history of creating Pokémon with technology. :robot_face:',
    'johto' => 'Johto is connected to the western part of Kanto, and features the iconic Bell and Brass Towers, guarded by newly discovered legendary Pokémon.',
    'hoenn' => 'Hoenn is based on the island of Kyūshū in Japan and lies to the southwest of Kanto and Johto in the Pokémon world. It is the only sea region. :ocean:',
    'sinnoh' => 'Sinnoh is a mountainous and temperate region, containing four lakes - each of which houses a legendary Pokémon. :mountain:',
    'unova' => 'Unova is the most diverse region of all, with many new Pokémon species discovered here. Taking inspiration from New York City, Unova in many ways resembles the island of Manhattan. :cityscape:',
    'kalos' => 'Shaped on the map like a five-pointed star, Kalos is home to French music, fashion and landmarks. Welcome to Paris! :flag-fr:',
  ];

  $regions = [];

  foreach ( $region_list as $region ) {

    if ( ! isset( $region_descriptions[ $region->name ] ) || ! $region_descriptions[ $region->name ] ) {
      continue;
    }

    if ( ! in_array( $region->name, SLACKEMON_AVAILABLE_REGIONS ) ) {
      continue;
    }

    $region_data        = json_decode( slackemon_get_cached_url( $region->url ) );
    $region_pokedex     = json_decode( slackemon_get_cached_url( $region_data->pokedexes[0]->url ) );
    $generation_pokedex = json_decode( slackemon_get_cached_url( $region_data->main_generation->url ) );

    $regions[ $region->name ] = [
      'name'               => $region->name,
      'generation'         => $region_data->main_generation->name,
      'description'        => $region_descriptions[ $region->name ],
      'data'               => $region_data,
      'region_pokedex'     => $region_pokedex->pokemon_entries,     // This region's original Pokedex
      'generation_pokedex' => $generation_pokedex->pokemon_species, // Unique Pokemon introduced in this generation
    ];

    // IMPORTANT! When using the region_pokedex, you will need to iterate down to ->pokemon_species->url to get the
    // Pokemon's direct URL (and use the basename of that to get it's national Pokedex number).
    // When using the generation_pokedex, you do NOT need to iterate down and can just use ->url directly.

  } // Foreach region

  $_cached_slackemon_regions = $regions;
  return $regions;

} // Function slackemon_get_regions

function slackemon_get_player_seen_caught_by_region( $region_name, $user_id = USER_ID ) {

  $regions = slackemon_get_regions();
  $player_data = slackemon_get_player_data();

  $totals = [ 'seen' => 0, 'caught' => 0 ];
  $region_pokedex = $regions[ $region_name ]['region_pokedex'];
  $player_pokedex = $player_data->pokedex;

  $player_pokedex_indexed = [];
  foreach ( $player_pokedex as $pokedex_entry ) {
    $player_pokedex_indexed[ $pokedex_entry->id ] = $pokedex_entry;
  }

  foreach ( $region_pokedex as $pokedex_entry ) {
    $pokedex_id = trim( basename( $pokedex_entry->pokemon_species->url ), '/' );
    if ( array_key_exists( $pokedex_id, $player_pokedex_indexed ) ) {
      if ( $player_pokedex_indexed[ $pokedex_id ]->seen   ) { $totals['seen']++;   }
      if ( $player_pokedex_indexed[ $pokedex_id ]->caught ) { $totals['caught']++; }
    }
  }

  return $totals;

} // Function slackemon_get_player_seen_caught_by_region

// The end!
