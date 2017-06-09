<?php

// TM 07/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class FunctionTest extends TestCase {

	public function testLongStringTruncates() {
		$string = 'This string is loooooooooong';
		$truncated = maybe_truncate( $string, strlen( $string ) - 1 );
		$this->assertStringStartsWith( substr( $string, 0, 5 ), $truncated );
		$this->assertStringEndsWith( '...', $truncated );
	}

	public function testShortStringDoesNotTruncate() {
		$string = 'This string is short';
		$this->assertEquals( $string, maybe_truncate( $string, strlen( $string ) ) );
	}

	public function testStringConvertsToTitleCase() {
		$string = 'writing unit tests';
		$this->assertEquals( 'Writing Unit Tests', strtotitle( $string ) );
	}

	public function testSmallWordsDoNotBecomeTitleCase() {
		$string = 'writing unit of the tests';
		$this->assertEquals( 'Writing Unit of the Tests', strtotitle( $string ) );
	}

	public function testSmallWordAtStartOfSentenceBecomesTitleCase() {
		$string = 'the test is here';
		$this->assertEquals( 'The Test is Here', strtotitle( $string ) );
	}

}

// The end!
