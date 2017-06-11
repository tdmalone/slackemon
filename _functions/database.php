<?php
/**
 * Database abstraction functions.
 *
 * @package Slackemon
 */

//$result = slackemon_pg_query( 'DROP TABLE ' . SLACKEMON_TABLE_PREFIX . 'players'   );
//$result = slackemon_pg_query( 'DROP TABLE ' . SLACKEMON_TABLE_PREFIX . 'battles' );
//$result = slackemon_pg_query( 'DROP TABLE ' . SLACKEMON_TABLE_PREFIX . 'spawns'  );

//$result = slackemon_pg_query( 'CREATE DATABASE slackemon' );

//slackemon_pg_query( 'SELECT * FROM pg_catalog.pg_tables' );

//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = '" . SLACKEMON_TABLE_PREFIX . "players'" );
//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = '" . SLACKEMON_TABLE_PREFIX . "battles'" );
//slackemon_pg_query( "SELECT * FROM information_schema.columns WHERE table_name = '" . SLACKEMON_TABLE_PREFIX . "spawns'" );

//$data = slackemon_pg_escape( json_encode( $player_data ) );

/*slackemon_pg_query(
  "INSERT INTO slackemon_users ( user_id, user_data, modified ) values ( '" . $user_id . "', '" . $data . "', '" . time() . "' )"
);*/

//slackemon_pg_query( 'SELECT * FROM ' . SLACKEMON_TABLE_PREFIX . 'players' );
//slackemon_pg_query( 'SELECT * FROM ' . SLACKEMON_TABLE_PREFIX . 'battles' );
//slackemon_pg_query( 'SELECT * FROM ' . SLACKEMON_TABLE_PREFIX . 'spawns'  );

//slackemon_pg_close();

/** Function to run to create a table in the DB, if we discover after our first query that it is not there yet. */
function slackemon_create_table( $table_name ) {

  $result = slackemon_pg_query(
    'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (
      id SERIAL PRIMARY KEY,
      filename varchar(100) UNIQUE NOT NULL,
      contents text NOT NULL,
      modified varchar(20) NOT NULL
    )'
  );

  if ( is_array( $result ) ) {
    slackemon_pg_debug( 'Table ' . $table_name . ' was created.' );
    return true;
  } else {
    slackemon_pg_debug( 'Table ' . $table_name . ' could not be created.' );
    return false;
  }

} // Function slackemon_set_up_db

function slackemon_pg_escape( $string, $escape_function = 'literal' ) {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready() ) {
    return false;
  }

  switch ( $escape_function ) {

    case 'literal':
      $escaped = pg_escape_literal( $_slackemon_postgres_connection, $string );
    break;

    case 'string':
      $escaped = pg_escape_string( $_slackemon_postgres_connection, $string );
    break;

    case 'identifier':
      $escaped = pg_escape_identifier( $_slackemon_postgres_connection, $string );
    break;

  }

  return $escaped;

}

function slackemon_pg_query( $query, $retries = 0 ) {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready() ) {
    return false;
  }

  slackemon_pg_debug( 'Running query `' .  $query . '`...' );

  $result = @pg_query( $_slackemon_postgres_connection, $query );

  if ( ! $result ) {

    $error_message = pg_last_error( $_slackemon_postgres_connection );

    // If query has failed because a table doesn't exist yet, create the table and re-run the query
    if ( preg_match( '/relation "(.*?)" does not exist/', $error_message, $matches ) ) {

      slackemon_pg_debug( 'Table does not exist, trying to create...' );

      if ( $retries ) {
        slackemon_pg_debug( 'Too many retries, cannot try again.' );
      }

      if ( ! $retries ) {
        slackemon_create_table( $matches[1] );
        slackemon_pg_debug( 'Retrying query...' );
        $retries++;
        return slackemon_pg_query( $query, $retries );
      }

    }

    slackemon_pg_debug( 'A query error occurred: ' . $error_message );

    return false;

  } // If not $result.

  if ( ! pg_num_rows( $result ) ) {
    $affected_rows = pg_affected_rows( $result );
    if ( $affected_rows ) {
      slackemon_pg_debug( 'Query successful, ' . $affected_rows . ' rows were affected.' );
      return $affected_rows;
    } else {
      slackemon_pg_debug( 'Query successful, but no data was returned or affected.' );
      return [];
    }
  }

  $rows = [];

  while ( $row = pg_fetch_row( $result ) ) {
    $rows[] = $row;
    slackemon_pg_debug( json_encode( $row ) );
  }

  return $rows;

} // Function slackemon_pg_query

function slackemon_pg_connect() {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready( [ 'check_connection' => false ] ) ) {
    return false;
  }

  if ( ! SLACKEMON_DATABASE_URL ) {
    slackemon_pg_debug( 'Database URL does not seem to be set.', true );
    return false;
  }

  $url = parse_url( SLACKEMON_DATABASE_URL );

  if ( ! isset( $url['host'] ) || ! isset( $url['path'] ) || ! isset( $url['user'] ) || ! isset( $url['pass'] ) ) {
    slackemon_pg_debug( 'Database URL does not seem to be valid.', true );
    return false;
  }

  if ( ! $url['host'] || ! $url['path'] || '/' === $url['path'] || ! $url['user'] || ! $url['pass'] ) {
    slackemon_pg_debug( 'Database URL does not seem to be valid.', true );
    return false;
  }

  $_slackemon_postgres_connection  = pg_connect(
    'host='     . $url['host'] . ' ' .
    ( isset( $url['port'] ) && $url['port'] ? 'port=' . $url['port'] . ' ' : '' ) .
    'dbname='   . ltrim( $url['path'], '/' ) . ' ' .
    'user='     . $url['user'] . ' ' .
    'password=' . $url['pass']
  );

  if ( $_slackemon_postgres_connection ) {
    slackemon_pg_debug( 'Connected to database.' );
    return true;
  } else {
    slackemon_pg_debug( 'Cannot connect to database.', true );
    return false;
  }

} // Function slackemon_pg_connect

function slackemon_pg_close() {
  global $_slackemon_postgres_connection;

  if ( ! slackemon_is_pg_ready( [ 'make_connection' => false ] ) ) {
    return false;
  }

  slackemon_pg_debug( 'Closing Postgres connection.' );
  pg_close( $_slackemon_postgres_connection );

}

function slackemon_is_pg_ready( $options = [] ) {
  global $_slackemon_postgres_connection;

  $options['check_connection'] = isset( $options['check_connection'] ) ? $options['check_connection'] : true;
  $options['make_connection']  = isset( $options['make_connection']  ) ? $options['make_connection']  : true;

  if ( ! $options['check_connection'] ) {
    $options['make_connection'] = false;
  }

  if ( ! function_exists( 'pg_connect' ) ) {
    slackemon_pg_debug( 'Postgres functions are not available.' );
    return false;
  }

  if ( $options['make_connection'] && ! $_slackemon_postgres_connection ) {
    slackemon_pg_connect();
  }

  if ( $options['check_connection'] && ! $_slackemon_postgres_connection ) {
    slackemon_pg_debug( 'Postgres connection does not seem to have been made.' );
    return false;
  }

  return true;

}

function slackemon_pg_debug( $message, $force_debug = false ) {

  if ( ! $force_debug && ! SLACKEMON_DATABASE_DEBUG ) {
    return;
  }

  error_log( $message );

} // Function slackemon_pg_debug

// The end!
