<?php

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class BattlesTest extends TestCase {

  public function testFriendlyCheckSucceedsOnFriendlyBattle() {
    $this->assertTrue( slackemon_is_friendly_battle( (object) [ 'challenge_type' => [ 'friendly' ] ] ) );
  }

  public function testFriendlyCheckFailsOnStandardBattle() {
    $this->assertFalse( slackemon_is_friendly_battle( (object) [ 'challenge_type' => [ 'normal' ] ] ) );
  }

  public function testUserRemainingPokemonRetrievedFromBattleData() {

    $user_id  = 'U012345678';
    $ts1      = time();
    $ts2      = time() + 300;

    $pokemon1 = (object) [
      'ts' => $ts1,
      'hp' => 100,
    ];

    $pokemon2 = (object) [
      'ts' => $ts2,
      'hp' => 100,
    ];

    $battle_data = [
      'users' => [
        $user_id => [
          'team' => [
            'ts' . $ts1 => $pokemon1,
            'ts' . $ts2 => $pokemon2,
          ],
          'status' => [
            'current' => $ts1,
          ],
        ],
      ],
    ];

    // Force recursively into an object.
    $battle_data = json_decode( json_encode( $battle_data ) );

    $this->assertEquals( 1, slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id ) );

    $battle_data->users->{ $user_id }->team->{ 'ts' . $ts2 }->hp = 0;
    $this->assertEquals( 0, slackemon_get_user_remaining_battle_pokemon( $battle_data, $user_id ) );

  }

  public function testOpponentIDRetrievedFromBattleData() {

    $user_id     = 'U12345678';
    $opponent_id = 'U87653122';

    $battle_data = [
      'users' => [
        $user_id     => [],
        $opponent_id => [],
      ]
    ];

    // Force recursively into an object.
    $battle_data = json_decode( json_encode( $battle_data ) );

    $this->assertSame( $opponent_id, slackemon_get_battle_opponent_id( $battle_data, $user_id ) );

  }

  public function testCurrentPokemonRetrievedFromBattleData() {

    $user_id = 'U012345678';
    $ts      = time();
    $pokemon = (object) [ 'ts' => $ts ];

    $battle_data = [
      'users' => [
        $user_id => [
          'team' => [
            'ts' . $ts => $pokemon,
          ],
          'status' => [
            'current' => $ts,
          ],
        ],
      ],
    ];

    // Force recursively into an object.
    $battle_data = json_decode( json_encode( $battle_data ) );

    $this->assertEquals( $pokemon, slackemon_get_battle_current_pokemon( $battle_data, $user_id ) );

  }

  public function testGeneratedBattleHashIsMD5() {
    $this->assertRegExp( '/^[a-f0-9]{32}$/', slackemon_generate_battle_hash( time(), 'U12345678', 'U98765432' ) );
  }

  public function testGeneratedBattleHashAcceptsUserInEitherOrder() {

    $ts = time();
    $user_id1 = 'U12345678';
    $user_id2 = 'U98765432';

    $hash1 = slackemon_generate_battle_hash( $ts, $user_id1, $user_id2 );
    $hash2 = slackemon_generate_battle_hash( $ts, $user_id2, $user_id1 );

    $this->assertSame( $hash1, $hash2 );

  }

  public function testGeneratedBattleHashIsUniqueAtDifferentTime() {

    $ts1 = time();
    $ts2 = $ts1 + 300;
    $user_id1 = 'U12345678';
    $user_id2 = 'U98765432';

    $hash1 = slackemon_generate_battle_hash( $ts1, $user_id1, $user_id2 );
    $hash2 = slackemon_generate_battle_hash( $ts2, $user_id1, $user_id2 );

    $this->assertNotEquals( $hash1, $hash2 );

  }

}

// The end!
