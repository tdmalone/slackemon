<?php

// TM 07/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class TimeTest extends TestCase {

  public function testNowReturnsNow() {
    $this->assertEquals( 'now', get_relative_time( time() ) );
  }

  public function testSupportsStringInput() {
    $this->assertEquals( 'now', get_relative_time( 'now' ) );
  }

  public function testLessThanOneMinute() {
    $this->assertEquals( 'just now', get_relative_time( time() - 30 ) );
  }

  public function testOneMinute() {
    $this->assertEquals( '1 minute ago', get_relative_time( time() - 60 ) );
  }

  public function testOneMinuteShort() {
    $this->assertEquals( '1 min ago', get_relative_time( time() - 60, false ) );
  }

  public function testMultipleMinutes() {
    $this->assertEquals( '2 minutes ago', get_relative_time( time() - 60 * 2 ) );
  }

  public function testMultipleMinutesShort() {
    $this->assertEquals( '2 mins ago', get_relative_time( time() - 60 * 2, false ) );
  }

  public function testOneHour() {
    $this->assertEquals( '1 hour ago', get_relative_time( time() - 3600 ) );
  }

  public function testOneHourShort() {
    $this->assertEquals( '1 hr ago', get_relative_time( time() - 3600, false ) );
  }

  public function testMultipleHours() {
    $this->assertEquals( '2 hours ago', get_relative_time( time() - 3600 * 2 ) );
  }

  public function testMultipleHoursShort() {
    $this->assertEquals( '2 hrs ago', get_relative_time( time() - 3600 * 2, false ) );
  }

  public function testYesterday() {
    $current_hour = date( 'ga' );
    $this->assertEquals( 'at ' . $current_hour . ' yesterday', get_relative_time( date( 'ga' ) . ' -1 day' ) );
  }

  public function testMultipleDays() {
    $this->assertEquals( '2 days ago', get_relative_time( time() - 3600 * 24 * 2 ) );
  }

  public function testOneWeek() {
    $this->assertEquals( '1 week ago', get_relative_time( time() - 3600 * 24 * 7 ) );
  }

  public function testMultipleWeeks() {
    $this->assertEquals( '2 weeks ago', get_relative_time( time() - 3600 * 24 * 7 * 2 ) );
  }

  public function testLastMonth() {
    $this->assertEquals( 'last month', get_relative_time( '-1 month' ) );
  }

}

// The end!
