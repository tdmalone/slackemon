<?php

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class TimeTest extends TestCase {

  public function testNowReturnsNow() {
    $this->assertSame( 'now', slackemon_get_relative_time( time() ) );
  }

  public function testSupportsStringInput() {
    $this->assertSame( 'now', slackemon_get_relative_time( 'now' ) );
  }

  public function testLessThanOneMinute() {
    $this->assertSame( 'just now', slackemon_get_relative_time( time() - 30 ) );
  }

  public function testOneMinute() {
    $this->assertSame( '1 minute ago', slackemon_get_relative_time( time() - 60 ) );
  }

  public function testOneMinuteShort() {
    $this->assertSame( '1 min ago', slackemon_get_relative_time( time() - 60, false ) );
  }

  public function testMultipleMinutes() {
    $this->assertSame( '2 minutes ago', slackemon_get_relative_time( time() - 60 * 2 ) );
  }

  public function testMultipleMinutesShort() {
    $this->assertSame( '2 mins ago', slackemon_get_relative_time( time() - 60 * 2, false ) );
  }

  public function testOneHour() {
    $this->assertSame( '1 hour ago', slackemon_get_relative_time( time() - 3600 ) );
  }

  public function testOneHourShort() {
    $this->assertSame( '1 hr ago', slackemon_get_relative_time( time() - 3600, false ) );
  }

  public function testMultipleHours() {
    $this->assertSame( '2 hours ago', slackemon_get_relative_time( time() - 3600 * 2 ) );
  }

  public function testMultipleHoursShort() {
    $this->assertSame( '2 hrs ago', slackemon_get_relative_time( time() - 3600 * 2, false ) );
  }

  public function testYesterday() {
    $current_hour = date( 'ga' );
    $this->assertSame( 'at ' . $current_hour . ' yesterday', slackemon_get_relative_time( date( 'ga' ) . ' -1 day' ) );
  }

  public function testMultipleDays() {
    $this->assertSame( '2 days ago', slackemon_get_relative_time( time() - 3600 * 24 * 2 ) );
  }

  public function testOneWeek() {
    $this->assertSame( '1 week ago', slackemon_get_relative_time( time() - 3600 * 24 * 7 ) );
  }

  public function testMultipleWeeks() {
    $this->assertSame( '2 weeks ago', slackemon_get_relative_time( time() - 3600 * 24 * 7 * 2 ) );
  }

  public function testLastMonth() {
    $this->assertSame( 'last month', slackemon_get_relative_time( '-1 month' ) );
  }

}

// The end!
