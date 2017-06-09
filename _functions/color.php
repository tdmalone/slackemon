<?php

// TM 21/01/2017
// Color related function helpers

// HT: https://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
function rgb2hex( $rgb ) {

    $hex = "#";
    $hex .= str_pad( dechex( $rgb[0] ), 2, '0', STR_PAD_LEFT );
    $hex .= str_pad( dechex( $rgb[1] ), 2, '0', STR_PAD_LEFT );
    $hex .= str_pad( dechex( $rgb[2] ), 2, '0', STR_PAD_LEFT );

    return $hex; // Returns the hex value including the number sign (#)

}

define( 'COLORS_BY_NAME', [
    'black'  => [   0,   0,   0 ],
    'blue'   => [  48, 136, 240 ],
    'brown'  => [ 176, 112,  48 ],
    'gray'   => [ 128, 128, 128 ],
    'green'  => [  64, 184, 104 ],
    'pink'   => [ 255, 192, 203 ],
    'purple' => [ 168, 104, 192 ],
    'red'    => [ 240,  88, 104 ],
    'white'  => [ 240, 240, 240 ],
    'yellow' => [ 240, 208,  72 ],
]);

// The end!
