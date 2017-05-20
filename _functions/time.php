<?php

// TM 30/01/2017
// Time related helper functions for Slackemon

// HT: https://gist.github.com/mattytemple/3804571
function get_relative_time( $ts, $long = true ) {
    if(!ctype_digit($ts)) {
        $ts = strtotime($ts);
    }
    $diff = time() - $ts;
    if($diff == 0) {
        return 'now';
    } elseif($diff > 0) {
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 60) return 'just now';
            if($diff < 120) return '1 min' . ( $long ? 'ute' : '' ) . ' ago';
            if($diff < 3600) return floor($diff / 60) . ' min' . ( $long ? 'ute' : '' ) . 's ago';
            if($diff < 7200) return '1 h' . ( $long ? 'ou' : '' ) . 'r ago';
            if($diff < 86400) return floor($diff / 3600) . ' h' . ( $long ? 'ou' : '' ) . 'rs ago';
        }
        if($day_diff == 1) { return 'at ' . date( 'ga', $ts ) . ' yesterday'; }
        if($day_diff < 7) { return $day_diff . ' days ago'; }
        if($day_diff < 31) { $weeks = ceil($day_diff / 7); return $weeks . ' week' . ( 1 == $weeks ? '' : 's' ) . ' ago'; }
        if($day_diff < 60) { return 'last month'; }
        return date('F Y', $ts);
    } else {
        $diff = abs($diff);
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 120) { return 'in a min' . ( $long ? 'ute' : '' ); }
            if($diff < 3600) { return 'in ' . floor($diff / 60) . ' min' . ( $long ? 'ute' : '' ) . 's'; }
            if($diff < 7200) { return 'in an h' . ( $long ? 'ou' : '' ) . 'r'; }
            if($diff < 86400) { return 'in ' . floor($diff / 3600) . ' h' . ( $long ? 'ou' : '' ) . 'rs'; }
        }
        if($day_diff == 1) { return 'tomorrow'; }
        if($day_diff < 4) { return date('l', $ts); }
        if($day_diff < 7 + (7 - date('w'))) { return 'next week'; }
        if(ceil($day_diff / 7) < 4) { return 'in ' . ceil($day_diff / 7) . ' weeks'; }
        if(date('n', $ts) == date('n') + 1) { return 'next month'; }
        return date('F Y', $ts);
    }
}

/**
 * Convert time into decimal time.
 *
 * @param string $time The time to convert
 * @link http://www.hashbangcode.com/blog/converting-and-decimal-time-php
 * @return integer The time as a decimal value.
 */
function time_to_decimal( $time ) {

    $timeArr = explode( ':', $time );

    $timeArr[0] = isset( $timeArr[0] ) ? $timeArr[0] : 0;
    $timeArr[1] = isset( $timeArr[1] ) ? $timeArr[1] : 0;
    $timeArr[2] = isset( $timeArr[2] ) ? $timeArr[2] : 0;

    $decTime = ( $timeArr[0] * 60 ) + ( $timeArr[1] ) + ( $timeArr[2] / 60 );
 
    return $decTime / 60;
}

/**
 * Convert decimal time into time in the format hh:mm:ss
 *
 * @param integer The time as a decimal value.
 * @link http://www.hashbangcode.com/blog/converting-and-decimal-time-php
 * @return string $time The converted time value.
 */
function decimal_to_time( $decimal ) {

    $hours = $decimal < 0 ? ceil( $decimal ) : floor( $decimal );
    $minutes = $decimal - (int) $decimal;
    $minutes = round( $minutes * 60 );
 
    return str_pad( $hours, 2, '0', STR_PAD_LEFT ) . ":" . str_pad( abs( $minutes ), 2, '0', STR_PAD_LEFT );

}

// The end!
