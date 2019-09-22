<?php

class Contact_Form_7_Multilingual {
	/**
	 * Instance of Sitepress.
	 *
	 * @var \SitePress
	 */
	private $sitepress;

	const POST_TYPE = 'wpcf7_contact_form';

	/**
	 * Contact_Form_7_Multilingual constructor.
	 *
	 * @param \SitePress $sitepress An instance of SitePress.
	 */
	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	/**
	 * Adds the required hooks.
	 */
	public function init_hooks() {
		add_filter( 'shortcode_atts_wpcf7', array( $this, 'translate_shortcode_form_id' ) );
		add_filter( 'icl_job_elements', array( $this, 'remove_body_from_translation_job' ), 10, 2 );
		add_filter( 'wpml_document_view_item_link', array( $this, 'document_view_item_link' ), 10, 5 );
		add_filter( 'wpml_document_edit_item_link', array( $this, 'document_edit_item_link' ), 10, 5 );

		add_action( 'save_post', array( $this, 'fix_setting_language_information' ) );

		add_action( 'wpcf7_admin_misc_pub_section', array( $this, 'add_language_meta_box' ) );

		add_action( 'admin_init', array( $this, 'maybe_setup_post_edit' ) );
	}

	/**
	 * Call WPML_Post_Edit_Screen while we are editing a form.
	 * This is required for the language meta box functionality.
	 */
	public function maybe_setup_post_edit() {
		global $pagenow, $post_edit_screen;

		// phpcs:disable
		$is_wpcf7_page  = ! empty( $_GET['page'] ) &&  ( 'wpcf7' === $_GET['page'] );
		$is_edit_action = ! empty( $_GET['post'] );
		// phpcs:enable

		if ( 'admin.php' === $pagenow && $is_wpcf7_page && $is_edit_action ) {
			// Duplicate the code in wpml_maybe_setup_post_edit().
			$post_edit_screen = new WPML_Post_Edit_Screen( $this->sitepress );
			add_action( 'admin_head', array( $this->sitepress, 'post_edit_language_options' ) );

			// Duplicate the code in SitePress::js_load().
			wp_register_script( 'sitepress-post-edit-tags', ICL_PLUGIN_URL . '/res/js/post-edit-terms.js', array( 'jquery' ) );
			$post_edit_messages = array(
				'switch_language_title'   => __( 'You are about to change the language of {post_name}.', 'sitepress' ),
				'switch_language_alert'   => __( 'All categories and tags will be translated if possible.', 'sitepress' ),
				'connection_loss_alert'   => __( 'The following terms do not have a translation in the chosen language and will be disconnected from this post:', 'sitepress' ),
				'loading'                 => __( 'Loading Language Data for {post_name}', 'sitepress' ),
				'switch_language_message' => __( 'Please make sure that you\'ve saved all the changes. We will have to reload the page.', 'sitepress' ),
				'switch_language_confirm' => __( 'Do you want to continue?', 'sitepress' ),
				'_nonce'                  => wp_create_nonce( 'wpml_switch_post_lang_nonce' ),
				'empty_post_title'        => __( '(No title for this post yet)', 'sitepress' ),
				'ok_button_label'         => __( 'OK', 'sitepress' ),
				'cancel_button_label'     => __( 'Cancel', 'sitepress' ),
			);
			wp_localize_script( 'sitepress-post-edit-tags', 'icl_post_edit_messages', $post_edit_messages );
			wp_enqueue_script( 'sitepress-post-edit-tags' );
		}
	}

	/**
	 * Translate the `id` in the shortcode attributes on-the-fly.
	 *
	 * @param array $out Shortcode attributes to be filtered.
	 *
	 * @return array
	 */
	public function translate_shortcode_form_id( $out ) {
		$out['id'] = apply_filters( 'wpml_object_id', $out['id'], self::POST_TYPE, true );

		return $out;
	}

	/**
	 * Don't translate the post_content of contact forms.
	 *
	 * @param array $elements Translation job elements.
	 * @param int   $post_id  The post ID.
	 *
	 * @return array
	 */
	public function remove_body_from_translation_job( $elements, $post_id ) {
		// Bail out early if its not a CF7 form.
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $elements;
		}

		// Search for the body element and empty it so that it's not displayed in the TE.
		$field_types = wp_list_pluck( $elements, 'field_type' );
		$index       = array_search( 'body', $field_types, true );
		if ( false !== $index ) {
			$elements[ $index ]->field_data            = '';
			$elements[ $index ]->field_data_translated = '';
		}

		return $elements;
	}

	/**
	 * Remove the 'View' link from translation jobs because Contact
	 * Forms don't have a link to 'View' them.
	 *
	 * @param string $link   The complete link.
	 * @param string $text   The text to link.
	 * @param object $job    The corresponding translation job.
	 * @param string $prefix The prefix of the element type.
	 * @param string $type   The element type.
	 *
	 * @return string
	 */
	public function document_view_item_link( $link, $text, $job, $prefix, $type ) {
		if ( self::POST_TYPE === $type ) {
			$link = '';
		}

		return $link;
	}

	/**
	 * Adjust the 'Edit' link from translation jobs because Contact
	 * Forms have a different URL for editing.
	 *
	 * @param string $link             The complete link.
	 * @param string $text             The text to link.
	 * @param object $current_document The document to translate.
	 * @param string $prefix           The prefix of the element type.
	 * @param string $type             The element type.
	 *
	 * @return string
	 */
	public function document_edit_item_link( $link, $text, $current_document, $prefix, $type ) {
		if ( self::POST_TYPE === $type ) {
			$url  = sprintf( 'admin.php?page=wpcf7&post=%d&action=edit', $current_document->ID );
			$link = sprintf( '<a href="%s">%s</a>', admin_url( $url ), $text );
		}

		return $link;
	}

	/**
	 * Add the WPML meta box when editing forms.
	 *
	 * @param int|\WP_Post $post The post ID or an instance of WP_Post.
	 */
	public function add_language_meta_box( $post ) {

		$post = get_post( $post );

		if ( $post ) {
			add_filter( 'wpml_post_edit_can_translate', '__return_true' );

			echo '<div style="padding: 6px 10px">';
			$this->sitepress->meta_box( $post );
			echo '</div>';
		}
	}

	/**
	 * CF7 sets post_ID to -1 for new forms.
	 * WPML thinks we are saving a different post and doesn't save language information.
	 * Removing it fixes the misunderstanding.
	 */
	public function fix_setting_language_information() {
		if ( empty( $_POST['_wpnonce'] ) || empty( $_POST['post_ID'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wpcf7-save-contact-form_' . $_POST['post_ID'] ) ) {
			return;
		}

		if ( -1 === (int) $_POST['post_ID'] ) {
			unset( $_POST['post_ID'] );
		}
	}

}
