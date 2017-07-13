<?php

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class PlayersTest extends TestCase {

  public function setUp() {
    $this->user_id       = 'U12345678';
    $this->user_filename = __DIR__ . '/../.data/players/' . $this->user_id;
  }

  public function tearDown() {
    $this->user_id       = null;
    $this->user_filename = null;
  }

  /** Clears the player data cache so we know we are retrieving from disk. */
  private function clear_cache() {
    global $_cached_slackemon_player_data;
    $_cached_slackemon_player_data = null;
  }

  public function testPlayerRegistrationSucessful() {

    // Returns no. of bytes written to player file.
    $this->assertInternalType( 'integer', slackemon_register_player( $this->user_id ) );

    $this->assertFileExists( $this->user_filename );

  }

  public function testIsPlayerReturnsTrue() {
    $this->assertTrue( slackemon_is_player( $this->user_id ) );
  }

  public function testPlayerDataRetrieved() {

    $this->clear_cache();

    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertSame( $this->user_id, $player_data->user_id );

  }

  public function testGettingPlayerDataCachesItInMemory() {

    global $_cached_slackemon_player_data;
    $this->clear_cache();

    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertSame( $player_data, $_cached_slackemon_player_data[ $this->user_id ] );

  }

  public function testPlayerDataSaved() {

    $new_xp_value = 12345678;

    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertNotEquals( $new_xp_value, $player_data->xp );

    $player_data->xp = $new_xp_value;
    slackemon_save_player_data( $player_data, $this->user_id );

    $this->clear_cache();

    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertSame( $new_xp_value, $player_data->xp );

  }

  public function testSavingPlayerDataCachesItInMemory() {

    global $_cached_slackemon_player_data;

    $new_xp_value = 87654321;

    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertNotEquals( $new_xp_value, $player_data->xp );

    $player_data->xp = $new_xp_value;
    slackemon_save_player_data( $player_data, $this->user_id );
    $this->assertSame( $player_data, $_cached_slackemon_player_data[ $this->user_id ] );
    
  }

  /*
  // This test is under construction. It cannot run yet, because DND checking fails because the user ID isn't valid.
  // This then causes the spawn to return because no user is active, and that leaves the player file 'locked' because
  // it is locked at the start of the scaffold. This may present an edge case where files might stay locked, but
  // impact is low because scaffolding is only used by developers.
  //
  // So, before we can proceed with this test we probably need to both test file locking, and mock up Slack API
  // responses when in test environments so we can start returning mock user data.
  //
  public function testPlayerDataCanScaffold() {
    $player_data = slackemon_get_player_data( $this->user_id );
    $this->assertCount( 0, $player_data->pokemon );
    slackemon_scaffold_player_file( 1, $this->user_id );
    $this->assertCount( 1, $player_data->pokemon );
  }
  */

  public function testPlayerCancellationSuccessful() {
    $this->assertTrue( slackemon_cancel_player( $this->user_id ) );
    $this->assertFileNotExists( $this->user_filename );
  }

  public function testIsPlayerReturnsFalse() {
    $this->assertFalse( slackemon_is_player( $this->user_id ) );
  }

}

// The end!
