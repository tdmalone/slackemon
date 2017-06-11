<?php

// Chromatix TM 04/04/2017
// Functions that support both the /pokedex Slashie as well as functionality in Slackemon Go

// We create our own function for this, because it is not in the API :)
function pokedex_is_legendary( $pokedex_id ) {

  // See: http://bulbapedia.bulbagarden.net/wiki/User:Focus58/List_of_Legendary_Pok%C3%A9mon

  $legendaries = [

    // Gen I
    144, // Articuno  Flying/Ice
    145, // Zapdos    Flying/Electric
    146, // Moltres   Flying/Fire
    150, // Mewtwo    Psychic
    151, // Mew       Psychic

    // Gen II
    243, // Raikou    Electric
    244, // Entei     Fire
    245, // Suicune   Water
    249, // Lugia     Flying/Psychic
    250, // Ho Oh     Flying/Fire
    251, // Celebi    Grass/Psychic

    // Gen III and onwards
    377, // Regirock
    378, // Regice
    379, // Registeel
    380, // Latias
    381, // Latios
    382, // Kyogre
    383, // Groudon
    384, // Rayquaza
    385, // Jirachi
    386, // Deoxys
    480, // Uxie
    481, // Mesprit
    482, // Azelf
    483, // Dialga
    484, // Palkia
    485, // Heatran
    486, // Regigigas
    487, // Giratina
    488, // Cresselia
    489, // Phione
    490, // Manaphy
    491, // Darkrai
    492, // Shaymin
    493, // Arceus
    494, // Victini
    638, // Cobalion
    639, // Terrakion
    640, // Virizion
    641, // Tornadus
    642, // Thundurus
    643, // Reshiram
    644, // Zekrom
    645, // Landorus
    646, // Kyurem
    647, // Keldeo
    648, // Meloetta
    649, // Genesect

  ];

  if ( in_array( $pokedex_id, $legendaries ) ) {
    return true;
  } else {
    return false;
  }

} // Function pokedex_is_legendary

// We create our own function for this, because the API data is pretty simple anyway and it's unlikely to change
function pokedex_get_natures() {

  $natures = [
    'hardy',
    'bold',
    'modest',
    'calm',
    'timid',
    'lonely',
    'docile',
    'mild',
    'gentle',
    'hasty',
    'adamant',
    'impish',
    'bashful',
    'careful',
    'rash',
    'jolly',
    'naughty',
    'lax',
    'quirky',
    'naive',
    'brave',
    'relaxed',
    'quiet',
    'sassy',
    'serious',
  ];

  return $natures;

} // Function pokedex_get_natures

// Make a system string (generally, Pokemon names, region names, etc.) human-readable
function pokedex_readable( $string, $display_gender = true, $abbrev = false ) {

  // Male & Female Pokemon species, eg. Nidoran
  $string = preg_replace( [ '/-m$/', '/-f$/' ], $display_gender ? [ '♂', '♀' ] : '', $string );

  // General word capitalisation & hyphen removal
  $string = ucwords( strtolower( str_replace( '-', ' ', $string ) ) );

  // Ensure Roman-numeral generation numbers are capitalised correctly
  $string = preg_replace_callback( '/\b(I|V)(i|v){1,2}\b/', function( $matches ) {
    return strtoupper( $matches[0] );
  }, $string );

  // Ensure some common two-character abbreviations are capitalised correctly
  $string = preg_replace([
    '/\bHp\b/',
    '/\bPp\b/',
    '/\bTm(\d|s)/',
    '/\bHm(\d|s)/',
  ], [
    'HP',
    'PP',
    'TM$1',
    'HM$1',
  ], $string );

  // Further abbreviations?
  if ( $abbrev ) {
    $string = preg_replace([
      '/\bSpecial Attack\b/',
      '/\bSpecial Defense\b/',
      '/\bAttack\b/',
      '/\bDefense\b/',
      '/\bGiga\b/',
      '/\bBeam\b/',
      '/\bAverage\b/',
      '/\bDouble\b/',
      '/\bPump\b/',
      '/\bExcellent\b/',
      '/\bDragon\b/',
      '/\bPower\b/',
      '/\bDynamic\b/',
    ], [
      'Sp Att',
      'Sp Def',
      'Attk',
      'Def',
      'G.',
      'B.',
      'Avg',
      'Dbl',
      'P.',
      'Exc.',
      'Drag.',
      'Pwr',
      'Dyn.',
    ], $string );
  } else {
    $string = preg_replace([
      '/\bSp\b/',
      '/\bDef\b/',
      '/\bAttk\b/',
      '/\bAtk\b/',
    ], [
      'Special',
      'Defense',
      'Attack',
      'Attack',
    ], $string );
  }

  return $string;

} // Function pokedex_readable

// Get Pokemon evolution chain, highlighting the current Pokemon
function pokedex_get_evolution_chain( $pokedex_id, $return_value_if_none = false ) {

  $output = '';

  $pokemon_data = slackemon_get_pokemon_data( $pokedex_id );
  $species_data = json_decode( get_cached_url( $pokemon_data->species->url ) );
  $evolution_data = json_decode( get_cached_url( $species_data->evolution_chain->url ) );

  $chain = $evolution_data->chain;
  $pokemon_name = $pokemon_data->name;

  $output = pokedex_build_evolution_chain( $chain, $pokemon_name );

  if ( false === strpos( $output, '>' ) ) {
    return $return_value_if_none; // Pokemon does not evolve
  }

  return $output;

} // Function pokedex_get_evolution_chain

function pokedex_build_evolution_chain( $chain, $pokemon_name ) {

  $output = '';

  if ( $chain->species->name === $pokemon_name ) { $output .= '_'; }
  $output .= pokedex_readable( $chain->species->name );
  if ( $chain->species->name === $pokemon_name ) { $output .= '_'; }

  if ( 1 === count( $chain->evolves_to ) ) {
    $output .= ' > ' . pokedex_build_evolution_chain( $chain->evolves_to[0], $pokemon_name );
  } else if ( count( $chain->evolves_to ) > 1 ) {
    $output .= ' > (';
    $branched_chain = '';
    foreach ( $chain->evolves_to as $evolution ) {
      if ( $branched_chain ) { $branched_chain .= ', '; }
      $branched_chain .= pokedex_build_evolution_chain( $evolution, $pokemon_name );
    }
    $output .= $branched_chain . ')';
  }

  return $output;

} // Function pokedex_build_evolution_chain

// The end!
