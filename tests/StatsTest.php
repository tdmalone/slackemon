<?php

// TM 08/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase {

	public function setUp() {

		$this->sample_stats = [
			'attack'          => 10,
			'defense'         => 10,
			'hp'              => 10,
			'speed'           => 10,
			'special-attack'  => 10,
			'special-defense' => 10,
		];

		// Set this to the total sum of the above values; used for addition tests
		$this->sample_stats_total = 60;

		$this->min_happiness = 0;
		$this->max_happiness = 255;

		$this->reasonable_min_cp = 1;
		$this->reasonable_max_cp = 999;

	}

	public function tearDown() {
		$this->sample_stats      = null;
		$this->min_happiness     = null;
		$this->max_happiness     = null;
		$this->reasonable_min_cp = null;
		$this->reasonable_max_cp = null;
	}

	public function testZeroIvsReturns0Percent() {
		$iv_percentage = slackemon_get_iv_percentage([ 0, 0 ]);
		$this->assertInternalType( 'int', $iv_percentage );
		$this->assertEquals( 0, $iv_percentage );
	}

	public function testMaxIvsReturns100Percent() {
		$iv_percentage = slackemon_get_iv_percentage([ SLACKEMON_MAX_IVS, SLACKEMON_MAX_IVS ]);
		$this->assertInternalType( 'int', $iv_percentage );
		$this->assertEquals( 100, $iv_percentage );
	}

	public function testCombinedEvsCorrectlyAddsUpMultipleEvs() {
		$combined_evs = slackemon_get_combined_evs( $this->sample_stats );
		$this->assertInternalType( 'int', $combined_evs );
		$this->assertEquals( $this->sample_stats_total, $combined_evs );
	}

	public function testCpReturnsReasonableIntegerWhenProvidedWithArray() {
		$cp = slackemon_calculate_cp( $this->sample_stats );
		$this->assertInternalType( 'int', $cp );
		$this->assertGreaterThanOrEqual( 1, $cp );
		$this->assertLessThanOrEqual( 1000, $cp );
	}

	public function testCpReturnsReasonableIntegerWhenProvidedWithObject() {
		$cp = slackemon_calculate_cp( (object) $this->sample_stats );
		$this->assertInternalType( 'int', $cp );
		$this->assertGreaterThanOrEqual( $this->reasonable_min_cp, $cp );
		$this->assertLessThanOrEqual( $this->reasonable_max_cp, $cp );
	}

	public function testValidAffectionReturnsInteger() {
		$happiness_value = slackemon_affection_to_happiness( 1 );
		$this->assertInternalType( 'int', $happiness_value );
	}

	public function testInvalidAffectionReturnsFalse() {
		$happiness_value = slackemon_affection_to_happiness( 6 );
		$this->assertFalse( $happiness_value );
	}

	public function testLowAffectionReturnsValidHappiness() {
		$happiness_value = slackemon_affection_to_happiness( 0 );
		$this->assertGreaterThanOrEqual( $this->min_happiness, $happiness_value );
		$this->assertLessThanOrEqual( $this->max_happiness, $happiness_value );
	}

	public function testHighAffectionReturnsValidHappiness() {
		$happiness_value = slackemon_affection_to_happiness( 5 );
		$this->assertGreaterThanOrEqual( $this->min_happiness, $happiness_value );
		$this->assertLessThanOrEqual( $this->max_happiness, $happiness_value );
	}

}

// The end!
