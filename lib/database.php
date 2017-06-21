<?php
/**
 * Database abstraction functions.
 *
 * @package Slackemon
 */

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
  }

  slackemon_pg_debug( 'Table ' . $table_name . ' could not be created.' );
  return false;

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

  slackemon_pg_debug( 'Connecting to database...' );

  // We're about to try connecting to the Postgres server. If the database doesn't exist, we'll attempt to create it.
  set_error_handler( 'slackemon_create_database_on_connection_error' );
  $_slackemon_postgres_connection = pg_connect( slackemon_get_database_connection_string( $url ) );
  restore_error_handler();

  // Determine if the database was just created, and if so, try our main connection again.
  global $_slackemon_postgres_db_just_created;
  if ( $_slackemon_postgres_db_just_created ) {
    $_slackemon_postgres_db_just_created = false;
    return slackemon_pg_connect();
  }

  if ( $_slackemon_postgres_connection ) {
    slackemon_pg_debug( 'Connected to database.' );
    return true;
  } else {

    slackemon_pg_debug( 'Cannot connect to database.', true );

    send2slack(
      'Oops, I can\'t connect to the Slackémon database! Please chat to <@' . SLACKEMON_MAINTAINER . '>, who ' .
      'may be able to solve this for you.'
    );

    slackemon_exit();

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
    return false;
  }

  return true;

}

function slackemon_get_database_connection_string( $url = '', $include_db_name = true ) {

  if ( ! $url ) {
    $url = parse_url( SLACKEMON_DATABASE_URL );
  }

  $connection_string = (
    'host='     . $url['host'] . ' ' .
    ( isset( $url['port'] ) && $url['port'] ? 'port=' . $url['port'] . ' ' : '' ) .
    ( $include_db_name ? 'dbname='   . ltrim( $url['path'], '/' ) . ' ' : '' ) .
    'user='     . $url['user'] . ' ' .
    'password=' . $url['pass']
  );

  return $connection_string;

} // Function slackemon_get_database_connection_string

/**
 * Attempts to create the Slackemon database if it doesn't exist. In a properly managed hosting environment, the
 * database name we're given should already exist. But on eg. a quick local Docker development environment, we
 * may have just gotten started with no DB yet.
 *
 * This function should only be called by being set as an error_handler before attempting to connect to the database.
 */
function slackemon_create_database_on_connection_error( $errno, $errstr, $errfile, $errline ) {
  global $_slackemon_postgres_connection;

  // Avoid looping if this fails again.
  restore_error_handler();

  if ( preg_match( '/database .*? does not exist/', $errstr, $matches ) ) {

    slackemon_pg_debug( 'Database does not exist, attempting to create it...', true );

    $url = parse_url( SLACKEMON_DATABASE_URL );
    $_slackemon_postgres_connection = pg_connect( slackemon_get_database_connection_string( $url, false ) );
    $query = slackemon_pg_query( 'CREATE DATABASE ' . ltrim( $url['path'], '/' ) );

    if ( false === $query ) {
      slackemon_pg_debug( 'Database could not be created.', true );
      slackemon_pg_close();
      return;
    }

    // Disconnect this connection, since it was created without the database name being provided.
    pg_close( $_slackemon_postgres_connection );

    // Set a global flag that the function that called this 'error handler' will be able to check.
    global $_slackemon_postgres_db_just_created;
    $_slackemon_postgres_db_just_created = true;

  } else {
    slackemon_pg_debug( $errstr, true );
  }

} // Function slackemon_create_database_on_connection_error

function slackemon_pg_debug( $message, $force_debug = false ) {

  if ( ! $force_debug && ! SLACKEMON_DATABASE_DEBUG ) {
    return;
  }

  slackemon_error_log( $message );

}

// The end!
