<?php
/**
 * Stats related functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_calculate_battle_experience( $_pokemon ) {

  $_pokemon_data = slackemon_get_pokemon_data( $_pokemon->pokedex );

  // HT: http://bulbapedia.bulbagarden.net/wiki/Experience#Gain_formula
  $a = 1; // Used for differentiating wild & trained Pokemon prior to Gen VII
  $b = $_pokemon_data->base_experience; // Base experience yield of the defeated Pokemon
  $e = 1; // Lucky Egg modifier (1.5 if winning Pokemon is holding it)
  $f = 1; // Affection modifier (*not* the same thing as Friendship/Happiness)
  $L = $_pokemon->level; // Defeated Pokemon's level
  $p = 1; // Experience point power modifier
  $s = 1; // Experience share modifier
  $t = 1; // Trade modifier (1 if the winning owner is the original trainer, 1.5 if Pokemon came from a trade)
  $v = 1; // Evolution modifier (1.2 if winning Pokemon is past evolve level but hasn't evolved)
  $c = SLACKEMON_EXP_GAIN_MODIFIER;

  $experience = ( $a * $t * $b * $e * $L * $p * $f * $v ) / ( 7 * $s ) * $c;

  return $experience;

} // Function slackemon_calculate_battle_experience

function slackemon_get_iv_percentage( $ivs ) {

  $ivs_total = 0;
  $ivs_max   = 0;

  foreach ( $ivs as $iv ) {
    $ivs_total += $iv;
    $ivs_max   += SLACKEMON_MAX_IVS;
  }

  $ivs_percentage = floor( $ivs_total / $ivs_max * 100 );

  return intval( $ivs_percentage );

} // Function slackemon_get_iv_percentage

function slackemon_get_combined_evs( $evs ) {

  $evs_total = 0;

  foreach ( $evs as $ev ) {
    $evs_total += $ev;
  }

  return $evs_total;

} // Function slackemon_get_combined_evs

function slackemon_get_nature_stat_modifications( $nature_name ) {

  if ( ! $nature_name ) {
    return [
      'increase' => false,
      'decrease' => false,
    ];
  }

  // Get and sort out nature data - because we don't have some stats, we apply the nature to others
  $nature_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/nature/' . $nature_name .'/' ) );
  $increased_stat = $nature_data->increased_stat ? $nature_data->increased_stat->name : false;
  $decreased_stat = $nature_data->decreased_stat ? $nature_data->decreased_stat->name : false;

  return [
    'increase' => $increased_stat,
    'decrease' => $decreased_stat,
  ];

} // Function slackemon_get_nature_stats

/**
 * Calculate a new stat for a Pokemon based on their level, IVs, EVs and nature.
 *
 * @param str     $stat_name             A recognised stat name. Accepts 'attack', 'defense', 'hp', 'speed',
 *                                       'special-attack' or 'special-defense'.
 * @param int|obj $pokedex_id_or_pokemon Either the Pokedex ID or a full Pokemon object. If a Pokemon object is
 *                                       supplied, the rest of the arguments are not required and will not be used.
 * @param float   $level
 * @param arr|obj $ivs
 * @param arr|obj $evs
 * @param string  $nature
 */
function slackemon_calculate_stats(
  $stat_name, $pokedex_id_or_pokemon, $level = null, $ivs = null, $evs = null, $nature = null
) {

  // Accept an entire Pokemon object being passed through.
  if ( is_object( $pokedex_id_or_pokemon ) ) {
    $pokemon    = $pokedex_id_or_pokemon;
    $pokedex_id = $pokemon->pokedex;
    $level      = $pokemon->level;
    $ivs        = $pokemon->ivs;
    $evs        = $pokemon->evs;
    $nature     = $pokemon->nature;
  } else {
    $pokedex_id = $pokedex_id_or_pokemon;
  }

  // Ensure things are objects
  $ivs = is_array( $ivs ) ? json_decode( json_encode( $ivs ) ) : $ivs;
  $evs = is_array( $evs ) ? json_decode( json_encode( $evs ) ) : $evs;

  $base_stats   = slackemon_get_base_stats( $pokedex_id );
  $nature_stats = slackemon_get_nature_stat_modifications( $nature );
  
  if ( $nature_stats['increase'] && $nature_stats['increase'] === $stat_name ) {
    $nature_value = 1.1;
  } else if ( $nature_stats['decrease'] && $nature_stats['decrease'] === $stat_name ) {
    $nature_value = 0.9;
  } else {
    $nature_value = 1;
  }

  // HT: http://bulbapedia.bulbagarden.net/wiki/Statistic#In_Generation_III_onward

  $b = $base_stats->{ $stat_name };
  $B = $base_stats->{ $stat_name } * 2;
  $i = isset( $ivs->{ $stat_name } ) ? $ivs->{ $stat_name } : ( is_numeric( $ivs ) ? $ivs : 0 );
  $e = isset( $evs->{ $stat_name } ) ? floor( $evs->{ $stat_name } / 4 ) : 0;
  $L = $level;
  $n = $nature_value;

  // Because we want our stats to differ a bit from the main games, we ADD (not multiply) own our modifier.
  // TODO: Try to tweak this to make the max CPs (and if possible the CP growth) more in line with Pokemon Go.
  $slackemon_modifier = $b;
  //$slackemon_modifier = 0;
  //$slackemon_modifier = $b * max( 1, $L / 4 );

  if ( 'hp' === $stat_name ) {
    $stat_value = $slackemon_modifier + floor( floor( ( ( $B + $i + $e ) * $L ) / 100 ) + $L + 10 );
  } else {
    $stat_value = $slackemon_modifier + floor( ( floor( ( ( $B + $i + $e ) * $L ) / 100 ) + 5 ) * $n );
  }

  return $stat_value;

} // Function slackemon_calculate_stats

function slackemon_get_base_stats( $pokedex_id ) {

  $pokemon_data = slackemon_get_pokemon_data( $pokedex_id );

  $base_stats = [];
  foreach ( $pokemon_data->stats as $_stat ) {
    $base_stats[ $_stat->stat->name ] = $_stat->base_stat;
  }

  return json_decode( json_encode( $base_stats ) );

} // Function slackemon_get_base_stats

function slackemon_calculate_cp( $stats ) {

  if ( is_array( $stats ) ) {
    $stats = json_decode( json_encode( $stats ) );
  }

  $cp = floor(
    ( ( ( $stats->attack  * 0.54 ) + ( $stats->{'special-attack'}  * 0.46 ) ) * 1.0 ) +
    ( ( ( $stats->defense * 0.54 ) + ( $stats->{'special-defense'} * 0.46 ) ) * 0.5 ) +
    ( $stats->hp    * 0.50 / 10 ) +
    ( $stats->speed * 0.25 / 10 )
  );

  return intval( $cp );

} // Function slackemon_calculate_cp

function slackemon_get_xp_for_level( $pokedex_id, $level ) {

  $level = floor( $level ); // Only support full levels
  $growth_rate_data = slackemon_get_pokemon_growth_rate_data( $pokedex_id );

  foreach ( $growth_rate_data->levels as $_level ) {
    if ( $_level->level == $level ) {
      return $_level->experience;
    }
  }

  return false;

} // Function slackemon_get_xp_for_level

function slackemon_get_min_max_cp( $pokemon, $level, $set_ivs ) {

  // Get the min/max CP either for a Pokemon species (if the Pokedex ID is sent through) or a particular Pokemon
  if ( is_numeric( $pokemon ) ) {
    $pokedex_id = $pokemon;
    $ivs = $set_ivs;
    $nature = null;
  } else {
    $pokedex_id = $pokemon->pokedex;
    $ivs        = $pokemon->ivs;
    $nature     = $pokemon->nature;
  }

  // If we've requested level 100, set the EVs to what should result in the theoretical maximum for CP
  if ( 100 == $level ) {
    $evs = [
      'attack'  => 252,
      'defense' => 129,
      'hp'      => 129,
    ];
  } else {
    $evs = null;
  }

  $stats = [
    'attack'          => slackemon_calculate_stats( 'attack',          $pokedex_id, $level, $ivs, $evs, $nature ),
    'defense'         => slackemon_calculate_stats( 'defense',         $pokedex_id, $level, $ivs, $evs, $nature ),
    'hp'              => slackemon_calculate_stats( 'hp',              $pokedex_id, $level, $ivs, $evs, $nature ),
    'special-attack'  => slackemon_calculate_stats( 'special-attack',  $pokedex_id, $level, $ivs, $evs, $nature ),
    'special-defense' => slackemon_calculate_stats( 'special-defense', $pokedex_id, $level, $ivs, $evs, $nature ),
    'speed'           => slackemon_calculate_stats( 'speed',           $pokedex_id, $level, $ivs, $evs, $nature ),
  ];

  $min_cp = slackemon_calculate_cp( $stats );

  return $min_cp;

} // Function slackemon_get_min_max_cp

function slackemon_get_min_cp( $pokemon ) {
  return slackemon_get_min_max_cp( $pokemon, 1, SLACKEMON_MIN_IVS );
}

function slackemon_get_max_cp( $pokemon ) {
  return slackemon_get_min_max_cp( $pokemon, 100, SLACKEMON_MAX_IVS );
}

/**
 * Converts an affection level to an appropriate level of happiness, given we don't have affection in this game.
 * Primarily used to determine eligibility for evolution for species that evolve on affection.
 * Levels are based on the table at http://bulbapedia.bulbagarden.net/wiki/Affection#Increasing_affection but adjusted
 * due to the fact that most Pokemon will start at 70 happiness anyway.
 */
function slackemon_affection_to_happiness( $affection_level ) {

  switch ( $affection_level ) {
    case 0:  $happiness =  70; break;
    case 1:  $happiness =  95; break; // Step up 25
    case 2:  $happiness = 125; break; // Step up 30
    case 3:  $happiness = 160; break; // Step up 35
    case 4:  $happiness = 200; break; // Step up 40
    case 5:  $happiness = 255; break; // Step up 55
    default: $happiness = false; break;
  }

  return $happiness;

} // Function slackemon_affection_to_happiness

/**
 * Returns the EV yield from defeating a team of Pokemon.
 * See also slackemon_get_xp_yield().
 *
 * @param arr|obj $opponent_team      Usually an array of Pokemon that were beaten. Also accepts a single Pokemon obj.
 * @param bool    $skip_non_fainted   Whether to skip Pokemon who haven't fainted for some reason. Usually true.
 * @return arr An array of EV stats yielded by the defeated team.
 */
function slackemon_get_ev_yield( $opponent_team, $skip_non_fainted = true ) {

  // Accept a single Pokemon as an alternative to a whole team.
  if ( ! is_array( $opponent_team ) && ! is_object( $opponent_team ) ) {
    $opponent_team = [ 'ts' . $opponent_team->ts => $opponent_team ];
  }

  $results = [
    'attack'          => 0,
    'defense'         => 0,
    'hp'              => 0,
    'special-attack'  => 0,
    'special-defense' => 0,
    'speed'           => 0,
  ];

  foreach ( $opponent_team as $_pokemon ) {

    // Skip if this opponent Pokemon isn't fainted.
    if ( $skip_non_fainted && $_pokemon->hp ) {
      continue;
    }

    // Grab the fainted Pokemon's API data.
    $_pokemon_data = slackemon_get_pokemon_data( $_pokemon->pokedex );

    // Calculate the EV gain from defeating this Pokemon.
    foreach ( $_pokemon_data->stats as $_stat ) {

      if ( ! $_stat->effort ) {
        continue;
      }

      $results[ $_stat->stat->name ] += (int) $_stat->effort;

      break;

    } // Foreach stats.
  } // Foreach opponent Pokemon.

  return $results;

} // Function slackemon_get_ev_yield.

/**
 * Returns the itemised and combined XP yield from defeating a team of Pokemon.
 * See also slackemon_get_ev_yield().
 *
 * @param array|obj $opponent_team Usually an array of Pokemon that were beaten. Also accepts a single Pokemon object.
 * @param bool $skip_non_fainted   Whether to skip Pokemon who haven't fainted. Usually should be true.
 * @return arr An array containing the total XP yield, plus an itemised array of Pokemon with the XP they each yielded.
 */
function slackemon_get_xp_yield( $opponent_team, $skip_non_fainted = true ) {

  // Accept a single Pokemon as an alternative to a whole team.
  if ( ! is_array( $opponent_team ) && ! is_object( $opponent_team ) ) {
    $opponent_team = [ 'ts' . $opponent_team->ts => $opponent_team ];
  }

  $results = [
    'itemised' => [],
    'total'    => 0,
  ];

  foreach ( $opponent_team as $_pokemon ) {

    // Skip if this opponent Pokemon isn't fainted.
    if ( $skip_non_fainted && $_pokemon->hp ) {
      continue;
    }

    // Grab the fainted Pokemon's API data.
    $_pokemon_data = slackemon_get_pokemon_data( $_pokemon->pokedex );

    // Calculate the experience yielded by this Pokemon.
    $experience        = (int) slackemon_calculate_battle_experience( $_pokemon );
    $results['total'] += $experience;

    $results['itemised'][] = [
      'name'     => $_pokemon->name,
      'pokedex'  => $_pokemon->pokedex,
      'level'    => $_pokemon->level,
      'xp_yield' => $experience,
    ];

  } // Foreach opponent Pokemon.

  return $results;

} // Function slackemon_get_xp_yield.

function slackemon_calculate_level( $pokedex_id_or_pokemon, $xp = null ) {

  // Accept an entire Pokemon object being passed through.
  if ( is_object( $pokedex_id_or_pokemon ) ) {
    $pokemon    = $pokedex_id_or_pokemon;
    $pokedex_id = $pokemon->pokedex;
    $xp         = $pokemon->xp;
  } else {
    $pokedex_id = $pokedex_id_or_pokemon;
  }

  $level = 1;

  $growth_rate_data = slackemon_get_pokemon_growth_rate_data( $pokedex_id );
  foreach ( $growth_rate_data->levels as $_level ) {

    // Levels in the API go from 100 down to 1.
    // If our experience is greater than the experience required for this level, *this* is our level!
    if ( $xp >= $_level->experience ) {

      // Work out a partial level if we're well on our way to the next.
      if ( isset( $next_level_experience ) && $xp > $_level->experience ) {

        $exp_required_to_next_level    = $next_level_experience - $_level->experience;
        $exp_gained_towards_next_level = $xp - $_level->experience;

        $level = $_level->level + ( $exp_gained_towards_next_level / $exp_required_to_next_level );

      } else {
        $level = $_level->level;
      }

      // Limit levels to 1 decimal point.
      $level = round( $level, 1, PHP_ROUND_HALF_DOWN );

      break;

    }

    // Pass experience required down to previous level as the loop continues.
    $next_level_experience = $_level->experience;

  } // Foreach growth rate level.

  return $level;

} // Function slackemon_calculate_level.

function slackemon_calculate_level_up_happiness( $old_level, $new_level_or_pokemon, $happiness = null ) {

  // Accept an entire Pokemon object being passed through.
  if ( is_object( $new_level_or_pokemon ) ) {
    $pokemon   = $new_level_or_pokemon;
    $happiness = $pokemon->happiness;
    $new_level = $pokemon->level;
  } else {
    $new_level = $new_level_or_pokemon;
  }

  // +5 per additional level if happiness 0-99; +3 if happiness 100-199; or +2 if 200-255.
  // Reference: http://bulbapedia.bulbagarden.net/wiki/Friendship#In_Generation_I.
  for ( $i = floor( $old_level ); $i < floor( $new_level ); $i++ ) {
    if ( $happiness < 100 ) {
      $happiness += 5;
    } else if ( $happiness < 200 ) {
      $happiness += 3;
    } else {
      $happiness += 2;
    }
  }

  // Ensure we don't exceed the 255 happiness cap.
  $happiness = min( 255, $happiness );

  return $happiness;

} // Function slackemon_calculate_level_up_happiness.

function slackemon_apply_evs( $evs_or_pokemon, $evs_to_apply ) {

  // Accept an entire Pokemon object being passed through.
  if ( is_object( $evs_or_pokemon ) ) {
    $pokemon = $evs_or_pokemon;
    $evs     = $pokemon->evs;
  } else {
    $evs     = $evs_or_pokemon;
  }

  // First, turn current EVs into an empty object values if they are an array, which is how they are created at spawn.
  if ( is_array( $evs ) ) {
    $evs = json_decode(
      json_encode([
        'attack'          => 0,
        'defense'         => 0,
        'hp'              => 0,
        'special-attack'  => 0,
        'special-defense' => 0,
        'speed'           => 0,
      ])
    );
  }

  // Apply all EVs, ensuring they stay below the maximums.
  $current_evs_total = slackemon_get_combined_evs( $evs );
  foreach ( $evs_to_apply as $key => $value ) {

    // Max 510 across all EV stats - reduce the value we're applying if it would otherwise take us over the limit.
    if ( $current_evs_total + $value > 510 ) {
      $value = $current_evs_total + $value - 510;
    }

    // Apply the value.
    $evs->{ $key } += $value;

    // Ensure a max 252 per EV stat.
    $evs->{ $key } = min( 252, $evs->{ $key } );

  }

  return $evs;

} // Function slackemon_apply_evs.

// The end!
