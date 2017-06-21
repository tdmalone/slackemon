<?php
/**
 * Assigns environment variable values to constants for easy access elsewhere.
 *
 * @package Slackemon
 */

// If running in development, attempt to load environment variables from .env file.
if ( file_exists( __DIR__ . '/.env' ) && 'development' === getenv( 'APP_ENV' ) ) {
  require_once( __DIR__ . '/vendor/autoload.php' );
  $dotenv = new Dotenv\Dotenv( __DIR__ );
  $dotenv->load();
}

/**
 * Instance configuration.
 */

define( 'SLACKEMON_SLACK_TOKEN',        getenv( 'SLACKEMON_SLACK_TOKEN'        ) );
define( 'SLACKEMON_SLACK_TEAM_ID',      getenv( 'SLACKEMON_SLACK_TEAM_ID'      ) );
define( 'SLACKEMON_SLACK_KEY',          getenv( 'SLACKEMON_SLACK_KEY'          ) );

define( 'SLACKEMON_MAINTAINER',         getenv( 'SLACKEMON_MAINTAINER'         ) );
define( 'SLACKEMON_CRON_TOKEN',         getenv( 'SLACKEMON_CRON_TOKEN'         ) );
define( 'SLACKEMON_INBOUND_URL',        getenv( 'SLACKEMON_INBOUND_URL'        ) );

define( 'SLACKEMON_OPENWEATHERMAP_KEY', getenv( 'SLACKEMON_OPENWEATHERMAP_KEY' ) );

define( 'SLACKEMON_DATA_STORE_METHOD',  getenv( 'SLACKEMON_DATA_STORE_METHOD'  ) ?: 'local'               );
define( 'SLACKEMON_DATA_CACHE_METHOD',  getenv( 'SLACKEMON_DATA_CACHE_METHOD'  ) ?: 'local'               );

define( 'SLACKEMON_DATA_FOLDER',        getenv( 'SLACKEMON_DATA_FOLDER'        ) ?: '.data'               );
define( 'SLACKEMON_DATA_BUCKET',        getenv( 'SLACKEMON_DATA_BUCKET'        ) );

define( 'SLACKEMON_IMAGE_CACHE_METHOD', getenv( 'SLACKEMON_IMAGE_CACHE_METHOD' ) ?: 'local'               );
define( 'SLACKEMON_IMAGE_CACHE_FOLDER', getenv( 'SLACKEMON_IMAGE_CACHE_FOLDER' ) ?: '.image-cache'        );
define( 'SLACKEMON_IMAGE_CACHE_BUCKET', getenv( 'SLACKEMON_IMAGE_CACHE_BUCKET' ) );

// Prefer SLACKEMON_DATABASE_URL, fallback to DATABASE_URL (Heroku) & then to POSTGRES_USER/PASSWORD/DB (Docker Compose)
if ( getenv( 'SLACKEMON_DATABASE_URL' ) ) {
  define( 'SLACKEMON_DATABASE_URL', getenv( 'SLACKEMON_DATABASE_URL' ) );
} else if ( getenv( 'DATABASE_URL' ) ) {
  define( 'SLACKEMON_DATABASE_URL', getenv( 'DATABASE_URL' ) );
} else if ( getenv( 'POSTGRES_USER' ) && getenv( 'POSTGRES_PASSWORD' ) && getenv( 'POSTGRES_DB' ) ) {
  define(
    'SLACKEMON_DATABASE_URL',
    'postgres://' . getenv( 'POSTGRES_USER' ) . ':' . getenv( 'POSTGRES_PASSWORD' ) .
    '@database:5432/' . getenv( 'POSTGRES_DB' )
  );
}

define( 'SLACKEMON_AWS_ID',             getenv( 'SLACKEMON_AWS_ID'             ) );
define( 'SLACKEMON_AWS_SECRET',         getenv( 'SLACKEMON_AWS_SECRET'         ) );
define( 'SLACKEMON_AWS_REGION',         getenv( 'SLACKEMON_AWS_REGION'         ) ?: 'us-east-1'           );

define( 'SLACKEMON_TIMEZONE',           getenv( 'SLACKEMON_TIMEZONE'           ) ?: 'Australia/Melbourne' );
define( 'SLACKEMON_WEATHER_LAT_LON',    getenv( 'SLACKEMON_WEATHER_LAT_LON'    ) ?: '-37.81,144.96'       );
define( 'SLACKEMON_MONETARY_LOCALE',    getenv( 'SLACKEMON_MONETARY_LOCALE'    ) ?: 'en_AU'               );

/**
 * Game behaviour configuration
 */

// Includes additional team news on the Slackemon home screen
define( 'SLACKEMON_ADDITIONAL_NEWS', [] );

define( 'SLACKEMON_AVAILABLE_REGIONS'     , [ 'kanto' ] );
define( 'SLACKEMON_BANNED_HOURS'          , [] ); // TODO - This is not yet implemented

// 1 in X chance of flee. For wild battles, chance is multipled by Y and divided by wild Pokemon's remaining HP.
// eg. Normal catch: 1 in 3 chance of flee.
//     Initial battle: 1 in 9 chance of flee (normal times 3).
//     At each move of wild Pokemon: eg. 1 in 18 chance of flee if Pokemon has 50% HP (1 in 9 / .5)
define( 'SLACKEMON_BASE_FLEE_CHANCE'      , 3 );
define( 'SLACKEMON_BATTLE_FLEE_MULTIPLIER', 3 );

define( 'SLACKEMON_BATTLE_TEAM_SIZE'      , 3 );

define( 'SLACKEMON_DATE_FORMAT'           , 'D jS M \a\t g:ia' );
define( 'SLACKEMON_DEFAULT_REGION'        , 'kanto' );

define( 'SLACKEMON_EXCLUDE_BABIES'        , true );
define( 'SLACKEMON_EXCLUDE_EVOLUTIONS'    , true );
define( 'SLACKEMON_EXCLUDE_LEGENDARIES'   , true );
define( 'SLACKEMON_EXCLUDE_ON_TIME_OF_DAY', true );
define( 'SLACKEMON_EXCLUDED_POKEMON'      , [ 132 ] ); // Ditto - need to decide how we deal with Transform in battle

define( 'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS', true );
define( 'SLACKEMON_ITEM_SPAWN_CHANCE'     , 5 ); // Chance out of 100 of spawning an item instead of a Pokemon

define( 'SLACKEMON_EXP_GAIN_MODIFIER'     , 1 );
define( 'SLACKEMON_FLEE_TIME_LIMIT'       , MINUTE_IN_SECONDS * 5 );
define( 'SLACKEMON_HOURLY_SPAWN_RATE'     , 20 );
define( 'SLACKEMON_HP_RESTORE_RATE'       , .05 ); // % per minute

/**
 * Debugging configuration.
 * If enabled, outputs verbose debugging messages to the error_log.
 * Useful when doing further development on certain features.
 */

define( 'SLACKEMON_BATTLE_DEBUG',   false );
define( 'SLACKEMON_CACHE_DEBUG',    false );
define( 'SLACKEMON_DATABASE_DEBUG', false );
define( 'SLACKEMON_SPAWN_DEBUG',    false );

/**
 * Internal configuration.
 * These variables generally don't need changing.
 */

define( 'SLACKEMON_ACTION_CALLBACK_ID', 'slackemon' );

// Database table prefix
define( 'SLACKEMON_TABLE_PREFIX'          , 'slackemon_' );

// If you need to deploy Slackemon, or different instances of it, at a different slash command
// TODO: This needs implementing
define( 'SLACKEMON_SLASH_COMMAND', '/slackemon' );

// Parameters sent to Slack to control the appearance of Slackemon messages
define( 'SLACKEMON_USERNAME', 'Slackémon'  );
define( 'SLACKEMON_ICON',     ':pokeball:' );

// The base URL used for all animated Pokemon sprite GIFs
define( 'SLACKEMON_ANIMATED_GIF_BASE'     ,
  'https://raw.githubusercontent.com/tdmalone/pokecss-media/57061f0fdfd664a1b6543ddb6913dfd9a52b157f/graphics/pokemon'
);

// The number of seconds that will be waited for when calling a background command/action
// You will usually want 1 second for this, but some servers may need longer
// Keep in mind that too long will cause Slack itself to timeout (it allows up to 3 seconds for the *total* roundtrip)
define( 'SLACKEMON_CURL_TIMEOUT'          , 1 );

define( 'SLACKEMON_ITEMS_PER_PAGE'        , 5 );
define( 'SLACKEMON_POKEMON_PER_PAGE'      , 5 );
define( 'SLACKEMON_POKEDEX_PER_PAGE'      , 20 );

define( 'SLACKEMON_MAX_IVS'               , 31 );
define( 'SLACKEMON_MIN_IVS'               , 0 );
define( 'SLACKEMON_MAX_KNOWN_MOVES'       , 4 );

// The end!
