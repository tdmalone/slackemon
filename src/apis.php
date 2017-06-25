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

  $api_base = 'http://pokeapi.co/api/v2'; // WARNING: All endpoints on this API must end in a forward slash
  $pokemon_url = $api_base . '/pokemon/' . $pokedex_number . '/';
  $pokemon_data = json_decode( slackemon_get_cached_url( $pokemon_url ) );

  $_cached_slackemon_pokemon_data[ $pokedex_number ] = $pokemon_data;

  return $pokemon_data;

} // Function slackemon_get_pokemon_data

function slackemon_get_pokemon_species_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_species_data;

  if ( isset( $_cached_slackemon_species_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_species_data[ $pokedex_number ];
  }

  $api_base = 'http://pokeapi.co/api/v2'; // WARNING: All endpoints on this API must end in a forward slash
  $species_url = $api_base . '/pokemon-species/' . $pokedex_number . '/';
  $species_data = json_decode( slackemon_get_cached_url( $species_url ) );

  $_cached_slackemon_species_data[ $pokedex_number ] = $species_data;

  return $species_data;

} // Function slackemon_get_pokemon_species_data

function slackemon_get_pokemon_evolution_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_evolution_data;

  if ( isset( $_cached_slackemon_evolution_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_evolution_data[ $pokedex_number ];
  }

  $species_data = slackemon_get_pokemon_species_data( $pokedex_number );
  $evolution_data = json_decode( slackemon_get_cached_url( $species_data->evolution_chain->url ) );

  $_cached_slackemon_evolution_data[ $pokedex_number ] = $evolution_data;

  return $evolution_data;

} // Function slackemon_get_pokemon_species_data

function slackemon_get_pokemon_growth_rate_data( $pokedex_number ) {
  global $data_folder, $_cached_slackemon_growth_rate_data;

  if ( isset( $_cached_slackemon_growth_rate_data[ $pokedex_number ] ) ) {
    return $_cached_slackemon_growth_rate_data[ $pokedex_number ];
  }

  $species_data = slackemon_get_pokemon_species_data( $pokedex_number );
  $growth_rate_data = json_decode( slackemon_get_cached_url( $species_data->growth_rate->url ) );

  $_cached_slackemon_growth_rate_data[ $pokedex_number ] = $growth_rate_data;

  return $growth_rate_data;

} // Function slackemon_get_pokemon_growth_rate_data

function slackemon_get_move_data( $move_name_or_id ) {
  global $_cached_slackemon_move_data;

  if ( isset( $_cached_slackemon_move_data[ $move_name_or_id ] ) ) {
    return $_cached_slackemon_move_data[ $move_name_or_id ];
  }

  $move_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/move/' . $move_name_or_id . '/' ) );

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

} // Function slackemon_get_move_data

function slackemon_get_supplementary_move_data() {
  global $_cached_slackemon_supplementary_move_data;

  if ( isset( $_cached_slackemon_supplementary_move_data ) ) {
    return $_cached_slackemon_supplementary_move_data;
  }

  $supplementary_move_filename = __DIR__ . '/../etc/moves.json';
  $supplementary_move_data = json_decode( file_get_contents( $supplementary_move_filename ) );

  $_cached_slackemon_supplementary_move_data = $supplementary_move_data;

  return $supplementary_move_data;

} // Function slackemon_get_supplementary_move_data 

function slackemon_get_item_data( $item_name_or_id ) {
  global $_cached_slackemon_item_data;

  if ( isset( $_cached_slackemon_item_data[ $item_name_or_id ] ) ) {
    return $_cached_slackemon_item_data[ $item_name_or_id ];
  }

  $item_data = json_decode( slackemon_get_cached_url( 'http://pokeapi.co/api/v2/item/' . $item_name_or_id . '/' ) );
  
  if ( $item_data ) {

    // Potential item category rewrite
    if ( isset( $item_data->category->name ) ) {
      $item_data->original_category_name = $item_data->category->name;
      $item_data->category->name = slackemon_rewrite_item_category( $item_data->category->name, $item_data );
    }

    // Supplementary item data
    $supplementary_item_data = slackemon_get_supplementary_item_data();
    if ( isset( $supplementary_item_data->{ $item_data->name } ) ) {
      $item_data->{ 'supplementary-data' } = $supplementary_item_data->{ $item_data->name };
      if ( isset( $item_data->{ 'supplementary-data' }->overrides ) ) {
        foreach ( $item_data->{ 'supplementary-data' }->overrides as $key => $value ) {
          $item_data->{ $key } = $value;
        }
      }
    }

  }

  $_cached_slackemon_item_data[ $item_name_or_id ] = $item_data;

  return $item_data;

} // Function slackemon_get_item_data

function slackemon_get_supplementary_item_data() {
  global $_cached_slackemon_supplementary_item_data;

  if ( isset( $_cached_slackemon_supplementary_item_data ) ) {
    return $_cached_slackemon_supplementary_item_data;
  }

  $supplementary_item_filename = __DIR__ . '/../etc/items.json';
  $supplementary_item_data = json_decode( file_get_contents( $supplementary_item_filename ) );

  $_cached_slackemon_supplementary_item_data = $supplementary_item_data;

  return $supplementary_item_data;

} // Function slackemon_get_supplementary_item_data 

function slackemon_update_triggering_attachment( $new_attachment, $action, $send = true ) {

  if ( is_string( $new_attachment ) ) {
    $new_attachment = [ 'text' => $new_attachment ];
  }

  $message = [];
  $message['text'] = $action->original_message->text;
  $message['attachments'] = $action->original_message->attachments;

  $original_attachment = $message['attachments'][ $action->attachment_id - 1 ];

  // Pass thru the color from the old attachment, if applicable and if none is set
  // There is probably not a lot more we'd want to automatically pass through
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

} // Function slackemon_update_attachment

function slackemon_do_action_response( $message ) {
  global $data_folder;

  if ( is_string( $message ) ) {
    $message = [ 'text' => $message ];
  }

  // Unless we say otherwise, we always want to replace the original message
  if ( ! isset( $message['replace_original' ] ) ) {
    $message['replace_original'] = true;
  }

  // Unless we say otherwise, let Slack assume we've included mrkdwn almost everywhere in our attachments
  // Also, if we've set action buttons, make sure we set the callback ID as well
  if ( isset( $message['attachments'] ) ) {
    foreach ( $message['attachments'] as $key => $attachment ) {

      if ( is_array( $attachment ) ) {

        if ( ! isset( $attachment['mrkdwn_in'] ) ) {
          $message['attachments'][ $key ]['mrkdwn_in'] = [ 'pretext', 'text', 'fields', 'footer' ];
        }

        if ( isset( $attachment['actions'] ) ) {
          $message['attachments'][ $key ]['callback_id'] = SLACKEMON_ACTION_CALLBACK_ID;
        }

      } else {

        if ( ! isset( $attachment->mrkdwn_in ) ) {
          $message['attachments'][ $key ]->mrkdwn_in = [ 'pretext', 'text', 'fields', 'footer' ];
        }

        if ( isset( $attachment->actions ) ) {
          $message['attachments'][ $key ]->callback_id = SLACKEMON_ACTION_CALLBACK_ID;
        }

      }

    } // Foreach attachment
  } // If message has attachments

  $result = send2slack( $message );

  if ( 'development' === APP_ENV ) {
    file_put_contents( $data_folder . '/last-action-response', $result );
  }

  return $result;

} // Function slackemon_do_action_response

function slackemon_get_flavour_text( $object ) {

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

  return $flavour_text;

} // Function slackemon_get_flavour_text

function slackemon_get_effect_text( $object ) {

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

  return $effect_text;

} // Function slackemon_get_effect_text

// The end!
