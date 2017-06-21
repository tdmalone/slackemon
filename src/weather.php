<?php
/**
 * Weather related functions for Slackemon.
 *
 * @package Slackemon
 */

function slackemon_get_weather( $cache_options = [] ) {

  // Documentation at http://openweathermap.org/current

  if (
    ! defined( 'SLACKEMON_OPENWEATHERMAP_KEY' ) ||
    ! SLACKEMON_OPENWEATHERMAP_KEY ||
    'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' === SLACKEMON_OPENWEATHERMAP_KEY
  ) {
    return false;
  }

  if ( ! isset( $cache_options['expiry_age'] ) ) {
    $cache_options['expiry_age'] = HOUR_IN_SECONDS * 1;
  }

  $coords = explode( ',', SLACKEMON_WEATHER_LAT_LON );

  $endpoint = 'http://api.openweathermap.org/data/2.5/weather';
  $params = [
    'lat' => $coords[0],
    'lon' => $coords[1],
    'units' => 'metric',
    'appid' => SLACKEMON_OPENWEATHERMAP_KEY,
  ];

  $weather = slackemon_get_cached_url( $endpoint . '?' . http_build_query( $params ), $cache_options );

  return json_decode( $weather );

} // Function slackemon_get_weather

function slackemon_get_weather_condition( $cache_options = [] ) {

  // Documentation: http://openweathermap.org/weather-conditions

  $weather = slackemon_get_weather( $cache_options );

  if ( ! isset( $weather->weather[0]->id ) ) {
    return '';
  }

  $weather_condition  = '';
  $condition_priority = 0;

  // Loop through each available weather condition, normalising the variations to our simple one-word responses
  // We only handle one condition at a time, so we prioritise the more crazy conditions :)
  foreach ( $weather->weather as $condition ) {

    $weather_code = $condition->id;

    switch ( substr( $weather_code, 0, 1 ) ) {

      case 2: // Group 2xx: Thunderstorm
        if ( $condition_priority < 7 ) {
          $weather_condition  = 'Stormy';
          $condition_priority = 7;
        }
      break;

      case 3: // Group 3xx: Drizzle
      case 5: // Group 5xx: Rain
        if ( $condition_priority < 6 ) {
          $weather_condition = 'Raining';
          $condition_priority = 6;
        }
      break;

      case 6: // Group 6xx: Snow
        if ( $condition_priority < 8 ) {
          $weather_condition = 'Snowing';
          $condition_priority = 8;
        }
      break;

      case 7: // Group 7xx: Atmospheric conditions
        if ( $weather_condition ) { $weather_condition .= ', '; }
        $weather_condition .= $condition->main;
        $condition_priority = 0;
      break;

      case 8: // Group 8xx: Clouds (800 Clear / 801+ = Clouds)
        if ( 800 == $weather_code ) {
          if ( slackemon_is_daytime() ) {
            if ( $condition_priority < 3 ) {
              $weather_condition  = 'Sunny';
              $condition_priority = 3;
            }
          } else {
            if ( $condition_priority < 1 ) {
              $weather_condition  = 'Clear';
              $condition_priority = 1;
            }
          }
        } else {
          if ( $condition_priority < 2 ) {
            $weather_condition  = 'Cloudy';
            $condition_priority = 2;
          }
        }
      break;

      case 9: // Group 9xx: Extreme Weather, or from 951, 'Additional' (mostly Windy)
        switch ( $weather_code ) {

          case 900: // Continue to 902...
          case 901: // Continue to 902...
          case 902:
            if ( $condition_priority < 7 ) {
              $weather_condition  = 'Stormy';
              $condition_priority = 7;
            }
          break;

          case 903:
            if ( $condition_priority < 4 ) {
              $weather_condition  = 'Cold';
              $condition_priority = 4;
            }
          break;

          case 904:
            if ( $condition_priority < 4 ) {
              $weather_condition  = 'Hot';
              $condition_priority = 4;
            }
          break;

          case 905:
            if ( $condition_priority < 5 ) {
              $weather_condition  = 'Windy';
              $condition_priority = 5;
            }
          break;

          case 906:
            if ( $condition_priority < 9 ) {
              $weather_condition  = 'Hailing';
              $condition_priority = 9;
            }
          break;

          default:
            if ( $weather_code >= 952 && $weather_code <= 962 ) {
              if ( $condition_priority < 5 ) {
                $weather_condition  = 'Windy';
                $condition_priority = 5;
              }
            }
          break;
        }
      break;

    } // Switch weather id
  } // Foreach condition

  return $weather_condition;

} // Function slackemon_get_weather_condition

function slackemon_is_daytime( $cache_options = [] ) {

  $weather = slackemon_get_weather( $cache_options );
  $now = time();

  // Best-guess mode (7am - 7pm) if we don't have up-to-date weather data
  if (
    ! isset( $weather->sys->sunrise ) ||
    ! isset( $weather->sys->sunset ) ||
    $weather->sys->sunrise < $now - DAY_IN_SECONDS ||
    $weather->sys->sunset  < $now - DAY_IN_SECONDS ||
    $weather->sys->sunrise > $now + DAY_IN_SECONDS ||
    $weather->sys->sunset  > $now + DAY_IN_SECONDS
  ) {
    if ( date( 'H' ) >= 7 && date( 'H' ) <= 18 ) {
      return true;
    } else {
      return false;
    }
  }

  $sunrise = $weather->sys->sunrise;
  $sunset  = $weather->sys->sunset;

  if ( $now > $sunrise && $now < $sunset ) {
    return true;
  } else {
    return false;
  }

} // Function slackemon_is_daytime

// The end!
