<?php

// TM 21/01/2017
// Color related function helpers

// ************************* from HEX to RGB, HSL and XY ************************* //

// HT: https://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
function hex2rgb( $hex ) {
    $hex = str_replace( '#', '', $hex );

    if ( 3 === strlen( $hex ) ) {
        $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
        $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
        $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
    } else {
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
    }

    return [ $r, $g, $b ];
}

function hex2hsl( $hex ) {
	return rgb2hsl ( hex2rgb( $hex) );
}

function hex2xy( $hex ) {
	return rgb2xy( hex2rgb( $hex ) );
}

// ************************* from RGB to HEX, HSL and XY ************************* //

// HT: https://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
function rgb2hex( $rgb ) {

    $hex = "#";
    $hex .= str_pad( dechex( $rgb[0] ), 2, '0', STR_PAD_LEFT );
    $hex .= str_pad( dechex( $rgb[1] ), 2, '0', STR_PAD_LEFT );
    $hex .= str_pad( dechex( $rgb[2] ), 2, '0', STR_PAD_LEFT );

    return $hex; // Returns the hex value including the number sign (#)
}

// HT: https://raw.githubusercontent.com/mpbzh/PHP-RGB-HSL-Converter/master/rgb_hsl_converter.inc.php
// Copyright Michael Burri, https://github.com/mpbzh
// License GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
function rgb2hsl( $rgb ) {

	// Convert color values to value between 0 and 1
	$r = $rgb[0] / 255;
	$g = $rgb[1] / 255;
	$b = $rgb[2] / 255;

	// Determine lowest & highest value and chroma
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$chroma = $max - $min;
    
	// Calculate Luminosity
	$l = ( $max + $min ) / 2;

	// If chroma is 0, the given color is grey
	// therefore hue and saturation are set to 0
	if ( 0 == $chroma ) {
		$h = 0;
		$s = 0;
    
	// Otherwise calculate hue and saturation
	// Check http://en.wikipedia.org/wiki/HSL_and_HSV for details
	} else {
		switch ( $max ) {
			case $r:
				$h_ = fmod( ( ( $g - $b ) / $chroma ), 6 );
				if ( $h_ < 0 ) {
					$h_ = ( 6 - fmod( abs( $h_ ), 6 ) ); // Bugfix: fmod() returns wrong values for negative numbers
				}
			break;

			case $g:
				$h_ = ( $b - $r ) / $chroma + 2;
			break;

			case $b:
				$h_ = ( $r - $g ) / $chroma + 4;
			break;
		}

		$h = $h_ / 6;
		$s = 1 - abs( 2 * $l - 1 );
	}

	return [ $h * 360, $s * 100, $l * 100 ];
}

// HT: https://developers.meethue.com/content/rgb-hue-color0-65535-javascript-language
function rgb2xy( $rgb ) {

	$red = $rgb[0];
	$green = $rgb[1];
	$blue = $rgb[2];

    // Gamma correction
    $red = ( $red > 0.04045 ) ? pow( ( $red + 0.055) / ( 1.0 + 0.055 ), 2.4 ) : ( $red / 12.92 );
    $green = ( $green > 0.04045 ) ? pow( ( $green + 0.055) / ( 1.0 + 0.055 ), 2.4 ) : ( $green / 12.92 );
    $blue = ( $blue > 0.04045 ) ? pow( ( $blue + 0.055) / ( 1.0 + 0.055 ), 2.4 ) : ( $blue / 12.92 );

    // Apply wide gamut conversion D65
    $X = ( $red * 0.664511 ) + ( $green * 0.154324 ) + ( $blue * 0.162028 );
    $Y = ( $red * 0.283881 ) + ( $green * 0.668433 ) + ( $blue * 0.047685 );
    $Z = ( $red * 0.000088 ) + ( $green * 0.072310 ) + ( $blue * 0.986039 );

    // Avoid division by 0
    $together = $X + $Y + $Z;

    $fx = 0 != $together ? $X / $together : $X;
    $fy = 0 != $together ? $Y / $together : $Y;

    if ( is_nan( $fx ) ) {
        $fx = 0;
    }

    if ( is_nan( $fy ) ) {
        $fy = 0;
    }

    $x = round( $fx, 4 );
    $y = round( $fy, 4 );
    return [ $x, $y ];
}

// ************************* from HSL to HEX, RGB and XY ************************* //

function hsl2hex( $hsl ) {
	return rgb2hex( hsl2rgb( $hsl ) );
}

// HT: https://raw.githubusercontent.com/mpbzh/PHP-RGB-HSL-Converter/master/rgb_hsl_converter.inc.php
// Copyright Michael Burri, https://github.com/mpbzh
// License GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
function hsl2rgb( $hsl ) {

	$h = $hsl[0] / 360;
	$s = $hsl[1] / 100;
	$l = $hsl[2] / 100;

	// If saturation is 0, the given color is grey and only
	// lightness is relevant
	if ( 0 == $s ) {
		$rgb = [ $l, $l, $l ];

	// Otherwise calculate r, g, b according to hue
	// Check http://en.wikipedia.org/wiki/HSL_and_HSV#From_HSL for details
	} else {
		$chroma	= ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$h_		= $h * 6;
		$x		= $chroma * ( 1 - abs( ( fmod( $h_, 2 ) ) - 1 ) ); // Note: fmod because % (modulo) returns int value!!
		$m		= $l - round( $chroma / 2, 10 ); // Bugfix for strange float behaviour (e.g. $l=0.17 and $s=1)

		if ( $h_ >= 0 && $h_ < 1 ) $rgb = [ ( $chroma + $m ), ( $x + $m ), $m ];
		else if ( $h_ >= 1 && $h_ < 2 ) $rgb = [ ( $x + $m ), ( $chroma + $m), $m ];
		else if ( $h_ >= 2 && $h_ < 3 ) $rgb = [ $m, ( $chroma + $m), ($x + $m ) ];
		else if ( $h_ >= 3 && $h_ < 4 ) $rgb = [ $m, ( $x + $m ), ( $chroma + $m ) ];
		else if ( $h_ >= 4 && $h_ < 5 ) $rgb = [ ( $x + $m ), $m, ( $chroma + $m ) ];
		else if ( $h_ >= 5 && $h_ < 6 ) $rgb = [ ( $chroma + $m ), $m, ( $x + $m ) ]; 
	}

	return [ $rgb[0] * 255, $rgb[1] * 255, $rgb[2] * 255 ];
}

function hsl2xy( $hsl ) {
	return rgb2xy( hsl2rgb( $hsl ) );
}

// ************************* from XY to HEX, RGB and HSL ************************* //

function xy2hex( $xy ) {
	return rgb2hex( xy2rgb( $xy ) );
}

// HT: http://stackoverflow.com/a/22918909/1982136
function xy2rgb( $xy ) {

	$x = $xy[0];
	$y = $xy[1];
	$bri = 255;

	$z = 1 - $x - $y;

	$Y = $bri / 255;
	$X = ( 0 != $y ? $Y / $y : $Y ) * $x;
	$Z = ( 0 != $y ? $Y / $y : $Y ) * $z;

	$r = ( $X * 1.612 ) - ( $Y * 0.203 ) - ( $Z * 0.302 );
	$g = ( -$X * 0.509 ) + ( $Y * 1.412 ) + ( $Z * 0.066 );
	$b = ( $X * 0.026 ) - ( $Y * 0.072 ) + ( $Z * 0.962 );

	$r = $r <= 0.0031308 ? 12.92 * $r : ( 1 + 0.055 ) * pow( $r, ( 1.0 / 2.4 ) ) - 0.055;
	$g = $g <= 0.0031308 ? 12.92 * $g : ( 1 + 0.055 ) * pow( $g, ( 1.0 / 2.4 ) ) - 0.055;
	$b = $b <= 0.0031308 ? 12.92 * $b : ( 1 + 0.055 ) * pow( $b, ( 1.0 / 2.4 ) ) - 0.055;

	$maxValue = max( $r, $g, $b );
	$r = $r / $maxValue;
	$g = $g / $maxValue;
	$b = $b / $maxValue;

	$r = $r * 255;
	$g = $g * 255;
	$b = $b * 255;

	if ( $r < 0 ) { $r = 255; }
	if ( $g < 0 ) { $g = 255; }
	if ( $b < 0 ) { $b = 255; }

	return [ $r, $g, $b ];
}

function xy2hsl( $xy ) {
	return rgb2hsl( xy2rgb( $xy ) );
}

// ************************* Color fading ************************* //

function fade_to_color( $hsl_from, $hsl_to, $ratio ) {
	$hsl = [
		( ( $hsl_to[0] - $hsl_from[0] ) * $ratio ) + $hsl_from[0],
		( ( $hsl_to[1] - $hsl_from[1] ) * $ratio ) + $hsl_from[1],
		( ( $hsl_to[2] - $hsl_from[2] ) * $ratio ) + $hsl_from[2],
	];
	return $hsl;
}

// ************************* Color distance ************************* //

// HT: http://stackoverflow.com/questions/4485229/rgb-to-closest-predefined-color

function get_color_name( $rgb ) {

	global $colors_by_name;

	$closest = $colors_by_name[ array_keys( $colors_by_name )[0] ];
	$mindist = get_color_distance( $rgb, $colors_by_name[ array_keys( $colors_by_name )[0] ] );
	$ncolors = sizeof($colors_by_name);

	for ( $i = 1; $i < $ncolors; $i++ ) {
	    $currdist = get_color_distance( $rgb, $colors_by_name[ array_keys( $colors_by_name )[ $i ] ] );
	    if ( $currdist < $mindist ) {
	      $mindist = $currdist;
	      $closest = array_keys( $colors_by_name )[ $i ];
	    }
	}

	return $closest;
}

function get_color_name2( $rgb ) {

	global $colors_by_name;

	$r = $rgb[0];
	$g = $rgb[0];
	$b = $rgb[0];

	$differences = [];

	foreach ( $colors_by_name as $value ) {
		$thisDifference = sqrt( pow( $r - $value[0], 2 ) + pow( $g - $value[1], 2 ) + pow( $b - $value[2], 2 ) );
		array_push( $differences, $thisDifference );
	}

	$smallest = min( $differences );
	$key = array_search( $smallest, $differences );

	return array_keys( $colors_by_name )[ $key ];

}

function get_color_distance( $col1, $col2 ) {
	$delta_r = $col1[0] - $col2[0];
	$delta_g = $col1[1] - $col2[1];
	$delta_b = $col1[2] - $col2[2];
	return $delta_r * $delta_r + $delta_g * $delta_g + $delta_b * $delta_b;
}

// ************************* Color naming data ************************* //

$colors_by_name = [

	'acidgreen' => [ 176, 191, 26 ],
	'aero' => [ 124, 185, 232 ],
	'aeroblue' => [ 201, 255, 229 ],
	'africanviolet' => [ 178, 132, 190 ],
	'airforceblue' => [ 93, 138, 168 ],
	'airsuperiorityblue' => [ 114, 160, 193 ],
	'alabamacrimson' => [ 175, 0, 42 ],
	'aliceblue' => [ 240, 248, 255 ],
	'alizarincrimson' => [ 227, 38, 54 ],
	'alloyorange' => [ 196, 98, 16 ],
	'almond' => [ 239, 222, 205 ],
	'amaranth' => [ 229, 43, 80 ],
	'amaranthdeeppurple' => [ 171, 39, 79 ],
	'amaranthpink' => [ 241, 156, 187 ],
	'amaranthpurple' => [ 171, 39, 79 ],
	'amaranthred' => [ 211, 33, 45 ],
	'amazon' => [ 59, 122, 87 ],
	'amber' => [ 255, 191, 0 ],
	'americanrose' => [ 255, 3, 62 ],
	'amethyst' => [ 153, 102, 204 ],
	'androidgreen' => [ 164, 198, 57 ],
	'antiflashwhite' => [ 242, 243, 244 ],
	'antiquebrass' => [ 205, 149, 117 ],
	'antiquebronze' => [ 102, 93, 30 ],
	'antiquefuchsia' => [ 145, 92, 131 ],
	'antiqueruby' => [ 132, 27, 45 ],
	'antiquewhite' => [ 250, 235, 215 ],
	'ao' => [ 0, 128, 0 ],
	'applegreen' => [ 141, 182, 0 ],
	'apricot' => [ 251, 206, 177 ],
	'aqua' => [ 0, 255, 255 ],
	'aquamarine' => [ 127, 255, 212 ],
	'arcticlime' => [ 208, 255, 20 ],
	'armygreen' => [ 75, 83, 32 ],
	'arsenic' => [ 59, 68, 75 ],
	'artichoke' => [ 143, 151, 121 ],
	'arylideyellow' => [ 233, 214, 107 ],
	'ashgrey' => [ 178, 190, 181 ],
	'asparagus' => [ 135, 169, 107 ],
	'atomictangerine' => [ 255, 153, 102 ],
	'auburn' => [ 165, 42, 42 ],
	'aureolin' => [ 253, 238, 0 ],
	'aurometalsaurus' => [ 110, 127, 128 ],
	'avocado' => [ 86, 130, 3 ],
	'azure' => [ 0, 127, 255 ],
	'azuremist' => [ 240, 255, 255 ],
	'azureishwhite' => [ 219, 233, 244 ],
	'babyblue' => [ 137, 207, 240 ],
	'babyblueeyes' => [ 161, 202, 241 ],
	'babypink' => [ 244, 194, 194 ],
	'babypowder' => [ 254, 254, 250 ],
	'bakermillerpink' => [ 255, 145, 175 ],
	'ballblue' => [ 33, 171, 205 ],
	'bananamania' => [ 250, 231, 181 ],
	'bananayellow' => [ 255, 225, 53 ],
	'bangladeshgreen' => [ 0, 106, 78 ],
	'barbiepink' => [ 224, 33, 138 ],
	'barnred' => [ 124, 10, 2 ],
	'battleshipgrey' => [ 132, 132, 130 ],
	'bazaar' => [ 152, 119, 123 ],
	'beaublue' => [ 188, 212, 230 ],
	'beaver' => [ 159, 129, 112 ],
	'beige' => [ 245, 245, 220 ],
	'bdazzledblue' => [ 46, 88, 148 ],
	'bigdipo’ruby' => [ 156, 37, 66 ],
	'bisque' => [ 255, 228, 196 ],
	'bistre' => [ 61, 43, 31 ],
	'bistrebrown' => [ 150, 113, 23 ],
	'bitterlemon' => [ 202, 224, 13 ],
	'bitterlime' => [ 191, 255, 0 ],
	'bittersweet' => [ 254, 111, 94 ],
	'bittersweetshimmer' => [ 191, 79, 81 ],
	'black' => [ 0, 0, 0 ],
	'blackbean' => [ 61, 12, 2 ],
	'blackleatherjacket' => [ 37, 53, 41 ],
	'blackolive' => [ 59, 60, 54 ],
	'blanchedalmond' => [ 255, 235, 205 ],
	'blastoffbronze' => [ 165, 113, 100 ],
	'bleudefrance' => [ 49, 140, 231 ],
	'blizzardblue' => [ 172, 229, 238 ],
	'blond' => [ 250, 240, 190 ],
	'blue' => [ 48, 136, 240 ], // Was [ 0, 0, 255 ] but the new one looks nicer
	'bluebell' => [ 162, 162, 208 ],
	'bluegray' => [ 102, 153, 204 ],
	'bluegreen' => [ 13, 152, 186 ],
	'bluelagoon' => [ 172, 229, 238 ],
	'bluemagentaviolet' => [ 85, 53, 146 ],
	'bluesapphire' => [ 18, 97, 128 ],
	'blueviolet' => [ 138, 43, 226 ],
	'blueyonder' => [ 80, 114, 167 ],
	'blueberry' => [ 79, 134, 247 ],
	'bluebonnet' => [ 28, 28, 240 ],
	'blush' => [ 222, 93, 131 ],
	'bole' => [ 121, 68, 59 ],
	'bondiblue' => [ 0, 149, 182 ],
	'bone' => [ 227, 218, 201 ],
	'bostonuniversityred' => [ 204, 0, 0 ],
	'bottlegreen' => [ 0, 106, 78 ],
	'boysenberry' => [ 135, 50, 96 ],
	'brandeisblue' => [ 0, 112, 255 ],
	'brass' => [ 181, 166, 66 ],
	'brickred' => [ 203, 65, 84 ],
	'brightcerulean' => [ 29, 172, 214 ],
	'brightgreen' => [ 102, 255, 0 ],
	'brightlavender' => [ 191, 148, 228 ],
	'brightlilac' => [ 216, 145, 239 ],
	'brightmaroon' => [ 195, 33, 72 ],
	'brightnavyblue' => [ 25, 116, 210 ],
	'brightpink' => [ 255, 0, 127 ],
	'brightturquoise' => [ 8, 232, 222 ],
	'brightube' => [ 209, 159, 232 ],
	'brilliantazure' => [ 51, 153, 255 ],
	'brilliantlavender' => [ 244, 187, 255 ],
	'brilliantrose' => [ 255, 85, 163 ],
	'brinkpink' => [ 251, 96, 127 ],
	'britishracinggreen' => [ 0, 66, 37 ],
	'bronze' => [ 205, 127, 50 ],
	'bronzeyellow' => [ 115, 112, 0 ],
	'brown' => [ 176, 112, 48 ], // Was [ 150, 75, 0 ], but the new one potentially looks nicer
	'brownnose' => [ 107, 68, 35 ],
	'brownyellow' => [ 204, 153, 102 ],
	'brunswickgreen' => [ 27, 77, 62 ],
	'bubblegum' => [ 255, 193, 204 ],
	'bubbles' => [ 231, 254, 255 ],
	'buff' => [ 240, 220, 130 ],
	'budgreen' => [ 123, 182, 97 ],
	'bulgarianrose' => [ 72, 6, 7 ],
	'burgundy' => [ 128, 0, 32 ],
	'burlywood' => [ 222, 184, 135 ],
	'burntorange' => [ 204, 85, 0 ],
	'burntsienna' => [ 233, 116, 81 ],
	'burntumber' => [ 138, 51, 36 ],
	'byzantine' => [ 189, 51, 164 ],
	'byzantium' => [ 112, 41, 99 ],
	'cadet' => [ 83, 104, 114 ],
	'cadetblue' => [ 95, 158, 160 ],
	'cadetgrey' => [ 145, 163, 176 ],
	'cadmiumgreen' => [ 0, 107, 60 ],
	'cadmiumorange' => [ 237, 135, 45 ],
	'cadmiumred' => [ 227, 0, 34 ],
	'cadmiumyellow' => [ 255, 246, 0 ],
	'caféaulait' => [ 166, 123, 91 ],
	'cafénoir' => [ 75, 54, 33 ],
	'calpolygreen' => [ 30, 77, 43 ],
	'cambridgeblue' => [ 163, 193, 173 ],
	'camel' => [ 193, 154, 107 ],
	'cameopink' => [ 239, 187, 204 ],
	'camouflagegreen' => [ 120, 134, 107 ],
	'canaryyellow' => [ 255, 239, 0 ],
	'candyapplered' => [ 255, 8, 0 ],
	'candypink' => [ 228, 113, 122 ],
	'capri' => [ 0, 191, 255 ],
	'caputmortuum' => [ 89, 39, 32 ],
	'cardinal' => [ 196, 30, 58 ],
	'caribbeangreen' => [ 0, 204, 153 ],
	'carmine' => [ 150, 0, 24 ],
	'carminepink' => [ 235, 76, 66 ],
	'carminered' => [ 255, 0, 56 ],
	'carnationpink' => [ 255, 166, 201 ],
	'carnelian' => [ 179, 27, 27 ],
	'carolinablue' => [ 86, 160, 211 ],
	'carrotorange' => [ 237, 145, 33 ],
	'castletongreen' => [ 0, 86, 63 ],
	'catalinablue' => [ 6, 42, 120 ],
	'catawba' => [ 112, 54, 66 ],
	'cedarchest' => [ 201, 90, 73 ],
	'ceil' => [ 146, 161, 207 ],
	'celadon' => [ 172, 225, 175 ],
	'celadonblue' => [ 0, 123, 167 ],
	'celadongreen' => [ 47, 132, 124 ],
	'celeste' => [ 178, 255, 255 ],
	'celestialblue' => [ 73, 151, 208 ],
	'cerise' => [ 222, 49, 99 ],
	'cerisepink' => [ 236, 59, 131 ],
	'cerulean' => [ 0, 123, 167 ],
	'ceruleanblue' => [ 42, 82, 190 ],
	'ceruleanfrost' => [ 109, 155, 195 ],
	'cgblue' => [ 0, 122, 165 ],
	'cgred' => [ 224, 60, 49 ],
	'chamoisee' => [ 160, 120, 90 ],
	'champagne' => [ 247, 231, 206 ],
	'charcoal' => [ 54, 69, 79 ],
	'charlestongreen' => [ 35, 43, 43 ],
	'charmpink' => [ 230, 143, 172 ],
	'chartreuse' => [ 223, 255, 0 ],
	'cherry' => [ 222, 49, 99 ],
	'cherryblossompink' => [ 255, 183, 197 ],
	'chestnut' => [ 149, 69, 53 ],
	'chinapink' => [ 222, 111, 161 ],
	'chinarose' => [ 168, 81, 110 ],
	'chinesered' => [ 170, 56, 30 ],
	'chineseviolet' => [ 133, 96, 136 ],
	'chocolate' => [ 123, 63, 0 ],
	'chromeyellow' => [ 255, 167, 0 ],
	'cinereous' => [ 152, 129, 123 ],
	'cinnabar' => [ 227, 66, 52 ],
	'cinnamon' => [ 210, 105, 30 ],
	'citrine' => [ 228, 208, 10 ],
	'citron' => [ 159, 169, 31 ],
	'claret' => [ 127, 23, 52 ],
	'classicrose' => [ 251, 204, 231 ],
	'cobaltblue' => [ 0, 71, 171 ],
	'cocoabrown' => [ 210, 105, 30 ],
	'coconut' => [ 150, 90, 62 ],
	'coffee' => [ 111, 78, 55 ],
	'columbiablue' => [ 196, 216, 226 ],
	'congopink' => [ 248, 131, 121 ],
	'coolblack' => [ 0, 46, 99 ],
	'coolgrey' => [ 140, 146, 172 ],
	'copper' => [ 184, 115, 51 ],
	'copperpenny' => [ 173, 111, 105 ],
	'copperred' => [ 203, 109, 81 ],
	'copperrose' => [ 153, 102, 102 ],
	'coquelicot' => [ 255, 56, 0 ],
	'coral' => [ 255, 127, 80 ],
	'coralpink' => [ 248, 131, 121 ],
	'coralred' => [ 255, 64, 64 ],
	'cordovan' => [ 137, 63, 69 ],
	'corn' => [ 251, 236, 93 ],
	'cornellred' => [ 179, 27, 27 ],
	'cornflowerblue' => [ 100, 149, 237 ],
	'cornsilk' => [ 255, 248, 220 ],
	'cosmiclatte' => [ 255, 248, 231 ],
	'coyotebrown' => [ 129, 97, 62 ],
	'cottoncandy' => [ 255, 188, 217 ],
	'cream' => [ 255, 253, 208 ],
	'crimson' => [ 220, 20, 60 ],
	'crimsonglory' => [ 190, 0, 50 ],
	'crimsonred' => [ 153, 0, 0 ],
	'cyan' => [ 0, 255, 255 ],
	'cyanazure' => [ 78, 130, 180 ],
	'cyanblueazure' => [ 70, 130, 191 ],
	'cyancobaltblue' => [ 40, 88, 156 ],
	'cyancornflowerblue' => [ 24, 139, 194 ],
	'cybergrape' => [ 88, 66, 124 ],
	'cyberyellow' => [ 255, 211, 0 ],
	'daffodil' => [ 255, 255, 49 ],
	'dandelion' => [ 240, 225, 48 ],
	'darkblue' => [ 0, 0, 139 ],
	'darkbluegray' => [ 102, 102, 153 ],
	'darkbrown' => [ 101, 67, 33 ],
	'darkbrowntangelo' => [ 136, 101, 78 ],
	'darkbyzantium' => [ 93, 57, 84 ],
	'darkcandyapplered' => [ 164, 0, 0 ],
	'darkcerulean' => [ 8, 69, 126 ],
	'darkchestnut' => [ 152, 105, 96 ],
	'darkcoral' => [ 205, 91, 69 ],
	'darkcyan' => [ 0, 139, 139 ],
	'darkelectricblue' => [ 83, 104, 120 ],
	'darkgoldenrod' => [ 184, 134, 11 ],
	'darkgray' => [ 169, 169, 169 ],
	'darkgreen' => [ 1, 50, 32 ],
	'darkgunmetal' => [ 0, 100, 0 ],
	'darkimperialblue' => [ 110, 110, 249 ],
	'darkjunglegreen' => [ 26, 36, 33 ],
	'darkkhaki' => [ 189, 183, 107 ],
	'darklava' => [ 72, 60, 50 ],
	'darklavender' => [ 115, 79, 150 ],
	'darkliver' => [ 83, 75, 79 ],
	'darkmagenta' => [ 139, 0, 139 ],
	'darkmediumgray' => [ 169, 169, 169 ],
	'darkmidnightblue' => [ 0, 51, 102 ],
	'darkmossgreen' => [ 74, 93, 35 ],
	'darkolivegreen' => [ 85, 107, 47 ],
	'darkorange' => [ 255, 140, 0 ],
	'darkorchid' => [ 153, 50, 204 ],
	'darkpastelblue' => [ 119, 158, 203 ],
	'darkpastelgreen' => [ 3, 192, 60 ],
	'darkpastelpurple' => [ 150, 111, 214 ],
	'darkpastelred' => [ 194, 59, 34 ],
	'darkpink' => [ 231, 84, 128 ],
	'darkpowderblue' => [ 0, 51, 153 ],
	'darkpuce' => [ 79, 58, 60 ],
	'darkpurple' => [ 48, 25, 52 ],
	'darkraspberry' => [ 135, 38, 87 ],
	'darkred' => [ 139, 0, 0 ],
	'darksalmon' => [ 233, 150, 122 ],
	'darkscarlet' => [ 86, 3, 25 ],
	'darkseagreen' => [ 143, 188, 143 ],
	'darksienna' => [ 60, 20, 20 ],
	'darkskyblue' => [ 140, 190, 214 ],
	'darkslateblue' => [ 72, 61, 139 ],
	'darkslategray' => [ 47, 79, 79 ],
	'darkspringgreen' => [ 23, 114, 69 ],
	'darktan' => [ 145, 129, 81 ],
	'darktangerine' => [ 255, 168, 18 ],
	'darktaupe' => [ 72, 60, 50 ],
	'darkterracotta' => [ 204, 78, 92 ],
	'darkturquoise' => [ 0, 206, 209 ],
	'darkvanilla' => [ 209, 190, 168 ],
	'darkviolet' => [ 148, 0, 211 ],
	'darkyellow' => [ 155, 135, 12 ],
	'dartmouthgreen' => [ 0, 112, 60 ],
	'davysgrey' => [ 85, 85, 85 ],
	'debianred' => [ 215, 10, 83 ],
	'deepaquamarine' => [ 64, 130, 109 ],
	'deepcarmine' => [ 169, 32, 62 ],
	'deepcarminepink' => [ 239, 48, 56 ],
	'deepcarrotorange' => [ 233, 105, 44 ],
	'deepcerise' => [ 218, 50, 135 ],
	'deepchampagne' => [ 250, 214, 165 ],
	'deepchestnut' => [ 185, 78, 72 ],
	'deepcoffee' => [ 112, 66, 65 ],
	'deepfuchsia' => [ 193, 84, 193 ],
	'deepgreen' => [ 5, 102, 8 ],
	'deepjunglegreen' => [ 0, 75, 73 ],
	'deepkoamaru' => [ 51, 51, 102 ],
	'deeplemon' => [ 245, 199, 26 ],
	'deeplilac' => [ 153, 85, 187 ],
	'deepmagenta' => [ 204, 0, 204 ],
	'deepmaroon' => [ 130, 0, 0 ],
	'deepmauve' => [ 212, 115, 212 ],
	'deepmossgreen' => [ 53, 94, 59 ],
	'deeppeach' => [ 255, 203, 164 ],
	'deeppink' => [ 255, 20, 147 ],
	'deeppuce' => [ 169, 92, 104 ],
	'deepred' => [ 133, 1, 1 ],
	'deepruby' => [ 132, 63, 91 ],
	'deepsaffron' => [ 255, 153, 51 ],
	'deepskyblue' => [ 0, 191, 255 ],
	'deepspacesparkle' => [ 74, 100, 108 ],
	'deepspringbud' => [ 85, 107, 47 ],
	'deeptaupe' => [ 126, 94, 96 ],
	'deeptuscanred' => [ 102, 66, 77 ],
	'deepviolet' => [ 51, 0, 102 ],
	'deer' => [ 186, 135, 89 ],
	'denim' => [ 21, 96, 189 ],
	'desaturatedcyan' => [ 102, 153, 153 ],
	'desert' => [ 193, 154, 107 ],
	'desertsand' => [ 237, 201, 175 ],
	'desire' => [ 234, 60, 83 ],
	'diamond' => [ 185, 242, 255 ],
	'dimgray' => [ 105, 105, 105 ],
	'dirt' => [ 155, 118, 83 ],
	'dodgerblue' => [ 30, 144, 255 ],
	'dogwoodrose' => [ 215, 24, 104 ],
	'dollarbill' => [ 133, 187, 101 ],
	'donkeybrown' => [ 102, 76, 40 ],
	'drab' => [ 150, 113, 23 ],
	'dukeblue' => [ 0, 0, 156 ],
	'duststorm' => [ 229, 204, 201 ],
	'dutchwhite' => [ 239, 223, 187 ],
	'earthyellow' => [ 225, 169, 95 ],
	'ebony' => [ 85, 93, 80 ],
	'ecru' => [ 194, 178, 128 ],
	'eerieblack' => [ 27, 27, 27 ],
	'eggplant' => [ 97, 64, 81 ],
	'eggshell' => [ 240, 234, 214 ],
	'egyptianblue' => [ 16, 52, 166 ],
	'electricblue' => [ 125, 249, 255 ],
	'electriccrimson' => [ 255, 0, 63 ],
	'electriccyan' => [ 0, 255, 255 ],
	'electricgreen' => [ 0, 255, 0 ],
	'electricindigo' => [ 111, 0, 255 ],
	'electriclavender' => [ 244, 187, 255 ],
	'electriclime' => [ 204, 255, 0 ],
	'electricpurple' => [ 191, 0, 255 ],
	'electricultramarine' => [ 63, 0, 255 ],
	'electricviolet' => [ 143, 0, 255 ],
	'electricyellow' => [ 255, 255, 51 ],
	'emerald' => [ 80, 200, 120 ],
	'eminence' => [ 108, 48, 130 ],
	'englishgreen' => [ 27, 77, 62 ],
	'englishlavender' => [ 180, 131, 149 ],
	'englishred' => [ 171, 75, 82 ],
	'englishviolet' => [ 86, 60, 92 ],
	'etonblue' => [ 150, 200, 162 ],
	'eucalyptus' => [ 68, 215, 168 ],
	'fallow' => [ 193, 154, 107 ],
	'falured' => [ 128, 24, 24 ],
	'fandango' => [ 181, 51, 137 ],
	'fandangopink' => [ 222, 82, 133 ],
	'fashionfuchsia' => [ 244, 0, 161 ],
	'fawn' => [ 229, 170, 112 ],
	'feldgrau' => [ 77, 93, 83 ],
	'feldspar' => [ 253, 213, 177 ],
	'ferngreen' => [ 79, 121, 66 ],
	'ferrarired' => [ 255, 40, 0 ],
	'fielddrab' => [ 108, 84, 30 ],
	'firebrick' => [ 178, 34, 34 ],
	'fireenginered' => [ 206, 32, 41 ],
	'flame' => [ 226, 88, 34 ],
	'flamingopink' => [ 252, 142, 172 ],
	'flattery' => [ 107, 68, 35 ],
	'flavescent' => [ 247, 233, 142 ],
	'flax' => [ 238, 220, 130 ],
	'flirt' => [ 162, 0, 109 ],
	'floralwhite' => [ 255, 250, 240 ],
	'fluorescentorange' => [ 255, 191, 0 ],
	'fluorescentpink' => [ 255, 20, 147 ],
	'fluorescentyellow' => [ 204, 255, 0 ],
	'folly' => [ 255, 0, 79 ],
	'forestgreen' => [ 1, 68, 33 ],
	'frenchbeige' => [ 166, 123, 91 ],
	'frenchbistre' => [ 133, 109, 77 ],
	'frenchblue' => [ 0, 114, 187 ],
	'frenchfuchsia' => [ 253, 63, 146 ],
	'frenchlilac' => [ 134, 96, 142 ],
	'frenchlime' => [ 158, 253, 56 ],
	'frenchmauve' => [ 212, 115, 212 ],
	'frenchpink' => [ 253, 108, 158 ],
	'frenchplum' => [ 129, 20, 83 ],
	'frenchpuce' => [ 78, 22, 9 ],
	'frenchraspberry' => [ 199, 44, 72 ],
	'frenchrose' => [ 246, 74, 138 ],
	'frenchskyblue' => [ 119, 181, 254 ],
	'frenchviolet' => [ 136, 6, 206 ],
	'frenchwine' => [ 172, 30, 68 ],
	'freshair' => [ 166, 231, 255 ],
	'fuchsia' => [ 255, 0, 255 ],
	'fuchsiapink' => [ 255, 119, 255 ],
	'fuchsiapurple' => [ 204, 57, 123 ],
	'fuchsiarose' => [ 199, 67, 117 ],
	'fulvous' => [ 228, 132, 0 ],
	'fuzzywuzzy' => [ 204, 102, 102 ],
	'gainsboro' => [ 220, 220, 220 ],
	'gamboge' => [ 228, 155, 15 ],
	'gambogeorange' => [ 153, 102, 0 ],
	'genericviridian' => [ 0, 127, 102 ],
	'ghostwhite' => [ 248, 248, 255 ],
	'giantsorange' => [ 254, 90, 29 ],
	'ginger' => [ 176, 101, 0 ],
	'glaucous' => [ 96, 130, 182 ],
	'glitter' => [ 230, 232, 250 ],
	'gogreen' => [ 0, 171, 102 ],
	'gold' => [ 255, 215, 0 ],
	'goldfusion' => [ 133, 117, 78 ],
	'goldenbrown' => [ 153, 101, 21 ],
	'goldenpoppy' => [ 252, 194, 0 ],
	'goldenyellow' => [ 255, 223, 0 ],
	'goldenrod' => [ 218, 165, 32 ],
	'grannysmithapple' => [ 168, 228, 160 ],
	'grape' => [ 111, 45, 168 ],
	'gray' => [ 128, 128, 128 ],
	'grayasparagus' => [ 70, 89, 69 ],
	'grayblue' => [ 140, 146, 172 ],
	'green' => [ 64, 184, 104 ], // Was [0, 255, 0 ], but the new one looks nicer
	'greenblue' => [ 17, 100, 180 ],
	'greencyan' => [ 0, 153, 102 ],
	'greenyellow' => [ 173, 255, 47 ],
	'grizzly' => [ 136, 88, 24 ],
	'grullo' => [ 169, 154, 134 ],
	'guppiegreen' => [ 0, 255, 127 ],
	'gunmetal' => [ 42, 52, 57 ],
	'halayaube' => [ 102, 56, 84 ],
	'hanblue' => [ 68, 108, 207 ],
	'hanpurple' => [ 82, 24, 250 ],
	'hansayellow' => [ 233, 214, 107 ],
	'harlequin' => [ 63, 255, 0 ],
	'harlequingreen' => [ 70, 203, 24 ],
	'harvardcrimson' => [ 201, 0, 22 ],
	'harvestgold' => [ 218, 145, 0 ],
	'heartgold' => [ 128, 128, 0 ],
	'heliotrope' => [ 223, 115, 255 ],
	'heliotropegray' => [ 170, 152, 169 ],
	'heliotropemagenta' => [ 170, 0, 187 ],
	'hollywoodcerise' => [ 244, 0, 161 ],
	'honeydew' => [ 240, 255, 240 ],
	'honolulublue' => [ 0, 109, 176 ],
	'hookersgreen' => [ 73, 121, 107 ],
	'hotmagenta' => [ 255, 29, 206 ],
	'hotpink' => [ 255, 105, 180 ],
	'huntergreen' => [ 53, 94, 59 ],
	'iceberg' => [ 113, 166, 210 ],
	'icterine' => [ 252, 247, 94 ],
	'illuminatingemerald' => [ 49, 145, 119 ],
	'imperial' => [ 96, 47, 107 ],
	'imperialblue' => [ 0, 35, 149 ],
	'imperialpurple' => [ 102, 2, 60 ],
	'imperialred' => [ 237, 41, 57 ],
	'inchworm' => [ 178, 236, 93 ],
	'independence' => [ 76, 81, 109 ],
	'indiagreen' => [ 19, 136, 8 ],
	'indianred' => [ 205, 92, 92 ],
	'indianyellow' => [ 227, 168, 87 ],
	'indigo' => [ 75, 0, 130 ],
	'indigodye' => [ 9, 31, 146 ],
	'internationalkleinblue' => [ 0, 47, 167 ],
	'internationalorange' => [ 255, 79, 0 ],
	'iris' => [ 90, 79, 207 ],
	'irresistible' => [ 179, 68, 108 ],
	'isabelline' => [ 244, 240, 236 ],
	'islamicgreen' => [ 0, 144, 0 ],
	'italianskyblue' => [ 178, 255, 255 ],
	'ivory' => [ 255, 255, 240 ],
	'jade' => [ 0, 168, 107 ],
	'japanesecarmine' => [ 157, 41, 51 ],
	'japaneseindigo' => [ 38, 67, 72 ],
	'japaneseviolet' => [ 91, 50, 86 ],
	'jasmine' => [ 248, 222, 126 ],
	'jasper' => [ 215, 59, 62 ],
	'jazzberryjam' => [ 165, 11, 94 ],
	'jellybean' => [ 218, 97, 78 ],
	'jet' => [ 52, 52, 52 ],
	'jonquil' => [ 244, 202, 22 ],
	'jordyblue' => [ 138, 185, 241 ],
	'junebud' => [ 189, 218, 87 ],
	'junglegreen' => [ 41, 171, 135 ],
	'kellygreen' => [ 76, 187, 23 ],
	'kenyancopper' => [ 124, 28, 5 ],
	'keppel' => [ 58, 176, 158 ],
	'khaki' => [ 195, 176, 145 ],
	'kobe' => [ 136, 45, 23 ],
	'kobi' => [ 231, 159, 196 ],
	'kobicha' => [ 107, 68, 35 ],
	'kombugreen' => [ 53, 66, 48 ],
	'kucrimson' => [ 232, 0, 13 ],
	'lasallegreen' => [ 8, 120, 48 ],
	'languidlavender' => [ 214, 202, 221 ],
	'lapislazuli' => [ 38, 97, 156 ],
	'laserlemon' => [ 255, 255, 102 ],
	'laurelgreen' => [ 169, 186, 157 ],
	'lava' => [ 207, 16, 32 ],
	'lavender' => [ 181, 126, 220 ],
	'lavenderblue' => [ 204, 204, 255 ],
	'lavenderblush' => [ 255, 240, 245 ],
	'lavendergray' => [ 196, 195, 208 ],
	'lavenderindigo' => [ 148, 87, 235 ],
	'lavendermagenta' => [ 238, 130, 238 ],
	'lavendermist' => [ 230, 230, 250 ],
	'lavenderpink' => [ 251, 174, 210 ],
	'lavenderpurple' => [ 150, 123, 182 ],
	'lavenderrose' => [ 251, 160, 227 ],
	'lawngreen' => [ 124, 252, 0 ],
	'lemon' => [ 255, 247, 0 ],
	'lemonchiffon' => [ 255, 250, 205 ],
	'lemoncurry' => [ 204, 160, 29 ],
	'lemonglacier' => [ 253, 255, 0 ],
	'lemonlime' => [ 227, 255, 0 ],
	'lemonmeringue' => [ 246, 234, 190 ],
	'lemonyellow' => [ 255, 244, 79 ],
	'lenurple' => [ 186, 147, 216 ],
	'licorice' => [ 26, 17, 16 ],
	'liberty' => [ 84, 90, 167 ],
	'lightapricot' => [ 253, 213, 177 ],
	'lightblue' => [ 173, 216, 230 ],
	'lightbrilliantred' => [ 254, 46, 46 ],
	'lightbrown' => [ 181, 101, 29 ],
	'lightcarminepink' => [ 230, 103, 113 ],
	'lightcobaltblue' => [ 136, 172, 224 ],
	'lightcoral' => [ 240, 128, 128 ],
	'lightcornflowerblue' => [ 147, 204, 234 ],
	'lightcrimson' => [ 245, 105, 145 ],
	'lightcyan' => [ 224, 255, 255 ],
	'lightdeeppink' => [ 255, 92, 205 ],
	'lightfrenchbeige' => [ 200, 173, 127 ],
	'lightfuchsiapink' => [ 249, 132, 239 ],
	'lightgoldenrodyellow' => [ 250, 250, 210 ],
	'lightgray' => [ 211, 211, 211 ],
	'lightgrayishmagenta' => [ 204, 153, 204 ],
	'lightgreen' => [ 144, 238, 144 ],
	'lighthotpink' => [ 255, 179, 222 ],
	'lightkhaki' => [ 240, 230, 140 ],
	'lightmediumorchid' => [ 211, 155, 203 ],
	'lightmossgreen' => [ 173, 223, 173 ],
	'lightorchid' => [ 230, 168, 215 ],
	'lightpastelpurple' => [ 177, 156, 217 ],
	'lightpink' => [ 255, 182, 193 ],
	'lightredochre' => [ 233, 116, 81 ],
	'lightsalmon' => [ 255, 160, 122 ],
	'lightsalmonpink' => [ 255, 153, 153 ],
	'lightseagreen' => [ 32, 178, 170 ],
	'lightskyblue' => [ 135, 206, 250 ],
	'lightslategray' => [ 119, 136, 153 ],
	'lightsteelblue' => [ 176, 196, 222 ],
	'lighttaupe' => [ 179, 139, 109 ],
	'lightthulianpink' => [ 230, 143, 172 ],
	'lightyellow' => [ 255, 255, 224 ],
	'lilac' => [ 200, 162, 200 ],
	'lime' => [ 191, 255, 0 ],
	'limegreen' => [ 50, 205, 50 ],
	'limerick' => [ 157, 194, 9 ],
	'lincolngreen' => [ 25, 89, 5 ],
	'linen' => [ 250, 240, 230 ],
	'lion' => [ 193, 154, 107 ],
	'liseranpurple' => [ 222, 111, 161 ],
	'littleboyblue' => [ 108, 160, 220 ],
	'liver' => [ 103, 76, 71 ],
	'liverchestnut' => [ 152, 116, 86 ],
	'livid' => [ 102, 153, 204 ],
	'lumber' => [ 255, 228, 205 ],
	'lust' => [ 230, 32, 32 ],
	'macaroniandcheese' => [ 255, 189, 136 ],
	'magenta' => [ 255, 0, 255 ],
	'magentahaze' => [ 159, 69, 118 ],
	'magentapink' => [ 204, 51, 139 ],
	'magicmint' => [ 170, 240, 209 ],
	'magnolia' => [ 248, 244, 255 ],
	'mahogany' => [ 192, 64, 0 ],
	'maize' => [ 251, 236, 93 ],
	'majorelleblue' => [ 96, 80, 220 ],
	'malachite' => [ 11, 218, 81 ],
	'manatee' => [ 151, 154, 170 ],
	'mangotango' => [ 255, 130, 67 ],
	'mantis' => [ 116, 195, 101 ],
	'mardigras' => [ 136, 0, 133 ],
	'marigold' => [ 234, 162, 33 ],
	'maroon' => [ 176, 48, 96 ],
	'mauve' => [ 224, 176, 255 ],
	'mauvetaupe' => [ 145, 95, 109 ],
	'mauvelous' => [ 239, 152, 170 ],
	'maygreen' => [ 76, 145, 65 ],
	'mayablue' => [ 115, 194, 251 ],
	'meatbrown' => [ 229, 183, 59 ],
	'mediumaquamarine' => [ 102, 221, 170 ],
	'mediumblue' => [ 0, 0, 205 ],
	'mediumcandyapplered' => [ 226, 6, 44 ],
	'mediumcarmine' => [ 175, 64, 53 ],
	'mediumchampagne' => [ 243, 229, 171 ],
	'mediumelectricblue' => [ 3, 80, 150 ],
	'mediumjunglegreen' => [ 28, 53, 45 ],
	'mediumlavendermagenta' => [ 221, 160, 221 ],
	'mediumorchid' => [ 186, 85, 211 ],
	'mediumpersianblue' => [ 0, 103, 165 ],
	'mediumpurple' => [ 147, 112, 219 ],
	'mediumredviolet' => [ 187, 51, 133 ],
	'mediumruby' => [ 170, 64, 105 ],
	'mediumseagreen' => [ 60, 179, 113 ],
	'mediumskyblue' => [ 128, 218, 235 ],
	'mediumslateblue' => [ 123, 104, 238 ],
	'mediumspringbud' => [ 201, 220, 135 ],
	'mediumspringgreen' => [ 0, 250, 154 ],
	'mediumtaupe' => [ 103, 76, 71 ],
	'mediumturquoise' => [ 72, 209, 204 ],
	'mediumtuscanred' => [ 121, 68, 59 ],
	'mediumvermilion' => [ 217, 96, 59 ],
	'mediumvioletred' => [ 199, 21, 133 ],
	'mellowapricot' => [ 248, 184, 120 ],
	'mellowyellow' => [ 248, 222, 126 ],
	'melon' => [ 253, 188, 180 ],
	'metallicseaweed' => [ 10, 126, 140 ],
	'metallicsunburst' => [ 156, 124, 56 ],
	'mexicanpink' => [ 228, 0, 124 ],
	'midnightblue' => [ 25, 25, 112 ],
	'midnightgreen' => [ 0, 73, 83 ],
	'mikadoyellow' => [ 255, 196, 12 ],
	'mindaro' => [ 227, 249, 136 ],
	'ming' => [ 54, 116, 125 ],
	'mint' => [ 62, 180, 137 ],
	'mintcream' => [ 245, 255, 250 ],
	'mintgreen' => [ 152, 255, 152 ],
	'mistyrose' => [ 255, 228, 225 ],
	'moccasin' => [ 250, 235, 215 ],
	'modebeige' => [ 150, 113, 23 ],
	'moonstoneblue' => [ 115, 169, 194 ],
	'mordantred19' => [ 174, 12, 0 ],
	'mossgreen' => [ 138, 154, 91 ],
	'mountainmeadow' => [ 48, 186, 143 ],
	'mountbattenpink' => [ 153, 122, 141 ],
	'msugreen' => [ 24, 69, 59 ],
	'mughalgreen' => [ 48, 96, 48 ],
	'mulberry' => [ 197, 75, 140 ],
	'mustard' => [ 255, 219, 88 ],
	'myrtlegreen' => [ 49, 120, 115 ],
	'nadeshikopink' => [ 246, 173, 198 ],
	'napiergreen' => [ 42, 128, 0 ],
	'naplesyellow' => [ 250, 218, 94 ],
	'navajowhite' => [ 255, 222, 173 ],
	'navy' => [ 0, 0, 128 ],
	'navypurple' => [ 148, 87, 235 ],
	'neoncarrot' => [ 255, 163, 67 ],
	'neonfuchsia' => [ 254, 65, 100 ],
	'neongreen' => [ 57, 255, 20 ],
	'newcar' => [ 33, 79, 198 ],
	'newyorkpink' => [ 215, 131, 127 ],
	'nonphotoblue' => [ 164, 221, 237 ],
	'northtexasgreen' => [ 5, 144, 51 ],
	'nyanza' => [ 233, 255, 219 ],
	'oceanboatblue' => [ 0, 119, 190 ],
	'ochre' => [ 204, 119, 34 ],
	'officegreen' => [ 0, 128, 0 ],
	'oldburgundy' => [ 67, 48, 46 ],
	'oldgold' => [ 207, 181, 59 ],
	'oldheliotrope' => [ 86, 60, 92 ],
	'oldlace' => [ 253, 245, 230 ],
	'oldlavender' => [ 121, 104, 120 ],
	'oldmauve' => [ 103, 49, 71 ],
	'oldmossgreen' => [ 134, 126, 54 ],
	'oldrose' => [ 192, 128, 129 ],
	'oldsilver' => [ 132, 132, 130 ],
	'olive' => [ 128, 128, 0 ],
	'olivedrab' => [ 60, 52, 31 ],
	'olivine' => [ 154, 185, 115 ],
	'onyx' => [ 53, 56, 57 ],
	'operamauve' => [ 183, 132, 167 ],
	'orange' => [ 255, 127, 0 ],
	'orangepeel' => [ 255, 159, 0 ],
	'orangered' => [ 255, 69, 0 ],
	'orangeyellow' => [ 248, 213, 104 ],
	'orchid' => [ 218, 112, 214 ],
	'orchidpink' => [ 242, 189, 205 ],
	'oriolesorange' => [ 251, 79, 20 ],
	'otterbrown' => [ 101, 67, 33 ],
	'outerspace' => [ 65, 74, 76 ],
	'outrageousorange' => [ 255, 110, 74 ],
	'oxfordblue' => [ 0, 33, 71 ],
	'oucrimsonred' => [ 153, 0, 0 ],
	'pacificblue' => [ 28, 169, 201 ],
	'pakistangreen' => [ 0, 102, 0 ],
	'palatinateblue' => [ 39, 59, 226 ],
	'palatinatepurple' => [ 104, 40, 96 ],
	'paleaqua' => [ 188, 212, 230 ],
	'paleblue' => [ 175, 238, 238 ],
	'palebrown' => [ 152, 118, 84 ],
	'palecarmine' => [ 175, 64, 53 ],
	'palecerulean' => [ 155, 196, 226 ],
	'palechestnut' => [ 221, 173, 175 ],
	'palecopper' => [ 218, 138, 103 ],
	'palecornflowerblue' => [ 171, 205, 239 ],
	'palecyan' => [ 135, 211, 248 ],
	'palegold' => [ 230, 190, 138 ],
	'palegoldenrod' => [ 238, 232, 170 ],
	'palegreen' => [ 152, 251, 152 ],
	'palelavender' => [ 220, 208, 255 ],
	'palemagenta' => [ 249, 132, 229 ],
	'palemagentapink' => [ 255, 153, 204 ],
	'palepink' => [ 250, 218, 221 ],
	'paleplum' => [ 221, 160, 221 ],
	'paleredviolet' => [ 219, 112, 147 ],
	'palerobineggblue' => [ 150, 222, 209 ],
	'palesilver' => [ 201, 192, 187 ],
	'palespringbud' => [ 236, 235, 189 ],
	'paletaupe' => [ 188, 152, 126 ],
	'paleturquoise' => [ 175, 238, 238 ],
	'paleviolet' => [ 204, 153, 255 ],
	'palevioletred' => [ 219, 112, 147 ],
	'pansypurple' => [ 120, 24, 74 ],
	'paoloveronesegreen' => [ 0, 155, 125 ],
	'papayawhip' => [ 255, 239, 213 ],
	'paradisepink' => [ 230, 62, 98 ],
	'parisgreen' => [ 80, 200, 120 ],
	'pastelblue' => [ 174, 198, 207 ],
	'pastelbrown' => [ 131, 105, 83 ],
	'pastelgray' => [ 207, 207, 196 ],
	'pastelgreen' => [ 119, 221, 119 ],
	'pastelmagenta' => [ 244, 154, 194 ],
	'pastelorange' => [ 255, 179, 71 ],
	'pastelpink' => [ 222, 165, 164 ],
	'pastelpurple' => [ 179, 158, 181 ],
	'pastelred' => [ 255, 105, 97 ],
	'pastelviolet' => [ 203, 153, 201 ],
	'pastelyellow' => [ 253, 253, 150 ],
	'patriarch' => [ 128, 0, 128 ],
	'paynesgrey' => [ 83, 104, 120 ],
	'peach' => [ 255, 203, 164 ],
	'peachorange' => [ 255, 204, 153 ],
	'peachpuff' => [ 255, 218, 185 ],
	'peachyellow' => [ 250, 223, 173 ],
	'pear' => [ 209, 226, 49 ],
	'pearl' => [ 234, 224, 200 ],
	'pearlaqua' => [ 136, 216, 192 ],
	'pearlypurple' => [ 183, 104, 162 ],
	'peridot' => [ 230, 226, 0 ],
	'periwinkle' => [ 204, 204, 255 ],
	'permanentgeraniumlake' => [ 225, 44, 44 ],
	'persianblue' => [ 28, 57, 187 ],
	'persiangreen' => [ 0, 166, 147 ],
	'persianindigo' => [ 50, 18, 122 ],
	'persianorange' => [ 217, 144, 88 ],
	'persianpink' => [ 247, 127, 190 ],
	'persianplum' => [ 112, 28, 28 ],
	'persianred' => [ 204, 51, 51 ],
	'persianrose' => [ 254, 40, 162 ],
	'persimmon' => [ 236, 88, 0 ],
	'peru' => [ 205, 133, 63 ],
	'phlox' => [ 223, 0, 255 ],
	'phthaloblue' => [ 0, 15, 137 ],
	'phthalogreen' => [ 18, 53, 36 ],
	'pictonblue' => [ 69, 177, 232 ],
	'pictorialcarmine' => [ 195, 11, 78 ],
	'piggypink' => [ 253, 221, 230 ],
	'pinegreen' => [ 1, 121, 111 ],
	'pineapple' => [ 86, 60, 92 ],
	'pink' => [ 255, 192, 203 ],
	'pinkflamingo' => [ 252, 116, 253 ],
	'pinklace' => [ 255, 221, 244 ],
	'pinklavender' => [ 216, 178, 209 ],
	'pinkorange' => [ 255, 153, 102 ],
	'pinkpearl' => [ 231, 172, 207 ],
	'pinkraspberry' => [ 152, 0, 54 ],
	'pinksherbet' => [ 247, 143, 167 ],
	'pistachio' => [ 147, 197, 114 ],
	'platinum' => [ 229, 228, 226 ],
	'plum' => [ 142, 69, 133 ],
	'pompandpower' => [ 134, 96, 142 ],
	'popstar' => [ 190, 79, 98 ],
	'portlandorange' => [ 255, 90, 54 ],
	'powderblue' => [ 176, 224, 230 ],
	'princetonorange' => [ 245, 128, 37 ],
	'prune' => [ 112, 28, 28 ],
	'prussianblue' => [ 0, 49, 83 ],
	'psychedelicpurple' => [ 223, 0, 255 ],
	'puce' => [ 204, 136, 153 ],
	'pucered' => [ 114, 47, 55 ],
	'pullmanbrown' => [ 100, 65, 23 ],
	'pullmangreen' => [ 59, 51, 28 ],
	'pumpkin' => [ 255, 117, 24 ],
	'purple' => [ 168, 104, 192 ], // Was [ 128, 0, 128 ], but new one is potentially nicer
	'purpleheart' => [ 105, 53, 156 ],
	'purplemountainmajesty' => [ 150, 120, 182 ],
	'purplenavy' => [ 78, 81, 128 ],
	'purplepizzazz' => [ 254, 78, 218 ],
	'purpletaupe' => [ 80, 64, 77 ],
	'purpureus' => [ 154, 78, 174 ],
	'quartz' => [ 81, 72, 79 ],
	'queenblue' => [ 67, 107, 149 ],
	'queenpink' => [ 232, 204, 215 ],
	'quinacridonemagenta' => [ 142, 58, 89 ],
	'rackley' => [ 93, 138, 168 ],
	'radicalred' => [ 255, 53, 94 ],
	'raisinblack' => [ 36, 33, 36 ],
	'rajah' => [ 251, 171, 96 ],
	'raspberry' => [ 227, 11, 93 ],
	'raspberryglace' => [ 145, 95, 109 ],
	'raspberrypink' => [ 226, 80, 152 ],
	'raspberryrose' => [ 179, 68, 108 ],
	'rawsienna' => [ 214, 138, 89 ],
	'rawumber' => [ 130, 102, 68 ],
	'razzledazzlerose' => [ 255, 51, 204 ],
	'razzmatazz' => [ 227, 37, 107 ],
	'razzmicberry' => [ 141, 78, 133 ],
	'rebeccapurple' => [ 102, 51, 153 ],
	'red' => [ 240, 88, 104 ], // Was [ 255, 0, 0 ], but the new one is nicer
	'redbrown' => [ 165, 42, 42 ],
	'reddevil' => [ 134, 1, 17 ],
	'redorange' => [ 255, 83, 73 ],
	'redpurple' => [ 228, 0, 120 ],
	'redviolet' => [ 199, 21, 133 ],
	'redwood' => [ 164, 90, 82 ],
	'regalia' => [ 82, 45, 128 ],
	'registrationblack' => [ 0, 0, 0 ],
	'resolutionblue' => [ 0, 35, 135 ],
	'rhythm' => [ 119, 118, 150 ],
	'richblack' => [ 0, 64, 64 ],
	'richbrilliantlavender' => [ 241, 167, 254 ],
	'richcarmine' => [ 215, 0, 64 ],
	'richelectricblue' => [ 8, 146, 208 ],
	'richlavender' => [ 167, 107, 207 ],
	'richlilac' => [ 182, 102, 210 ],
	'richmaroon' => [ 176, 48, 96 ],
	'riflegreen' => [ 68, 76, 56 ],
	'roastcoffee' => [ 112, 66, 65 ],
	'robineggblue' => [ 0, 204, 204 ],
	'rocketmetallic' => [ 138, 127, 128 ],
	'romansilver' => [ 131, 137, 150 ],
	'rose' => [ 255, 0, 127 ],
	'rosebonbon' => [ 249, 66, 158 ],
	'roseebony' => [ 103, 72, 70 ],
	'rosegold' => [ 183, 110, 121 ],
	'rosemadder' => [ 227, 38, 54 ],
	'rosepink' => [ 255, 102, 204 ],
	'rosequartz' => [ 170, 152, 169 ],
	'rosered' => [ 194, 30, 86 ],
	'rosetaupe' => [ 144, 93, 93 ],
	'rosevale' => [ 171, 78, 82 ],
	'rosewood' => [ 101, 0, 11 ],
	'rossocorsa' => [ 212, 0, 0 ],
	'rosybrown' => [ 188, 143, 143 ],
	'royalazure' => [ 0, 56, 168 ],
	'royalblue' => [ 65, 105, 225 ],
	'royalfuchsia' => [ 202, 44, 146 ],
	'royalpurple' => [ 120, 81, 169 ],
	'royalyellow' => [ 250, 218, 94 ],
	'ruber' => [ 206, 70, 118 ],
	'rubinered' => [ 209, 0, 86 ],
	'ruby' => [ 224, 17, 95 ],
	'rubyred' => [ 155, 17, 30 ],
	'ruddy' => [ 255, 0, 40 ],
	'ruddybrown' => [ 187, 101, 40 ],
	'ruddypink' => [ 225, 142, 150 ],
	'rufous' => [ 168, 28, 7 ],
	'russet' => [ 128, 70, 27 ],
	'russiangreen' => [ 103, 146, 103 ],
	'russianviolet' => [ 50, 23, 77 ],
	'rust' => [ 183, 65, 14 ],
	'rustyred' => [ 218, 44, 67 ],
	'sacramentostategreen' => [ 0, 86, 63 ],
	'saddlebrown' => [ 139, 69, 19 ],
	'safetyorange' => [ 255, 120, 0 ],
	'safetyyellow' => [ 238, 210, 2 ],
	'saffron' => [ 244, 196, 48 ],
	'sage' => [ 188, 184, 138 ],
	'stpatricksblue' => [ 35, 41, 122 ],
	'salmon' => [ 250, 128, 114 ],
	'salmonpink' => [ 255, 145, 164 ],
	'sand' => [ 194, 178, 128 ],
	'sanddune' => [ 150, 113, 23 ],
	'sandstorm' => [ 236, 213, 64 ],
	'sandybrown' => [ 244, 164, 96 ],
	'sandytaupe' => [ 150, 113, 23 ],
	'sangria' => [ 146, 0, 10 ],
	'sapgreen' => [ 80, 125, 42 ],
	'sapphire' => [ 15, 82, 186 ],
	'sapphireblue' => [ 0, 103, 165 ],
	'satinsheengold' => [ 203, 161, 53 ],
	'scarlet' => [ 253, 14, 53 ],
	'schausspink' => [ 255, 145, 175 ],
	'schoolbusyellow' => [ 255, 216, 0 ],
	'screamingreen' => [ 118, 255, 122 ],
	'seablue' => [ 0, 105, 148 ],
	'seagreen' => [ 46, 139, 87 ],
	'sealbrown' => [ 89, 38, 11 ],
	'seashell' => [ 255, 245, 238 ],
	'selectiveyellow' => [ 255, 186, 0 ],
	'sepia' => [ 112, 66, 20 ],
	'shadow' => [ 138, 121, 93 ],
	'shadowblue' => [ 119, 139, 165 ],
	'shampoo' => [ 255, 207, 241 ],
	'shamrockgreen' => [ 0, 158, 96 ],
	'sheengreen' => [ 143, 212, 0 ],
	'shimmeringblush' => [ 217, 134, 149 ],
	'shockingpink' => [ 252, 15, 192 ],
	'sienna' => [ 136, 45, 23 ],
	'silver' => [ 192, 192, 192 ],
	'silverchalice' => [ 172, 172, 172 ],
	'silverlakeblue' => [ 93, 137, 186 ],
	'silverpink' => [ 196, 174, 173 ],
	'silversand' => [ 191, 193, 194 ],
	'sinopia' => [ 203, 65, 11 ],
	'skobeloff' => [ 0, 116, 116 ],
	'skyblue' => [ 135, 206, 235 ],
	'skymagenta' => [ 207, 113, 175 ],
	'slateblue' => [ 106, 90, 205 ],
	'slategray' => [ 112, 128, 144 ],
	'smalt' => [ 0, 51, 153 ],
	'smitten' => [ 200, 65, 134 ],
	'smoke' => [ 115, 130, 118 ],
	'smokyblack' => [ 16, 12, 8 ],
	'smokytopaz' => [ 147, 61, 65 ],
	'snow' => [ 255, 250, 250 ],
	'soap' => [ 206, 200, 239 ],
	'solidpink' => [ 137, 56, 67 ],
	'sonicsilver' => [ 117, 117, 117 ],
	'spartancrimson' => [ 158, 19, 22 ],
	'spacecadet' => [ 29, 41, 81 ],
	'spanishbistre' => [ 128, 117, 50 ],
	'spanishblue' => [ 0, 112, 184 ],
	'spanishcarmine' => [ 209, 0, 71 ],
	'spanishcrimson' => [ 229, 26, 76 ],
	'spanishgray' => [ 152, 152, 152 ],
	'spanishgreen' => [ 0, 145, 80 ],
	'spanishorange' => [ 232, 97, 0 ],
	'spanishpink' => [ 247, 191, 190 ],
	'spanishred' => [ 230, 0, 38 ],
	'spanishskyblue' => [ 0, 255, 255 ],
	'spanishviolet' => [ 76, 40, 130 ],
	'spanishviridian' => [ 0, 127, 92 ],
	'spicymix' => [ 139, 95, 77 ],
	'spirodiscoball' => [ 15, 192, 252 ],
	'springbud' => [ 167, 252, 0 ],
	'springgreen' => [ 0, 255, 127 ],
	'starcommandblue' => [ 0, 123, 184 ],
	'steelblue' => [ 70, 130, 180 ],
	'steelpink' => [ 204, 51, 204 ],
	'stildegrainyellow' => [ 250, 218, 94 ],
	'stizza' => [ 153, 0, 0 ],
	'stormcloud' => [ 79, 102, 106 ],
	'straw' => [ 228, 217, 111 ],
	'strawberry' => [ 252, 90, 141 ],
	'sunglow' => [ 255, 204, 51 ],
	'sunray' => [ 227, 171, 87 ],
	'sunset' => [ 250, 214, 165 ],
	'sunsetorange' => [ 253, 94, 83 ],
	'superpink' => [ 207, 107, 169 ],
	'tan' => [ 210, 180, 140 ],
	'tangelo' => [ 249, 77, 0 ],
	'tangerine' => [ 242, 133, 0 ],
	'tangerineyellow' => [ 255, 204, 0 ],
	'tangopink' => [ 228, 113, 122 ],
	'taupe' => [ 72, 60, 50 ],
	'taupegray' => [ 139, 133, 137 ],
	'teagreen' => [ 208, 240, 192 ],
	'tearose' => [ 244, 194, 194 ],
	'teal' => [ 0, 128, 128 ],
	'tealblue' => [ 54, 117, 136 ],
	'tealdeer' => [ 153, 230, 179 ],
	'tealgreen' => [ 0, 130, 127 ],
	'telemagenta' => [ 207, 52, 118 ],
	'tenné' => [ 205, 87, 0 ],
	'terracotta' => [ 226, 114, 91 ],
	'thistle' => [ 216, 191, 216 ],
	'thulianpink' => [ 222, 111, 161 ],
	'ticklemepink' => [ 252, 137, 172 ],
	'tiffanyblue' => [ 10, 186, 181 ],
	'tigerseye' => [ 224, 141, 60 ],
	'timberwolf' => [ 219, 215, 210 ],
	'titaniumyellow' => [ 238, 230, 0 ],
	'tomato' => [ 255, 99, 71 ],
	'toolbox' => [ 116, 108, 192 ],
	'topaz' => [ 255, 200, 124 ],
	'tractorred' => [ 253, 14, 53 ],
	'trolleygrey' => [ 128, 128, 128 ],
	'tropicalrainforest' => [ 0, 117, 94 ],
	'tropicalviolet' => [ 205, 164, 222 ],
	'trueblue' => [ 0, 115, 207 ],
	'tuftsblue' => [ 65, 125, 193 ],
	'tulip' => [ 255, 135, 141 ],
	'tumbleweed' => [ 222, 170, 136 ],
	'turkishrose' => [ 181, 114, 129 ],
	'turquoise' => [ 64, 224, 208 ],
	'turquoiseblue' => [ 0, 255, 239 ],
	'turquoisegreen' => [ 160, 214, 180 ],
	'tuscan' => [ 250, 214, 165 ],
	'tuscanbrown' => [ 111, 78, 55 ],
	'tuscanred' => [ 124, 72, 72 ],
	'tuscantan' => [ 166, 123, 91 ],
	'tuscany' => [ 192, 153, 153 ],
	'twilightlavender' => [ 138, 73, 107 ],
	'tyrianpurple' => [ 102, 2, 60 ],
	'uablue' => [ 0, 51, 170 ],
	'uared' => [ 217, 0, 76 ],
	'ube' => [ 136, 120, 195 ],
	'uclablue' => [ 83, 104, 149 ],
	'uclagold' => [ 255, 179, 0 ],
	'ufogreen' => [ 60, 208, 112 ],
	'ultramarine' => [ 63, 0, 255 ],
	'ultramarineblue' => [ 65, 102, 245 ],
	'ultrapink' => [ 255, 111, 255 ],
	'ultrared' => [ 252, 108, 133 ],
	'umber' => [ 99, 81, 71 ],
	'unbleachedsilk' => [ 255, 221, 202 ],
	'unitednationsblue' => [ 91, 146, 229 ],
	'universityofcaliforniagold' => [ 183, 135, 39 ],
	'unmellowyellow' => [ 255, 255, 102 ],
	'upforestgreen' => [ 1, 68, 33 ],
	'upmaroon' => [ 123, 17, 19 ],
	'upsdellred' => [ 174, 32, 41 ],
	'urobilin' => [ 225, 173, 33 ],
	'usafablue' => [ 0, 79, 152 ],
	'usccardinal' => [ 153, 0, 0 ],
	'uscgold' => [ 255, 204, 0 ],
	'universityoftennesseeorange' => [ 247, 127, 0 ],
	'utahcrimson' => [ 211, 0, 63 ],
	'vanilla' => [ 243, 229, 171 ],
	'vanillaice' => [ 243, 143, 169 ],
	'vegasgold' => [ 197, 179, 88 ],
	'venetianred' => [ 200, 8, 21 ],
	'verdigris' => [ 67, 179, 174 ],
	'vermilion' => [ 217, 56, 30 ],
	'veronica' => [ 160, 32, 240 ],
	'verylightazure' => [ 116, 187, 251 ],
	'verylightblue' => [ 102, 102, 255 ],
	'verylightmalachitegreen' => [ 100, 233, 134 ],
	'verylighttangelo' => [ 255, 176, 119 ],
	'verypaleorange' => [ 255, 223, 191 ],
	'verypaleyellow' => [ 255, 255, 191 ],
	'violet' => [ 143, 0, 255 ],
	'violetblue' => [ 50, 74, 178 ],
	'violetred' => [ 247, 83, 148 ],
	'viridian' => [ 64, 130, 109 ],
	'viridiangreen' => [ 0, 150, 152 ],
	'vistablue' => [ 124, 158, 217 ],
	'vividamber' => [ 204, 153, 0 ],
	'vividauburn' => [ 146, 39, 36 ],
	'vividburgundy' => [ 159, 29, 53 ],
	'vividcerise' => [ 218, 29, 129 ],
	'vividcerulean' => [ 0, 170, 238 ],
	'vividcrimson' => [ 204, 0, 51 ],
	'vividgamboge' => [ 255, 153, 0 ],
	'vividlimegreen' => [ 166, 214, 8 ],
	'vividmalachite' => [ 0, 204, 51 ],
	'vividmulberry' => [ 184, 12, 227 ],
	'vividorange' => [ 255, 95, 0 ],
	'vividorangepeel' => [ 255, 160, 0 ],
	'vividorchid' => [ 204, 0, 255 ],
	'vividraspberry' => [ 255, 0, 108 ],
	'vividred' => [ 247, 13, 26 ],
	'vividredtangelo' => [ 223, 97, 36 ],
	'vividskyblue' => [ 0, 204, 255 ],
	'vividtangelo' => [ 240, 116, 39 ],
	'vividtangerine' => [ 255, 160, 137 ],
	'vividvermilion' => [ 229, 96, 36 ],
	'vividviolet' => [ 159, 0, 255 ],
	'vividyellow' => [ 255, 227, 2 ],
	'volt' => [ 206, 255, 0 ],
	'warmblack' => [ 0, 66, 66 ],
	'waterspout' => [ 164, 244, 249 ],
	'weldonblue' => [ 124, 152, 171 ],
	'wenge' => [ 100, 84, 82 ],
	'wheat' => [ 245, 222, 179 ],
	'white' => [ 240, 240, 240 ], // Was [ 255, 255, 255 ], but that doesn't really 'show'
	'whitesmoke' => [ 245, 245, 245 ],
	'wildblueyonder' => [ 162, 173, 208 ],
	'wildorchid' => [ 212, 112, 162 ],
	'wildstrawberry' => [ 255, 67, 164 ],
	'wildwatermelon' => [ 252, 108, 133 ],
	'willpowerorange' => [ 253, 88, 0 ],
	'windsortan' => [ 167, 85, 2 ],
	'wine' => [ 114, 47, 55 ],
	'winedregs' => [ 103, 49, 71 ],
	'wisteria' => [ 201, 160, 220 ],
	'woodbrown' => [ 193, 154, 107 ],
	'xanadu' => [ 115, 134, 120 ],
	'yaleblue' => [ 15, 77, 146 ],
	'yankeesblue' => [ 28, 40, 65 ],
	'yellow' => [ 240, 208, 72 ], // Was [255, 255, 0 ], but the new one looks nicer
	'yellowgreen' => [ 154, 205, 50 ],
	'yelloworange' => [ 255, 174, 66 ],
	'yellowrose' => [ 255, 240, 0 ],
	'zaffre' => [ 0, 20, 168 ],
	'zinnwalditebrown' => [ 44, 22, 8 ],
	'zomp' => [ 57, 167, 142 ],

]; // $colors_by_name

define( 'COLORS_BY_NAME', $colors_by_name );

// The end!
