<?php

class EPL_Gateway_Model extends EPL_Model {


    function __construct() {
        parent::__construct();

        $this->erm = $this->epl->load_model( 'epl-registration-model' );
        $this->ecm = $this->epl->load_model( 'epl-common-model' );
    }

    /*
     * get the token and redirect to paypal
     */


    function _express_checkout_redirect() {
        global $event_details;
        $event_id = key( ( array ) $_SESSION['__epl'][$regis_id]['events'] );
        
                if (is_null($event_id)){
            return false;
        }
        $this->epl->load_file( 'libraries/gateways/paypal/paypal.php' );

        $url = (!empty( $_SERVER['HTTPS'] )) ? "https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] : "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

        //echo "<pre class='prettyprint'>" . print_r( $_SESSION, true ) . "</pre>";
        $regis_id = $this->erm->get_regis_id();



        $post_ID = $_SESSION['__epl']['post_ID'];

        //echo "<pre class='prettyprint'>" . print_r($post_ID, true). "</pre>";

        $this->ecm->setup_event_details( $event_id );

        $_totals = $this->erm->calculate_totals();

        $requestParams = array(
            'RETURNURL' => add_query_arg( array( 'cart_action' => '', 'p_ID' => $post_ID, 'regis_id' => $regis_id, 'epl_action' => '_exp_checkout_payment_success' ), $url ),
            'CANCELURL' => add_query_arg( array( 'cart_action' => '', 'p_ID' => $post_ID, 'regis_id' => $regis_id, 'epl_action' => '_exp_checkout_payment_cancel' ), $url )
        );

        $orderParams = array(
            'PAYMENTREQUEST_0_AMT' => $_totals['money_totals']['grand_total'],
            'PAYMENTREQUEST_0_SHIPPINGAMT' => 0,
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
            'PAYMENTREQUEST_0_ITEMAMT' => $_totals['money_totals']['grand_total']
        );

        $item = array(
            'L_PAYMENTREQUEST_0_NAME0' => 'Event Registration',
            'L_PAYMENTREQUEST_0_DESC0' =>  $event_details['post_title'] . ', ' . $_totals['att_quantity']['total'][$event_id] . ' tickets' ,
            'L_PAYMENTREQUEST_0_AMT0' => $_totals['money_totals']['grand_total'],
            'L_PAYMENTREQUEST_0_QTY0' => '1'
        );

        //echo "<pre class='prettyprint'>" . print_r($requestParams + $orderParams +  $item , true). "</pre>";

        $paypal = new Paypal();
        $response = $paypal->request( 'SetExpressCheckout', $requestParams + $orderParams + $item );


        if ( is_array( $response ) && $response['ACK'] == 'Success' ) { //Request successful
            $token = $response['TOKEN'];


            header( 'Location: https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=' . urlencode( $token ) );
            //header( 'Location: https://www.paypal.com/webscr?cmd=_express-checkout&token=' . urlencode( $token ) );
        }
        else {

            echo "sorry, error";
        }
    }

    /*
     * payment successfull and  back to the overview page
     *
     */


    function _exp_checkout_payment_success() {


        $this->epl->load_file( 'libraries/gateways/paypal/paypal.php' );
        if ( isset( $_GET['token'] ) && !empty( $_GET['token'] ) ) { // Token parameter exists
            // Get checkout details, including buyer information.
            // We can save it for future reference or cross-check with the data we have
            $paypal = new Paypal();
            $checkoutDetails = $paypal->request( 'GetExpressCheckoutDetails', array( 'TOKEN' => $_GET['token'] ) );
            echo "<pre class='prettyprint'>" . print_r( $checkoutDetails, true ) . "</pre>";

            // Complete the checkout transaction

            return true;
        }

        return false;
    }

    /*
     * collect payment and send to payment made page.
     */


    function _exp_checkout_do_payment() {

        global $event_details;
        $event_id = key( ( array ) $_SESSION['__epl'][$regis_id]['events'] );

        if (is_null($event_id)){
            return false;
        }

        $regis_id = $this->erm->get_regis_id();

        
        $post_ID = $_SESSION['__epl']['post_ID'];


        $this->ecm->setup_event_details( $event_id );

        $_totals = $this->erm->calculate_totals();


        $this->epl->load_file( 'libraries/gateways/paypal/paypal.php' );
        $paypal = new Paypal();
        $requestParams = array(
            'TOKEN' => $_GET['token'],
            'PAYMENTACTION' => 'Sale',
            'PAYERID' => $_GET['PayerID'],
            'PAYMENTREQUEST_0_AMT' => $_totals['money_totals']['grand_total'], // Same amount as in the original request
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD' // Same currency as the original request
        );

        $response = $paypal->request( 'DoExpressCheckoutPayment', $requestParams );
        if ( is_array( $response ) && $response['ACK'] == 'Success' ) {
            // INSET TO DB
            // SAVE DETAILS, destroy session
            //echo "<pre class='prettyprint'>" . print_r($response, true). "</pre>";


            $total_paid = $_totals['money_totals']['grand_total'];
            $date_paid = current_time('mysql');

            $transactionId = $response['PAYMENTINFO_0_TRANSACTIONID'];

            update_post_meta($post_ID,'_payment_status', 'Completed' );
            update_post_meta($post_ID,'_total_paid', $total_paid );
            update_post_meta($post_ID,'_date_paid', $date_paid );
            update_post_meta($post_ID,'_transaction_id', $transactionId );
            update_post_meta($post_ID,'_payment_type', $response['PAYMENTINFO_0_TRANSACTIONTYPE'] );

            return true; //echo "DONE";
        }
        else {
            //display error message
            echo "Sorry, but it looks like something went wrong.  Please notify the administrator or try again.";
        }
    }

}