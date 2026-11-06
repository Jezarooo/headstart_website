<?php

/**
 * Search-Topics
 */
/**
 * Site search: Quick answers and "Hide from site search".
 *
 * - Registers the private post type hs_search_topic ("Quick answers") under a new
 *   top-level admin menu "Site search". The index status and Documents screens are
 *   added to the same menu by the Search-Index-Builder snippet.
 * - Registers the ACF fields for a quick answer: a standard card (text, note, up to two
 *   buttons) or a contact card (one column per campus).
 * - Adds a "Hide from site search" tick box to pages, posts, weekly updates and events.
 * - Seeds the starter quick answers once, as drafts (drafts never reach the index).
 *
 * No rewrite rules: no permalink flush needed.
 */

if ( ! function_exists( 'hs_search_menu_slug' ) ) {
	function hs_search_menu_slug() { return 'hs-site-search'; }
}

/* ---------------------------------------------------------------------------
 * Post type + admin menu
 * ------------------------------------------------------------------------ */
add_action( 'init', 'hs_search_register_topic_cpt' );
function hs_search_register_topic_cpt() {
	register_post_type( 'hs_search_topic', array(
		'labels'              => array(
			'name'               => 'Quick answers',
			'singular_name'      => 'Quick answer',
			'add_new_item'       => 'Add quick answer',
			'edit_item'          => 'Edit quick answer',
			'new_item'           => 'New quick answer',
			'search_items'       => 'Search quick answers',
			'not_found'          => 'No quick answers yet',
			'menu_name'          => 'Quick answers',
			'all_items'          => 'Quick answers',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => hs_search_menu_slug(),
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'rewrite'             => false,
		'query_var'           => false,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'hierarchical'        => false,
		'supports'            => array( 'title', 'page-attributes' ),
		'capability_type'     => 'page',
	) );
}

add_action( 'admin_menu', 'hs_search_add_menu', 9 );
function hs_search_add_menu() {
	add_menu_page(
		'Site search',
		'Site search',
		'edit_others_posts',
		hs_search_menu_slug(),
		'hs_search_menu_router',
		'dashicons-search',
		26
	);
}

/** The top-level page shows the index status (provided by the Search-Index-Builder snippet). */
function hs_search_menu_router() {
	if ( function_exists( 'hs_search_render_status_page' ) ) {
		hs_search_render_status_page();
		return;
	}
	echo '<div class="wrap"><h1>Site search</h1><p>The Search Index Builder snippet is not active.</p></div>';
}

/* ---------------------------------------------------------------------------
 * ACF fields
 * ------------------------------------------------------------------------ */
add_action( 'acf/init', 'hs_search_register_topic_fields' );
function hs_search_register_topic_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$is_contact = array( array( array( 'field' => 'field_hs_search_card', 'operator' => '==', 'value' => 'contact' ) ) );
	$text = function ( $name, $label, $extra = array() ) {
		return array_merge( array(
			'key'   => 'field_hs_search_' . $name,
			'label' => $label,
			'name'  => 'hs_topic_' . $name,
			'type'  => 'text',
		), $extra );
	};

	$fields = array(
		array(
			'key'           => 'field_hs_search_card',
			'label'         => 'Card type',
			'name'          => 'hs_topic_card',
			'type'          => 'select',
			'choices'       => array( 'standard' => 'Standard (text and buttons)', 'contact' => 'Contact (one column per campus)' ),
			'default_value' => 'standard',
			'return_format' => 'value',
		),
		array(
			'key'          => 'field_hs_search_synonyms',
			'label'        => 'Search words',
			'name'         => 'hs_topic_synonyms',
			'type'         => 'textarea',
			'rows'         => 3,
			'instructions' => 'Words and phrases people type, separated by commas or new lines. Example: fees, cost, how much, tuition.',
		),
		array(
			'key'          => 'field_hs_search_answer',
			'label'        => 'Short answer',
			'name'         => 'hs_topic_answer',
			'type'         => 'textarea',
			'rows'         => 2,
			'maxlength'    => 300,
			'instructions' => 'One or two sentences shown on the card.',
		),
		$text( 'note', 'Note line', array( 'instructions' => 'Optional. An email address in it becomes a link.' ) ),
		$text( 'btn1_label', 'Button 1 label', array( 'wrapper' => array( 'width' => '40' ) ) ),
		$text( 'btn1_url', 'Button 1 link', array( 'wrapper' => array( 'width' => '60' ), 'instructions' => 'A page path such as /contact-us/ or a full URL.' ) ),
		$text( 'btn2_label', 'Button 2 label', array( 'wrapper' => array( 'width' => '40' ) ) ),
		$text( 'btn2_url', 'Button 2 link', array( 'wrapper' => array( 'width' => '60' ) ) ),
		array(
			'key'          => 'field_hs_search_pinned',
			'label'        => 'Show when the search box is empty',
			'name'         => 'hs_topic_pinned',
			'type'         => 'true_false',
			'ui'           => 1,
		),
	);

	foreach ( array( 'c1' => 'Campus 1', 'c2' => 'Campus 2' ) as $c => $label ) {
		$fields[] = array( 'key' => 'field_hs_search_' . $c . '_tab', 'label' => $label, 'name' => '', 'type' => 'tab', 'conditional_logic' => $is_contact );
		$fields[] = $text( $c . '_name', 'Campus name', array( 'conditional_logic' => $is_contact ) );
		$fields[] = array( 'key' => 'field_hs_search_' . $c . '_address', 'label' => 'Address', 'name' => 'hs_topic_' . $c . '_address', 'type' => 'textarea', 'rows' => 2, 'conditional_logic' => $is_contact );
		$fields[] = $text( $c . '_phone1', 'Phone 1', array( 'conditional_logic' => $is_contact, 'wrapper' => array( 'width' => '50' ) ) );
		$fields[] = $text( $c . '_phone2', 'Phone 2', array( 'conditional_logic' => $is_contact, 'wrapper' => array( 'width' => '50' ) ) );
		$fields[] = $text( $c . '_email', 'Email', array( 'conditional_logic' => $is_contact, 'wrapper' => array( 'width' => '50' ) ) );
		$fields[] = $text( $c . '_map', 'Google Maps link', array( 'conditional_logic' => $is_contact, 'wrapper' => array( 'width' => '50' ) ) );
	}
	$fields[] = array( 'key' => 'field_hs_search_hours_tab', 'label' => 'Times', 'name' => '', 'type' => 'tab', 'conditional_logic' => $is_contact );
	$fields[] = $text( 'hours_label', 'Times label', array( 'conditional_logic' => $is_contact, 'default_value' => 'School visits & tours' ) );
	$fields[] = $text( 'hours', 'Times', array( 'conditional_logic' => $is_contact ) );

	acf_add_local_field_group( array(
		'key'      => 'group_hs_search_topic',
		'title'    => 'Quick answer',
		'fields'   => $fields,
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'hs_search_topic' ) ) ),
		'position' => 'normal',
		'style'    => 'default',
	) );

	$where = array();
	foreach ( array( 'page', 'post', 'weekly_update', 'events' ) as $pt ) {
		$where[] = array( array( 'param' => 'post_type', 'operator' => '==', 'value' => $pt ) );
	}
	acf_add_local_field_group( array(
		'key'      => 'group_hs_search_exclude',
		'title'    => 'Site search',
		'fields'   => array( array(
			'key'          => 'field_hs_search_exclude',
			'label'        => 'Hide from site search',
			'name'         => 'hs_search_exclude',
			'type'         => 'true_false',
			'ui'           => 1,
			'instructions' => 'Leave this item (and the documents found only on it) out of the search box.',
		) ),
		'location' => $where,
		'position' => 'side',
		'style'    => 'default',
	) );
}

/* ---------------------------------------------------------------------------
 * Seed the starter quick answers once, as drafts
 * ------------------------------------------------------------------------ */
add_action( 'admin_init', 'hs_search_seed_topics' );
function hs_search_seed_topics() {
	if ( get_option( 'hs_search_topics_seeded' ) || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$existing = get_posts( array( 'post_type' => 'hs_search_topic', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		update_option( 'hs_search_topics_seeded', 1, false );
		return;
	}

	$seed = array(
		array( 'Contact the school', array(
			'card' => 'contact', 'pinned' => 1,
			'synonyms' => 'contact, contact us, phone, telephone, call, email, address, office, reception, location, map, directions, find us, where',
			'btn1_label' => 'Contact page', 'btn1_url' => '/contact-us/',
			'c1_name' => 'Chaofah City Campus', 'c1_address' => '39/99 Moo 4, Vichitsongkram Rd., Soi Na Seau 1, T.Vichit, A.Muang, Phuket 83000',
			'c1_phone1' => '+66-76-612875', 'c1_phone2' => '+66-88-765-5878', 'c1_email' => 'info.city@headstartphuket.com', 'c1_map' => 'https://maps.app.goo.gl/h1Bf1KAVkkpF7KuL6',
			'c2_name' => 'Cherngtalay Campus', 'c2_address' => '9/2 Srisoonthorn Road, T.Cherngtalay, A.Thalang, Phuket 83110',
			'c2_phone1' => '+66-76-510623', 'c2_phone2' => '+66-81-678-6466', 'c2_email' => 'info.north@headstartphuket.com', 'c2_map' => 'https://maps.app.goo.gl/JdCREJnwAFNRrVDq6',
			'hours_label' => 'School visits & tours', 'hours' => 'Mon – Fri · 9:00 – 15:00, or Saturday mornings by appointment',
		) ),
		array( 'School fees', array(
			'pinned' => 1,
			'synonyms' => 'fees, fee, cost, costs, price, prices, tuition, how much, payment, pay, invoice, discount, sibling discount',
			'answer' => 'Tuition fees, deposits and payment terms for both campuses are on one page.',
			'note' => 'Questions about fees? fees@headstartphuket.com',
			'btn1_label' => 'Tuition & Fees', 'btn1_url' => '/resources-and-information/tuition-fees/',
		) ),
		array( 'How to enrol', array(
			'pinned' => 1,
			'synonyms' => 'admissions, admission, enrol, enroll, enrolment, enrollment, apply, application, join, registration, register, places, waiting list, visa',
			'answer' => 'Start with enrolment and visa support, or book a school tour with the admissions team.',
			'btn1_label' => 'Enrolment & Visa Support', 'btn1_url' => '/resources-and-information/enrolment-and-visa-support/',
			'btn2_label' => 'Book a school tour', 'btn2_url' => '/booking/',
		) ),
		array( 'Book a tour', array(
			'synonyms' => 'tour, school tour, visit, open day, open morning, look around, book a visit',
			'answer' => 'Visits run Monday to Friday, 9:00 to 15:00, or on Saturday mornings by appointment.',
			'btn1_label' => 'Book a school tour', 'btn1_url' => '/booking/',
		) ),
		array( 'Term dates', array(
			'pinned' => 1,
			'synonyms' => 'term dates, calendar, holidays, half term, school year, academic calendar, closures, key dates',
			'answer' => 'Term dates and key dates for the academic year.',
			'btn1_label' => 'Calendars & Key Dates', 'btn1_url' => '/resources-and-information/calendars-key-dates/',
		) ),
		array( 'ECAs & clubs', array(
			'synonyms' => 'eca, ecas, clubs, after school, activities, extracurricular, extra curricular',
			'answer' => 'Extra-curricular activities run after school on both campuses.',
			'btn1_label' => 'Extra-curricular activities', 'btn1_url' => '/en/extra-curricular-activities-ecas/',
		) ),
		array( 'Careers', array(
			'synonyms' => 'jobs, careers, vacancies, work here, recruitment, teaching jobs',
			'answer' => 'Current vacancies and how to apply.',
			'btn1_label' => 'Recruitment', 'btn1_url' => '/recruitment/',
		) ),
		array( 'Policies', array(
			'synonyms' => 'policy, policies, safeguarding, child protection, complaints, privacy, pdpa',
			'btn1_label' => 'Key Policies', 'btn1_url' => '/key-policies/',
		) ),
		array( 'Alumni', array(
			'synonyms' => 'alumni, former students, old students, graduates',
			'btn1_label' => 'Alumni Network', 'btn1_url' => '/alumni-network/',
		) ),
		array( 'Uniform', array( 'synonyms' => 'uniform, school uniform, pe kit, shoes, uniform shop' ) ),
		array( 'Lunch menu', array( 'synonyms' => 'lunch, food, canteen, menu, meals, snacks' ) ),
		array( 'Transport', array( 'synonyms' => 'bus, buses, transport, school bus, pick up, drop off' ) ),
		array( 'Curriculum', array( 'synonyms' => 'curriculum, british curriculum, igcse, a level, a-levels, eyfs, key stage, subjects' ) ),
		array( 'Scholarships', array( 'synonyms' => 'scholarship, scholarships, bursary, financial aid' ) ),
		array( 'Shop', array( 'synonyms' => 'shop, yearbook, store, buy' ) ),
	);

	$order = 0;
	foreach ( $seed as $row ) {
		$id = wp_insert_post( array(
			'post_type'   => 'hs_search_topic',
			'post_status' => 'draft',
			'post_title'  => $row[0],
			'menu_order'  => $order++,
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		$data = array_merge( array( 'card' => 'standard' ), $row[1] );
		foreach ( $data as $name => $value ) {
			update_post_meta( $id, 'hs_topic_' . $name, $value );
			update_post_meta( $id, '_hs_topic_' . $name, 'field_hs_search_' . ( 'card' === $name ? 'card' : $name ) );
		}
	}
	update_option( 'hs_search_topics_seeded', 1, false );
}
