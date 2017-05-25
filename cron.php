<?php

// TM 19/01/2017
// Enables commands to be simulated via a scheduled cron
// Schedule can be edited in config.php

define( 'SKIP_AUTH', true );
require_once( __DIR__ . '/init.php' );

// AUTH: Check if the cron token was set
if (
	( ! isset( $argv[1] ) || '--token=' . CRON_TOKEN !== $argv[1] ) &&
	( ! isset( $_POST['token'] ) || CRON_TOKEN !== $_POST['token'] ) &&
	( ! isset( $_GET['token'] ) || CRON_TOKEN !== $_GET['token'] )
) {
	http_response_code( 403 );
	exit( 'Not authorised for this cron request.' );
}

// Set the current time and date parameters
define( 'MINUTE', (int) date( 'i' ) );
define( 'HOUR',   (int) date( 'G' ) );
define( 'DATE',   (int) date( 'j' ) );
define( 'MONTH',  (int) date( 'n' ) );
define( 'DAY',    (int) date( 'w' ) );

// Output the current time and date environment
echo MINUTE . ' ' . HOUR . ' ' . DATE . ' ' . MONTH . ' ' . DAY;

// Check schedule, and run commands if it's time
foreach ( CRON_SCHEDULE as $item ) {

	// Decide whether to skip this item if it doesn't match every condition
	if (
		! check_cron_value( $item[0], MINUTE ) ||
		! check_cron_value( $item[1], HOUR ) ||
		! check_cron_value( $item[2], DATE ) ||
		! check_cron_value( $item[3], MONTH ) ||
		! check_cron_value( $item[4], DAY )
	) {
		continue;
	}

	// Prepare data
	$command = $item[5][0];
	$user_id = $item[5][1];
	$team_id = $item[5][2];

	// Run the command, and output the initial result back to the cron caller
	$result = run_automated_command( $command, $user_id, $team_id, [ 'run_mode' => 'cron' ] );
	echo $result;

} // Foreach CRON_SCHEDULE $item

// The end!
