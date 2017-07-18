<?php
/**
 * Assigns environment variable values to constants for easy access elsewhere.
 *
 * @package Slackemon
 */

define( 'APP_ENV', getenv( 'APP_ENV' ) );

// If running in development, attempt to load environment variables from .env file.
if ( file_exists( __DIR__ . '/.env' ) && 'development' === APP_ENV ) {
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

// If you need to deploy Slackemon, or different instances of it, at a different Slash command, you can change this.
define( 'SLACKEMON_SLASH_COMMAND', getenv( 'SLACKEMON_SLASH_COMMAND' ) ?: '/slackemon' );

/**
 * Game behaviour configuration
 * See the descriptions of these variables in .env.example.
 */

// Include additional team news on the Slackemon home screen. You might use this for example to notify users of a
// special event where you are making more regions available, allowing legendary spawns, or doubling experience gain.
// Separate additional news items with a pipe (|). Leave blank (eg. '') for no extra news.
// Examples:
// 'This is a single news item'
// ':simple_smile: A news item with a smiley!|Another news item'
define( 'SLACKEMON_ADDITIONAL_NEWS', trim( getenv( 'SLACKEMON_ADDITIONAL_NEWS' ), '"' ) ?: '' );

// The regions that players can travel to in order to catch Pokemon, and the default region that new players start in.
// MAKE SURE YOUR DEFAULT REGION IS INCLUDED AS AN AVAILABLE REGION!
// For available regions, separate additional regions with a pipe. For default region, please only set one ;)
// Examples: 'kanto' or 'kanto|sinnoh'
define( 'SLACKEMON_AVAILABLE_REGIONS', getenv( 'SLACKEMON_AVAILABLE_REGIONS' ) ?: 'kanto' );
define( 'SLACKEMON_DEFAULT_REGION',    getenv( 'SLACKEMON_DEFAULT_REGION'    ) ?: 'kanto' );

// If you aren't able to upload custom emoji to your Slack team, you can turn off all use of them.
// Note that this will most likely reduce the visual appeal of some features, particularly the battle HP meter!
define(
  'SLACKEMON_ENABLE_CUSTOM_EMOJI',
  getenv( 'SLACKEMON_ENABLE_CUSTOM_EMOJI' ) ?
  filter_var( getenv( 'SLACKEMON_ENABLE_CUSTOM_EMOJI' ), FILTER_VALIDATE_BOOLEAN ) :
  true
);

// The hours that Slackemon cannot be played by any user.
// TODO: This feature is not yet implemented.
// TODO: Document how to use this feature when it is implemented.
define( 'SLACKEMON_BANNED_HOURS', getenv( 'SLACKEMON_BANNED_HOURS' ) ?: '' );

// A callback URL that will be called regularly (and cached) to determine whether a specific user is allowed to play.
// The URL will be appended with ?user_id=UXXXXXXXX (or &user_id=UXXXXXXXX if you include other GET parameters in it),
// and you just need to return a plaintext response of 'yes' if they are allowed to play and 'no' if they aren't.
// You can use this in a business situation to deny certain users access if they should be working, eg. if they have
// an active timer or task through your API-enabled task management system. This feature obviously requires custom
// programming to implement.
// TODO: This feature is not yet implemented.
define( 'SLACKEMON_USER_BAN_CALLBACK_URL', getenv( 'SLACKEMON_USER_BAN_CALLBACK_URL' ) ?: '' );

// Defines the chance that wild Pokemon will flee from the player trying to catch them.
// Expressed as 1 in X chance of flee.
// For wild battles, chance is multipled by Y and divided by wild Pokemon's remaining HP.
// eg. Normal catch: 1 in 3 chance of flee.
//     Initial wild battle: 1 in 9 chance of flee (i.e. normal times 3).
//     At each move of wild Pokemon: eg. 1 in 18 chance of flee if Pokemon has 50% HP (1 in 9 / .5)
define( 'SLACKEMON_BASE_FLEE_CHANCE',       (int) getenv( 'SLACKEMON_BASE_FLEE_CHANCE'       ) ?: 3 );
define( 'SLACKEMON_BATTLE_FLEE_MULTIPLIER', (int) getenv( 'SLACKEMON_BATTLE_FLEE_MULTIPLIER' ) ?: 3 );

// How long Pokemon remain catchable for before they flee.
// Note that at this stage, only one Pokemon will ever spawn at once. So the higher this is, the less spawns.
define( 'SLACKEMON_FLEE_TIME_LIMIT', (int) getenv( 'SLACKEMON_FLEE_TIME_LIMIT' ) ?: MINUTE_IN_SECONDS * 5 );

// Roughly how many chances there are of a spawn each hour.
define( 'SLACKEMON_HOURLY_SPAWN_RATE', (int) getenv( 'SLACKEMON_HOURLY_SPAWN_RATE' ) ?: 10 );

// At each spawn, the chance out of 100 of spawning an item instead of a Pokemon.
define( 'SLACKEMON_ITEM_SPAWN_CHANCE', (int) getenv( 'SLACKEMON_ITEM_SPAWN_CHANCE' ) ?:  5 );

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
$_exclude_vars = [
  'SLACKEMON_EXCLUDE_BABIES',
  'SLACKEMON_EXCLUDE_EVOLUTIONS',
  'SLACKEMON_EXCLUDE_LEGENDARIES',
  'SLACKEMON_EXCLUDE_ON_TIME_OF_DAY'
];
foreach ( $_exclude_vars as $var ) { // Foreach of the above vars, force the env var to boolean or default to TRUE
  define( $var, getenv( $var ) ? filter_var( getenv( $var ), FILTER_VALIDATE_BOOLEAN ) : true );
}

// Should legendary Pokemon be allowed to spawn when their type is weather-friendly?
// Note that weather matchups don't exist for every legendary Pokemon; eg. Suicune can spawn when it's raining but
// there's nothing for Registeel.
define(
  'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS',
  getenv( 'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS' ) ?
  filter_var( getenv( 'SLACKEMON_ALLOW_LEGENDARY_WEATHER_SPAWNS' ), FILTER_VALIDATE_BOOLEAN ) :
  true
);

// Certain individual Pokemon that are excluded from spawns altogether.
// Separate multiple values with a pipe (|).
// Currently, only Ditto is excluded by default, because the Transform move is not properly handled in battle yet.
define( 'SLACKEMON_EXCLUDED_POKEMON', getenv( 'SLACKEMON_EXCLUDED_POKEMON' ) ?: '132' );

// The size of a player's battle team (aka Pokemon Party).
// For wild battles, this determines how many Pokemon the trainer can select to have randomly chosen between.
// For trainer battles, every Pokemon must be beaten, so this value determines how long these battles go for!
define( 'SLACKEMON_BATTLE_TEAM_SIZE', (int) getenv( 'SLACKEMON_BATTLE_TEAM_SIZE' ) ?: 3 );

// How much battle experience gains are multiplied by.
// This is separate to any other experience modifiers that may be afforded by certain items.
// By default this is 1, and you could use it to eg. offer double experience (2) during a local event or party.
define( 'SLACKEMON_EXP_GAIN_MODIFIER', (int) getenv( 'SLACKEMON_EXP_GAIN_MODIFIER' ) ?: 1 );

// The percentage per minute that Pokemon HP and PP restores after battle.
// Restores only happen when a player is active (i.e. online and not in battle).
// Example: .05 for 5% per minute.
define( 'SLACKEMON_HP_RESTORE_RATE', (float) getenv( 'SLACKEMON_HP_RESTORE_RATE' ) ?: .05 );

// The number of times a user can swap Pokemon during a P2P battle.
// 'Free swaps' after a Pokemon has fainted are not included in this total.
define( 'SLACKEMON_BATTLE_SWAP_LIMIT', (int) getenv( 'SLACKEMON_BATTLE_SWAP_LIMIT' ) ?: 5 );

/**
 * Debugging configuration.
 * If enabled, outputs verbose debugging messages to the error_log.
 * Useful when doing further development on certain features.
 */

$_debug_vars = [
  'SLACKEMON_BATTLE_DEBUG',
  'SLACKEMON_CACHE_DEBUG',
  'SLACKEMON_DATABASE_DEBUG',
  'SLACKEMON_LOCK_DEBUG',
  'SLACKEMON_SPAWN_DEBUG'
];

foreach ( $_debug_vars as $var ) { // Foreach of the above vars, force the env var to boolean or default to FALSE
  define( $var, getenv( $var ) ? filter_var( getenv( $var ), FILTER_VALIDATE_BOOLEAN ) : false );
}

// In addition, 'file locking' is new, so there's a variable that can be used to disable it if it causes issues
define(
  'SLACKEMON_ENABLE_FILE_LOCKING',
  getenv( 'SLACKEMON_ENABLE_FILE_LOCKING' ) ?
  filter_var( getenv( 'SLACKEMON_ENABLE_FILE_LOCKING' ), FILTER_VALIDATE_BOOLEAN ) :
  true
);

/**
 * Internal configuration.
 * These variables generally don't need changing.
 */

define( 'SLACKEMON_ACTION_CALLBACK_ID', getenv( 'SLACKEMON_ACTION_CALLBACK_ID' ) ?: 'slackemon'  );
define( 'SLACKEMON_TABLE_PREFIX',       getenv( 'SLACKEMON_TABLE_PREFIX'       ) ?: 'slackemon_' );

// Parameters sent to Slack to control the appearance of Slackemon messages.
define( 'SLACKEMON_USERNAME', trim( getenv( 'SLACKEMON_USERNAME' ), '"' ) ?: 'Slackémon'  );
define(
  'SLACKEMON_ICON',
  getenv( 'SLACKEMON_ICON' ) ?:
  ( SLACKEMON_ENABLE_CUSTOM_EMOJI ? ':pokeball:' : ':monkey:' )
);

// The base URL used for all animated Pokemon sprite GIFs.
// Note that changing this will start your image cache again from scratch, as cache keys are based on the full URL.
define( 'SLACKEMON_ANIMATED_GIF_BASE',
  getenv( 'SLACKEMON_ANIMATED_GIF_BASE' ) ?:
  'https://raw.githubusercontent.com/tdmalone/pokecss-media/3e8efb8144e2401cbcb411e685bed53c9e8f430b/graphics/pokemon'
);

// The number of seconds that will be waited for when calling a background command/action.
// You will usually want 1 second for this, but some servers have been observed to need 2 seconds.
// Keep in mind that too long will cause Slack itself to timeout (it allows up to 3 seconds for the *total* roundtrip).
define( 'SLACKEMON_CURL_TIMEOUT', (int) getenv( 'SLACKEMON_CURL_TIMEOUT' ) ?: 1 );

// When running Slackemon on a server behind a proxy, you may find that calling background commands/actions takes
// longer than it should. When in this situation, you can define a local URL such as 'http://localhost/'.
// Defaults to whatever the inbound URL is set to.
// Please always include the trailing slash.
define( 'SLACKEMON_LOCAL_URL', getenv( 'SLACKEMON_LOCAL_URL' ) ?: SLACKEMON_INBOUND_URL );

// In-message pagination configuration.
// Don't set these values too high - you might hit the Slack attachment limit.
// Also, higher values means longer load time for the relevant messages, including action button responses.
define( 'SLACKEMON_ITEMS_PER_PAGE',   (int) getenv( 'SLACKEMON_ITEMS_PER_PAGE'   ) ?:  5 );
define( 'SLACKEMON_POKEMON_PER_PAGE', (int) getenv( 'SLACKEMON_POKEMON_PER_PAGE' ) ?:  5 );
define( 'SLACKEMON_POKEDEX_PER_PAGE', (int) getenv( 'SLACKEMON_POKEDEX_PER_PAGE' ) ?: 20 );

// The default fields that we generally pass through to Slack message attachments' 'mrkdwn_in' parameter. This
// generally doesn't need to be changed, but is defined here so it can easily be re-used. A bug exists on Slack's
// iPhone app that requires 'text' to be in this list even if you want mrkdwn to be used in other fields. Since we
// never *don't* want mrkdwn to be parsed, we just send through every field we might end up using it in. Note also
// that mrkdwn will *never* be parsed in the footer field on the iPhone app.
$default_mrkdwn_in = [
  'pretext',
  'text',
  'fields',
  'footer',
];
define( 'SLACKEMON_MRKDWN_IN', getenv( 'SLACKEMON_MRKDWN_IN' ) ?: $default_mrkdwn_in );

// Changing these values may not be fully supported at this stage.
define( 'SLACKEMON_MAX_IVS',         (int) getenv( 'SLACKEMON_MAX_IVS'         ) ?: 31 );
define( 'SLACKEMON_MIN_IVS',         (int) getenv( 'SLACKEMON_MIN_IVS'         ) ?:  0 );
define( 'SLACKEMON_MAX_KNOWN_MOVES', (int) getenv( 'SLACKEMON_MAX_KNOWN_MOVES' ) ?:  4 );

// The end!
