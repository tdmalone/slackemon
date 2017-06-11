<?php
/**
 * Database abstraction functions.
 *
 * @package Slackemon
 */

slackemon_pg_connect();

//$result = slackemon_pg_query( 'DROP TABLE slackemon_users'   );
//$result = slackemon_pg_query( 'DROP TABLE slackemon_battles' );
//$result = slackemon_pg_query( 'DROP TABLE slackemon_spawns'  );

slackemon_set_up_db();

//$result = slackemon_pg_query( 'CREATE DATABASE slackemon' );

//slackemon_pg_query( 'SELECT * FROM pg_catalog.pg_tables' );

//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = 'slackemon_users'" );
//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = 'slackemon_battles'" );
//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = 'slackemon_spawns'" );

$user_id = 'UXXXXXXXX';

$player_data = [
  'registered' => time(),
  'user_id'    => $user_id,
  'team_id'    => defined( 'TEAM_ID' ) ? TEAM_ID : '',
  'status'     => 1, // 1 == Active, 2 == Muted, 3 == In Battle
  'xp'         => 0,
  'region'     => SLACKEMON_DEFAULT_REGION,
  'pokemon'    => [],
  'pokedex'    => [],
  'battles'    => [
    'won'               => 0,
    'participated'      => 0,
    'last_won'          => false,
    'last_participated' => false,
  ],
  'items'      => [],
  'version'    => SLACKEMON_VERSION,
];

$data = slackemon_pg_escape( json_encode( $player_data ) );

/*slackemon_pg_query(
  "INSERT INTO slackemon_users ( user_id, user_data, modified ) values ( '" . $user_id . "', '" . $data . "', '" . time() . "' )"
);*/

slackemon_pg_query( 'SELECT * FROM slackemon_users'   );
slackemon_pg_query( 'SELECT * FROM slackemon_battles' );
slackemon_pg_query( 'SELECT * FROM slackemon_spawns'  );

slackemon_pg_close();

/** Function to run to set up the DB, if we discover after our first query that it is not set up yet. */
function slackemon_set_up_db() {

  $result = slackemon_pg_query(
    'CREATE TABLE IF NOT EXISTS slackemon_users (
      id SERIAL PRIMARY KEY,
      user_id char(9) UNIQUE NOT NULL,
      user_data text NOT NULL,
      modified varchar(20) NOT NULL
    )'
  );

  $result = slackemon_pg_query(
    'CREATE TABLE IF NOT EXISTS slackemon_battles (
      id SERIAL PRIMARY KEY,
      battle_hash varchar(20) UNIQUE NOT NULL,
      battle_data text NOT NULL,
      modified varchar(20) NOT NULL
    )'
  );

  $result = slackemon_pg_query(
    'CREATE TABLE IF NOT EXISTS slackemon_spawns (
      id SERIAL PRIMARY KEY,
      spawn_ts varchar(20) NOT NULL,
      spawn_region varchar(20) NOT NULL,
      spawn_data text NOT NULL,
      modified varchar(20) NOT NULL
    )'
  );

} // Function slackemon_set_up_db

function slackemon_pg_escape( $string ) {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready() ) {
    return false;
  }

  return pg_escape_string( $_slackemon_postgres_connection, $string );

}

function slackemon_pg_query( $query ) {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready() ) {
    return false;
  }

  $result = pg_query( $_slackemon_postgres_connection, $query );

  // TODO: If query fails because table doesn't exist yet, we need to run slackemon_set_up_db() and then try again.

  if ( ! $result ) {
    error_log( 'A query error occurred.' );
    error_log( pg_result_error( $result ) );
    return false;
  }

  if ( ! pg_num_rows( $result ) ) {
    error_log( 'No data was returned.' );
    return false;
  }

  while ( $row = pg_fetch_row( $result ) ) {
    error_log( json_encode( $row ) );
  }

} // Function slackemon_pg_query

function slackemon_pg_connect() {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready( false ) ) {
    return false;
  }

  if ( ! SLACKEMON_DATABASE_URL ) {
    error_log( 'Database URL does not seem to be set.' );
    return false;
  }

  $url = parse_url( SLACKEMON_DATABASE_URL );

  if ( ! isset( $url['host'] ) || ! isset( $url['path'] ) || ! isset( $url['user'] ) || ! isset( $url['pass'] ) ) {
    error_log( 'Database URL does not seem to be valid.' );
    return false;
  }

  if ( ! $url['host'] || ! $url['path'] || '/' === $url['path'] || ! $url['user'] || ! $url['pass'] ) {
    error_log( 'Database URL does not seem to be valid.' );
    return false;
  }

  $_slackemon_postgres_connection  = pg_connect(
    'host='     . $url['host'] . ' ' .
    ( isset( $url['port'] ) && $url['port'] ? 'port=' . $url['port'] . ' ' : '' ) .
    'dbname='   . ltrim( $url['path'], '/' ) . ' ' .
    'user='     . $url['user'] . ' ' .
    'password=' . $url['pass']
  );

  if ( ! $_slackemon_postgres_connection ) {
    error_log( 'Cannot connect to database.' );
  }

} // Function slackemon_pg_connect

function slackemon_pg_close() {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready() ) {
    return false;
  }

  pg_close( $_slackemon_postgres_connection );

}

function slackemon_is_pg_ready( $check_connection = true ) {
  global $_slackemon_postgres_connection;

  if ( ! function_exists( 'pg_connect' ) ) {
    error_log( 'Postgres functions are not available.' );
    return false;
  }

  if ( $check_connection && ! $_slackemon_postgres_connection ) {
    error_log( 'Postgres connection does not seem to have been made.' );
    return false;
  }

  return true;

}

// The end!
