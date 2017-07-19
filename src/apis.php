<?php
/**
 * External API specific functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_pokemon_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_pokemon_data;

  if ( isset( $_cached_slackemon_pokemon_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_pokemon_data[ $pokedex_number ];
  }

  $api_base     = 'http://pokeapi.co/api/v2'; // WARNING: All endpoints on this API must end in a forward slash.
  $pokemon_url  = $api_base . '/pokemon/' . $pokedex_number . '/';
  $pokemon_data = slackemon_get_cached_url( $pokemon_url, [ 'json' => true ] );

  if ( ! $pokemon_data ) {
    slackemon_error_log( 'Error retrieving Pokemon data: ' . $pokemon_url );
    return false;
  }

  $_cached_slackemon_pokemon_data[ $pokedex_number ] = $pokemon_data;

  return $pokemon_data;

} // Function slackemon_get_pokemon_data.

function slackemon_get_pokemon_species_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_species_data;

  if ( isset( $_cached_slackemon_species_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_species_data[ $pokedex_number ];
  }

  $api_base     = 'http://pokeapi.co/api/v2'; // WARNING: All endpoints on this API must end in a forward slash.
  $species_url  = $api_base . '/pokemon-species/' . $pokedex_number . '/';
  $species_data = slackemon_get_cached_url( $species_url, [ 'json' => true ] );

  if ( ! $species_data ) {
    slackemon_error_log( 'Error retrieving Pokemon species data: ' . $species_url );
    return false;
  }

  $_cached_slackemon_species_data[ $pokedex_number ] = $species_data;

  return $species_data;

} // Function slackemon_get_pokemon_species_data.

function slackemon_get_pokemon_evolution_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_evolution_data;

  if ( isset( $_cached_slackemon_evolution_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_evolution_data[ $pokedex_number ];
  }

  $species_data = slackemon_get_pokemon_species_data( $pokedex_number );

  if ( ! $species_data ) {
    return false;
  }

  $evolution_data = slackemon_get_cached_url( $species_data->evolution_chain->url, [ 'json' => true ] );

  if ( ! $evolution_data ) {
    slackemon_error_log( 'Error retrieving Pokemon evolution data: ' . $species_data->evolution_chain->url );
    return false;
  }

  $_cached_slackemon_evolution_data[ $pokedex_number ] = $evolution_data;

  return $evolution_data;

} // Function slackemon_get_pokemon_species_data.

function slackemon_get_pokemon_growth_rate_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_growth_rate_data;

  if ( isset( $_cached_slackemon_growth_rate_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_growth_rate_data[ $pokedex_number ];
  }

  $species_data = slackemon_get_pokemon_species_data( $pokedex_number );

  if ( ! $species_data ) {
    return false;
  }

  $growth_rate_data = slackemon_get_cached_url( $species_data->growth_rate->url, [ 'json' => true ] );

  if ( ! $growth_rate_data ) {
    slackemon_error_log( 'Error retrieving Pokemon growth rate data: ' . $species_data->growth_rate->url );
    return false;
  }

  $_cached_slackemon_growth_rate_data[ $pokedex_number ] = $growth_rate_data;

  return $growth_rate_data;

} // Function slackemon_get_pokemon_growth_rate_data.

function slackemon_get_move_data( $move_name_or_id ) {
  global $_cached_slackemon_move_data;

  if ( isset( $_cached_slackemon_move_data[ $move_name_or_id ] ) ) {
    return $_cached_slackemon_move_data[ $move_name_or_id ];
  }

  $move_url  = 'http://pokeapi.co/api/v2/move/' . $move_name_or_id . '/';
  $move_data = slackemon_get_cached_url( $move_url, [ 'json' => true ] );

  if ( ! $move_data ) {
    slackemon_error_log( 'Error retrieving move data for move ' . $move_name_or_id );
    return false;
  }

  // Supplementary move data
  $supplementary_move_data = slackemon_get_supplementary_move_data();
  if ( isset( $supplementary_move_data->{ $move_data->name } ) ) {
    $move_data->{ 'supplementary-data' } = $supplementary_move_data->{ $move_data->name };
    if ( isset( $move_data->{ 'supplementary-data' }->overrides ) ) {
      foreach ( $move_data->{ 'supplementary-data' }->overrides as $key => $value ) {
        if ( is_object( $value ) ) {
          foreach ( $value as $inner_key => $inner_value ) {
            $move_data->{ $key }->{ $inner_key } = $inner_value;
          }
        } else if ( is_array( $value ) ) {
          foreach ( $value as $inner_key => $inner_value ) {
            $move_data->{ $key }[ $inner_key ] = $inner_value;
          }
        } else {
          $move_data->{ $key } = $value;
        }
      }
    }
  }

  $_cached_slackemon_move_data[ $move_name_or_id ] = $move_data;

  return $move_data;

} // Function slackemon_get_move_data.

function slackemon_get_supplementary_move_data() {
  global $_cached_slackemon_supplementary_move_data;

  if ( isset( $_cached_slackemon_supplementary_move_data ) ) {
    return $_cached_slackemon_supplementary_move_data;
  }

  $supplementary_move_filename = __DIR__ . '/../etc/moves.json';
  $supplementary_move_data     = json_decode( file_get_contents( $supplementary_move_filename ) );

  $_cached_slackemon_supplementary_move_data = $supplementary_move_data;

  return $supplementary_move_data;

} // Function slackemon_get_supplementary_move_data.

function slackemon_get_item_data( $item_name_or_id ) {
  global $_cached_slackemon_item_data;

  if ( isset( $_cached_slackemon_item_data[ $item_name_or_id ] ) ) {
    return $_cached_slackemon_item_data[ $item_name_or_id ];
  }

  $item_url  = 'http://pokeapi.co/api/v2/item/' . $item_name_or_id . '/';
  $item_data = slackemon_get_cached_url( $item_url, [ 'json' => true ] );

  if ( ! $item_data ) {
    slackemon_error_log( 'Error retrieving item data for item ' . $item_name_or_id . '.' );
    return false;
  }

  if ( isset( $item_data->detail ) && 'Not found.' === $item_data->detail ) {
    return false;
  }

  // Potential item category rewrite.
  if ( isset( $item_data->category->name ) ) {
    $item_data->original_category_name = $item_data->category->name;
    $item_data->category->name = slackemon_rewrite_item_category( $item_data->category->name, $item_data );
  }

  // Supplementary item data.
  $supplementary_item_data = slackemon_get_supplementary_item_data();
  if ( isset( $supplementary_item_data->{ $item_data->name } ) ) {

    $item_data->{ 'supplementary-data' } = $supplementary_item_data->{ $item_data->name };

    if ( isset( $item_data->{ 'supplementary-data' }->overrides ) ) {

      foreach ( $item_data->{ 'supplementary-data' }->overrides as $key => $value ) {
        $item_data->{ $key } = $value;
      }
    }
  }

  $_cached_slackemon_item_data[ $item_name_or_id ] = $item_data;

  return $item_data;

} // Function slackemon_get_item_data.

function slackemon_get_supplementary_item_data() {
  global $_cached_slackemon_supplementary_item_data;

  if ( isset( $_cached_slackemon_supplementary_item_data ) ) {
    return $_cached_slackemon_supplementary_item_data;
  }

  $supplementary_item_filename = __DIR__ . '/../etc/items.json';
  $supplementary_item_data     = json_decode( file_get_contents( $supplementary_item_filename ) );

  $_cached_slackemon_supplementary_item_data = $supplementary_item_data;

  return $supplementary_item_data;

} // Function slackemon_get_supplementary_item_data.

function slackemon_update_triggering_attachment( $new_attachment, $action, $send = true ) {

  if ( is_string( $new_attachment ) ) {
    $new_attachment = [ 'text' => $new_attachment ];
  }

  $message = [
    'text'        => $action->original_message->text,
    'attachments' => $action->original_message->attachments,
  ];

  $original_attachment = $message['attachments'][ $action->attachment_id - 1 ];

  // Pass thru the color from the old attachment, if applicable and if none is set.
  // There is probably not a lot more we'd want to automatically pass through.
  if ( is_array( $new_attachment ) ) {
    if ( isset( $original_attachment->color ) && ! isset( $new_attachment['color'] ) ) {
      $new_attachment['color'] = $original_attachment->color;
    }
  } else {
    if ( isset( $original_attachment->color ) && ! isset( $new_attachment->color ) ) {
      $new_attachment->color = $original_attachment->color;
    }
  }

  $message['attachments'][ $action->attachment_id - 1 ] = $new_attachment;

  if ( $send ) {
    return slackemon_do_action_response( $message );
  } else {
    return $message;
  }

} // Function slackemon_update_attachment.

function slackemon_get_flavour_text( $object, $clean_up = true ) {

  $flavour_text = '';

  if ( ! isset( $object->flavor_text_entries ) ) {
    return $flavour_text;
  }

  foreach ( $object->flavor_text_entries as $_entry ) {
    if ( 'en' === $_entry->language->name ) {
      $flavour_text = isset( $_entry->text ) ? $_entry->text : $_entry->flavor_text;
      break;
    }
  }

  if ( $clean_up ) {
    $flavour_text = str_replace( "\n", ' ', $flavour_text );
  }

  return $flavour_text;

} // Function slackemon_get_flavour_text.

function slackemon_get_effect_text( $object, $clean_up = true ) {

  $effect_text = '';

  if ( ! isset( $object->effect_entries ) ) {
    return $effect_text;
  }

  foreach ( $object->effect_entries as $_entry ) {
    if ( 'en' === $_entry->language->name ) {
      $effect_text = $_entry->effect;
      break;
    }
  }

  if ( $clean_up ) {
    $effect_text = str_replace( "\n", ' ', $effect_text );
  }

  return $effect_text;

} // Function slackemon_get_effect_text.

// The end!
