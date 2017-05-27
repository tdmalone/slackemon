<?php

// TM 09/12/2016
// Apply Slackemon config

$SLACKEMON_SLACK_TOKEN        = getenv( 'SLACKEMON_SLACK_TOKEN' );
$SLACKEMON_SLACK_TEAM_ID      = getenv( 'SLACKEMON_SLACK_TEAM_ID' );
$SLACKEMON_SLACK_MAINTAINER   = getenv( 'SLACKEMON_SLACK_MAINTAINER' );

$SLACKEMON_SLACK_KEY          = getenv( 'SLACKEMON_SLACK_KEY' );
$SLACKEMON_OPENWEATHERMAP_KEY = getenv( 'SLACKEMON_OPENWEATHERMAP_KEY' );

$SLACKEMON_CRON_TOKEN         = getenv( 'SLACKEMON_CRON_TOKEN' );

$SLACKEMON_INBOUND_URL        = getenv( 'SLACKEMON_INBOUND_URL' );

$SLACKEMON_DATA_CACHE_METHOD  = getenv( 'SLACKEMON_DATA_CACHE_METHOD' );
$SLACKEMON_DATA_CACHE_FOLDER  = getenv( 'SLACKEMON_DATA_CACHE_FOLDER' );
$SLACKEMON_DATA_CACHE_BUCKET  = getenv( 'SLACKEMON_DATA_CACHE_BUCKET' );

$SLACKEMON_IMAGE_CACHE_METHOD = getenv( 'SLACKEMON_IMAGE_CACHE_METHOD' );
$SLACKEMON_IMAGE_CACHE_FOLDER = getenv( 'SLACKEMON_IMAGE_CACHE_FOLDER' );
$SLACKEMON_IMAGE_CACHE_BUCKET = getenv( 'SLACKEMON_IMAGE_CACHE_BUCKET' );

$SLACKEMON_AWS_ID             = getenv( 'SLACKEMON_AWS_ID' );
$SLACKEMON_AWS_SECRET         = getenv( 'SLACKEMON_AWS_SECRET' );
$SLACKEMON_AWS_REGION         = getenv( 'SLACKEMON_AWS_REGION' );

$SLACKEMON_TIMEZONE           = getenv( 'SLACKEMON_TIMEZONE' );
$SLACKEMON_WEATHER_LAT_LON    = getenv( 'SLACKEMON_WEATHER_LAT_LON' );
$SLACKEMON_MONETARY_LOCALE    = getenv( 'SLACKEMON_MONETARY_LOCALE' );

if ( ! ( $SLACKEMON_SLACK_TOKEN && $SLACKEMON_SLACK_TEAM_ID && $SLACKEMON_SLACK_GLOBAL_MAINTAINER && $SLACKEMON_CRON_TOKEN && $SLACKEMON_AWS_ID && $SLACKEMON_AWS_SECRET && $SLACKEMON_OPENWEATHERMAP_TOKEN ) ) {

    // If one of the above fields is false, try to fetch it from Dotenv
    require __DIR__ . '/vendor/autoload.php';
    $dotenv = new Dotenv\Dotenv();
    if ( getenv( 'APP_ENV' ) === 'development' ) {
        $dotenv->load( __DIR__ );
    }

    // Assign variable from newly populated env
    $SLACKEMON_SLACK_TOKEN             = getenv( 'SLACKEMON_SLACK_TOKEN' );
    $SLACKEMON_SLACK_TEAM_ID           = getenv( 'SLACKEMON_SLACK_TEAM_ID' );
    $SLACKEMON_SLACK_GLOBAL_MAINTAINER = getenv( 'SLACKEMON_SLACK_GLOBAL_MAINTAINER' );
    $SLACKEMON_CRON_TOKEN              = getenv( 'SLACKEMON_CRON_TOKEN' );
    $SLACKEMON_AWS_ID                  = getenv( 'SLACKEMON_AWS_ID' );
    $SLACKEMON_AWS_SECRET              = getenv( 'SLACKEMON_AWS_SECRET' );
    $SLACKEMON_OPENWEATHERMAP_TOKEN    = getenv( 'SLACKEMON_OPENWEATHERMAP_TOKEN' );
    # print('Loaded from Dotenv lib');

} else {
    # print('Loaded from memory');
}

// Slack App token
define( 'SLACK_TOKEN_TXXXXXXXX', $SLACKEMON_SLACK_TOKEN );

// The Slack user ID of the team maintainer, who people can be directed to contact for help when errors occur.
define( 'MAINTAINER_TXXXXXXXX', $SLACKEMON_SLACK_GLOBAL_MAINTAINER );

// API keys for accessing external services
define( 'SLACK_KEY__TXXXXXXXX', 'xoxp-0000000000-000000000000-000000000000-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
define( 'WEATHER_KEY', $SLACKEMON_OPENWEATHERMAP_TOKEN ); // Optional

// Set a random token that cron.php will check for before running
// When you cron cron.php, make sure you send the token too - eg. cron.php?token=XXXXX or `php cron.php --token=XXXXX`
// This protects the cron from being invoked by another source
define( 'CRON_TOKEN', $SLACKEMON_CRON_TOKEN );

// Cron schedule (will only work if cron.php is run every minute by your system; see above)
// TODO: AN ENVIRONMENT VARIABLE ALTERNATIVE FOR THE CRON SCHEDULE DOES NOT YET EXIST
define( 'CRON_SCHEDULE', [

	[ '*', '*', '*', '*', '*', [ '/slackemon maybe-spawn', 		 $SLACKEMON_SLACK_GLOBAL_MAINTAINER, 'TXXXXXXXX' ] ], // Runs every minute
	[ '*', '*', '*', '*', '*', [ '/slackemon battle-updates', 	 $SLACKEMON_SLACK_GLOBAL_MAINTAINER, 'TXXXXXXXX' ] ], // Runs every minute
	[ '1', '1', '*', '*', '*', [ '/slackemon happiness-updates', $SLACKEMON_SLACK_GLOBAL_MAINTAINER, 'TXXXXXXXX' ] ], // Runs once a day
		
	// The format is almost just like normal crons, and supports * / and - values:
	// [ 'MIN', 'HOUR', 'DATE', 'MONTH', 'DAY', [ '/COMMAND ARGS', 'USER-ID', 'TEAM-ID' ] ],

]);

// The URL used for the image cache (if caching locally) and for cron to trigger commands
// PLEASE INCLUDE THE TRAILING SLASH
define( 'INBOUND_URL', 'https://example.com/slackemon/' );

// Configure the data cache/store - two methods are support; either local, or via an AWS S3 bucket
// Local is recommended, unless you're hosting on a service with a ephemeral filesystem
// The data cache cannot be disabled - it is required to run Slackemon
define( 'DATA_CACHE_METHOD', 'local' ); // 'aws' or 'local'
define( 'DATA_CACHE_FOLDER', __DIR__ . '/.data' ); // Only required if using local
define( 'DATA_CACHE_BUCKET', 'slackemon-data'   ); // Only required if using aws

// Configure the image cache - two methods are supported; either local, or via an AWS S3 bucket (or you can disable).
// You should use the AWS option unless you are hosting Slackemon on a suitably fast server/connection.
// Please do NOT disable the image cache unless doing so for a short time for testing - otherwise it will cause unfair
// load for the external resources that Slackemon uses.
define( 'IMAGE_CACHE_METHOD', 'local' ); // 'aws', 'local', or 'disabled'
define( 'IMAGE_CACHE_FOLDER', __DIR__ . '/.image-cache' ); // Only required if using local
define( 'IMAGE_CACHE_BUCKET', 'slackemon-images'        ); // Only required if using aws

// AWS access details
// Only required if using 'aws' for either the data cache or image cache above
define( 'AWS_ID',     $SLACKEMON_AWS_ID );
define( 'AWS_SECRET', $SLACKEMON_AWS_SECRET );
define( 'AWS_REGION', 'us-east-1' ); // 'us-east-1' is recommended, as it is the same region Slack uses!

// Default timezone
// User specific timezones are not yet supported
define( 'DEFAULT_TIMEZONE', 'Australia/Melbourne' );

// Weather location (default is Melbourne, Australia)
define( 'SLACKEMON_WEATHER_LAT_LON', '-37.81,144.96' );

// Default monetary locale
// Used for displaying the correct currency formatting
define( 'DEFAULT_MONETARY_LOCALE', 'en_AU' );

// The end!
