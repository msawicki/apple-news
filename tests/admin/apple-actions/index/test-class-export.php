<?php

use \Apple_Actions\Index\Export as Export;
use \Apple_Exporter\Settings as Settings;

class Admin_Action_Index_Export_Test extends WP_UnitTestCase {

	public function setup() {
		$this->settings = new Settings();
	}

	/**
	 * A filter to ensure that the is_exporting flag is set during export.
	 *
	 * @access public
	 * @return string The filtered content.
	 */
	public function filterTheContentTestIsExporting() {
		return apple_news_is_exporting() ? 'is exporting' : 'is not exporting';
	}

	/**
	 * Tests the ability to include a caption with a cover image.
	 */
	public function testCoverWithCaption() {

		// Create dummy post and attachment.
		$file    = dirname( dirname( dirname( __DIR__ ) ) ) . '/data/test-image.jpg';
		$post_id = self::factory()->post->create();
		$image   = self::factory()->attachment->create_upload_object( $file, $post_id );

		// Add a caption to the image.
		$image_post = get_post( $image );
		$image_post->post_excerpt = 'Test Caption';
		wp_update_post( $image_post );

		// Set the image as the featured image for the post.
		update_post_meta( $post_id, '_thumbnail_id', $image );

		// Run the export and check the result.
		$sections = \Admin_Apple_Sections::get_sections_for_post( $post_id );
		$export   = new Export( $this->settings, $post_id, $sections );
		$json     = json_decode( $export->perform(), true );
		$this->assertEquals( 'photo', $json['components'][0]['components'][0]['role'] );
		$this->assertEquals( wp_get_attachment_url( $image ), $json['components'][0]['components'][0]['URL'] );
		$this->assertEquals( 'Test Caption', $json['components'][0]['components'][0]['caption']['text'] );
		$this->assertEquals( 'caption', $json['components'][0]['components'][1]['role'] );
		$this->assertEquals( 'Test Caption', $json['components'][0]['components'][1]['text'] );

		// Set cover image and caption via postmeta and ensure it takes priority.
		$image2 = self::factory()->attachment->create_upload_object( $file );
		update_post_meta( $post_id, 'apple_news_coverimage', $image2 );
		update_post_meta( $post_id, 'apple_news_coverimage_caption', 'Test Caption 2' );
		$export   = new Export( $this->settings, $post_id, $sections );
		$json     = json_decode( $export->perform(), true );
		$this->assertEquals( 'photo', $json['components'][0]['components'][0]['role'] );
		$this->assertEquals( wp_get_attachment_url( $image2 ), $json['components'][0]['components'][0]['URL'] );
		$this->assertEquals( 'Test Caption 2', $json['components'][0]['components'][0]['caption']['text'] );
		$this->assertEquals( 'caption', $json['components'][0]['components'][1]['role'] );
		$this->assertEquals( 'Test Caption 2', $json['components'][0]['components'][1]['text'] );
	}

	public function testHasExcerpt() {
		$title = 'My Title';
		$excerpt = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue...';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => '',
			'post_excerpt' => $excerpt,
		) );

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue...', $exporter_content->intro() );
	}

	public function testNoExcerpt() {
		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => '',
		) );

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals( '', $exporter_content->intro() );
	}

	public function testShortcodeInExcerpt() {
		$title = 'My Title';
		$content = '<p>[caption id="attachment_12345" align="aligncenter" width="500"]Test[/caption]Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => '',
		) );

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals( '', $exporter_content->intro() );
	}

	public function testBylineFormat() {
		$user_id = $this->factory->user->create( array(
			'role' => 'administrator',
			'display_name' => 'Testuser',
		) );

		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => '',
			'post_author' => $user_id,
			'post_date' => '2016-08-26 12:00',
		) );

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals( 'by Testuser | Aug 26, 2016 | 12:00 PM', $exporter_content->byline() );
	}

	public function testBylineFormatWithHashtag() {
		$user_id = $this->factory->user->create( array(
			'role' => 'administrator',
			'display_name' => '#Testuser',
		) );

		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => '',
			'post_author' => $user_id,
			'post_date' => '2016-08-26 12:00',
		) );

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals( 'by #Testuser | Aug 26, 2016 | 12:00 PM', $exporter_content->byline() );
	}

	public function testRemoveEntities() {
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Title',
			'post_content' => '<p>&amp;Lorem ipsum dolor sit amet &amp; consectetur adipiscing elit.&amp;</p>',
			'post_date' => '2016-08-26 12:00',
		) );

		// Set HTML content format.
		$this->settings->html_support = 'yes';

		$export = new Export( $this->settings, $post_id );
		$exporter = $export->fetch_exporter();
		$exporter_content = $exporter->get_content();

		$this->assertEquals(
			'<p>&amp;Lorem ipsum dolor sit amet &amp; consectetur adipiscing elit.&amp;</p>',
			str_replace( array( "\n","\r" ), '', $exporter_content->content() )
		);

		// Set Markdown content format.
		$this->settings->html_support = 'no';

		$markdown_export = new Export( $this->settings, $post_id );
		$markdown_exporter = $markdown_export->fetch_exporter();
		$markdown_exporter_content = $markdown_exporter->get_content();
		$this->assertEquals(
			'<p>&Lorem ipsum dolor sit amet & consectetur adipiscing elit.&</p>',
			str_replace( array( "\n","\r" ), '', $markdown_exporter_content->content() )
		);
	}

	public function testSectionMapping() {
		// Create a post
		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
		) );

		// Create a term and add it to the post
		$term_id = $this->factory->term->create( array(
			'taxonomy' => 'category',
			'name' => 'news',
		) );
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );

		// Create a taxonomy map
		update_option( \Admin_Apple_Sections::TAXONOMY_MAPPING_KEY, array(
			'abcdef01-2345-6789-abcd-ef012356789a' => array( $term_id ),
		) );

		// Cache as a transient to bypass the API call
		$self = 'https://news-api.apple.com/channels/abcdef01-2345-6789-abcd-ef012356789a';
		set_transient(
			'apple_news_sections',
			array(
				(object) array(
					'createdAt' => '2017-01-01T00:00:00Z',
					'id' => 'abcdef01-2345-6789-abcd-ef012356789a',
					'isDefault' => true,
					'links' => (object) array(
						'channel' => 'https://news-api.apple.com/channels/abcdef01-2345-6789-abcd-ef0123567890',
						'self' => $self,
					),
					'modifiedAt' => '2017-01-01T00:00:00Z',
					'name' => 'Main',
					'shareUrl' => 'https://apple.news/AbCdEfGhIj-KlMnOpQrStUv',
					'type' => 'section',
				),
			)
		);

		// Get sections for the post
		$sections = \Admin_Apple_Sections::get_sections_for_post( $post_id );

		// Check that the correct mapping was returned
		$this->assertEquals(
			$sections,
			array( $self )
		);

		// Remove the transient and the map
		delete_option( \Admin_Apple_Sections::TAXONOMY_MAPPING_KEY );
		delete_transient( 'apple_news_sections' );
	}

	public function testThemeMapping() {

		// Create a default theme.
		$default_theme = new \Apple_Exporter\Theme;
		$default_theme->set_name( 'Default' );
		$this->assertTrue( $default_theme->save() );

		// Create a test theme with different settings to differentiate.
		$test_theme = new \Apple_Exporter\Theme;
		$test_theme->set_name( 'Test Theme' );
		$test_settings = $test_theme->all_settings();
		$test_settings['body_color'] = '#123456';
		$test_theme->load( $test_settings );
		$this->assertTrue( $test_theme->save() );

		// Create a post.
		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';

		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
		) );

		// Create a term and add it to the post.
		$term_id = $this->factory->term->create( array(
			'taxonomy' => 'category',
			'name' => 'entertainment',
		) );
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );

		// Create a taxonomy map.
		update_option( \Admin_Apple_Sections::TAXONOMY_MAPPING_KEY, array(
			'abcdef01-2345-6789-abcd-ef012356789a' => array( $term_id ),
		) );
		update_option( \Admin_Apple_Sections::THEME_MAPPING_KEY, array(
			'abcdef01-2345-6789-abcd-ef012356789a' => 'Test Theme',
		) );

		// Cache as a transient to bypass the API call.
		$self = 'https://news-api.apple.com/channels/abcdef01-2345-6789-abcd-ef012356789a';
		set_transient(
			'apple_news_sections',
			array(
				(object) array(
					'createdAt' => '2017-01-01T00:00:00Z',
					'id' => 'abcdef01-2345-6789-abcd-ef012356789a',
					'isDefault' => true,
					'links' => (object) array(
						'channel' => 'https://news-api.apple.com/channels/abcdef01-2345-6789-abcd-ef0123567890',
						'self' => $self,
					),
					'modifiedAt' => '2017-01-01T00:00:00Z',
					'name' => 'Main',
					'shareUrl' => 'https://apple.news/AbCdEfGhIj-KlMnOpQrStUv',
					'type' => 'section',
				),
			)
		);

		// Get sections for the post.
		$sections = \Admin_Apple_Sections::get_sections_for_post( $post_id );
		$export = new Export( $this->settings, $post_id, $sections );
		$exporter = $export->fetch_exporter();
		$exporter->generate();
		$json = $exporter->get_json();
		$settings = json_decode( $json );

		$this->assertEquals(
			$settings->componentTextStyles->dropcapBodyStyle->textColor,
			$test_settings['body_color']
		);

		// Clean up.
		$default_theme->delete();
		$test_theme->delete();
		delete_option( \Admin_Apple_Sections::TAXONOMY_MAPPING_KEY );
		delete_transient( 'apple_news_sections' );
	}

	/**
	 * Tests the behavior of the apple_news_is_exporting() function.
	 *
	 * @access public
	 */
	public function testIsExporting() {

		// Setup.
		$title = 'My Title';
		$content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras tristique quis justo sit amet eleifend. Praesent id metus semper, fermentum nibh at, malesuada enim. Mauris eget faucibus lectus. Vivamus iaculis eget urna non porttitor. Donec in dignissim neque. Vivamus ut ornare magna. Nulla eros nisi, maximus nec neque at, condimentum lobortis leo. Fusce in augue arcu. Curabitur lacus elit, venenatis a laoreet sit amet, imperdiet ac lorem. Curabitur sed leo sed ligula tempor feugiat. Cras in tellus et elit volutpat.</p>';
		$post_id = $this->factory->post->create( array(
			'post_title' => $title,
			'post_content' => $content,
		) );
		add_filter(
			'the_content',
			array( $this, 'filterTheContentTestIsExporting' )
		);

		// Ensure is_exporting returns false before exporting.
		$this->assertEquals(
			'is not exporting',
			apply_filters( 'the_content', 'Lorem ipsum dolor sit amet' )
		);

		// Get sections for the post.
		$sections = \Admin_Apple_Sections::get_sections_for_post( $post_id );
		$export = new Export( $this->settings, $post_id, $sections );
		$json = json_decode( $export->perform() );
		$this->assertEquals(
			'<p>is exporting</p>',
			$json->components[3]->text
		);

		// Ensure is_exporting returns false after exporting.
		$this->assertEquals(
			'is not exporting',
			apply_filters( 'the_content', 'Lorem ipsum dolor sit amet' )
		);

		// Teardown.
		remove_filter(
			'the_content',
			array( $this, 'filterTheContentTestIsExporting' )
		);
	}
}
