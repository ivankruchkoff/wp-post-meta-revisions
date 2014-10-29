<?php

class MetaRevisionTests extends WP_UnitTestCase {

	/**
	 * Test the revisions system for storage of meta values
	 */
	function test_revisions_stores_meta_values() {

		// Set up a new post
		$original_post_id = $post_id = $this->factory->post->create();
		// And update to store an initial revision
		wp_update_post( array( 'post_content'	=> 'some initial content', 'ID' => $post_id ) );

		// One revision so far
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		/**
		 * First set up a meta value
		 */

		// Store a custom meta value, which is not revisioned by default
		update_post_meta( $post_id, 'meta_revision_test', 'original' );

		// Update the post, storing a revision
		wp_update_post( array( 'post_content'	=> 'some more content', 'ID' => $post_id ) );

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $revisions );


		//  Next, store some updated meta values for the same key
		update_post_meta( $post_id, 'meta_revision_test', 'update1' );

		// Save the post, changing content to force a revision
		wp_update_post( array( 'post_content'	=> 'some updated content', 'ID' => $post_id ) );

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 3, $revisions );


		/**
		 * Now restore the original revision
		 */

		// Restore the previous revision
		$revisions = (Array) wp_get_post_revisions( $post_id );
		// Go back two to load the previous revision
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		// Restore!
		wp_restore_post_revision( $last_revision->ID );

		wp_update_post( array( 'ID' => $post_id ) );
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 4, $revisions );

		/**
		 * Check the meta values to verify they are NOT revisioned - they are not revisioned by default
		 */

		// Custom post meta should NOT be restored, orignal value should not be restored, value still 'update1'
		$this->assertEquals( 'update1', get_post_meta( $post_id, 'meta_revision_test', true ) );

		update_post_meta( $post_id, 'meta_revision_test', 'update2' );


		/*
		 * Now test the revisioning of custom meta when enabled by the wp_post_revision_meta_keys filter
		 */

		// Add the custom field to be revised via the wp_post_revision_meta_keys filter
		add_filter( 'wp_post_revision_meta_keys', function( $keys ) {
			$keys[] = 'meta_revision_test';
			return $keys;
		});

		// Save the post, changing content to force a revision
		wp_update_post( array( 'post_content'	=> 'more updated content', 'ID' => $post_id ) );

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 5, $revisions );

		// Store custom meta values, which should now be revisioned
		update_post_meta( $post_id, 'meta_revision_test', 'update3' );

		/**
		 * Save the post again, custom meta should now be revisioned
		 *
		 * Note that a revision is saved even though there is no change
		 * in post content, becaused the revisioned post_meta has changed
		 *
		 */
		wp_update_post( array( 'ID' => $post_id ) );

		// This revision contains the existing post meta ('update3')
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 6, $revisions );

		// Verify that previous post meta is set
		$this->assertEquals( 'update3', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Retore the previous revision
		$revisions = wp_get_post_revisions( $post_id );

		// Go back two to load the previous revision
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		// Verify that previous post meta is restored
		$this->assertEquals( 'update2', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Try storing a blank meta
		update_post_meta( $post_id, 'meta_revision_test', '' );
		wp_update_post( array( 'ID' => $post_id ) );

		update_post_meta( $post_id, 'meta_revision_test', 'update 4' );
		wp_update_post( array( 'ID' => $post_id ) );

		// Retore the previous revision
		$revisions = wp_get_post_revisions( $post_id );
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		// Verify that previous post meta is restored
		$this->assertEquals( '', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Test not tracking a key
		remove_all_filters( 'wp_post_revision_meta_keys' );

		// Should no longer be revisioned
		update_post_meta( $post_id, 'meta_revision_test', 'update 5' );
		wp_update_post( array( 'ID' => $post_id, 'post_content'	=> 'changed content' ) );
		update_post_meta( $post_id, 'meta_revision_test', 'update 6' );
		wp_update_post( array( 'ID' => $post_id, 'post_content'	=> 'go updated content' ) );
		// Retore the previous revision
		$revisions = wp_get_post_revisions( $post_id );
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		// Verify that previous post meta is NOT restored
		$this->assertEquals( 'update 6', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Add the custom field to be revised via the wp_post_revision_meta_keys filter
		add_filter( 'wp_post_revision_meta_keys', function( $keys ) {
			$keys[] = 'meta_revision_test';
			return $keys;
		});

		/**
		 * Test the revisioning of multiple meta keys
		 */

		// Add three values for meta
		update_post_meta( $post_id, 'meta_revision_test', 'update 7' );
		add_post_meta( $post_id, 'meta_revision_test', 'update 7 number 2' );
		add_post_meta( $post_id, 'meta_revision_test', 'update 7 number 3' );
		wp_update_post( array( 'ID' => $post_id ) );

		// Update all three values
		update_post_meta( $post_id, 'meta_revision_test', 'update 8', 'update 7' );
		update_post_meta( $post_id, 'meta_revision_test', 'update 8 number 2', 'update 7 number 2'  );
		update_post_meta( $post_id, 'meta_revision_test', 'update 8 number 3', 'update 7 number 3'  );
		wp_update_post( array( 'ID' => $post_id ) );

		// Retore the previous revision
		$revisions = wp_get_post_revisions( $post_id );
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		$this->assertEquals( array( 'update 7', 'update 7 number 2', 'update 7 number 3' ), get_post_meta( $post_id, 'meta_revision_test' ) );

		// Cleanup!
		wp_delete_post( $original_post_id );

	}

}
