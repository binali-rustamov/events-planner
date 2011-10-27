<?php

/*
 * Initializes menus, post types, loads assets, etc
 *
 */

class EPL_Init {


    function EPL_Init() {

        $this->__constuct();
    }


    function __constuct() {

        $this->epl = EPL_Base::get_instance();
        
        add_action( 'init', array( &$this, 'create_post_types' ) );
        add_action( 'admin_menu', array( &$this, 'admin_specific' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'front_specific' ) );
    }


    function admin_specific() {

        $this->common_js_files()
                ->admin_js_files()
                ->load_common_stylesheets()
                ->load_admin_stylesheets()
                ->create_admin_menu();

        add_action( 'admin_footer', array( &$this, 'load_slide_down_box' ) );
    }


    function front_specific() {

        $this->common_js_files()
                ->front_js_files()
                ->load_common_stylesheets()
                ->load_front_stylesheets();
        add_action( 'wp_footer', array( &$this, 'load_slide_down_box' ) );
    }

    /*
     * JS files
     */


    function common_js_files() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'events_planner_js', EPL_FULL_URL . 'js/events-planner.js', array( 'jquery' ) );
        wp_enqueue_script( 'tipsy-js', EPL_FULL_URL . 'js/tipsy.js', array( 'jquery' ) );
        wp_localize_script( 'events_planner_js', 'EPL', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'plugin_url' => EPL_FULL_URL ) );


        return $this;
    }


    function admin_js_files() {
        wp_register_script( 'jui', (EPL_FULL_URL . "js/jquery-ui-1.8.12.custom.min.js" ), false, '1.8.12' );
        wp_enqueue_script( 'jui' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'epl-forms-js', EPL_FULL_URL . 'js/events-planner-forms.js', array( 'jquery' ) );
        wp_enqueue_script( 'epl-event-manager-js', EPL_FULL_URL . 'js/epl-event-manager.js', array( 'jquery' ) );
        wp_enqueue_script( 'jquery-ui-timepicker', EPL_FULL_URL . 'js/jquery.ui.timepicker.js', array( 'jquery' ) );

        return $this;
    }


    function front_js_files() {
        wp_enqueue_script( 'events_planner_front_js', EPL_FULL_URL . 'js/epl-front.js', array( 'jquery' ) );

        return $this;
    }

    /*
     * CSS files
     */


    function load_common_stylesheets() {

        wp_enqueue_style( 'events-planner-stylesheet-main', EPL_FULL_URL . 'css/style.css' );

        //wp_enqueue_style( 'widget-calendar-css', EPL_FULL_URL . 'css/calendar/widget-calendar-default.css' );
        wp_enqueue_style( 'small-calendar-css', EPL_FULL_URL . 'css/calendar/small-calendar.css' );

        return $this;
    }


    function load_admin_stylesheets() {

        wp_enqueue_style( 'events-planner-jquery-ui-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/smoothness/jquery-ui.css' );
        wp_enqueue_style( 'events-planner-stylesheet', EPL_FULL_URL . 'css/admin_style.css' );
        //wp_enqueue_style( 'events-planner-jquery-ui-style', EPL_FULL_URL . 'css/ui-lightness/jquery-ui-1.8.12.custom.css' );

        return $this;
    }


    function load_front_stylesheets() {
        wp_enqueue_style( 'registration-form-css', EPL_FULL_URL . 'css/regis-form/regis-form-style1.css' );
        //wp_enqueue_style( 'widget-calendar-css', EPL_FULL_URL . 'css/calendar/widget-calendar-default.css' );
    }

    /*
     * HTML
     */


    function load_slide_down_box() {
        echo $this->epl->load_view( 'common/slide-down-box', '', true );
    }

    /*
     * Menus
     */


    function create_admin_menu() {

        add_submenu_page( 'edit.php?post_type=epl_event', epl__( 'Form Manager' ), epl__( 'Form Manager' ), 'manage_options', 'epl_form_manager', 'events_planner_route' );
        add_submenu_page( 'edit.php?post_type=epl_event', epl__( 'Settings' ), epl__( 'Settings' ), 'manage_options', 'epl_settings', 'events_planner_route' );
    }

    /*
     * Misc
     */


    function activate() {
        update_option( 'events_planner_version', EPL_PLUGIN_VERSION );
        update_option( 'events_planner_active', 1 );
    }


    function deactivate() {

        update_option( 'events_planner_active', 0 );
    }


    function route() {

        echo $this->epl->epl_router->route();
    }


    function shortcode_route() {
        return $this->epl->epl_router->shortcode_route();
    }


    /*
     * Custom Post types (http://codex.wordpress.org/Function_Reference/register_post_type)
     */


    function create_post_types() {

        $events_planner_args = array(
            'public' => true,
            'query_var' => 'epl_event',
            'rewrite' => array(
                'slug' => 'event',
                'with_front' => false,
            ),
            'supports' => array( 'title', 'post-formats', 'thumbnail', 'editor' ),
            'labels' => array(
                'name' => 'Events Planner',
                'singular_name' => 'Event Planner',
                'add_new' => 'Add New Event',
                'add_new_item' => 'Add New Event',
                'edit_item' => 'Edit Event',
                'new_item' => 'New Event',
                'view_item' => 'View Event',
                'search_items' => 'Search Events',
                'not_found' => 'No Events Found',
                'not_found_in_trash' => 'No Events Found In Trash'
            ),
        );

        /* Register main custom post type. */
        register_post_type( 'epl_event', $events_planner_args );

        $post_type_args = array(
            'public' => true,
            'query_var' => 'epl_location',
            'rewrite' => array(
                'slug' => 'location',
                'with_front' => false,
            ),
            'supports' => array( 'title', 'post-formats', 'editor', 'thumbnail' ),
            'labels' => array(
                'name' => 'Event Locations',
                'singular_name' => 'Event Location',
                'add_new' => 'Add New Event Location',
                'add_new_item' => 'Add New Event Location',
                'edit_item' => 'Edit Event Location',
                'new_item' => 'New Event Location',
                'view_item' => 'View Event Location',
                'search_items' => 'Search Event Locations',
                'not_found' => 'No Event Locations Found',
                'not_found_in_trash' => 'No Event Locations Found In Trash'
            ),
            'show_in_menu' => 'edit.php?post_type=epl_event'
        );


        register_post_type( 'epl_location', $post_type_args );

       //this will be turned in 1.1 or 1.2
        
       /* $post_type_args = array(
            //'public' => true,
            'show_ui' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_in_nav_menus' => false,
            'show_in_menu' => true,
            'query_var' => 'epl_registration',
            'rewrite' => array(
                'slug' => 'registration',
                'with_front' => false,
            ),
            'supports' => array( 'title' ),
            'labels' => array(
                'name' => 'Registrations',
                'singular_name' => 'Registration',
                'add_new' => 'Add New Registration',
                'add_new_item' => 'Add New Registration',
                'edit_item' => 'Edit Registration',
                'new_item' => 'New Registration',
                'view_item' => 'View Registration',
                'search_items' => 'Search Registrations',
                'not_found' => 'No Registrations Found',
                'not_found_in_trash' => 'No Registrations Found In Trash'
            ),
            'show_in_menu' => 'edit.php?post_type=epl_event'
        );


        register_post_type( 'epl_registration', $post_type_args );*/


        $post_type_args = array(
            //'public' => true,
            'show_ui' => true,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_in_nav_menus' => false,
            'show_in_menu' => true,
            'query_var' => 'epl_pay_profile',
            'rewrite' => array(
                'slug' => 'pay_profile',
                'with_front' => false,
            ),
            'supports' => array( 'title' ),
            'labels' => array(
                'name' => 'Payment Profiles',
                'singular_name' => 'Payment Profile',
                'add_new' => 'Add New Payment Profile',
                'add_new_item' => 'Add New Payment Profile',
                'edit_item' => 'Edit Payment Profiles',
                'new_item' => 'New Payment Profile',
                'view_item' => 'View Payment Profile',
                'search_items' => 'Search Payment Profiles',
                'not_found' => 'No Payment Profiles Found',
                'not_found_in_trash' => 'No Payment Profiles Found In Trash'
            ),
            'show_in_menu' => 'edit.php?post_type=epl_event'
        );


        register_post_type( 'epl_pay_profile', $post_type_args );

        $post_type_args = array(
            //'public' => true,
            'show_ui' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'show_in_nav_menus' => true,
            'show_in_menu' => true,
            'query_var' => 'epl_org',
            'rewrite' => array(
                'slug' => 'org',
                'with_front' => false,
            ),
            'supports' => array( 'title', 'editor', 'thumbnail' ),
            'labels' => array(
                'name' => 'Organizations',
                'singular_name' => 'Organization',
                'add_new' => 'Add New Organization',
                'add_new_item' => 'Add New Organization',
                'edit_item' => 'Edit Organization',
                'new_item' => 'New Organization',
                'view_item' => 'View Organization',
                'search_items' => 'Search Organizations',
                'not_found' => 'No Payment Organizations Found',
                'not_found_in_trash' => 'No Payment Organizations Found In Trash'
            ),
            'show_in_menu' => 'edit.php?post_type=epl_event'
        );


        register_post_type( 'epl_org', $post_type_args );


        /*
         * event categories
         */

        $events_planner_cat_args = array(
            'hierarchical' => true,
            'query_var' => 'events_categories',
            'show_tagcloud' => true,
            'rewrite' => array(
                'slug' => 'event_categories',
                'with_front' => false
            ),
            'labels' => array(
                'name' => 'Categories',
                'singular_name' => 'Category',
                'edit_item' => 'Edit Category',
                'update_item' => 'Update Category',
                'add_new_item' => 'Add New Category',
                'new_item_name' => 'New Category Name',
                'all_items' => 'All Category',
                'search_items' => 'Search Category',
                'parent_item' => 'Parent Category',
                'parent_item_colon' => 'Parent Category:',
            ),
        );


        /* Register the event taxonomy. */
        register_taxonomy( 'epl_event_categories', array( 'epl_event' ), $events_planner_cat_args );
    }

}