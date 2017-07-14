<?php

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase {

  public function testFileExists() {

    $test_filename = __DIR__ . '/test-filesystem-fileexists';
    touch( $test_filename );

    $this->assertTrue( slackemon_file_exists( $test_filename, 'local' ) );

    unlink( __DIR__ . '/test-filesystem-fileexists' );

  }

  public function testFileDoesNotExist() {

    $test_filename = __DIR__ . '/test-filesystem-fileexists';

    if ( file_exists( $test_filename ) ) {
      unlink( $test_filename );
    }

    $this->assertFalse( slackemon_file_exists( $test_filename, 'local' ) );

  }

}

// The end!
