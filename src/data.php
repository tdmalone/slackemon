<?php
/**
 * Functions that return specific data required by Slackemon.
 * TODO: These functions should really be turned into data files that sit in /etc.
 *
 * @package Slackemon
 */

// We create our own function for this, because it is not in the API :)
function slackemon_is_legendary( $pokedex_id ) {

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

} // Function slackemon_is_legendary

// We create our own function for this, because the API data is pretty simple anyway and it's unlikely to change
function slackemon_get_natures() {

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

} // Function slackemon_get_natures

// The end!
