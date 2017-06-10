<?php
/**
 * Filesystem abstraction functions.
 *
 * @package Slackemon
 */

// Set up AWS access if we are going to be using it
if ( 'aws' === SLACKEMON_DATA_CACHE_METHOD || 'aws' === SLACKEMON_IMAGE_CACHE_METHOD ) {

  global $slackemon_s3;

  require_once( __DIR__ . '/aws/aws.phar' );

  $slackemon_s3 = new Aws\S3\S3Client([
    'version' => 'latest',
    'region'  => SLACKEMON_AWS_REGION,
    'credentials' => [
        	'key'    => SLACKEMON_AWS_ID,
        	'secret' => SLACKEMON_AWS_SECRET,
    	],
  ]);

}

/**
 * Semi drop-in replacement for PHP's file_get_contents (only supports the required arguments for now) which abstracts
 * access to either the local file system or an external data store, depending on SLACKEMON_DATA_CACHE_METHOD.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @link http://php.net/file_get_contents
 */
function slackemon_file_get_contents( $filename ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':

      if ( slackemon_file_exists( $filename ) ) {
        return file_get_contents( $filename );
      } else {
        return false;
      }

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->getObject([
          'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
          'Key'    => slackemon_get_s3_key( $filename ),
        ]);
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here

        slackemon_log_cache_event(
          '',
          slackemon_get_s3_key( $filename ),
          'file-get-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      slackemon_log_cache_event( '', $filename, 'aws-file-get', slackemon_get_s3_key( $filename ) );

      return $result['Body'];

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_file_get_contents

/**
 * Semi drop-in replacement for PHP's file_put_contents (only supports the required arguments for now) which abstracts
 * access to either the local file system or an external data store, depending on SLACKEMON_DATA_CACHE_METHOD.
 *
 * NOTE: Does not support stream resources for the $data param if an external data store is used.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @link http://php.net/file_put_contents
 */
function slackemon_file_put_contents( $filename, $data ) {

  // Support $data being an array, like file_put_contents() does
  if ( is_array( $data ) ) {
    $data = implode( '', $data );
  }

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':

      // Make sure the folder exists first
      $folder = pathinfo( $filename, PATHINFO_DIRNAME );
      if ( ! is_dir( $folder ) ) {
        mkdir( $folder, 0777, true );
      }

      return file_put_contents( $filename, $data );

    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->putObject([
          'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
          'Key'    => slackemon_get_s3_key( $filename ),
          'Body'   => $data,
          'ACL'    => 'bucket-owner-full-control',
          'Metadata' => [
            'original_filename' => $filename,
            'uploaded_by'       => SLACKEMON_INBOUND_URL,
          ],
        ]);
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here

        slackemon_log_cache_event(
          '',
          $hash['filename'],
          'file-put-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      slackemon_log_cache_event( '', $filename, 'aws-file-put', slackemon_get_s3_key( $filename ) );

      return $result;

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function file_put_contents

/**
 * Drop-in replacement for PHP's file_exists, which abstracts access to either the local file system or an external
 * data store, depending on SLACKEMON_DATA_CACHE_METHOD.
 *
 * DO NOT USE THIS FUNCTION FOR IMAGE CACHING. Image caching relies on its own method, and still needs local access
 * even when images are stored remotely.
 *
 * @link http://php.net/file_exists
 */
function slackemon_file_exists( $filename ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':
      return file_exists( $filename );
    break;

    case 'aws':
      global $slackemon_s3;
      return $slackemon_s3->doesObjectExist( SLACKEMON_DATA_CACHE_BUCKET, slackemon_get_s3_key( $filename ) );
    break;

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_file_exists

/**
 * Drop-in replacement for PHP's filemtime, which abstracts access to either the local file system or an external
 * data store, depending on SLACKEMON_DATA_CACHE_METHOD.
 *
 * @link http://php.net/filemtime
 */
function slackemon_filemtime( $filename ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':
      return filemtime( $filename );
    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->headObject([
          'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
          'Key'    => slackemon_get_s3_key( $filename ),
        ]);
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here

        slackemon_log_cache_event(
          '',
          $hash['filename'],
          'file-mtime-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      return date_timestamp_get( $result['LastModified'] );

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_filemtime

/** Semi drop-in replacement for PHP's rename() function, supporting S3. No support for the third $context parameter. */
function slackemon_rename( $old_filename, $new_filename ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':
      return rename( $old_filename, $new_filename );
    break;

    case 'aws':

      global $slackemon_s3;

      // TODO: Need to track return values of each step here, and skip the next step and return false on failure
      // Possibly should also, if the unlink fails, undo the put.

      $data = slackemon_file_get_contents( $old_filename );
      slackemon_file_put_contents( $new_filename, $data );
      slackemon_unlink( $old_filename );

      return true;

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_rename

/** Semi drop-in replacement for PHP's unlink() function, supporting S3. No support for the second $context parameter. */
function slackemon_unlink( $filename ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':
      return unlink( $filename );
    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->deleteObject([
          'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
          'Key'    => slackemon_get_s3_key( $filename ),
        ]);
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here

        slackemon_log_cache_event(
          '',
          $hash['filename'],
          'unlink-error-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      return true;

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_unlink

/** Sort of a replacement for PHP's glob() function, that supports S3's prefixes search if using S3 as the data cache. */
function slackemon_get_files_by_prefix( $prefix ) {

  switch ( SLACKEMON_DATA_CACHE_METHOD ) {

    case 'local':
      return glob( $prefix . '*' );
    break;

    case 'aws':

      global $slackemon_s3;

      try {
        $result = $slackemon_s3->listObjectsV2([
          'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
          'Prefix' => slackemon_get_s3_key( $prefix ),
        ]);
      } catch ( Aws\S3\Exception\S3Exception $e ) {

        // TODO: Need some sort of error handling here

        slackemon_log_cache_event(
          '',
          $hash['filename'],
          'list-objects-aws-exception',
          $e->getAwsErrorMessage()
        );

        return false;

      }

      return array_map( function( $object ) {
        return $object['Key'];
      }, $result['Contents'] );

    break; // Case aws

  } // Switch SLACKEMON_DATA_CACHE_METHOD

} // Function slackemon_get_files_by_prefix

/** Abstracts the method we use to calculate the key for S3 storage. */
function slackemon_get_s3_key( $filename ) {
  global $data_folder;

  return trim( str_replace( $data_folder, '', $filename ), '/' );

}

// The end!
