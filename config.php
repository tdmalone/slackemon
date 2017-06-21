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
 * See the descriptions of these variables in .env.example.
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

// If you need to deploy Slackemon, or different instances of it, at a different Slash command, you can change this.
// TODO: This needs implementing.
define( 'SLACKEMON_SLASH_COMMAND', getenv( 'SLACKEMON_XXXX' ) ?: '/slackemon' );

/**
 * Game behaviour configuration
 * See the descriptions of these variables in .env.example.
 */

// Include additional team news on the Slackemon home screen.
// Separate additional news items with a pipe (|). Leave blank (eg. '') for no extra news.
// Examples:
// 'This is a single news item'
// ':simple_smile: A news item with a smiley!|Another news item'
define( 'SLACKEMON_ADDITIONAL_NEWS', getenv( 'SLACKEMON_ADDITIONAL_NEWS' ) ?: '' );

// The regions that players can travel to in order to catch Pokemon, and the default region that new players start in.
// MAKE SURE YOUR DEFAULT REGION IS INCLUDED AS AN AVAILABLE REGION!
// For available regions, separate additional regions with a pipe. For default region, please only set one ;)
// Examples: 'kanto' or 'kanto|sinnoh'
define( 'SLACKEMON_AVAILABLE_REGIONS', getenv( 'SLACKEMON_AVAILABLE_REGIONS' ) ?: 'kanto' );
define( 'SLACKEMON_DEFAULT_REGION',    getenv( 'SLACKEMON_DEFAULT_REGION'    ) ?: 'kanto' );

// The hours that Slackemon cannot be played by any user.
// TODO: This feature is not yet implemented.
// TODO: Document how to use this feature when it is implemented.
define( 'SLACKEMON_BANNED_HOURS', getenv( 'SLACKEMON_BANNED_HOURS' ) ?: '' );

// Defines the chance that wild Pokemon will flee from the player trying to catch them.
// Expressed as 1 in X chance of flee.
// For wild battles, chance is multipled by Y and divided by wild Pokemon's remaining HP.
// eg. Normal catch: 1 in 3 chance of flee.
//     Initial wild battle: 1 in 9 chance of flee (i.e. normal times 3).
//     At each move of wild Pokemon: eg. 1 in 18 chance of flee if Pokemon has 50% HP (1 in 9 / .5)
define( 'SLACKEMON_BASE_FLEE_CHANCE',       getenv( 'SLACKEMON_BASE_FLEE_CHANCE'       ) ?: 3 );
define( 'SLACKEMON_BATTLE_FLEE_MULTIPLIER', getenv( 'SLACKEMON_BATTLE_FLEE_MULTIPLIER' ) ?: 3 );

// How long Pokemon remain catchable for before they flee.
// Note that at this stage, only one Pokemon will ever spawn at once. So the higher this is, the less spawns.
define( 'SLACKEMON_FLEE_TIME_LIMIT', getenv( 'SLACKEMON_FLEE_TIME_LIMIT' ) ?: MINUTE_IN_SECONDS * 5 );

// Roughly how many chances there are of a spawn each hour.
// HOWEVER, because only one Pokemon will ever spawn at once, this is not a true chance and is *heavily* weighted
// by whatever the flee time limit is set to above.
define( 'SLACKEMON_HOURLY_SPAWN_RATE', getenv( 'SLACKEMON_HOURLY_SPAWN_RATE' ) ?: 20 );

// At each spawn, the chance out of 100 of spawning an item instead of a Pokemon.
define( 'SLACKEMON_ITEM_SPAWN_CHANCE', getenv( 'SLACKEMON_ITEM_SPAWN_CHANCE' ) ?:  5 );

// Certain classes of Pokemon to exclude from spawns.
// The defaults for all of these are `true`:
// - babies because they will spawn as eggs in a future version of Slackemon;
// - evolutions because it kinda ruins the point of training your Pokemon up if you then just catch the evolved form
//   in the wild (however note that if you set this to false, we don't have the logic to ensure they only spawn at
//   'legal' levels);
// - legendaries because they should be harder to catch, so by default are saved for weather or other custom events
//   (i.e. you could set this to true during certain local parties etc.); and
// - time of day refers to not spawning Flying/Normal Pokemon at night (eg. Pidgey) and not spawning Ghost or Dark
//   types or Abra and Zubat during the day, because that makes the game feel that little bit more realistic!
define( 'SLACKEMON_EXCLUDE_BABIES',         getenv( 'SLACKEMON_EXCLUDE_BABIES'         ) ?: true );
define( 'SLACKEMON_EXCLUDE_EVOLUTIONS',     getenv( 'SLACKEMON_EXCLUDE_EVOLUTIONS'     ) ?: true );
define( 'SLACKEMON_EXCLUDE_LEGENDARIES',    getenv( 'SLACKEMON_EXCLUDE_LEGENDARIES'    ) ?: true );
define( 'SLACKEMON_EXCLUDE_ON_TIME_OF_DAY', getenv( 'SLACKEMON_EXCLUDE_ON_TIME_OF_DAY' ) ?: true );

// Should legendary Pokemon be allowed to spawn when their type is weather-friendly?
// Note that weather matchups don't exist for every legendary Pokemon; eg. Suicune can spawn when it's raining but
// there's nothing for Registeel.
define( 'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS', getenv( 'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS' ) ?: true );

// Certain individual Pokemon that are excluded from spawns altogether.
// Separate multiple values with a pipe (|).
// Currently, only Ditto is excluded by default, because the Transform move is not properly handled in battle yet.
define( 'SLACKEMON_EXCLUDED_POKEMON', getenv( 'SLACKEMON_EXCLUDED_POKEMON' ) ?: '132' );

// The size of a player's battle team (aka Pokemon Party).
// For wild battles, this determines how many Pokemon the trainer can select to have randomly chosen between.
// For trainer battles, every Pokemon must be beaten, so this value determines how long these battles go for!
define( 'SLACKEMON_BATTLE_TEAM_SIZE', getenv( 'SLACKEMON_BATTLE_TEAM_SIZE' ) ?: 3 );

// How much battle experience gains are multiplied by.
// This is separate to any other experience modifiers that may be afforded by certain items.
// By default this is 1, and you could use it to eg. offer double experience (2) during a local event or party.
define( 'SLACKEMON_EXP_GAIN_MODIFIER', getenv( 'SLACKEMON_EXP_GAIN_MODIFIER' ) ?: 1 );

// The percentage per minute that Pokemon HP and PP restores after battle.
// Restores only happen when a player is active (i.e. online and not in battle).
// Example: .05 for 5% per minute.
define( 'SLACKEMON_HP_RESTORE_RATE', getenv( 'SLACKEMON_HP_RESTORE_RATE' ) ?: .05 );

/**
 * Debugging configuration.
 * If enabled, outputs verbose debugging messages to the error_log.
 * Useful when doing further development on certain features.
 */

define( 'SLACKEMON_BATTLE_DEBUG',   getenv( 'SLACKEMON_BATTLE_DEBUG'   ) ?: false );
define( 'SLACKEMON_CACHE_DEBUG',    getenv( 'SLACKEMON_CACHE_DEBUG'    ) ?: false );
define( 'SLACKEMON_DATABASE_DEBUG', getenv( 'SLACKEMON_DATABASE_DEBUG' ) ?: false );
define( 'SLACKEMON_SPAWN_DEBUG',    getenv( 'SLACKEMON_SPAWN_DEBUG'    ) ?: false );

/**
 * Internal configuration.
 * These variables generally don't need changing.
 */

define( 'SLACKEMON_ACTION_CALLBACK_ID', getenv( 'SLACKEMON_ACTION_CALLBACK_ID' ) ?: 'slackemon'  );
define( 'SLACKEMON_TABLE_PREFIX',       getenv( 'SLACKEMON_TABLE_PREFIX'       ) ?: 'slackemon_' );

// Parameters sent to Slack to control the appearance of Slackemon messages.
define( 'SLACKEMON_USERNAME', getenv( 'SLACKEMON_USERNAME' ) ?: 'Slackémon'  );
define( 'SLACKEMON_ICON',     getenv( 'SLACKEMON_ICON'     ) ?: ':pokeball:' );

// The base URL used for all animated Pokemon sprite GIFs.
// Note that changing this will start your image cache again from scratch, as cache keys are based on the full URL.
define( 'SLACKEMON_ANIMATED_GIF_BASE',
  getenv( 'SLACKEMON_ANIMATED_GIF_BASE' ) ?:
  'https://raw.githubusercontent.com/tdmalone/pokecss-media/57061f0fdfd664a1b6543ddb6913dfd9a52b157f/graphics/pokemon'
);

// The number of seconds that will be waited for when calling a background command/action.
// You will usually want 1 second for this, but some servers may need longer.
// Keep in mind that too long will cause Slack itself to timeout (it allows up to 3 seconds for the *total* roundtrip).
define( 'SLACKEMON_CURL_TIMEOUT', getenv( 'SLACKEMON_CURL_TIMEOUT' ) ?: 1 );

// In-message pagination configuration.
// Don't set these values too high - you might hit the Slack attachment limit.
// Also, higher values means longer load time for the relevant messages, including action button responses.
define( 'SLACKEMON_ITEMS_PER_PAGE',   getenv( 'SLACKEMON_ITEMS_PER_PAGE'   ) ?:  5 );
define( 'SLACKEMON_POKEMON_PER_PAGE', getenv( 'SLACKEMON_POKEMON_PER_PAGE' ) ?:  5 );
define( 'SLACKEMON_POKEDEX_PER_PAGE', getenv( 'SLACKEMON_POKEDEX_PER_PAGE' ) ?: 20 );

// Changing these values may not be fully supported at this stage.
define( 'SLACKEMON_MAX_IVS',         getenv( 'SLACKEMON_MAX_IVS'         ) ?: 31 );
define( 'SLACKEMON_MIN_IVS',         getenv( 'SLACKEMON_MIN_IVS'         ) ?:  0 );
define( 'SLACKEMON_MAX_KNOWN_MOVES', getenv( 'SLACKEMON_MAX_KNOWN_MOVES' ) ?:  4 );

// The end!
