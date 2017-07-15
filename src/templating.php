<?php
/**
 * Templating/output specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_readable_moveset( $moves, $types, $include_bullets = false, $include_pp = false ) {

  $output = '';
  $moves  = slackemon_sort_battle_moves( $moves, $types );

  foreach ( $moves as $move ) {

    if ( $output ) { $output .= "\n"; }

    $move_data = slackemon_get_move_data( $move->name );

    $output .= (
      ( $include_bullets ? '• ' : '' ) .
      slackemon_readable( $move->name ) . ' ' .
      '(' .
      ( in_array( ucfirst( $move_data->type->name ), $types ) ? '*' : '' ) .
      slackemon_readable( $move_data->type->name ) .
      ( in_array( ucfirst( $move_data->type->name ), $types ) ? '*' : '' ) .
      ', x' . ( $move_data->power ? $move_data->power : 0 ) .
      ( $include_pp ? ', ' . floor( $move->{'pp-current'} ) . '/' . $move->pp : '' ) .
      ')'
    );

  }

  return $output;

} // Function slackemon_readable_moveset.

function slackemon_condensed_moveset( $moves, $types, $abbrev = false ) {

  $output = '';
  $moves  = slackemon_sort_battle_moves( $moves, $types );

  foreach ( $moves as $move ) {
    if ( $output ) { $output .= " / "; }
    $move_data = slackemon_get_move_data( $move->name );
    $output .= (
      slackemon_readable( $move->name, true, $abbrev ) . ' ' .
      'x' . ( $move_data->power ? $move_data->power : 0 )
    );
  }

  return $output;

} // Function slackemon_condensed_moveset.

function slackemon_get_gender_symbol( $gender, $space_location = 'before' ) {

  $gender_symbols = [
    'male'   => ( 'before' === $space_location ? ' ' : '' ) . '♂' . ( 'after' === $space_location ? ' ' : '' ),
    'female' => ( 'before' === $space_location ? ' ' : '' ) . '♀' . ( 'after' === $space_location ? ' ' : '' ),
    false    => '',
  ];

  return $gender_symbols[ $gender ];

} // Function slackemon_get_gender_symbol.

function slackemon_get_gender_pronoun( $gender ) {

  $pronouns = [
    'male'   => 'he',
    'female' => 'she',
    false    => 'it',
  ];

  return $pronouns[ $gender ];

} // Function slackemon_get_gender_pronoun.

function slackemon_appraise_ivs( $ivs, $include_emoji = true, $abbrev = false ) {

  $ivs_percentage = slackemon_get_iv_percentage( $ivs );

  if ( 100 == $ivs_percentage ) {
    $ivs_appraisal = 'Perfect IVs'   . ( $include_emoji ? ' :heart_eyes:' : '');
  } else if ( $ivs_percentage >= 80 ) {
    $ivs_appraisal = ( $abbrev ? 'Exc.' : 'Excellent' ) . ' IVs' . ( $include_emoji ? ' :tada:' : '' );
  } else if ( $ivs_percentage >= 60 ) {
    $ivs_appraisal = 'Good IVs'      . ( $include_emoji ? ' :thumbsup:' : '');
  } else if ( $ivs_percentage >= 40 ) { // Emoji for this was :wavy_dash: but need something better...
    $ivs_appraisal = ( $abbrev ? 'Avg' : 'Average' ) . ' IVs' . ( $include_emoji ? ' :wavy_dash:' : '' );
  } else if ( $ivs_percentage >= 20 ) {
    $ivs_appraisal = 'Low IVs'       . ( $include_emoji ? ' :arrow_heading_down:' : '');
  } else if ( $ivs_percentage >= 0 ) {
    $ivs_appraisal = 'Poor IVs'      . ( $include_emoji ? ' :thumbsdown:' : '');
  } else {
    $ivs_appraisal = 'Unknown IVs'   . ( $include_emoji ? ' :grey_question:' : '');
  }

  return $ivs_appraisal;

} // Function slackemon_appraise_ivs.

/**
 * Accepts a string of one or more Pokemon types, separated by a space, and turns them into emoji references,
 * optionally removing the original text, and optionally placing the emoji _after_ that original text.
 *
 * @param string $type_string    A string containing one or more space-separated types eg. 'Fire' or 'Fire Water'.
 * @param bool   $include_text   Whether or not to include the original text along with the emoji. Defaults to true.
 * @param string $emoji_position If $include_text is true, whether to place the emoji 'before' or 'after' the text.
 */
function slackemon_emojify_types( $type_string, $include_text = true, $emoji_position = 'before' ) {

  // If custom emoji are not enabled, just separate multiple types with a slash.
  if ( ! SLACKEMON_ENABLE_CUSTOM_EMOJI ) {
    return preg_replace( '/\s+/', '/', $type_string );
  }

  $type_string = preg_replace_callback(
    '|(\S*)|',
    function( $matches ) use ( $include_text, $emoji_position ) {
      if ( ! $matches[1] ) { return ''; }
      return (
        ( $include_text && 'after' === $emoji_position ? $matches[1] . ' ' : '' ) .
        ':type-' . strtolower( $matches[1] ) . ':' .
        ( $include_text && 'before' === $emoji_position ? ' ' . $matches[1] : '' )
      );
    },
    $type_string
  );

  return $type_string;

} // Function slackemon_emojify_types.

function slackemon_get_type_color( $type ) {

  $colors = [
    'bug'      => '#b3d76e',
    'dark'     => '#68487d',
    'dragon'   => '#a7132e',
    'electric' => '#ecda09',
    'fairy'    => '#fb5ed4',
    'fighting' => '#f3a32a',
    'fire'     => '#e65822',
    'flying'   => '#a1d8e6',
    'ghost'    => '#46345e',
    'grass'    => '#2ecc71',
    'ground'   => '#967b4b',
    'ice'      => '#54cdd4',
    'normal'   => '#deb19c',
    'poison'   => '#a27498',
    'psychic'  => '#8845c6',
    'rock'     => '#959ca6',
    'steel'    => '#91a7be',
    'water'    => '#1073a2',
  ];

  if ( isset( $colors[ $type ] ) ) {
    return $colors[ $type ];
  }
  
  return '';

} // Function slackemon_get_type_color.

function slackemon_get_happiness_emoji( $happiness_rate ) {

  // For reference, possible base happiness rates are 0, 35, 70, 90, 100 and 140.
  // Happiness is capped at 0 and 255.

  if ( ! $happiness_rate ) {
    $happiness_emoji = ':unamused:'; // 0%.
  } else if ( $happiness_rate <= 10 ) { // 3%.
    $happiness_emoji = ':pensive:';
  } else if ( $happiness_rate <= 30 ) { // 11%.
    $happiness_emoji = ':disappointed:';
  } else if ( $happiness_rate <= 50 ) { // 19%.
    $happiness_emoji = ':slightly_frowning_face:';
  } else if ( $happiness_rate <= 70 ) { // 27%.
    $happiness_emoji = ':thinking_face:';
  } else if ( $happiness_rate <= 95 ) { // 37%.
    $happiness_emoji = ':neutral_face:';
  } else if ( $happiness_rate <= 120 ) { // 47%.
    $happiness_emoji = ':slightly_smiling_face:';
  } else if ( $happiness_rate <= 150 ) { // 58%.
    $happiness_emoji = ':smiley:';
  } else if ( $happiness_rate <= 180 ) { // 70%.
    $happiness_emoji = ':smile:';
  } else if ( $happiness_rate <= 230 ) { // 90%.
    $happiness_emoji = ':relaxed:';
  } else if ( $happiness_rate <= 254 ) { // 99%.
    $happiness_emoji = ':sunglasses:';
  } else if ( $happiness_rate == 255 ) { // 100%.
    $happiness_emoji = ':heart_eyes:';
  } else {
    $happiness_emoji = ''; // This shouldn't happen.
  }

  return $happiness_emoji;

} // Function slackemon_get_happiness_emoji.

function slackemon_get_nature_emoji( $nature ) {

  // TODO: Try to make sure none of the below are the same as any happiness emoji.
  // TODO: These could probably be moved into data files in /etc when data.php is moved also.

  $emoji = [
    'adamant' => ':triumph:',
    'bashful' => ':blush:',
    'bold'    => ':smiling_imp:',
    'brave'   => ':sunglasses:',
    'calm'    => ':innocent:',
    'careful' => ':face_with_head_bandage:',
    'docile'  => ':hugging_face:',
    'gentle'  => ':smiley:',
    'hardy'   => ':sweat_smile:',
    'hasty'   => ':rage:',
    'jolly'   => ':smile:',
    'lax'     => ':sleeping:',
    'lonely'  => ':disappointed:',
    'impish'  => ':smirk:',
    'mild'    => ':slightly_smiling_face:',
    'modest'  => ':nerd_face:',
    'naive'   => ':yum:',
    'naughty' => ':stuck_out_tongue_winking_eye:',
    'quiet'   => ':zipper_mouth_face:',
    'quirky'  => ':upside_down_face:',
    'rash'    => ':laughing:',
    'relaxed' => ':relieved:',
    'sassy'   => ':face_with_rolling_eyes:',
    'serious' => ':neutral_face:',
    'timid'   => ':confused:',
  ];

  if ( isset( $emoji[ $nature ] ) ) {
    return $emoji[ $nature ];
  }
  
  return '';

} // Function slackemon_get_nature_emoji.

/**
 * Returns emoji representing battle challenge types.
 *
 * @param str|arr $challenge_type A string identifying the system name of the challenge type, eg. 'friendly' or
 *                                'level'. Since challenge types can come with parameters, if you specify it as an
 *                                array the first key (0) will be used.
 * @return str An emoji, or an empty string if no emoji is available.
 */
function slackemon_get_battle_challenge_emoji( $challenge_type ) {

  // If a challenge_type array has been provided, the first key will contain the string we need.
  if ( is_array( $challenge_type ) ) {
    $challenge_type = $challenge_type[0];
  }

  $emoji = [
    'normal'         => ':facepunch:',
    'friendly'       => ':heart:',
    'fast'           => ':fast_forward:',
    'double-xp'      => ':part_alternation_mark:',
    'legendary'      => ':star2:',
    'type-inverse'   => ':left_right_arrow:',
    'unlimited-swap' => ':arrows_counterclockwise:',
    'random-team'    => ':grey_question:', // TODO: This could get confusing in battle due to the unseen Pokemon emoji.
    'no-pp'          => ':rage:',          // TODO: Is this the most appropriate?
    'level'          => '',                // TODO: Need to determine.
  ];

  if ( isset( $emoji[ $challenge_type ] ) ) {
    return $emoji[ $challenge_type ];
  }
  
  return '';

} // Function slackemon_get_battle_challenge_emoji.

function slackemon_get_color_as_hex( $color_name ) {
  return slackemon_rgb2hex( COLORS_BY_NAME[ $color_name ] );
}

function slackemon_paginate( $objects, $page_number, $items_per_page = 5 ) {

  $total_objects = count( $objects );
  $total_pages   = ceil( $total_objects / $items_per_page );

  // Default to last page if we have requested a page that no longer exists (eg. due to transferring).
  $page_number = $page_number <= $total_pages ? $page_number : $total_pages;

  $paginated = array_chunk( $objects, $items_per_page )[ $page_number - 1 ];

  return $paginated;

} // Function slackemon_paginate.

function slackemon_get_pagination_attachment(
  $objects, $page_number, $action_name, $items_per_page = 5, $action_value_prefix = ''
) {

  $total_objects = count( $objects );
  $total_pages   = ceil( $total_objects / $items_per_page );

  if ( $total_pages > 1 ) {

    // Partial pagination mode, or all pages?
    if ( $total_pages > 5 ) {
      $pagination_actions = [
        [
          'name'  => $action_name,
          'text'  => ':rewind:',
          'type'  => 'button',
          'value' => $action_value_prefix . '1',
          'style' => 1 == $page_number ? 'primary' : '',
        ], (
          $total_pages == $page_number ?
          [
            'name'  => $action_name,
            'text'  => $page_number - 2,
            'type'  => 'button',
            'value' => $action_value_prefix . ( $page_number - 2 ),
          ] :
          ''
        ), (
          1 == $page_number ?
          '' : [
            'name'  => $action_name,
            'text'  => $page_number - 1,
            'type'  => 'button',
            'value' => $action_value_prefix . ( $page_number - 1 ),
          ]
        ), [
          'name'  => $action_name,
          'text'  => $page_number,
          'type'  => 'button',
          'value' => $action_value_prefix . $page_number,
          'style' => 'primary',
        ], (
          $total_pages == $page_number ?
          '' : [
            'name'  => $action_name,
            'text'  => $page_number + 1,
            'type'  => 'button',
            'value' => $action_value_prefix . ( $page_number + 1 ),
          ]
        ), (
          1 == $page_number ?
          [
            'name'  => $action_name,
            'text'  => $page_number + 2,
            'type'  => 'button',
            'value' => $action_value_prefix . ( $page_number + 2 ),
          ] : ''
        ), [
          'name'  => $action_name,
          'text'  => ':fast_forward:',
          'type'  => 'button',
          'value' => $action_value_prefix . $total_pages,
          'style' => $total_pages == $page_number ? 'primary' : '',
        ],
      ];
    } else {
      $pagination_actions = [];
      for ( $i = 1; $i <= $total_pages; $i++ ) {
        $pagination_actions[] = [
          'name'  => $action_name,
          'text'  => $i,
          'type'  => 'button',
          'value' => $action_value_prefix . $i,
          'style' => $i == $page_number ? 'primary' : '',
        ];
      }
    }

    $attachment = [
      'fallback' => 'Page',
      'color'    => '#333333',
      'actions'  => $pagination_actions,
      'footer'   => (
        'Viewing ' . ( $items_per_page * ( $page_number - 1 ) + 1 ) . ' - ' .
        min( $total_objects, $items_per_page * $page_number ) .
        ' of ' . $total_objects
      ),
    ];

    return $attachment;

  } // If more than 1 page.

  return [];

}  // Function slackemon_get_pagination_attachment.

// Make a system string (generally, Pokemon names, region names, etc.) human-readable.
function slackemon_readable( $string, $display_gender = true, $abbrev = false ) {

  // Male & Female Pokemon species, eg. Nidoran.
  $string = preg_replace( [ '/-m$/', '/-f$/' ], $display_gender ? [ '♂', '♀' ] : '', $string );

  // General word capitalisation & hyphen removal.
  $string = ucwords( strtolower( str_replace( '-', ' ', $string ) ) );

  // Ensure Roman-numeral generation numbers are capitalised correctly.
  $string = preg_replace_callback( '/\b(I|V)(i|v){1,2}\b/', function( $matches ) {
    return strtoupper( $matches[0] );
  }, $string );

  // Ensure some common two-character abbreviations are capitalised correctly.
  $string = preg_replace([
    '/\bHp\b/',
    '/\bPp\b/',
    '/\bXp\b/',
    '/\bTm(\d|s)/',
    '/\bHm(\d|s)/',
  ], [
    'HP',
    'PP',
    'XP',
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

} // function slackemon_readable.

/**
 * Gets a Pokemon evolution chain, highlighting the current Pokemon.
 *
 * @param int  $pokedex_id
 * @param bool $return_value_if_none
 */
function slackemon_get_evolution_chain( $pokedex_id, $return_value_if_none = false ) {

  $output = '';

  $pokemon_data   = slackemon_get_pokemon_data( $pokedex_id );
  $species_data   = json_decode( slackemon_get_cached_url( $pokemon_data->species->url ) );
  $evolution_data = json_decode( slackemon_get_cached_url( $species_data->evolution_chain->url ) );

  $chain        = $evolution_data->chain;
  $pokemon_name = $pokemon_data->name;

  $output = slackemon_build_evolution_chain( $chain, $pokemon_name );

  if ( false === strpos( $output, '>' ) ) {
    return $return_value_if_none; // Pokemon does not evolve.
  }

  return $output;

} // Function slackemon_get_evolution_chain.

function slackemon_build_evolution_chain( $chain, $pokemon_name ) {

  $output = '';

  if ( $chain->species->name === $pokemon_name ) {
    $output .= '_';
  }

  $output .= slackemon_readable( $chain->species->name );

  if ( $chain->species->name === $pokemon_name ) {
    $output .= '_';
  }

  if ( 1 === count( $chain->evolves_to ) ) {

    $output .= ' > ' . slackemon_build_evolution_chain( $chain->evolves_to[0], $pokemon_name );

  } else if ( count( $chain->evolves_to ) > 1 ) {

    $output .= ' > (';
    $branched_chain = '';

    foreach ( $chain->evolves_to as $evolution ) {

      if ( $branched_chain ) {
        $branched_chain .= ', ';
      }

      $branched_chain .= slackemon_build_evolution_chain( $evolution, $pokemon_name );

    }

    $output .= $branched_chain . ')';

  }

  return $output;

} // Function slackemon_build_evolution_chain.

function slackemon_get_loading_indicator( $user_id = USER_ID, $include_fallback = true ) {
  
  if ( SLACKEMON_ENABLE_CUSTOM_EMOJI && 'desktop' === slackemon_get_player_menu_mode( $user_id ) ) {
    return ':loading:';
  }

  if ( $include_fallback ) {
    return 'Loading...';
  }

  return '';

} // Function slackemon_get_loading_indicator.

// The end!
