<?php
/**
 * Filesystem abstraction functions that can be used across local, postgres and aws data/cache stores.
 *
 * Functions include:
 * slackemon_file_get_contents
 * slackemon_file_put_contents
 * slackemon_file_exists
 * slackemon_filemtime
 * slackemon_rename
 * slackemon_unlink
 * slackemon_get_files_by_prefix - kindof like glob()
 *
 * Functions generally take the same standard arguments that their PHP counterparts do, with the addition of the
 * $purpose argument, which can be set to either 'cache' or 'store'.
 *
 * @package Slackemon
 */

// Set up Postgres access if we are going to be using it.
if ( 'postgres' === SLACKEMON_DATA_STORE_METHOD ) {
  require_once( __DIR__ . '/database.php' );
}

// Set up AWS access if we are going to be using it.
if (
  'aws' === SLACKEMON_DATA_STORE_METHOD ||
  'aws' === SLACKEMON_DATA_CACHE_METHOD ||
  'aws' === SLACKEMON_IMAGE_CACHE_METHOD
) {

  global $slackemon_s3;

  require_once( __DIR__ . '/../vendor/autoload.php' );

  $slackemon_s3 = new Aws\S3\S3Client(
    [
      'version' => 'latest',
      'region'  => SLACKEMON_AWS_REGION,
      'credentials' => [
          'key'    => SLACKEMON_AWS_ID,
          'secret' => SLACKEMON_AWS_SECRET,
      ],
    ]
  );

}

/**
 * Semi drop-in replacement for PHP's file_get_contents (only supports the required arguments for now) which abstracts
 * access to either the local file system or an external data store, depending on SLACKEMON_DATA_CACHE/STORE_METHOD.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @param string $filename Name of the file to read.
 * @param string $purpose  The purpose of the read - 'cache' or 'store'.
 * @link http://php.net/file_get_contents
 */
function slackemon_file_get_contents( $filename, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':

      if ( slackemon_file_exists( $filename, $purpose ) ) {
        $return = file_get_contents( $filename );
      }

    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $filename );

      $result = slackemon_pg_query(
        "SELECT contents FROM {$key['table']} WHERE filename = '{$key['filename']}'"
      );

      if ( count( $result ) ) {
        $return = $result[0][0];
        slackemon_pg_debug( $result[0][0] );
      } else {
        slackemon_pg_debug( json_encode( $result ) );
      }

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->getObject(
          [
            'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
            'Key'    => slackemon_get_s3_key( $filename ),
          ]
        );
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here.

        slackemon_cache_debug(
          '',
          slackemon_get_s3_key( $filename ),
          'file-get-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      slackemon_cache_debug( '', $filename, 'aws-file-get', slackemon_get_s3_key( $filename ) );

      $return = $result['Body'];

    break; // Case aws.

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_file_get_contents

/**
 * Semi drop-in replacement for PHP's file_put_contents (only supports the required arguments for now) which abstracts
 * access to either the local file system or an external data store, depending on SLACKEMON_DATA_CACHE/STORE_METHOD.
 *
 * NOTE: Does not support stream resources for the $data param if an external data store is used.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @param string $filename Name of the file to write to.
 * @param string $data     The data to write to the file.
 * @param string $purpose  The purpose of the write - 'cache' or 'store'.
 * @link http://php.net/file_put_contents
 */
function slackemon_file_put_contents( $filename, $data, $purpose ) {

  // Support $data being an array, like file_put_contents() does.
  if ( is_array( $data ) ) {
    $data = implode( '', $data );
  }

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':

      // Make sure the folder exists first.
      $folder = pathinfo( $filename, PATHINFO_DIRNAME );
      if ( ! is_dir( $folder ) ) {
        mkdir( $folder, 0777, true );
      }

      $return = file_put_contents( $filename, $data );

    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $filename );
      $contents = slackemon_pg_escape( $data );
      $modified = slackemon_pg_escape( time() );

      $result = slackemon_pg_query(
        "UPDATE {$key['table']} SET contents = {$contents}, modified = {$modified}
        WHERE filename = '{$key['filename']}'"
      );

      // If we got no result, it means there were no affected rows and thus this 'file' doesn't exist yet
      // So, let's create it
      if ( ! $result ) {
        $result = slackemon_pg_query(
          "INSERT INTO {$key['table']} ( filename, contents, modified )
          VALUES ( '{$key['filename']}', {$contents}, {$modified} )"
        );
      }

      if ( $result ) {
        $return = true;
      }

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->putObject(
          [
            'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
            'Key'    => slackemon_get_s3_key( $filename ),
            'Body'   => $data,
            'ACL'    => 'bucket-owner-full-control',
            'Metadata' => [
              'original_filename' => $filename,
              'uploaded_by'       => SLACKEMON_INBOUND_URL,
            ],
          ]
        );
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here.

        slackemon_cache_debug(
          '',
          $hash['filename'],
          'file-put-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      slackemon_cache_debug( '', $filename, 'aws-file-put', slackemon_get_s3_key( $filename ) );

      $return = $result;

    break; // Case aws.

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_file_put_contents

/**
 * Drop-in replacement for PHP's file_exists, which abstracts access to either the local file system or an external
 * data store, depending on SLACKEMON_DATA_CACHE/STORE_METHOD.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @param string $filename The filename to check existence of.
 * @param string $purpose  The purpose of the check - 'cache' or 'store'.
 * @link http://php.net/file_exists
 */
function slackemon_file_exists( $filename, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':
      $return = file_exists( $filename );
    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $filename );

      $result = slackemon_pg_query(
        "SELECT filename FROM {$key['table']} WHERE filename = '{$key['filename']}'"
      );

      if ( count( $result ) ) {
        $return = true;
      } else {
        $return = false;
      }

      slackemon_pg_debug( ( $return ? 'true' : 'false' ) . ': ' . json_encode( $result ) );

    break;

    case 'aws':
      global $slackemon_s3;
      $return = $slackemon_s3->doesObjectExist( SLACKEMON_DATA_CACHE_BUCKET, slackemon_get_s3_key( $filename ) );
    break;

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_file_exists

/**
 * Drop-in replacement for PHP's filemtime, which abstracts access to either the local file system or an external
 * data store, depending on SLACKEMON_DATA_CACHE/STORE_METHOD.
 *
 * @param string $filename The filename to return the modified time of.
 * @param string $purpose  The purpose of the file - 'cache' or 'store'.
 * @link http://php.net/filemtime
 */
function slackemon_filemtime( $filename, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':
      $return = filemtime( $filename );
    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $filename );

      $result = slackemon_pg_query(
        "SELECT modified FROM {$key['table']} WHERE filename = '{$key['filename']}'"
      );

      if ( count( $result ) ) {
        $return = $result[0][0];
        slackemon_pg_debug( $result[0][0] );
      } else {
        slackemon_pg_debug( json_encode( $result ) );
      }

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->headObject(
          [
            'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
            'Key'    => slackemon_get_s3_key( $filename ),
          ]
        );
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here.

        slackemon_cache_debug(
          '',
          $hash['filename'],
          'file-mtime-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      $return = date_timestamp_get( $result['LastModified'] );

    break; // Case aws.

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_filemtime

/**
 * Semi drop-in replacement for PHP's rename() function, supporting S3. No support for the third $context parameter.
 *
 * @param string $old_filename The old filename.
 * @param string $new_filename The new filename.
 * @param string $purpose      The purpose of the files - 'cache' or 'store'.
 * @link http://php.net/rename
 */
function slackemon_rename( $old_filename, $new_filename, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':

      $return = rename( $old_filename, $new_filename );

    break;

    case 'postgres':
    case 'aws':

      // TODO: Need to track return values of each step here, and skip the next step and return false on failure
      // Possibly should also, if the unlink fails, undo the put.

      $data = slackemon_file_get_contents( $old_filename, $purpose );
      slackemon_file_put_contents( $new_filename, $data, $purpose );
      slackemon_unlink( $old_filename, $purpose );

    break;

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_rename

/**
 * Semi drop-in replacement for PHP's unlink() function, supporting S3. No support for the second $context parameter.
 *
 * @param string $filename The filename to remove.
 * @param string $purpose  The purpose of the file - 'cache' or 'store'.
 * @link http://php.net/unlink
 */
function slackemon_unlink( $filename, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':
      $return = unlink( $filename );
    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $filename );
      $result = slackemon_pg_query( "DELETE FROM {$key['table']} WHERE filename = '{$key['filename']}'" );

      if ( $result ) {
        $return = true;
      }

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $slackemon_s3->deleteObject(
          [
            'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
            'Key'    => slackemon_get_s3_key( $filename ),
          ]
        );
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here.

        slackemon_cache_debug(
          '',
          $hash['filename'],
          'unlink-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      $return = true;

    break; // Case aws.

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_unlink

/**
 * Sort of a replacement for PHP's glob() function, that supports S3's prefixes search if using S3 as the data cache.
 *
 * @param string $prefix  The filename prefix to search for.
 * @param string $purpose The purpose of the search - 'cache' or 'store'.
 * @link http://php.net/glob
 */
function slackemon_get_files_by_prefix( $prefix, $purpose ) {

  switch ( slackemon_get_data_method( $purpose ) ) {

    case 'local':
      $return = glob( $prefix . '*' );
    break;

    case 'postgres':

      $key = slackemon_get_pg_key( $prefix );

      $result = slackemon_pg_query(
        "SELECT filename FROM {$key['table']} WHERE filename LIKE '{$key['filename']}%'"
      );

      $return = array_map(
        function( $filename ) use ( $key ) {
          return $key['table_raw'] . '/' . $filename[0];
        },
        $result
      );

      slackemon_pg_debug( json_encode( $return ) );

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->listObjectsV2(
          [
            'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
            'Prefix' => slackemon_get_s3_key( $prefix ),
          ]
        );
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here.

        slackemon_cache_debug(
          '',
          $hash['filename'],
          'list-objects-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      $return = array_map(
        function( $object ) {
          return $object['Key'];
        },
        $result['Contents']
      );

    break; // Case aws.

  } // Switch slackemon_get_data_method

  if ( isset( $return ) ) {
    return $return;
  } else {
    return false;
  }

} // Function slackemon_get_files_by_prefix

/**
 * Abstracts the method we use to convert a filename to a database table and row.
 *
 * @param string $filename The filename to convert to an address of the data in Postgres.
 */
function slackemon_get_pg_key( $filename ) {
  global $data_folder;

  $filename_trimmed = trim( str_replace( $data_folder, '', $filename ), '/' );
  $filename_parts   = pathinfo( $filename_trimmed );

  // If we're just looking for a directory and not a particular file, we need to swap things around
  if ( '.' === $filename_parts['dirname'] ) {
    $filename_parts['dirname'] = $filename_parts['filename'];
    $filename_parts['filename'] = '';
  }

  $key = [
    'table'        => slackemon_pg_escape( SLACKEMON_TABLE_PREFIX . $filename_parts['dirname'], 'identifier' ),
    'filename'     => slackemon_pg_escape( $filename_parts['filename'], 'string' ),
    'table_raw'    => $filename_parts['dirname'],
    'filename_raw' => $filename_parts['filename'],
  ];

  return $key;

}

/**
 * Abstracts the method we use to calculate the key for S3 storage.
 *
 * @param string $filename The filename to convert to the format we use for S3 object keys.
 */
function slackemon_get_s3_key( $filename ) {
  global $data_folder;

  $key = trim( str_replace( $data_folder, '', $filename ), '/' );

  return $key;

}

/**
 * Returns the appropriate data storage method based on the purpose of the request.
 * In a practical sense, this allows us to use the same functions eg. slackemon_file_get_contents for both data caching
 * and data storage, while sending through the purpose of our usage each time so the correct method is used.
 *
 * @param string $purpose The purpose of a data read/write event. Accepts 'cache', 'store' or 'local', the latter of
 *                        which forces a local store and should only be used for very temporary data storage.
 */
function slackemon_get_data_method( $purpose ) {

  switch ( $purpose ) {

    case 'cache':
      $method = SLACKEMON_DATA_CACHE_METHOD;
    break;

    case 'store':
      $method = SLACKEMON_DATA_STORE_METHOD;
    break;

    case 'local':
      $method = 'local';
    break;

  }

  return $method;

} // Function slackemon_get_data_method

// The end!
