<?php

// TM 27/01/2017
// Functions to abstract access to commonly used APIs, including curl

require( __DIR__ . '/slack.php'	 );
require( __DIR__ . '/pokemon/pokemon.php' );

/** Get a URL using curl, and return the result. TODO: Allow e-mail/version/etc. here to be changed in config. */
function get_url( $url, $options = [] ) {

	// Get the maintainer's e-mail, or fallback to the Slackemon developer's e-mail address if not available
	$maintainer_email = 'tdmalone@gmail.com';

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // TODO: See http://php.net/manual/en/function.curl-setopt.php#110457
	curl_setopt( $ch, CURLOPT_USERAGENT, 'Slackemon for Slack (' . $maintainer_email . ')' );

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
	
} // Function get_url

/** Easily manage cache of individual URLs. */
function get_cached_url( $url, $options = [] ) {
	global $data_folder;

	// Create our filename, made up of a partially human readable basename and a hash of the full, unique URL
	// We also append a JSON hash of the curl_options, if set, as these can often be used to change auth options 
	$hash = md5( $url . ( isset( $options['curl_options'] ) ? json_encode( $options['curl_options'] ) : '' ) );
	$folder = $data_folder . '/' . substr( $hash, 0, 1 );
	$basename = basename( parse_url( $url, PHP_URL_PATH ) ); // Limit filename to just the most relevant URL portion
	$basename = preg_replace( '/[^A-Za-z0-9]/', '', $basename ); // Make sure filename doesn't have unsafe characters
	$basename = substr( $basename, 0, 50 ); // Make sure filename isn't too long
	$filename = $folder . '/' . $basename . '-' . $hash;

	// Make sure full cache folder exists
	if ( ! is_dir( $folder ) ) {
		mkdir( $folder, 0777, true );
	}

	// By default, the cache does not expire, unless an optional parameter is provided setting the age
	$is_cache_expired = false;
	if (
		isset( $options['expiry_age'] ) &&
		$options['expiry_age'] &&
		file_exists( $filename ) &&
		filemtime( $filename ) < time() - $options['expiry_age']
	) {
		$is_cache_expired = true;
	}

	if ( file_exists( $filename ) && file_get_contents( $filename ) && ! $is_cache_expired ) {

		log_cache_event( $url, $filename, 'hit' );
		$data = file_get_contents( $filename );
		
	} else {

		// Allow a waiting message to be sent, if requested
		if ( isset( $options['alert_if_uncached'] ) && $options['alert_if_uncached'] ) {
			send2slack( 'Updating my cache, won\'t be a moment...' );
		}

		// Allow a 'real URL' to be provided, useful for including eg. an access token which may need separating from
		// the cache. Take care when providing this that the query will always return the same information even if
		// auth'ed against a different user - i.e. it should be specific to the *company* and not a specific *user*.
		if ( isset( $options['real_url'] ) && $options['real_url'] ) {
			$real_url = $options['real_url'];
		} else {
			$real_url = $url;
		}

		log_cache_event( $url, $filename, $is_cache_expired ? 'expired' : 'miss' );

		$data = get_url( $real_url, $options );
		file_put_contents( $filename, $data );

	}

	return $data;
} // Function get_cached_url

function get_cached_image_url( $image_url, $options = [] ) {

	// Simply return the requested URL directly if the image cache is disabled
	if ( ! defined( 'SLACKEMON_IMAGE_CACHE' ) || 'disabled' === SLACKEMON_IMAGE_CACHE['method'] ) {
		return $image_url;
	}

	if ( ! $image_url ) {
		log_cache_event( $image_url, '', 'image-error-missing-url' );
		return false;
	}

	$hash      = md5( $image_url );
	$folder    = __DIR__ . '/../.image-cache/' . substr( $hash, 0, 1 );
	$basename  = basename( parse_url( $image_url, PHP_URL_PATH ) ); // Limit filename to most relevant URL portion
	$basename  = preg_replace( '/[^A-Za-z0-9\.]/', '', $basename ); // Make sure filename doesn't have unsafe characters
	$basename  = substr( $basename, 0, 50 ); // Make sure filename isn't too long
	$filename  = $folder . '/' . $hash . '-' . $basename . ( 'aws' === SLACKEMON_IMAGE_CACHE['method'] ? '.aws' : '' );
	
	// If the 'local' option is in use, this is where the image will be found
	$local_url  = INBOUND_URL . '.image-cache/' . substr( $hash, 0, 1 ) . '/' . $hash . '-' . $basename;

	// If the 'aws' option is in use, this is the location the object will be stored at
	$remote_key = 'image-cache/' . substr( $hash, 0, 1 ) . '/' . $hash . '-' . $basename;

	// Does image exist in local cache? Return the URL now - either the local URL, or the remote URL stored in the file
	if ( file_exists( $filename ) ) {
		log_cache_event( $image_url, $filename, 'image-hit' );
		return 'local' === SLACKEMON_IMAGE_CACHE['method'] ? $local_url : file_get_contents( $filename );
	}

	// Make sure full cache folder exists
	if ( ! is_dir( $folder ) ) {
		mkdir( $folder, 0777, true );
	}

	// Get image and store it before returning the local URL
	// TODO: If using AWS, check if the remote_key exists first, then just store the ObjectURL locally instead of
	//       getting and uploading the image again
	$image_data = get_url( $image_url );
	if ( ! $image_data ) {
		log_cache_event( $image_url, $filename, 'image-error-no-data-at-url' );
		return false;
	}
	log_cache_event( $image_url, $filename, 'image-miss' );

	switch ( SLACKEMON_IMAGE_CACHE['method'] ) {

		case 'local':

			// Store the image data locally, and return the local URL

			file_put_contents( $filename, $image_data );
			return $local_url;

		break; // Case local

		case 'aws':

			// Store the image data in AWS, then store and return the AWS URL

			require_once( __DIR__ . '/aws/aws.phar' );

			$s3 = new Aws\S3\S3Client([
				'version' => 'latest',
				'region'  => SLACKEMON_IMAGE_CACHE['aws_region'],
				'credentials' => [
		        'key'    => SLACKEMON_IMAGE_CACHE['aws_id'],
		        'secret' => SLACKEMON_IMAGE_CACHE['aws_secret'],
		    ],
			]);

			try {
				$result = $s3->putObject([
					'Bucket' => SLACKEMON_IMAGE_CACHE['aws_bucket'],
					'Key'    => $remote_key,
					'Body'   => $image_data,
					'ACL'    => 'public-read',
					'Metadata' => [
						'original_url' => $image_url,
						'uploaded_by'  => INBOUND_URL,
					],
					'CacheControl' => YEAR_IN_SECONDS,
				]);
			} catch ( Aws\S3\Exception\S3Exception $e ) {

				// Log an event and return the original image URL in case of exception
				log_cache_event( $image_url, $filename, 'image-error-aws-exception' );
				return $image_url;

			}

			$remote_url = $result['ObjectURL'];
			file_put_contents( $filename, $remote_url );

			return $remote_url;

		break; // Case aws

	} // Switch image cache method
} // Function get_cached_image_url

/** Simple function to log cache events. */
function log_cache_event( $url, $filename, $cache_status ) {

	// TODO

	return;

} // Function log_cache_event

// The end!
