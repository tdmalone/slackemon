<?php

// TM 08/06/2017

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class TemplatingTest extends TestCase {

	public function setUp() {
		$this->default_items_per_page = 5;
	}

	public function tearDown() {
		$this->default_items_per_page = null;
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

		return $pagination_actions;

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

		// ...

	}

	public function testTenPagesOnPageOneReturnsFirstThreePagesAndPrevNextButtons() {

		$pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 1 );
		$pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

		// ...

	}

	public function testTenPagesOnPageFiveReturnsOnePageEitherSideAndPrevNextButtons() {

		$pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 5 );
		$pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

		// ...

	}

	public function testTenPagesOnPageTenReturnsLastThreePagesAndPrevNextButtons() {

		$pagination = $this->get_pagination_response( $this->default_items_per_page * 10, 10 );
		$pagination_actions = $this->get_pagination_actions( $pagination['actions'] );

		// ...

	}

}

// The end!
