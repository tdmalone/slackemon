<?php

// TM 07/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class TimeTest extends TestCase {

	public function testNowReturnsNow() {
		$this->assertEquals( get_relative_time( time() ), 'now' );
	}

	public function testSupportsStringInput() {
		$this->assertEquals( get_relative_time( 'now' ), 'now' );
	}

	public function testLessThanOneMinute() {
		$this->assertEquals( get_relative_time( time() - 30 ), 'just now' );
	}

	public function testOneMinute() {
		$this->assertEquals( get_relative_time( time() - 60 ), '1 minute ago' );
	}

	public function testOneMinuteShort() {
		$this->assertEquals( get_relative_time( time() - 60, false ), '1 min ago' );
	}

	public function testMultipleMinutes() {
		$this->assertEquals( get_relative_time( time() - 60 * 2 ), '2 minutes ago' );
	}

	public function testMultipleMinutesShort() {
		$this->assertEquals( get_relative_time( time() - 60 * 2, false ), '2 mins ago' );
	}

	public function testOneHour() {
		$this->assertEquals( get_relative_time( time() - 3600 ), '1 hour ago' );
	}

	public function testOneHourShort() {
		$this->assertEquals( get_relative_time( time() - 3600, false ), '1 hr ago' );
	}

	public function testMultipleHours() {
		$this->assertEquals( get_relative_time( time() - 3600 * 2 ), '2 hours ago' );
	}

	public function testMultipleHoursShort() {
		$this->assertEquals( get_relative_time( time() - 3600 * 2, false ), '2 hrs ago' );
	}

}

// The end!
