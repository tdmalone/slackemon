<?php

// TM 07/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase {

  public function testWhiteRgbReturnsWhiteHex() {
    $this->assertContains( strtolower( rgb2hex([ 255, 255, 255 ]) ), [ '#ffffff', '#fff' ]);
  }

  public function testBlackRgbReturnsBlackHex() {
    $this->assertContains( strtolower( rgb2hex([ 0, 0, 0 ]) ), [ '#000000', '#000' ]);
  }

}

// The end!
