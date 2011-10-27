<?php

class EPL_common_model extends EPL_Model {

    private static $instance;


    function __construct() {
        parent::__construct();
        epl_log( 'init', get_class() . " initialized" );
        global $ecm;
        //$ecm = & $this;

        self::$instance = $this;
    }


    public static function get_instance() {
        if ( !self::$instance ) {

            self::$instance = new EPL_common_model;
        }

        return self::$instance;
    }


    function epl_get_event_data() {
        
    }


    function _delete() {

        if ( !empty( $_POST ) && check_admin_referer( 'epl_form_nonce', '_epl_nonce' ) ) {

            global $epl_fields;

            $this->scope = esc_sql( $_POST['form_scope'] );

            if ( !array_key_exists( $this->scope, $epl_fields ) )
                exit( $this->epl_util->epl_invoke_error( 1 ) );

            $this->d[$this->scope] = $this->_get_fields( $this->scope );

            $_key = $_POST['_id'];

            unset( $this->d[$this->scope][$_key] );

            //if a quesiton is being deleted, we need to make sure
            //that question is removed from all forms also
            if ( $this->scope == 'epl_fields' ) {
                $this->d['epl_forms'] = $this->_get_fields( 'epl_forms' );

                if ( !empty( $this->d['epl_forms'] ) ) {

                    foreach ( $this->d['epl_forms'] as $form_id => $form_data ) {

                        if ( is_array( $form_data['epl_form_fields'] ) ) {

                            $_tmp_key = array_search( $_key, $form_data['epl_form_fields'] );

                            if ( $_tmp_key !== false ) {
                                unset( $this->d['epl_forms'][$form_id]['epl_form_fields'][$_tmp_key] );
                            }
                        }
                    }

                    update_option( 'epl_forms', $this->d['epl_forms'] );
                }
            }

            epl_log( 'debug', "<pre>" . print_r( $this->d[$this->scope], true ) . "</pre>" );

            update_option( $this->scope, $this->d[$this->scope] );

            return true;
        }
        return false;
    }


    function _save() {

        if ( !empty( $_POST ) && check_admin_referer( 'epl_form_nonce', '_epl_nonce' ) ) {
            global $epl_fields;

            //tells us which form the data comes from
            $this->scope = esc_sql( $_POST['form_scope'] );

            //Check to see if this is a valid scope.  All forms require a config array
            if ( !array_key_exists( $this->scope, $epl_fields ) )
                exit( $this->epl_util->epl_invoke_error( 1, 'no scope' ) );

            //get the options already saved for this scope
            $this->d[$this->scope] = $this->_get_fields( $this->scope );

            //get all the relevant fields associated with this scope
            $_fields = $epl_fields[$this->scope];

            //get the name of the unique id field.  The FIRST ARRAY ITEM is always the id field
            $id_field = key( $_fields );
            epl_log( 'debug', "<pre>" . print_r( $id_field, true ) . "</pre>", 1 );
            if ( is_null( $id_field ) )
                exit( $this->epl_util->epl_invoke_error( 1, 'no id' ) );

            //if adding then the id field will come in as empty
            //we create a unique id based on the microtime
            //and add it to the post
            if ( $_POST['epl_form_action'] == 'add' ) {
                //$_key = (string) microtime(true); //making this string so it can be used in array-flip, can also use uniqid()
                $_key = uniqid(); //usnig uniqid because the microtime(true) will not work in js ID field
                $_POST[$id_field] = $_key;
            }
            else {
                //in edit mode, we expect a unique id already present.
                //if not, something must have gone wrong
                $_key = $_POST[$id_field];
                if ( is_null( $_key ) )
                    exit( $this->epl_util->epl_invoke_error( 1, 'no' ) );
            }

            //this field comes in based on the row order of the form table that has sortable enabled.
            //we append the new key to the _order, for use below in rearranging 
            //the order of the keys based on user sortable action on the form
            if ( isset( $_POST['_order'] ) && is_array( $_POST['_order'] ) )
                $_POST['_order'][] = $_key;

            //We only want to save posted data that is relevant to this scope
            //so we only grab the appropriate values from the $_POST and ignore everything else
            $_post = array_intersect_key( $_POST, $_fields );

            //Since we already have the options pulled from the db into the $this->d var,
            //we just append the new key OR replace its values
            $this->d[$this->scope][$_key] = $_post;

            //temporarily assign the data to this var for reordering
            $_meta = $this->d[$this->scope];

            //if the _order field is set, we need to rearrange the keys in the order that
            //the user has selected to keep the data in
            if ( isset( $_POST['_order'] ) && is_array( $_POST['_order'] ) )
                $_meta = $this->epl_util->sort_array_by_array( $this->d[$this->scope], array_flip( $_POST['_order'] ) ); //can use uasort()
                //Save the options
                //epl_log( 'debug', "<pre>" . print_r( $_meta, true ) . "</pre>", 1 );
 update_option( $this->scope, $_meta );

            //Get ready to send the new row back
            $data[$this->scope] = $this->d[$this->scope];

            //the data that will be sent back as a table row
            $data['params']['values'][$_key] = $_post;

            //Special circumstance:
            //since the associaton between the form and fields is key based, we want
            //to display the field name also.  This makes it happen
            if ( $this->scope == 'epl_forms' || $this->scope == 'epl_admin_forms' )
                $data['epl_fields'] = $this->_get_fields( $this->scope );

            //views to use based on the scope
            //TODO make this a config item, out of this file.
            $response_views = array(
                'epl_fields' => 'admin/forms/field-small-block',
                'epl_forms' => 'admin/forms/form-small-block',
                'epl_admin_fields' => 'admin/forms/field-small-block',
                'epl_admin_forms' => 'admin/forms/form-small-block',
            );

            //return the relevant view based on scope
            return $this->epl->load_view( $response_views[$this->scope], $data, true );
        }
        return false;
    }


    function _get_fields( $scope = null, $key = null ) {


        if ( is_null( $scope ) )
            return null;

        $r = get_option( maybe_unserialize( $scope ) );

        if ( !is_null( $key ) ) {
            $r = array_key_exists( $key, $r ) ? $r[$key] : $r;
        }

        return $r;
    }


    function get_metabox_content( $param = array( ) ) {

    }


    function get_list_of_available_forms( $scope = 'epl_forms' ) {


        return $this->_get_fields( $scope );
    }


    function get_list_of_available_fields( $scope = 'epl_fields' ) {


        return $this->_get_fields( $scope );
    }


    function setup_event_details( $event_id = null ) {

        if ( is_null( $event_id ) )
            return null;

        global $event_details;
        $post_data = get_post( $event_id, ARRAY_A );

        $post_meta = $this->get_post_meta_all( $event_id );
        $event_details = ( array ) $post_data + ( array ) $post_meta;
        return $event_details;
    }


    function get_post_meta_all( $post_ID ) {
        if ( $post_ID == '' )
            __return_empty_array();

        static $r = array( ); //will keep the data just in case this method gets called again for this id

        if ( array_key_exists( $post_ID, $r ) )
            return $r[$post_ID];


        global $wpdb;
        $data = array( );
        $wpdb->query( $wpdb->prepare( "
        SELECT meta_id, post_id, meta_key, meta_value
        FROM $wpdb->postmeta
        WHERE `post_id` = %d ORDER BY meta_id
         ", $post_ID ) );

        foreach ( $wpdb->last_result as $k => $v ) {

            $data[$v->meta_key] = maybe_unserialize( $v->meta_value );

            //}
        };
        $r[] = $data;

        return $data;
    }


    function get_all_events() {
        $args = array(
            'post_type' => 'epl_event',
        );
        $e = new WP_Query( $args );
        $r = array( );

        if ( $e->have_posts() ) {

            while ( $e->have_posts() ) :
                $e->the_post();

                $r[get_the_ID()] = get_the_title();

            endwhile;
        }
        wp_reset_postdata();
        return $r;
    }


    function get_current_att_count() {
        global $post, $event_details, $wpdb, $current_att_count;

        $current_att_count = array( );

        $q = $wpdb->get_results( "SELECT meta_key, SUM(meta_value) as num_attendees
                FROM $wpdb->postmeta as pm
                INNER JOIN $wpdb->posts p ON p.ID = pm.post_id
                WHERE p.post_status = 'publish'
                AND meta_key LIKE '_total_att_{$event_details['ID']}%'
                GROUP BY meta_key", ARRAY_A );

        if ( $wpdb->num_rows > 0 ) {

            foreach ( $q as $k => $v ) {
                $current_att_count[$v['meta_key']] = $v['num_attendees'];
            }
        }
        // echo "<pre class='prettyprint'>" . print_r( $current_att_count, true ) . "</pre>";
    }


    function events_list( $param =array( ) ) {

        $args = array(
            'post_type' => 'epl_event',
            'meta_query' => array(
                'relation' => 'AND',
                /* array(
                  'key' => '_q_epl_regis_start_date',
                  'value' => array( strtotime( date("Y-m-d") ), strtotime( '2011-09-30 23:59:59' ) ),
                  //'type' => 'date',
                  'compare' => 'BETWEEN'
                  ) */
                array(
                    'key' => '_q__epl_start_date',
                    'value' => strtotime( date( "Y-m-d" ) ),
                    //'type' => 'NUMERIC',
                    'compare' => '>='
                ),
                array(
                    'key' => '_epl_event_status',
                    'value' => 1,
                    'type' => 'NUMERIC',
                    'compare' => '='
                )
            )
        );
        // The Query

        global $event_list;
        $event_list = new WP_Query( $args );

        epl_log( "debug", "<pre>" . print_r( $event_list, true ) . "</pre>" );

        return; // $the_query;

        if ( $the_query->have_posts() )
            $epl_options = $this->epl_util->get_epl_options( 'events_planner_event_options' );


        $post_info = array( );
        //ob_start();
        while ( $the_query->have_posts() ) :
            $the_query->the_post();



            $post_info[get_the_ID()] = $this->setup_event_details( get_the_ID() ); //= $this->get_post_meta_all( get_the_ID() );
        //echo "<pre class='prettyprint'>" . print_r($pm, true). "</pre>";
        //$this->epl_util

        /* echo "<h1>" . get_the_title() . "</h1>";
          /*
          echo $this->epl_util->get_time_display( &$post_mata );
          echo $this->epl_util->get_prices_display( &$post_mata );

          $epl_options['epl_show_event_description'] != 0 ? the_content() : '';

          echo $this->epl_util->construct_date_display_table( array( 'post_ID' => get_the_ID(), 'meta' => $post_mata ) );
         */
        //echo $this->epl_util->construct_calendar($pm['epl_date_blueprint']);
        endwhile;
        //$r = ob_get_contents();
        //ob_end_clean();
        //wp_reset_postdata();
        return $post_info;
    }


    function get_epl_options( $param = array( ) ) {
        $this->epl_options = array( );
        $this->epl_options = ( array ) get_option( 'events_planner_general_options' );
        $this->epl_options += ( array ) get_option( 'epl_addon_options' );
    }


    function epl_insert_post( $post_type, $meta ) {

        // Create post object
        $my_post = array(
            'post_type' => 'epl_registration',
            'post_title' => strtoupper( $this->epl_util->make_unique_id( 20 ) ),
            'post_content' => "$meta",
            'post_status' => 'draft'
        );

// Insert the post into the database
        $post_ID = wp_insert_post( $my_post );

        add_post_meta( $post_ID, 'regis_fields', $meta );
    }

    /* When the post is saved, saves our custom data */


    function _save_postdata( $args = array( ) ) {


        extract( $args );
        epl_log( "debug", "<pre>THE ARGS" . print_r($args, true ) . "</pre>" );

        if (!isset($fields) || empty($fields))
            return;
        //$epl_fields = $this->rekey_fields_array($epl_fields);
        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        //if ( !wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename( __FILE__ ) ) )
          // return;

        /*if ( !empty( $_POST ) || !check_admin_referer( 'epl_form_nonce', '_epl_nonce' ) ) {
            return;
        }*/
        // Check permissions
        /* if ( 'page' == $_POST['post_type'] )
          {
          if ( !current_user_can( 'edit_page', $post_id ) )
          return;
          }
          else
          {
          if ( !current_user_can( 'edit_post', $post_id ) )
          return;
          } */
       // epl_log( "debug", "<pre>EPL FIEDS " . print_r( $fields, true ) . "</pre>" );


        //From the config file, only get the fields that pertain to this section
        //We are only interested in the posted fields that pertain to events planner
        $event_meta = array_intersect_key( $_POST, $fields );


        epl_log( "debug", "<pre>THE META" . print_r($event_meta, true ) . "</pre>" );
        //post save callback function, if adding
        $_save_cb = 'epl_add_post_meta';

        //if editing, callback is different
        if ( $edit_mode )
            $_save_cb = 'epl_update_post_meta';

        epl_log( "debug", "<pre>" . print_r( $event_meta, true ) . "</pre>" );

        foreach ( $event_meta as $k => $data['values'] ) {

            $meta_k = $k;

            /*
             * since we need the dates to be saved as individual records (so we can query),
             * we need to check the field attribute for save_type
             *
             * TODO check if save type is ind_row > save as individual
             *  if it is individual, check if array.  If so, loop, and for each one,
             *  save accordingly
             * TODO check if data_type exists > convert to data type
             * TODO if they delete a row, need to delete it from the meta table also
             */

            /*
             * when data comes in as an array, sometimes we want to save each one of the values as
             * individual rows in the meta table so that we can query it more efficiently with the WP_Query.
             *
             */

            //check if save_type is defined for this field
            if ( array_key_exists( 'query', $fields[$meta_k] ) ) {
                delete_post_meta( $post_ID, '_q_' . $meta_k ); //these are special meta keys that will allow querying
                //check if this is an array
                if ( is_array( $data['values'] ) ) {

                    foreach ( $data['values'] as $_k => $_v ) {

                        if ( isset( $fields[$meta_k]['data_type'] ) )
                            $this->epl->epl_util->process_data_type( &$_v, $fields[$meta_k]['data_type'], 's' );

                        $this->epl_add_post_meta( $post_ID, '_q_' . $meta_k, $_v, $_k );
                    }
                }
            }

            if ( !is_array( $data['values'] ) )
                $data['values'] = esc_attr( $data['values'] );

            $this->$_save_cb( $post_ID, $meta_k, $data['values'], '' );


            /* if ( !$this->edit_mode )
              epl_add_post_meta( $post_id, $meta_k, $this->data['values'] );
              else
              update_post_meta( $post_id, $meta_k, $this->data['values'] ); */
        }

        //$epl_date_blueprint = recurrence_dates_from_meta($event_meta);
        //update_post_meta( $post_ID, 'epl_date_blueprint', recurrence_dates_from_meta );
        //return $mydata;
        //$this->upd_meta($post_id, $data);
    }


    function epl_add_post_meta( $post_id, $meta_k, $meta_value ) {

        add_post_meta( $post_id, $meta_k, $meta_value );
    }


    function epl_update_post_meta( $post_id, $meta_key, $meta_value ) {

        update_post_meta( $post_id, $meta_key, $meta_value );
    }

}