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

define( 'SLACKEMON_SLACK_TOKEN',        getenv( 'SLACKEMON_SLACK_TOKEN'        ) );
define( 'SLACKEMON_SLACK_TEAM_ID',      getenv( 'SLACKEMON_SLACK_TEAM_ID'      ) );
define( 'SLACKEMON_SLACK_KEY',          getenv( 'SLACKEMON_SLACK_KEY'          ) );

define( 'SLACKEMON_MAINTAINER',         getenv( 'SLACKEMON_MAINTAINER'         ) );
define( 'SLACKEMON_CRON_TOKEN',         getenv( 'SLACKEMON_CRON_TOKEN'         ) );
define( 'SLACKEMON_INBOUND_URL',        getenv( 'SLACKEMON_INBOUND_URL'        ) );

define( 'SLACKEMON_OPENWEATHERMAP_KEY', getenv( 'SLACKEMON_OPENWEATHERMAP_KEY' ) );

define( 'SLACKEMON_DATA_CACHE_METHOD',  getenv( 'SLACKEMON_DATA_CACHE_METHOD'  ) ?: 'local'               );
define( 'SLACKEMON_DATA_CACHE_FOLDER',  getenv( 'SLACKEMON_DATA_CACHE_FOLDER'  ) ?: '.data'               );
define( 'SLACKEMON_DATA_CACHE_BUCKET',  getenv( 'SLACKEMON_DATA_CACHE_BUCKET'  ) ?: 'slackemon-data'      );

define( 'SLACKEMON_IMAGE_CACHE_METHOD', getenv( 'SLACKEMON_IMAGE_CACHE_METHOD' ) ?: 'local'               );
define( 'SLACKEMON_IMAGE_CACHE_FOLDER', getenv( 'SLACKEMON_IMAGE_CACHE_FOLDER' ) ?: '.image-cache'        );
define( 'SLACKEMON_IMAGE_CACHE_BUCKET', getenv( 'SLACKEMON_IMAGE_CACHE_BUCKET' ) ?: 'slackemon-images'    );

define( 'SLACKEMON_AWS_ID',             getenv( 'SLACKEMON_AWS_ID'             ) );
define( 'SLACKEMON_AWS_SECRET',         getenv( 'SLACKEMON_AWS_SECRET'         ) );
define( 'SLACKEMON_AWS_REGION',         getenv( 'SLACKEMON_AWS_REGION'         ) ?: 'us-east-1'           );

define( 'SLACKEMON_TIMEZONE',           getenv( 'SLACKEMON_TIMEZONE'           ) ?: 'Australia/Melbourne' );
define( 'SLACKEMON_WEATHER_LAT_LON',    getenv( 'SLACKEMON_WEATHER_LAT_LON'    ) ?: '-37.81,144.96'       );
define( 'SLACKEMON_MONETARY_LOCALE',    getenv( 'SLACKEMON_MONETARY_LOCALE'    ) ?: 'en_AU'               );

// The end!
