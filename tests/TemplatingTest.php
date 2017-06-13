<?php

// TM 08/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class TemplatingTest extends TestCase {

  public function setUp() {
    $this->default_items_per_page  = 5;
    $this->valid_emoji_only_regexp = '/^:[a-z_]*:$/'; // Matches :this_emoji: as the entire string only
    $this->valid_emoji_incl_regexp = '/(^|\s):[a-z_]*:($|\s)/'; // Matches :this_emoji: with spaces or string start/end around it
  }

  public function tearDown() {
    $this->default_items_per_page  = null;
    $this->valid_emoji_only_regexp = null;
    $this->valid_emoji_incl_regexp = null;
  }

  /** Abstracts the faking out of objects used in these tests to generate pagination attachments. */
  private function get_pagination_response( $item_count, $current_page = 1 ) {

    $objects    = array_fill( 0, $item_count, '' );
    $pagination = slackemon_get_pagination_attachment( $objects, $current_page, '' );

    return $pagination;

  }

  /**
   * Abstracts the filtering of returned actions in these tests to ensure the count of actions excludes empty
   * actions, just the Slack API does, and like we take slight advantage of in our code when a ternery operator
   * decides an action is not needed.
   */
  private function get_pagination_actions( $actions ) {

    $pagination_actions = array_filter( $actions, function( $action ) {
      if ( ! $action || ! count( $action ) ) {
        return false;
      }
      return true;
    });

    // Re-index the array
    $pagination_actions = array_values( $pagination_actions );		

    return $pagination_actions;

  }

  public function testGenderPronounReturnsAStringForMale() {
    $pronoun = slackemon_get_gender_pronoun( 'male' );
    $this->assertInternalType( 'string', $pronoun );
    $this->assertNotEmpty( $pronoun );
  }

  public function testGenderPronounReturnsAStringForFemale() {
    $pronoun = slackemon_get_gender_pronoun( 'female' );
    $this->assertInternalType( 'string', $pronoun );
    $this->assertNotEmpty( $pronoun );
  }

  public function testGenderPronounReturnsAStringForFalse() {
    $pronoun = slackemon_get_gender_pronoun( false );
    $this->assertInternalType( 'string', $pronoun );
    $this->assertNotEmpty( $pronoun );
  }

  public function testRandomIvAppraisalReturnsAStringWithSomethingInIt() {
    $appraisal = slackemon_appraise_ivs([ random_int( 0, SLACKEMON_MAX_IVS ) ]);
    $this->assertInternalType( 'string', $appraisal );
    $this->assertNotEmpty( $appraisal );
  }

  public function testRandomIvAppraisalIncludesEmoji() {
    $appraisal = slackemon_appraise_ivs([ random_int( 0, SLACKEMON_MAX_IVS ) ]);
    $this->assertRegExp( $this->valid_emoji_incl_regexp, $appraisal );
  }

  public function testRandomIvAppraisalDoesNotIncludeEmoji() {
    $appraisal = slackemon_appraise_ivs([ random_int( 0, SLACKEMON_MAX_IVS ) ], false );
    $this->assertNotRegExp( $this->valid_emoji_incl_regexp, $appraisal );
  }

  public function testStringWithOneTypeAddsEmojiBeforeIt() {
    $this->assertEquals( ':type-fire: Fire', slackemon_emojify_types( 'Fire' ) );
  }

  public function testStringWithOneTypeReturnsEmojiOnly() {
    $this->assertEquals( ':type-fire:', slackemon_emojify_types( 'Fire', false ) );	
  }

  public function testStringWithTwoTypesAddEmojisAfterText() {
    $this->assertEquals(
      'Fire :type-fire: Water :type-water:',
      slackemon_emojify_types( 'Fire Water', true, 'after' )
    );
  }

  public function testRandomHappinessLevelReturnsWhatLooksLikeAnEmojiString() {
    $this->assertRegExp( $this->valid_emoji_only_regexp, slackemon_get_happiness_emoji( random_int( 0, 255 ) ) );
  }

  public function testInvalidHappinessForHappinessEmojiGetsABlankString() {
    $this->assertSame( '', slackemon_get_happiness_emoji( 10000 ) );
  }

  public function testRandomNatureEmojiReturnsWhatLooksLikeAnEmojiString() {
    $natures = slackemon_get_natures();
    $random_nature = $natures[ array_rand( $natures ) ];
    $this->assertRegExp( $this->valid_emoji_only_regexp, slackemon_get_nature_emoji( $random_nature ) );
  }

  public function testInvalidNatureForNatureEmojiGetsABlankString() {
    $this->assertSame( '', slackemon_get_nature_emoji( 'non-existent-emoji' ) );
  }

  public function testPaginateChunkReturnsPartialChunk() {
    $objects = slackemon_paginate( [ 1, 2, 3 ], 1 );
    $this->assertEquals( [ 1, 2, 3 ], $objects );
  }

  public function testPaginateChunkReturnsOneChunk() {
    $objects = slackemon_paginate( [ 1, 2, 3, 4, 5 ], 1 );
    $this->assertEquals( [ 1, 2, 3, 4, 5 ], $objects );
  }

  public function testPaginateChunkReturnsMiddleChunk() {
    $objects = slackemon_paginate( [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ], 2 );
    $this->assertEquals( [ 6, 7, 8, 9, 10 ], $objects );
  }

  public function testPaginateChunkReturnsLastChunk() {
    $objects = slackemon_paginate( [ 1, 2, 3, 4, 5, 6 ], 2 );
    $this->assertEquals( [ 6 ], $objects );
  }

  public function testPaginateChunkReturnsLastChunkWhenInvalidPageGiven() {
    $objects = slackemon_paginate( [ 1, 2, 3, 4, 5, 6 ], 20 );
    $this->assertEquals( [ 6 ], $objects );
  }

  public function testEmptyPaginationReturnsEmptyArray() {

    $pagination = $this->get_pagination_response( 0 );

    $this->assertInternalType( 'array', $pagination );
    $this->assertCount( 0, $pagination );

  }

  public function testJustEnoughObjectsForOneFullPageReturnsEmptyArray() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page );

    $this->assertInternalType( 'array', $pagination );
    $this->assertCount( 0, $pagination );

  }

  public function testJustEnoughObjectsToStartASecondPageReturnsTwoActions() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page + 1 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertInternalType( 'array', $pagination );
    $this->assertCount( 2, $pagination_actions );

  }

  public function testJustEnoughObjectsToFillFivePagesReturnsFiveActions() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 5 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertInternalType( 'array', $pagination );
    $this->assertCount( 5, $pagination_actions );

  }

  public function testJustEnoughObjectsToStartASixthPageStillReturnsFiveActions() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 5 + 1 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertInternalType( 'array', $pagination );
    $this->assertCount( 5, $pagination_actions );

  }

  public function testFivePagesReturnsActionButtonsForEachPage() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 5 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertEquals( '1', $pagination_actions[0]['text'] );
    $this->assertEquals( '2', $pagination_actions[1]['text'] );
    $this->assertEquals( '3', $pagination_actions[2]['text'] );
    $this->assertEquals( '4', $pagination_actions[3]['text'] );
    $this->assertEquals( '5', $pagination_actions[4]['text'] );

  }

  public function testTenPagesOnPageOneReturnsFirstThreePagesAndPrevNextButtons() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 1 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertEquals( ':rewind:',       $pagination_actions[0]['text'] );
    $this->assertEquals( '1',              $pagination_actions[1]['text'] );
    $this->assertEquals( '2',              $pagination_actions[2]['text'] );
    $this->assertEquals( '3',              $pagination_actions[3]['text'] );
    $this->assertEquals( ':fast_forward:', $pagination_actions[4]['text'] );

  }

  public function testTenPagesOnPageFiveReturnsOnePageEitherSideAndPrevNextButtons() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 5 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertEquals( ':rewind:',       $pagination_actions[0]['text'] );
    $this->assertEquals( '4',              $pagination_actions[1]['text'] );
    $this->assertEquals( '5',              $pagination_actions[2]['text'] );
    $this->assertEquals( '6',              $pagination_actions[3]['text'] );
    $this->assertEquals( ':fast_forward:', $pagination_actions[4]['text'] );

  }

  public function testTenPagesOnPageTenReturnsLastThreePagesAndPrevNextButtons() {

    $pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 10 );
    $pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

    $this->assertEquals( ':rewind:',       $pagination_actions[0]['text'] );
    $this->assertEquals( '8',              $pagination_actions[1]['text'] );
    $this->assertEquals( '9',              $pagination_actions[2]['text'] );
    $this->assertEquals( '10',             $pagination_actions[3]['text'] );
    $this->assertEquals( ':fast_forward:', $pagination_actions[4]['text'] );

  }

}

// The end!
