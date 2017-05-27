<?php

// TM 09/12/2016
// Config options for Slackemon

// Although Slackemon installs only officially support one Slack team at present, it is not far away from supporting
// multiple teams on one install. Many of the config constants below therefore contain a team ID in the constant name.

// ENSURE YOU COPY THIS FILE TO CONFIG.PHP BEFORE YOU EDIT IT.
// Configuration via environment variables is also available. If you want to use env vars instead, there is no need to
// copy or edit this file - just ensure you define each of the constants referenced below.

// require __DIR__ . '/vendor/autoload.php';

// use Dotenv\Dotenv;

// $dotenv = new Dotenv(__DIR__);
// $dotenv->load();

// Slack App token
define( 'SLACK_TOKEN_TXXXXXXXX', 'XXXXXXXXXXXXXXXXXXXXXXXX' );

// The Slack user ID of the team maintainer, who people can be directed to contact for help when errors occur.
define( 'MAINTAINER_TXXXXXXXX', 'UXXXXXXXX' );

// API keys for accessing external services
define( 'SLACK_KEY__TXXXXXXXX', 'xoxp-0000000000-000000000000-000000000000-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
define( 'WEATHER_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ); // Optional

// Set a random token that cron.php will check for before running
// When you cron cron.php, make sure you send the token too - eg. cron.php?token=XXXXX or `php cron.php --token=XXXXX`
// This protects the cron from being invoked by another source
define( 'CRON_TOKEN', 'XXXXXXXXXXXXXXXXXXXX' );

// Cron schedule (will only work if cron.php is run every minute by your system; see above)
// TODO: AN ENVIRONMENT VARIABLE ALTERNATIVE FOR THE CRON SCHEDULE DOES NOT YET EXIST
define( 'CRON_SCHEDULE', [

	[ '*', '*', '*', '*', '*', [ '/slackemon maybe-spawn', 		 'UXXXXXXXX', 'TXXXXXXXX' ] ], // Runs every minute
	[ '*', '*', '*', '*', '*', [ '/slackemon battle-updates', 	 'UXXXXXXXX', 'TXXXXXXXX' ] ], // Runs every minute
	[ '1', '1', '*', '*', '*', [ '/slackemon happiness-updates', 'UXXXXXXXX', 'TXXXXXXXX' ] ], // Runs once a day
		
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
define( 'AWS_ID',     'XXXXXXXXXXXXXXXXXXXX' );
define( 'AWS_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
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