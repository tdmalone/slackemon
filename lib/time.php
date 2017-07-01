<?php
/**
 * Time related helper functions for Slackemon.
 *
 * @package Slackemon
 */

/**
 * Gets a string representating a time relatively from now, eg. '2 minutes ago' or 'in 3 days'.
 *
 * @param int|string $ts   The timestamp to compare with now. Also accepts a string in any format that strtotime() does.
 * @param bool       $long Whether long names should be used for time units eg. minute rather than min. Defaults to true.
 * @return string
 * @author Matthew Temple, Tim Malone
 * @link https://gist.github.com/mattytemple/3804571
 */
function slackemon_get_relative_time( $ts, $long = true ) {

    if ( ! ctype_digit( $ts ) ) {
        $ts = strtotime( $ts );
    }

    $diff = time() - $ts;

    if ( 0 === $diff ) {
        return 'now';
    } else if ( $diff > 0 ) {

        // Past date

        $day_diff = floor( $diff / DAY_IN_SECONDS );

        if ( 0 == $day_diff ) {
            if ( $diff < MINUTE_IN_SECONDS ) {
                return 'just now';
            } else if ( $diff < MINUTE_IN_SECONDS * 2 ) {
                return '1 min' . ( $long ? 'ute' : '' ) . ' ago';
            } else  if ( $diff < HOUR_IN_SECONDS ) {
                return floor( $diff / 60 ) . ' min' . ( $long ? 'ute' : '' ) . 's ago';
            } else if ( $diff < HOUR_IN_SECONDS * 2 ) {
                return '1 h' . ( $long ? 'ou' : '' ) . 'r ago';
            } else if ( $diff < DAY_IN_SECONDS ) {
                return floor( $diff / 3600 ) . ' h' . ( $long ? 'ou' : '' ) . 'rs ago';
            }
        } else if ( 1 == $day_diff ) {

            // TODO: 'Yesterday' is not always true here, this needs expanding
            return 'at ' . date( 'ga', $ts ) . ' yesterday';

        } else if ( $day_diff < 7 ) {
            return $day_diff . ' days ago';
        } else if ( $day_diff < 30 ) {
            $weeks = ceil( $day_diff / 7 );
            return $weeks . ' week' . ( 1 == $weeks ? '' : 's' ) . ' ago';
        } else if ( $day_diff < 60 ) {
            return 'last month';
        }

        // Fallback displays the month and year if nothing else matched
        // TODO: Implement month/year relativity instead of this fallback
        return date( 'F Y', $ts );

    } else {

        // Future date

        $diff = abs( $diff );
        $day_diff = floor( $diff / DAY_IN_SECONDS );

        if ( 0 == $day_diff ) {
            if ( $diff < 120) {
                return 'in a min' . ( $long ? 'ute' : '' );
            } else if ( $diff < 3600 ) {
                return 'in ' . floor( $diff / 60 ) . ' min' . ( $long ? 'ute' : '' ) . 's';
            } else if ( $diff < 7200 ) {
                return 'in an h' . ( $long ? 'ou' : '' ) . 'r';
            } else if ( $diff < DAY_IN_SECONDS ) {
                return 'in ' . floor( $diff / 3600 ) . ' h' . ( $long ? 'ou' : '' ) . 'rs';
            }
        } else if ( 1 == $day_diff ) {
            return 'tomorrow';
        } else if ( $day_diff < 4 ) {
            return date( 'l', $ts );
        } else if ( $day_diff < 7 + ( 7 - date( 'w' ) ) ) {
            return 'next week';
        } else if ( ceil( $day_diff / 7 ) < 4 ) {
            return 'in ' . ceil( $day_diff / 7 ) . ' weeks';
        } else if ( date( 'n', $ts ) == date( 'n' ) + 1 ) {
            return 'next month';
        }

        // Fallback displays the month and year if nothing else matched
        // TODO: Implement month/year relativity instead of this fallback
        return date( 'F Y', $ts );

    }

} // Function get_relative_time

// The end!
