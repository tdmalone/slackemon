<?php

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class FunctionTest extends TestCase {

  public function testStringConvertsToTitleCase() {
    $string = 'writing unit tests';
    $this->assertSame( 'Writing Unit Tests', slackemon_strtotitle( $string ) );
  }

  public function testSmallWordsDoNotBecomeTitleCase() {
    $string = 'writing unit of the tests';
    $this->assertSame( 'Writing Unit of the Tests', slackemon_strtotitle( $string ) );
  }

  public function testSmallWordAtStartOfSentenceBecomesTitleCase() {
    $string = 'the test is here';
    $this->assertSame( 'The Test is Here', slackemon_strtotitle( $string ) );
  }

}

// The end!
