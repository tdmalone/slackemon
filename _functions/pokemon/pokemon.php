<?php

// Chromatix TM 24/03/2017

define( 'SLACKEMON_VERSION', '0.0.37' );

// Define default settings
// These can all be overriden by placing each setting you want to override in your local config.php like this:
//   define( 'SLACKEMON_AVAILABLE_REGIONS', [ 'kanto', 'johto' ] );

$_slackemon_constant_defaults = [

  // This can be useful to change in local development config, to send action data to another predefined location
  'ACTION_CALLBACK_ID'     => 'slackemon',

  'ADDITIONAL_NEWS'        => [],
  'ANIMATED_GIF_BASE'      => 'https://assets-lmcrhbacy2s.stackpathdns.com/img/pokemon/animated',
  'AVAILABLE_REGIONS'      => [ 'kanto' ],
  'BANNED_HOURS'           => [],

  // 1 in X chance of flee. For wild battles, chance is multipled by Y and divided by wild Pokemon's remaining HP.
  // eg. Normal catch: 1 in 3 chance of flee.
  //     Initial battle: 1 in 9 chance of flee (normal times 3).
  //     At each move of wild Pokemon: eg. 1 in 18 chance of flee if Pokemon has 50% HP (1 in 9 / .5)
  'BASE_FLEE_CHANCE'       => 3,
  'BATTLE_FLEE_MULTIPLIER' => 3,

  'BATTLE_TEAM_SIZE'       => 3,

  // The number of seconds that will be waited for when calling a background command/action
  // You will usually want 1 second for this, but some servers may need longer
  // Keep in mind that too long will cause Slack itself to timeout (it allows up to 3 seconds for the *total* roundtrip)
  'CURL_TIMEOUT'           => 1,

  'DATE_FORMAT'            => 'D jS M \a\t g:ia',
  'DEFAULT_REGION'         => 'kanto',

  'EXCLUDE_BABIES'         => true,
  'EXCLUDE_EVOLUTIONS'     => true,
  'EXCLUDE_LEGENDARIES'    => true,
  'EXCLUDE_ON_TIME_OF_DAY' => true,
  'EXCLUDED_POKEMON'       => [ 132 ], // Ditto - need to decide how we deal with Transform in battle

  'ALLOW_LEGENDARY_WEATHER_SPAWNS' => true,

  'EXP_GAIN_MODIFIER'      => 1,
  'FLEE_TIME_LIMIT'        => MINUTE_IN_SECONDS * 5,
  'HOURLY_SPAWN_RATE'      => 20,
  'HP_RESTORE_RATE'        => .05, // % per minute
  'MAX_IVS'                => 31,
  'MIN_IVS'                => 0, 
  'ITEMS_PER_PAGE'         => 5,
  'POKEMON_PER_PAGE'       => 5,
  'POKEDEX_PER_PAGE'       => 20,
  'MAX_KNOWN_MOVES'        => 4,

  'BATTLE_DEBUG'           => false,
  'SPAWN_DEBUG'            => false,

  'ITEM_SPAWN_CHANCE'      => 5, // Chance out of 100 of spawning an item instead of a Pokemon

  // Change this to make weather info reflect your local area
  // You can get your lat lon from eg. Google Maps
  // Default is Melbourne, Australia
  'WEATHER_LAT_LON'        => '-37.81,144.96',

];

foreach ( $_slackemon_constant_defaults as $key => $value ) {
  if ( ! defined( 'SLACKEMON_' . $key ) ) {
  	define( 'SLACKEMON_' . $key, $value );
  }
}

// Now, let's get going with the functions!

require_once( __DIR__ . '/apis.php'       );
require_once( __DIR__ . '/battles.php'    );
require_once( __DIR__ . '/catching.php'   );
require_once( __DIR__ . '/evolution.php'  );
require_once( __DIR__ . '/items.php'      );
require_once( __DIR__ . '/moves.php'      );
require_once( __DIR__ . '/organising.php' );
require_once( __DIR__ . '/players.php'    );
require_once( __DIR__ . '/pokedex.php'    );
require_once( __DIR__ . '/spawns.php'     );
require_once( __DIR__ . '/stats.php'      );
require_once( __DIR__ . '/templating.php' );
require_once( __DIR__ . '/trading.php'    );
require_once( __DIR__ . '/travel.php'     );
require_once( __DIR__ . '/weather.php'    );

require_once( __DIR__ . '/menus/achievements-menu.php' );
require_once( __DIR__ . '/menus/battle-menu.php'       );
require_once( __DIR__ . '/menus/items-menu.php'        );
require_once( __DIR__ . '/menus/main-menu.php'         );
require_once( __DIR__ . '/menus/onboarding-menu.php'   );
require_once( __DIR__ . '/menus/pokemon-menu.php'      );
require_once( __DIR__ . '/menus/tools-menu.php'        );
require_once( __DIR__ . '/menus/travel-menu.php'       );

// The end!
