<?php

// TM 27/01/2017
// Functions to abstract access to commonly used APIs, including curl

require_once( __DIR__ . '/slack.php' );

// Set up AWS access if we are going to be using it
if ( 'aws' === SLACKEMON_DATA_CACHE_METHOD || 'aws' === SLACKEMON_IMAGE_CACHE_METHOD ) {

	require_once( __DIR__ . '/../vendor/autoload.php' );

	global $slackemon_s3;

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
			return file_get_contents( $filename );
		break;

		case 'aws':

			global $slackemon_s3;
			$remote_key = slackemon_calculate_hash( $filename )['path'];

			try {
				$result = $slackemon_s3->getObject([
					'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
					'Key'    => $remote_key,
				]);
			} catch ( Aws\S3\Exception\S3Exception $e ) {

				// TODO: Need some sort of error handling here

				slackemon_log_cache_event( '', $hash['filename'], 'file-get-error-aws-exception' );
				return false;

			}

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
			return file_put_contents( $filename, $data );
		break;

		case 'aws':

			global $slackemon_s3;
			$remote_key = slackemon_calculate_hash( $filename )['path'];

			try {
				$result = $slackemon_s3->putObject([
					'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
					'Key'    => $remote_key,
					'Body'   => $data,
					'ACL'    => 'bucket-owner-full-control',
					'Metadata' => [
						'original_filename' => $filename,
						'uploaded_by'       => SLACKEMON_INBOUND_URL,
					],
				]);
			} catch ( Aws\S3\Exception\S3Exception $e ) {

				// TODO: Need some sort of error handling here

				slackemon_log_cache_event( '', $hash['filename'], 'file-put-error-aws-exception' );
				return false;

			}

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
			$remote_key = slackemon_calculate_hash( $filename )['path'];

			return $slackemon_s3->doesObjectExist( SLACKEMON_DATA_CACHE_BUCKET, $remote_key );

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
			$remote_key = slackemon_calculate_hash( $filename )['path'];

			try {
				$result = $slackemon_s3->headObject([
					'Bucket' => SLACKEMON_DATA_CACHE_BUCKET,
					'Key'    => $remote_key,
				]);
			} catch ( Aws\S3\Exception\S3Exception $e ) {

				// TODO: Need some sort of error handling here

				slackemon_log_cache_event( '', $hash['filename'], 'file-mtime-error-aws-exception' );
				return false;

			}

			return date_timestamp_get( $result['LastModified'] );

		break; // Case aws

	} // Switch SLACKEMON_DATA_CACHE_METHOD
} // Function slackemon_filemtime

/** Get a URL using curl, and return the result. */
function slackemon_get_url( $url, $options = [] ) {

	$user_agent = 'Slackemon for Slack v' . SLACKEMON_VERSION . ' (https://github.com/tdmalone/slackemon)';

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // TODO: http://php.net/manual/en/function.curl-setopt.php#110457
	curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );

	if ( isset( $options['curl_options'] ) ) {
		foreach( $options['curl_options'] as $key => $value ) {
			curl_setopt( $ch, $key, $value );
		}
	}

	$result = curl_exec( $ch );

	if ( false === $result ) {
		send2slack( ':no_entry: ' . curl_error( $ch ) . "\n" . '_' . $url . '_' ); // Send errors to Slack
		curl_close( $ch );
		exit();
	}

	curl_close( $ch );
	return $result;
	
} // Function slackemon_get_url

/** Easily manage cache of individual URLs. */
function get_cached_url( $url, $options = [] ) {
	global $data_folder;

	// Get our hash, sending through curl_options if set, to ensure the file hash is unique when an auth key is provided
	$hash = slackemon_calculate_hash(
		$url, $data_folder, isset( $options['curl_options'] ) ? $options['curl_options'] : []
	);

	// Make sure full cache folder exists (if using the local data cache method)
	if ( 'local' === SLACKEMON_DATA_CACHE_METHOD && ! is_dir( $hash['folder'] ) ) {
		mkdir( $hash['folder'], 0777, true );
	}

	$file_exists = slackemon_file_exists( $hash['filename'] );

	// By default, the cache does not expire, unless an optional parameter is provided setting the age
	$is_cache_expired = false;
	if (
		isset( $options['expiry_age'] ) &&
		$options['expiry_age'] &&
		$file_exists &&
		slackemon_filemtime( $hash['filename'] ) < time() - $options['expiry_age']
	) {
		$is_cache_expired = true;
	}

	if ( $file_exists && ! $is_cache_expired ) {

		$data = slackemon_file_get_contents( $hash['filename'] );

		if ( $data ) {
			slackemon_log_cache_event( $url, $hash['filename'], 'hit' );
			return $data;
		} else {
			slackemon_log_cache_event( $url, $hash['filename'], 'empty' );
		}
	}

	// If we've got here, the file doesn't exist, is empty, or has expired, so we need to retrieve, store, and return it

	// Allow a waiting message to be sent, if requested
	if ( isset( $options['alert_if_uncached'] ) && $options['alert_if_uncached'] ) {
		send2slack( 'Updating my cache, won\'t be a moment...' );
	}

	// Allow a 'real URL' to be provided, useful for including eg. an access token which may need separating from
	// the cache. Take care when providing this that the query will always return the same information even if
	// auth'ed against a different user - i.e. it should be specific to the *organisation* and not a specific *user*.
	if ( isset( $options['real_url'] ) && $options['real_url'] ) {
		$real_url = $options['real_url'];
	} else {
		$real_url = $url;
	}

	slackemon_log_cache_event( $url, $hash['filename'], $is_cache_expired ? 'expired' : 'miss' );

	$data = slackemon_get_url( $real_url, $options );
	slackemon_file_put_contents( $hash['filename'], $data );

	return $data;

} // Function get_cached_url

function get_cached_image_url( $image_url ) {

	// Simply return the requested URL directly if the image cache is disabled
	if ( 'disabled' === SLACKEMON_IMAGE_CACHE_METHOD ) {
		return $image_url;
	}

	if ( ! $image_url ) {
		slackemon_log_cache_event( $image_url, '', 'image-error-missing-url' );
		return false;
	}

	$hash = slackemon_calculate_hash( $image_url, __DIR__ . '/../' . SLACKEMON_IMAGE_CACHE_FOLDER );
	$hash['filename'] .= ( 'aws' === SLACKEMON_IMAGE_CACHE_METHOD ? '.aws' : '' );
	
	// If the 'local' option is in use, this is where the image will be found
	$local_url = SLACKEMON_INBOUND_URL . $hash['path'];

	// Does image exist in local cache? Return the URL now - either the local URL, or the remote URL stored in the file
	if ( file_exists( $hash['filename'] ) ) {
		slackemon_log_cache_event( $image_url, $hash['filename'], 'image-hit' );
		return 'local' === SLACKEMON_IMAGE_CACHE_METHOD ? $local_url : file_get_contents( $hash['filename'] );
	}

	// Make sure full local cache folder exists
	if ( ! is_dir( $hash['folder'] ) ) {
		mkdir( $hash['folder'], 0777, true );
	}

	switch ( SLACKEMON_IMAGE_CACHE_METHOD ) {

		case 'local':

			// Store the image data locally, and return the local URL
	
			$image_data = slackemon_get_url( $image_url );

			if ( ! $image_data ) {
				slackemon_log_cache_event( $image_url, $hash['filename'], 'image-error-no-data-at-url' );
				return false;
			}
			
			slackemon_log_cache_event( $image_url, $hash['filename'], 'image-miss' );
			file_put_contents( $hash['filename'], $image_data );

			return $local_url;

		break; // Case local

		case 'aws':

			global $slackemon_s3;

			$remote_key = $hash['path'];

			// Check if the remote_key exists first, before we potentially get and upload the image again

			if ( $slackemon_s3->doesObjectExist( SLACKEMON_DATA_CACHE_BUCKET, $remote_key ) ) {

				$remote_url = $slackemon_s3->getObjectUrl( SLACKEMON_IMAGE_CACHE_BUCKET, $remote_key );
				slackemon_log_cache_event( $image_url, $hash['filename'], 'image-soft-miss' );

			} else {

				$image_data = slackemon_get_url( $image_url );

				if ( ! $image_data ) {
					slackemon_log_cache_event( $image_url, $hash['filename'], 'image-error-no-data-at-url' );
					return false;
				}

				try {
					$result = $slackemon_s3->putObject([
						'Bucket' => SLACKEMON_IMAGE_CACHE_BUCKET,
						'Key'    => $remote_key,
						'Body'   => $image_data,
						'ACL'    => 'public-read',
						'Metadata' => [
							'original_url' => $image_url,
							'uploaded_by'  => SLACKEMON_INBOUND_URL,
						],
						'CacheControl' => YEAR_IN_SECONDS,
					]);
				} catch ( Aws\S3\Exception\S3Exception $e ) {

					// Log an event and return the original image URL in case of exception
					slackemon_log_cache_event( $image_url, $hash['filename'], 'image-error-aws-exception' );
					return $image_url;

				}

				$remote_url = $result['ObjectURL'];
				slackemon_log_cache_event( $image_url, $hash['filename'], 'image-miss' );

			}

			// Store the AWS URL locally so we can use it next time
			file_put_contents( $hash['filename'], $remote_url );

			return $remote_url;

		break; // Case aws

	} // Switch SLACKEMON_IMAGE_CACHE_METHOD
} // Function get_cached_image_url

/**
 * Calculates a simple hash that can be used as a URL/filesystem safe name for caching purposes. Returns the separate
 * parts of the hash, folder, basename and the full filename.
 *
 * Used by get_cached_url, get_cached_image_url, slackemon_file_get_contents, etc.
 *
 * @param string $url_or_filename
 * @param string $base_dir
 * @param array  $context_data    Additional context to make sure the hash is unique, eg. an API token.
 * @return array
 */
function slackemon_calculate_hash( $url_or_filename, $base_dir = '', $context_data = [] ) {

	if ( ! $base_dir ) {
		$base_dir = $data_folder;
	}

	// Create our filename, made up of a partially human readable basename and a hash of the full, unique URL
	$hash      = md5( $url_or_filename . ( count( $context_data ) ? json_encode( $context_data ) : '' ) );
	$folder    = $base_dir . '/' . substr( $hash, 0, 1 );
	$basename  = basename( parse_url( $url_or_filename, PHP_URL_PATH ) ); // Limit filename to most relevant URL portion
	$basename  = preg_replace( '/[^A-Za-z0-9\.]/', '', $basename ); // Make sure filename doesn't have unsafe characters
	$basename  = substr( $basename, 0, 50 ); // Make sure filename isn't too long
	$filename  = $folder . '/' . $basename . '-' . $hash;

	return [
		'hash' 		=> $hash,
		'folder' 	=> $folder,
		'basename' 	=> $basename,
		'filename' 	=> $filename,
		'path'      => str_replace( $base_dir . '/', '', $filename ),
	];

} // Function slackemon_calculate_hash

/** Simple function to log cache events. */
function slackemon_log_cache_event( $url, $filename, $cache_status ) {

	// TODO

	return;

} // Function slackemon_log_cache_event

// The end!
