<?php

// TM 04/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase {

	public function testFileExists() {

		$testFilename = __DIR__ . '/test-filesystem-fileexists';
		touch( $testFilename );

		$this->assertTrue( slackemon_file_exists( $testFilename ) );

		unlink( __DIR__ . '/test-filesystem-fileexists' );

	}

	public function testFileDoesNotExist() {

		$testFilename = __DIR__ . '/test-filesystem-fileexists';
		@unlink( $testFilename );

		$this->assertFalse( slackemon_file_exists( $testFilename ) );

	}

}

// The end!
