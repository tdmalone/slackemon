<?php

// Chromatix TM 04/04/2017
// Region travelling menu for Slackemon Go

function slackemon_get_travel_menu() {

	$regions = slackemon_get_regions();
	//$habitats = json_decode( get_cached_url( 'http://pokeapi.co/api/v2/pokemon-habitat/' ) )->results; (url/name) // TODO
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

	$attachments = [];

	foreach ( $regions as $region ) {

	  // Get a random Pokemon first discovered in this region, and make sure it's not an evolvee (i.e. it *can* be caught!)
	  $random_pokemon = $region['generation_pokedex'][ array_rand( $region['generation_pokedex'] ) ];
	  $random_pokemon_species_data = json_decode( get_cached_url( $random_pokemon->url ) );
	  while ( $random_pokemon_species_data->evolves_from_species ) {
	    $random_pokemon = $region['generation_pokedex'][ array_rand( $region['generation_pokedex'] ) ];
	    $random_pokemon_species_data = json_decode( get_cached_url( $random_pokemon->url ) );
	  }
	  $random_pokemon_name   = $random_pokemon->name;
	  $random_pokemon_number = trim( basename( $random_pokemon->url ), '/' );
    $random_pokemon_color  = slackemon_get_color_as_hex( $random_pokemon_species_data->color->name );

    $totals = slackemon_get_player_seen_caught_by_region( $region['name'] );
    $total_in_region     = count( $region['region_pokedex']     );
    $total_in_generation = count( $region['generation_pokedex'] );

	  $attachments[] = [
	    'text' => (
        '*' . pokedex_readable( $region['name'] ) . ' - ' . pokedex_readable( $region['generation'] ) . '*' . "\n" .
	      $region['description'] . "\n\n" .
        ':pokeball: You have caught *' . $totals['caught'] . '* and seen *' . $totals['seen'] . '* of the ' .
        'Pokémon found in this region.' . "\n" .
        ':bar_chart: There are *' . $total_in_region . '* Pokémon ' .
        'in ' . pokedex_readable( $region['name'] ) . '; ' .
        '*' . ( $total_in_generation === $total_in_region ? 'all' : $total_in_generation ) . '* of them ' .
        'first discovered here.'
	    ),
	    'thumb_url' => (
        $is_desktop ?
        get_cached_image_url( SLACKEMON_ANIMATED_GIF_BASE . '/' . $random_pokemon_name . '.gif' ) :
        ''
      ),
	    'color' => $random_pokemon_color,
	    'footer' => (
        slackemon_get_player_region() === $region['name'] ?
        'You are currently travelling in this region.' :
        ''
	    ),
	    'actions' => [
	      (
	        slackemon_get_player_region() === $region['name'] ?
	        [] :
	        [
	          'name' => 'travel',
	          'text' => 'Travel to ' . pokedex_readable( $region['name'] ),
	          'type' => 'button',
	          'value' => $region['name'],
	          'style' => 'primary',
	        ]
	      ),
	    ],
	  ];

	} // Foreach regions

	$attachments[] = slackemon_back_to_menu_attachment();

	$message = [
	  'text' => (
	    '*Tʀᴀᴠᴇʟ*' . "\n" .
	    'If you wanna catch \'em all, it\'s time to go on a journey!'
	  ),
	  'attachments' => $attachments,
	];

  return $message;

} // Function slackemon_get_travel_menu

function slackemon_get_region_message( $new_region_name ) {

  $regions = slackemon_get_regions();
  $player_data = slackemon_get_player_data();
  $is_desktop = 'desktop' === slackemon_get_player_menu_mode();

  // Have we visited this region before?
  if ( SLACKEMON_DEFAULT_REGION === $new_region_name ) {
    $is_region_new = false;  
  } else {
    $is_region_new = true;
    if ( isset( $player_data->regions ) ) {
      foreach ( $player_data->regions as $_region ) {
        if ( $_region->name === $new_region_name ) {
          $is_region_new = false;
          break;
        }
      }
    }
  }

  $totals = slackemon_get_player_seen_caught_by_region( $new_region_name );
  $total_in_region     = count( $regions[ $new_region_name ]['region_pokedex']     );
  $total_in_generation = count( $regions[ $new_region_name ]['generation_pokedex'] );
 
  $message = [
    'text' => '',
    'attachments' => [
      [
        'text' => (
          '*Welcome ' . ( $is_region_new ? '' : 'back ' ) . 'to ' .
          pokedex_readable( $new_region_name ) . '!*' .
          ( $is_desktop ? "\n" : "\n\n" ) .
          $regions[ $new_region_name ]['description'] . "\n\n" .
          (
            $is_region_new ?
            ':tada: *+1000 XP*: First visit to a new region!' :
            ':pokeball: You have caught *' . $totals['caught'] . '* and seen *' . $totals['seen'] . '* of the ' .
            'Pokémon found in this region.' . "\n" .
            ':bar_chart: There are *' . $total_in_region . '* Pokémon ' .
            'in ' . pokedex_readable( $new_region_name ) . '; ' .
            '*' . ( $total_in_generation === $total_in_region ? 'all' : $total_in_generation ) . '* of them ' .
            'first discovered here.' . "\n"
          )
        ),
        'color' => '#333333',
        'image_url' => get_cached_image_url( SLACKEMON_INBOUND_URL . '_images/slackemon-' . $new_region_name . '.png' ),
      ],
      slackemon_back_to_menu_attachment(),
    ],
  ];

  return $message;

} // Function slackemon_get_region_message

// The end!
