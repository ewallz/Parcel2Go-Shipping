<?php
   /*
   Plugin Name: Parcel2Go Shipping
   Plugin URI: https://www.parcel2go.com/plugins/woocommerce/instructions
   description: A plugin to book your WooCommerce sales with a shipping provider
   Version: 1.0.4
   Author: Parcel2Go.com
   Author URI: https://www.parcel2go.com
   License: GPL2
   */

// These need to be kept in sync with trunk versions
define("p2gPluginVersion", "1.0.4");
define("p2gPluginName", "P2G_Wordpress_WooCommerce_Plugin");



if (!function_exists('parcel2go_write_log')) {
    function parcel2go_write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log("Parcel2Go_Item_" . print_r( $log, true ) );
            } else {
                error_log("Parcel2Go_" . $log );
            }
        }
    }
}


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    parcel2go_write_log("Woo Commerce Not Active");
    exit;
}

class Parcel2Go_Api
{
    private $options;
    private $access_token;
    private $api_namespace;
    private $api_root;
    private $auth_root;
    private $timeout;    

    public function __construct() {
        $this->timeout = 45;
        $this->options = get_option( 'parcel2go_option_name' );
        $this->access_token = null;
        $this->api_namespace = 'parcel2go/v1';
        $this->addEndPoints();

        if((isset($this->options['client_sandbox']) && ($this->options['client_sandbox'] == "on"))){
            $this->api_root = 'https://sandbox.parcel2go.com/api'; 
            $this->auth_root = 'https://sandbox.parcel2go.com/auth';
            parcel2go_write_log("SANDBOX MODE");
        }
        else {
            $this->api_root = 'https://www.parcel2go.com/api';
            $this->auth_root = 'https://www.parcel2go.com/auth';
        }


        //$this->getToken();
    }

    public function hasAccessToken() {
        if(!isset($this->access_token) 
            || is_null($this->access_token)
            || !isset($this->access_token["success"]) 
            || is_null($this->access_token["success"])
            || $this->access_token["success"] !== true
            || !isset($this->access_token["token"]) 
            || is_null($this->access_token["token"])) {
                return false;
        }
        return true;
    }

    public function getToken() {   
        $response = wp_remote_post(  $this->auth_root . '/connect/token', array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'body' => array( 
                'grant_type' => 'client_credentials', 
                'scope' => 'public-api payment', 
                'client_id' => $this->options['client_id'], 
                'client_secret' => $this->options['client_secret'] 
            ))
        );
       
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $this->access_token = array( 'success' => false, 'token' => null, 'error' => $error_message );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            $this->access_token = array( 'success' => false, 'token' => null, 'error' => $data->error );
        } 
        else {
            $body = wp_remote_retrieve_body( $response );
            $token = json_decode($body);
            $this->access_token = array( 'success' => true, 'token' => $token->access_token, 'error' => null );
        }
    }

    private function addEndPoints() {

        add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/quote', array(
              'methods' => 'POST',
              'callback' => array( $this, 'getQuote' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/collection-dates', array(
              'methods' => 'POST',
              'callback' => array( $this, 'collectionDates' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/drop-shops', array(
              'methods' => 'POST',
              'callback' => array( $this, 'dropShops' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/order', array(
              'methods' => 'POST',
              'callback' => array( $this, 'placeOrder' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/prepaybalance', array(
              'methods' => 'GET',
              'callback' => array( $this, 'prepayBalance' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/storedcards', array(
              'methods' => 'GET',
              'callback' => array( $this, 'storedCards' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/paywithprepay', array(
              'methods' => 'POST',
              'callback' => array( $this, 'payWithPrePay' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/tracking', array(
              'methods' => 'POST',
              'callback' => array( $this, 'getTracking' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/label', array(
              'methods' => 'POST',
              'callback' => array( $this, 'getLabel' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/defaults', array(
              'methods' => 'GET',
              'callback' => array( $this, 'getDefaults' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/countries', array(
              'methods' => 'GET',
              'callback' => array( $this, 'getCountries' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/paywithstoredcard', array(
              'methods' => 'POST',
              'callback' => array( $this, 'payWithStoredCard' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });

          add_action( 'rest_api_init', function () {
            register_rest_route( $this->api_namespace, '/customs', array(
              'methods' => 'POST',
              'callback' => array( $this, 'customs' ),
              'permission_callback' => array( $this, 'permissionsCheck' )
              )
            );
          });
    }

    public function getCountries() {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }
      
        $response = wp_remote_post( $this->api_root . '/countries', array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion)
        ));

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Countries " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function getDefaults(WP_REST_Request $request) {
        $this->getToken();

        $parameters = $request->get_params();
        parcel2go_write_log("Defaults Request " . json_encode($parameters));

        if(!isset($parameters['orderId']) || is_null($parameters['orderId']) || empty($parameters['orderId'])) {
            return new WP_Error( 'Error', 'Expected Order Id', array( 'status' => 400 ) );
        }

        $order = wc_get_order( $parameters['orderId'] );

        if(!isset($order) || is_null($order) || empty($order)) {
            return new WP_Error( 'Error', 'No Such Order', array( 'status' => 400 ) );
        }
       
        $parcels = array();
        $value = 0;
        $items = 0;
        $weight = 0;
        $item;
        $contents = "";
        $orderItems = array();
        foreach ( $order->get_items() as $orderItem ) {
            
            $product = $orderItem->get_product();

            $productWeight = empty($product->get_weight()) ? 0 : $product->get_weight();

            $value += ($product->get_price() * $orderItem->get_quantity());
            //$weight += ($product->get_weight() * $orderItem->get_quantity());
            $items += $orderItem->get_quantity();
            $contents = $contents . $product->get_name() . ",";
            $item = array(
                "EstimatedValue" => ($product->get_price() * $orderItem->get_quantity()),
                "Weight" => $productWeight,
                "Length" => empty($product->get_length()) ? (float)$this->options['dimension_length'] : (float)$product->get_length(),
                "Width" => empty($product->get_width()) ? (float)$this->options['dimension_width'] : (float)$product->get_width(),
                "Height" => empty($product->get_height()) ? (float)$this->options['dimension_height'] : (float)$product->get_height(),
                "ContentsSummary" => $product->get_name(),
                "Id" => $product->get_id()
            );

            if (!array_key_exists($item->Id, $orderItems)) {
                array_push($orderItems,array(
                    "EstimatedValue" => ($product->get_price() * $orderItem->get_quantity()),
                    "Quantity" => $orderItem->get_quantity(),
                    "Description" => $product->get_name(),
                    "Id" => $product->get_id()
                ));
            }
        }

        if($items > 1) {
            $item = array(
                "Weight" => $weight,
                "Length" => (float)$this->options['dimension_length'],
                "Width" => (float)$this->options['dimension_width'],
                "Height" => (float)$this->options['dimension_height'],
                "EstimatedValue" => $value,
                "ContentsSummary" => rtrim($contents, ',')
            );
        }
       
		
					// Displaying something related
				if( $order->get_shipping_first_name() != $order->get_billing_first_name() ) {
					return (object) [
            "DeliveryAddress" => array(
                "CountryIsoCode" => "GBR",
                "Property" => $order->get_shipping_address_1(),
                "Street" => $order->get_shipping_address_2(),
                "Postcode" => $order->get_shipping_postcode(),
                "Town" => $order->get_shipping_city(),
                "Email" => $order->get_billing_email(),
                "Phone" => $order->get_billing_phone(),
                "ContactName" => $order->get_shipping_first_name() . " " . $order->get_shipping_last_name(),
                "Country" => $order->get_shipping_country(),
                "County" => $order->get_shipping_state(),
                "SpecialInstructions" => $order->get_customer_note()
            ),
			
            "CollectionAddress" => array(
                "CountryIsoCode" => "GBR",
                "Property" =>  $this->options['collection_property'],
                "Street" =>  $this->options['collection_street'],
                "Postcode" =>  $this->options['collection_postcode'],
                "Town" =>  $this->options['collection_town'],
                "Email" =>  $this->options['collection_email'],
                "Phone" =>  $this->options['collection_phone'],
                "ContactName" => $this->options['collection_firstname'] . " " . $this->options['collection_lastname'],
                "Country" =>  $this->options['collection_country'],
                "County" =>  $this->options['collection_county']
            ),
            "Box" => $item,
            "Multiple" => $items > 1,
            "Items" => $items,
            "IncludeCover" => (isset($this->options['default_include_cover']) && ($this->options['default_include_cover'] == "on")),
            "OrderItems" => $orderItems,
            "VatNumber" => $this->options['default_vat_number']
        ];
				} else {
					return (object) [
            "DeliveryAddress" => array(
                "CountryIsoCode" => "GBR",
                "Property" => $order->get_shipping_address_1(),
                "Street" => $order->get_shipping_address_2(),
                "Postcode" => $order->get_shipping_postcode(),
                "Town" => $order->get_shipping_city(),
                "Email" => $order->get_billing_email(),
                "Phone" => $order->get_billing_phone(),
                "ContactName" => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
                "Country" => $order->get_shipping_country(),
                "County" => $order->get_shipping_state(),
                "SpecialInstructions" => $order->get_customer_note()
            ),
			
            "CollectionAddress" => array(
                "CountryIsoCode" => "GBR",
                "Property" =>  $this->options['collection_property'],
                "Street" =>  $this->options['collection_street'],
                "Postcode" =>  $this->options['collection_postcode'],
                "Town" =>  $this->options['collection_town'],
                "Email" =>  $this->options['collection_email'],
                "Phone" =>  $this->options['collection_phone'],
                "ContactName" => $this->options['collection_firstname'] . " " . $this->options['collection_lastname'],
                "Country" =>  $this->options['collection_country'],
                "County" =>  $this->options['collection_county']
            ),
            "Box" => $item,
            "Multiple" => $items > 1,
            "Items" => $items,
            "IncludeCover" => (isset($this->options['default_include_cover']) && ($this->options['default_include_cover'] == "on")),
            "OrderItems" => $orderItems,
            "VatNumber" => $this->options['default_vat_number']
        ];
				}
       
        
    }

    public function permissionsCheck($request) {
        $can_use_api = current_user_can('administrator');
        return $can_use_api;
    }

    public function getQuote(WP_REST_Request $request) {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Quote Request" . json_encode($parameters));

        if(!isset($parameters['value']) || is_null($parameters['value']) || empty($parameters['value'])) {
            return new WP_Error( 'Error', 'Expected Value', array( 'status' => 400 ) );
        }
        if(!isset($parameters['weight']) || is_null($parameters['weight']) || empty($parameters['weight'])) {
            return new WP_Error( 'Error', 'Expected Weight', array( 'status' => 400 ) );
        }
        if(!isset($parameters['length']) || is_null($parameters['length']) || empty($parameters['length'])) {
            return new WP_Error( 'Error', 'Expected Length', array( 'status' => 400 ) );
        }
        if(!isset($parameters['width']) || is_null($parameters['width']) || empty($parameters['width'])) {
            return new WP_Error( 'Error', 'Expected Width', array( 'status' => 400 ) );
        }
        if(!isset($parameters['height']) || is_null($parameters['height']) || empty($parameters['height'])) {
            return new WP_Error( 'Error', 'Expected Height', array( 'status' => 400 ) );
        }
        if(!isset($parameters['collectionCountry']) || is_null($parameters['collectionCountry']) || empty($parameters['collectionCountry'])) {
            return new WP_Error( 'Error', 'Expected Collection Country', array( 'status' => 400 ) );
        }
        if(!isset($parameters['deliveryCountry']) || is_null($parameters['deliveryCountry']) || empty($parameters['deliveryCountry'])) {
            return new WP_Error( 'Error', 'Expected Delivery Country', array( 'status' => 400 ) );
        }

      
        $quote = array(
            "CollectionAddress" => array(
               "Country" => $parameters['collectionCountry'],
               "Postcode" => $parameters['collectionPostcode']
            ),
            "DeliveryAddress" => array(
                "Country" => $parameters['deliveryCountry'],
                "Postcode" => $parameters['deliveryPostcode']
             ),
            "Parcels" => array(array(
                "Value" => $parameters['value'],
                "Weight" => $parameters['weight'],
                "Length" => $parameters['length'],
                "Width" => $parameters['width'],
                "Height" => $parameters['height']
            )),
            "Extras" => array()
        );

        if($parameters['includeCover'] == "true") {
            $quote["Extras"] = array(array("type" => "ExtendedBaseCover"), array("type" => "Cover"));
        }

        parcel2go_write_log("Quoting Model" . json_encode($quote));

        $response = wp_remote_post( $this->api_root . '/quotes', array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'body' => $quote)
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Quote " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "quote" => $quote,
                "result" => $data
            ];
        }

        return (object) [
            "quote" => $quote,
            "result" => null
        ];
    }

    public function collectionDates(WP_REST_Request $request) {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Collection Dates Request " . json_encode($parameters));

        $request = array(
            "ServiceSlug" => $parameters['slug'],
            "Postcode" => $parameters['postcode'] ,
            "CountryISO3Code" => $parameters['country'] 
        );

        parcel2go_write_log("Collection Dates Model" . json_encode($request));

        $response = wp_remote_post( $this->api_root . '/collectiondates', array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'body' => $request)
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Collection Dates " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "request" => $request,
                "result" => $data
            ];
        }

        return (object) [
            "request" => $request,
            "result" => null
        ];
    }

    public function dropShops(WP_REST_Request $request) {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Drop Shops Request" . json_encode($parameters));

        $query = '/dropshops/' . $parameters['providerCode'] . '/location?location=' . $parameters['postcode'] . '&iso3CountryCode=' . $parameters['country'];
        parcel2go_write_log("Drop Shops Model" . $query);

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'body' => $request)
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Drop Shops " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "request" => $request,
                "result" => $data
            ];
        }

        return (object) [
            "request" => $request,
            "result" => null
        ];
    }

    public function customs(WP_REST_Request $request) {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Customs Request" . json_encode($parameters));
     
        $request = array(
            "Items" => array(array(
               "Id" => "00000000-0000-0000-0000-000000000000",
               "Upsells" => array(),
               "CollectionDate" => $parameters['collectionDate'],
               "Parcels" => array(array(
                    "Id" => "00000000-0000-0000-0000-000000000000",
                    "EstimatedValue" => floatval($parameters['value']),
                    "Weight" => floatval($parameters['weight']),
                    "Length" => floatval($parameters['length']),
                    "Width" => floatval($parameters['width']),
                    "Height" => floatval($parameters['height']),
                    "DeliveryAddress" => array(
                        "CountryIsoCode" => $parameters['deliveryCountry'],
                        "Property" => $parameters['deliveryProperty'],
                        "Street" => $parameters['deliveryStreet'],
                        "Postcode" => $parameters['deliveryPostcode'],
                        "Town" => $parameters['deliveryTown'],
                        "Email" => $parameters['deliveryEmail'],
                        "Phone" => $parameters['deliveryPhone'],
                        "ContactName" => $parameters['deliveryName'],
                        "County" => $parameters['deliveryCounty'],
                        "SpecialInstructions" => $parameters['specialInstructions']
                    ),
                    "ContentsSummary" => $parameters['contents']
                )),
               "Service" => $parameters['slug'],
               "CollectionAddress" => array(
                    "CountryIsoCode" => $parameters['collectionCountry'],
                    "Property" => $parameters['collectionProperty'],
                    "Street" => $parameters['collectionStreet'],
                    "Postcode" => $parameters['collectionPostcode'],
                    "Town" => $parameters['collectionTown'],
                    "ContactName" => $parameters['collectionName'],
                    "Email" => $parameters['collectionEmail'],
                    "Phone" => $parameters['collectionPhone'],
                    "County" => $parameters['collectionCounty'],
                    "ShopId" => $parameters['shopId']
               ),
            )),
            "CustomerDetails" => array(
                "Email" => $this->options['collection_email'],
                "Forename" => $this->options['collection_firstname'],
                "Surname" => $this->options['collection_lastname'],
                "OptInToEmails" => false,
                "OptInToPhone" => false,
                "OptInToPost" => false,
                "OptInToOther" => false
            ) 
        );

        if($parameters['includeCover'] == "true") {
            $request["Items"][0]["Upsells"] = array("ExtendedBaseCover", "Cover");
        }

        parcel2go_write_log("Customs Model" . json_encode($request));

        $response = wp_remote_post( $this->api_root . '/customs' , array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'body' => $request)
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Placing Order " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "request" => $request,
                "result" => $data
            ];
        }

        return (object) [
            "request" => $request,
            "result" => null
        ];
    }

    public function placeOrder(WP_REST_Request $request) {
        $this->getToken();

        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Order Request" . json_encode($parameters));
     
        $request = array(
            "Items" => array(array(
               "Id" => "6722093f-5df5-425e-a140-7c3b9e117921",
               "Upsells" => array(),
               "CollectionDate" => $parameters['collectionDate'],
               "VatNumber" => empty($parameters['vatNumber']) ? null : $parameters['vatNumber'],
               "ExportReason" => empty($parameters['exportReason']) ? null : $parameters['exportReason'],
               "OriginCountry" => empty($parameters['originCountry']) ? null : $parameters['originCountry'],
               "VatStatus" => empty($parameters['vatStatus']) ? null : $parameters['vatStatus'],
               "Parcels" => array(array(
                    "Id" => "00000000-0000-0000-0000-000000000000",
                    "EstimatedValue" => floatval($parameters['value']),
                    "Weight" => floatval($parameters['weight']),
                    "Length" => floatval($parameters['length']),
                    "Width" => floatval($parameters['width']),
                    "Height" => floatval($parameters['height']),
                    "DeliveryAddress" => array(
                        "CountryIsoCode" => $parameters['deliveryCountry'],
                        "Property" => $parameters['deliveryProperty'],
                        "Street" => $parameters['deliveryStreet'],
                        "Postcode" => $parameters['deliveryPostcode'],
                        "Town" => $parameters['deliveryTown'],
                        "Email" => $parameters['deliveryEmail'],
                        "Phone" => $parameters['deliveryPhone'],
                        "ContactName" => $parameters['deliveryName'],
                        "County" => $parameters['deliveryCounty'],
                        "SpecialInstructions" => $parameters['specialInstructions']
                    ),
                    "ContentsSummary" => $parameters['contents'],
                    "Contents" => empty($parameters['summary']) ? null : $parameters['summary']
                )),
               "Service" => $parameters['slug'],
               "CollectionAddress" => array(
                    "CountryIsoCode" => $parameters['collectionCountry'],
                    "Property" => $parameters['collectionProperty'],
                    "Street" => $parameters['collectionStreet'],
                    "Postcode" => $parameters['collectionPostcode'],
                    "Town" => $parameters['collectionTown'],
                    "ContactName" => $parameters['collectionName'],
                    "Email" => $parameters['collectionEmail'],
                    "Phone" => $parameters['collectionPhone'],
                    "County" => $parameters['collectionCounty'],
                    "ShopId" => $parameters['shopId']
               ),
            )),
            "CustomerDetails" => array(
                "Email" => $this->options['collection_email'],
                "Forename" => $this->options['collection_firstname'],
                "Surname" => $this->options['collection_lastname'],
                "OptInToEmails" => false,
                "OptInToPhone" => false,
                "OptInToPost" => false,
                "OptInToOther" => false
            ) 
        );

        if($parameters['includeCover'] == "true") {
            $request["Items"][0]["Upsells"] = array("ExtendedBaseCover", "Cover");
        }

        parcel2go_write_log("Order Model" . json_encode($request));

        $response = wp_remote_post( $this->api_root . '/orders' , array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'body' => $request
        ));

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Placing Order " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "request" => $request,
                "result" => $data
            ];
        }

        return (object) [
            "request" => $request,
            "result" => null
        ];
    }

    public function prepayBalance(WP_REST_Request $request) {
        $this->getToken();
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        parcel2go_write_log("PrePay Request");

        $query = '/prepay';

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion))
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting PrePal " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function storedCards(WP_REST_Request $request) {
        $this->getToken();
        
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        parcel2go_write_log("Stored Cards Request");

        $query = '/storedcards';

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion))
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Stored Cards " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function payWithPrePay(WP_REST_Request $request) {
        $this->getToken();
        
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Pay With PrePay Request" . json_encode($parameters));

        $query = '/orders/' . $parameters["order"]["OrderId"] . '/paywithprepay?hash=' . $parameters["order"]["Hash"];

        parcel2go_write_log("Pay With PrePay Model" . $query);

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion))
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Paying With PrePay " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);

            $order = wc_get_order( $parameters['internalOrderId'] );
            $message = sprintf( __( 'Order Booked With Parcel2Go.com by %s OrderId %s', 'parcel2go' ), wp_get_current_user()->display_name, $parameters["order"]["OrderId"] );
            $order->add_order_note( $message );
            $order->update_status('completed');
            
            update_post_meta( $order->get_id(), '_parcel2go_order', json_encode($parameters) );
            update_post_meta( $order->get_id(), '_parcel2go_orderId', $parameters["order"]["OrderId"] );
            update_post_meta( $order->get_id(), '_parcel2go_orderLineId', $parameters["order"]["OrderlineIdMap"][0]["OrderLineId"] );
            
            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function payWithStoredCard(WP_REST_Request $request) {
        $this->getToken();
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Pay With Stored Card Request" . json_encode($parameters));

        $query = '/orders/' . $parameters["order"]["OrderId"] . '/paywithstoredcard?hash=' . $parameters["order"]["Hash"] . '&storedCardId=' . $parameters["storedCardId"];

        parcel2go_write_log("Pay With Stored Card Model" . $query);

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion))
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Paying With PrePay " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);

            $order = wc_get_order( $parameters['internalOrderId'] );
            $message = sprintf( __( 'Order Booked With Parcel2Go.com by %s OrderId %s', 'parcel2go' ), wp_get_current_user()->display_name, $parameters["order"]["OrderId"] );
            $order->add_order_note( $message );
            $order->update_status('completed');
            
            update_post_meta( $order->get_id(), '_parcel2go_order', json_encode($parameters) );
            update_post_meta( $order->get_id(), '_parcel2go_orderId', $parameters["order"]["OrderId"] );
            update_post_meta( $order->get_id(), '_parcel2go_orderLineId', $parameters["order"]["OrderlineIdMap"][0]["OrderLineId"] );
            
            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function getTracking(WP_REST_Request $request) {
        $this->getToken();
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Tracking Request" . json_encode($parameters));


        $query = '/tracking/' . $parameters["orderLineId"];
        // $query = '/tracking/43949175';

        // parcel2go_write_log("Tracking Model" . $query);
        // parcel2go_write_log("Tracking timeOut" . $this->timeout);
        // parcel2go_write_log("Tracking whole req " . $request);
        

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion),
            'cookies' => array())
        );
        
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Tracking " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);

            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }

    public function getLabel(WP_REST_Request $request) {
        $this->getToken();
        if(!$this->hasAccessToken()) {
            return new WP_Error( 'Auth', 'Authorization Failed', array( 'status' => 403, 'reason' => $this->access_token["error"] ) );
        }

        $parameters = $request->get_params();
        parcel2go_write_log("Get Label Request " . json_encode($parameters));

        $query = '/labels/' . $parameters["orderId"] . '?hash=' . $parameters["hash"] . '&referenceType=OrderId&detailLevel=All&labelMedia=' . $parameters["labelSize"]. '&labelFormat=PDF';

        parcel2go_write_log("Get Label Model" . $query);

        $response = wp_remote_post( $this->api_root . $query , array(
            'method' => 'GET',
            'timeout' => $this->timeout,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token["token"],
                'ClientApp' => p2gPluginName,
                'AppVersion' => p2gPluginVersion))
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            parcel2go_write_log("Error Getting Label " . $error_message);
            return new WP_Error( 'Error', $error_message, array( 'status' => 500 ) );
        } 
        else if($response_code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            return new WP_Error( 'Error', $data, array( 'status' => $response_code ) );
        }
        else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);

            return (object) [
                "result" => $data
            ];
        }

        return (object) [
            "result" => null
        ];
    }
}


class Parcel2Go_SettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    private $api;
    /**
     * Start up
     */
    public function __construct($api)
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        $this->api = $api;
        $this->api->getToken();
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_submenu_page( 'parcel2go_quoting.php', 'Settings', 'Settings', 'manage_options', 'parcel2go/settings.php',  array( $this, 'create_admin_page' ) ); 
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'parcel2go_option_name' );
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'parcel2go_option_group' );
                do_settings_sections( 'parcel2go-setting-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'parcel2go_option_group', // Option group
            'parcel2go_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        //API credentials
        add_settings_section(
            'parcel2go_credentials_section', // ID
            'Credentials', // Title
            array( $this, 'print_parcel2go_credentials_section' ), // Callback
            'parcel2go-setting-admin' // Page
        );  

        add_settings_field(
            'client_id', // ID
            'Client Id', // Title 
            array( $this, 'client_id_callback' ), // Callback
            'parcel2go-setting-admin', // Page
            'parcel2go_credentials_section' // Section           
        );      

        add_settings_field(
            'client_secret', 
            'Client Secret', 
            array( $this, 'client_secret_callback' ), 
            'parcel2go-setting-admin', 
            'parcel2go_credentials_section'
        );
        
        add_settings_field(
            'client_sandbox', // ID
            'Sandbox Mode', // Title 
            array( $this, 'client_sandbox_callback' ), // Callback
            'parcel2go-setting-admin', // Page
            'parcel2go_credentials_section' // Section           
        ); 

        if($this->api->hasAccessToken()) {
            //Default address
            add_settings_section(
                'parcel2go_address_section', // ID
                'Collection / Return Address', // Title
                array( $this, 'print_parcel2go_address_section' ), // Callback
                'parcel2go-setting-admin' // Page
            );  

            add_settings_field(
                'collection_organisation', // ID
                'Organisation', // Title 
                array( $this, 'collection_organisation_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            ); 

            add_settings_field(
                'collection_firstname', // ID
                'First Name', // Title 
                array( $this, 'collection_firstname_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            ); 

            add_settings_field(
                'collection_lastname', // ID
                'Last Name', // Title 
                array( $this, 'collection_lastname_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            ); 

            add_settings_field(
                'collection_email', // ID
                'Email', // Title 
                array( $this, 'collection_email_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            ); 

            add_settings_field(
                'collection_phone', // ID
                'Main Contact Number', // Title 
                array( $this, 'collection_phone_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            ); 

            add_settings_field(
                'collection_property', // ID
                'Property', // Title 
                array( $this, 'collection_property_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );   
            
            add_settings_field(
                'collection_street', // ID
                'Street', // Title 
                array( $this, 'collection_street_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );

            add_settings_field(
                'collection_town', // ID
                'Town', // Title 
                array( $this, 'collection_town_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );

            add_settings_field(
                'collection_postcode', // ID
                'Postcode', // Title 
                array( $this, 'collection_postcode_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );

            add_settings_field(
                'collection_county', // ID
                'County', // Title 
                array( $this, 'collection_county_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );

            add_settings_field(
                'collection_country', // ID
                'Country', // Title 
                array( $this, 'collection_country_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_address_section' // Section           
            );
        

            //Default dims
            add_settings_section(
                'parcel2go_dimension_section', // ID
                'Default Box Dimension', // Title
                array( $this, 'print_parcel2go_dimension_section' ), // Callback
                'parcel2go-setting-admin' // Page
            );  

            add_settings_field(
                'dimension_width', // ID
                'Width (cm)', // Title 
                array( $this, 'dimension_width_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_dimension_section' // Section           
            ); 

            add_settings_field(
                'dimension_height', // ID
                'Height (cm)', // Title 
                array( $this, 'dimension_height_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_dimension_section' // Section           
            ); 

            add_settings_field(
                'dimension_length', // ID
                'Length (cm)', // Title 
                array( $this, 'dimension_length_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_dimension_section' // Section           
            ); 

            add_settings_field(
                'dimension_weight', // ID
                'Weight (kg)', // Title 
                array( $this, 'dimension_weight_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_dimension_section' // Section           
            ); 

            //Default options
            add_settings_section(
                'parcel2go_default_settings_section', // ID
                'Default Settings', // Title
                array( $this, 'print_parcel2go_default_settings_section' ), // Callback
                'parcel2go-setting-admin' // Page
            );  

            add_settings_field(
                'default_include_cover', // ID
                'Include Protection', // Title 
                array( $this, 'default_include_cover_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_default_settings_section' // Section           
            ); 

            add_settings_field(
                'default_vat_number', // ID
                'VAT Number', // Title 
                array( $this, 'default_vat_number_callback' ), // Callback
                'parcel2go-setting-admin', // Page
                'parcel2go_default_settings_section' // Section           
            ); 
        }
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();

        if( isset( $input['client_id'] ) ) {
            $new_input['client_id'] = sanitize_text_field( $input['client_id'] );
        }
        if( isset( $input['client_secret'] ) ) {
            $new_input['client_secret'] = sanitize_text_field( $input['client_secret'] );
        }
        if( isset( $input['client_sandbox'] ) ) {
            $new_input['client_sandbox'] = sanitize_text_field( $input['client_sandbox'] );
        }


        if($this->api->hasAccessToken()) {
            if( isset( $input['collection_organisation'] ) ) {
                $new_input['collection_organisation'] = sanitize_text_field( $input['collection_organisation'] );
            }
            if( isset( $input['collection_firstname'] ) ) {
                $new_input['collection_firstname'] = sanitize_text_field( $input['collection_firstname'] );
            }
            if( isset( $input['collection_lastname'] ) ) {
                $new_input['collection_lastname'] = sanitize_text_field( $input['collection_lastname'] );
            }
            if( isset( $input['collection_email'] ) ) {
                $new_input['collection_email'] = sanitize_text_field( $input['collection_email'] );
            }
            if( isset( $input['collection_phone'] ) ) {
                $new_input['collection_phone'] = sanitize_text_field( $input['collection_phone'] );
            }
            if( isset( $input['collection_property'] ) ) {
                $new_input['collection_property'] = sanitize_text_field( $input['collection_property'] );
            }
            if( isset( $input['collection_street'] ) ) {
                $new_input['collection_street'] = sanitize_text_field( $input['collection_street'] );
            }
            if( isset( $input['collection_town'] ) ) {
                $new_input['collection_town'] = sanitize_text_field( $input['collection_town'] );
            }
            if( isset( $input['collection_postcode'] ) ) {
                $new_input['collection_postcode'] = sanitize_text_field( $input['collection_postcode'] );
            }
            if( isset( $input['collection_county'] ) ) {
                $new_input['collection_county'] = sanitize_text_field( $input['collection_county'] );
            }
            if( isset( $input['collection_country'] ) ) {
                $new_input['collection_country'] = sanitize_text_field( $input['collection_country'] );
            }



            if( isset( $input['dimension_weight'] ) ) {
                $new_input['dimension_weight'] = sanitize_text_field( $input['dimension_weight'] );
            }
            if( isset( $input['dimension_width'] ) ) {
                $new_input['dimension_width'] = sanitize_text_field( $input['dimension_width'] );
            }
            if( isset( $input['dimension_height'] ) ) {
                $new_input['dimension_height'] = sanitize_text_field( $input['dimension_height'] );
            }
            if( isset( $input['dimension_length'] ) ) {
                $new_input['dimension_length'] = sanitize_text_field( $input['dimension_length'] );
            }

            if( isset( $input['default_include_cover'] ) ) {
                $new_input['default_include_cover'] = sanitize_text_field( $input['default_include_cover'] );
            }
            if( isset( $input['default_vat_number'] ) ) {
                $new_input['default_vat_number'] = sanitize_text_field( $input['default_vat_number'] );
            }
        }
        
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_parcel2go_credentials_section()
    {
        print '<p>Enter your Parcel2Go.com API Credentitals:</p>';
        print '<p><a href="https://www.parcel2go.com/myaccount/api">You can set up and manage your API Client Credentials here</a></p>';
        if(!$this->api->hasAccessToken()) {
            print '<p style="color:red"><strong>Your API Credentitals are not correct<strong></p>';
        }
    }
    public function print_parcel2go_address_section()
    {
        if($this->api->hasAccessToken()) {
            print '<p>Enter your default collection / return address:</p>';
        }    
    }
    public function print_parcel2go_dimension_section()
    {
        if($this->api->hasAccessToken()) {
            print '<p>Enter your default box dimensions:</p>';
        }
    }
    public function print_parcel2go_default_settings_section()
    {
        if($this->api->hasAccessToken()) {
            print '<p>Select your default booking options:</p>';
        }
    }


    public function client_id_callback()
    {
        printf('<input type="text" id="client_id" name="parcel2go_option_name[client_id]" value="%s" class="regular-text" />',
            isset( $this->options['client_id'] ) ? esc_attr( $this->options['client_id']) : ''
        );
    }
    public function client_secret_callback()
    {
        printf('<input type="text" id="client_secret" name="parcel2go_option_name[client_secret]" value="%s" class="regular-text" />',
            isset( $this->options['client_secret'] ) ? esc_attr( $this->options['client_secret']) : ''
        );
    }
    public function client_sandbox_callback()
    {
        if(isset( $this->options['client_sandbox'] ) && $this->options['client_sandbox'] == 'on')
        {
            printf('<input type="checkbox" id="client_sandbox" name="parcel2go_option_name[client_sandbox]" checked="checked" />');
        }
        else {
            printf('<input type="checkbox" id="client_sandbox" name="parcel2go_option_name[client_sandbox]" />');
        }
    }

    public function collection_organisation_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_organisation" name="parcel2go_option_name[collection_organisation]" value="%s" class="regular-text" />',
                isset( $this->options['collection_organisation'] ) ? esc_attr( $this->options['collection_organisation']) : ''
            );
        }
    }
    public function collection_firstname_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_firstname" name="parcel2go_option_name[collection_firstname]" value="%s" class="regular-text" />',
                isset( $this->options['collection_firstname'] ) ? esc_attr( $this->options['collection_firstname']) : ''
            );
        }
    }
    public function collection_lastname_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_lastname" name="parcel2go_option_name[collection_lastname]" value="%s" class="regular-text" />',
                isset( $this->options['collection_lastname'] ) ? esc_attr( $this->options['collection_lastname']) : ''
            );
        }
    }
    public function collection_email_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="email" id="collection_email" name="parcel2go_option_name[collection_email]" value="%s" class="regular-text" />',
                isset( $this->options['collection_email'] ) ? esc_attr( $this->options['collection_email']) : ''
            );
        } 
    }
    public function collection_phone_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="tel" id="collection_phone" name="parcel2go_option_name[collection_phone]" value="%s" class="regular-text" />',
                isset( $this->options['collection_phone'] ) ? esc_attr( $this->options['collection_phone']) : ''
            );
        }
    }
    public function collection_property_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_property" name="parcel2go_option_name[collection_property]" value="%s" class="regular-text" />',
                isset( $this->options['collection_property'] ) ? esc_attr( $this->options['collection_property']) : ''
            );
        }
    }
    public function collection_street_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_street" name="parcel2go_option_name[collection_street]" value="%s" class="regular-text" />',
                isset( $this->options['collection_street'] ) ? esc_attr( $this->options['collection_street']) : ''
            );
        } 
    }
    public function collection_town_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_town" name="parcel2go_option_name[collection_town]" value="%s" class="regular-text" />',
                isset( $this->options['collection_town'] ) ? esc_attr( $this->options['collection_town']) : ''
            );
        }
    }
    public function collection_postcode_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_postcode" name="parcel2go_option_name[collection_postcode]" value="%s" class="regular-text" />',
                isset( $this->options['collection_postcode'] ) ? esc_attr( $this->options['collection_postcode']) : ''
            );
        }
    }
    public function collection_county_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="collection_county" name="parcel2go_option_name[collection_county]" value="%s" class="regular-text" />',
                isset( $this->options['collection_county'] ) ? esc_attr( $this->options['collection_county']) : ''
            );
        } 
    }

    public function collection_country_callback() {
        if($this->api->hasAccessToken()) {
            $countries = $this->api->getCountries();
            printf('<select id="collection_country" name="parcel2go_option_name[collection_country]" class="regular-text">');
            if(isset($countries)) {
                $selected = false;
                foreach ($countries->result as $c) {
                    if(!$selected && isset( $this->options['collection_country'] ) && $this->options['collection_country'] == $c->Iso3Code) {
                        printf('<option value="%s" selected="selected">%s</option>',$c->Iso3Code, $c->Name);
                        $selected = true;
                    }
                    else {
                        printf('<option value="%s">%s</option>',$c->Iso3Code, $c->Name);
                    }
                }
            }
            printf('</select>');
        }
    }

    public function dimension_width_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="number" min="1" step="1" id="dimension_width" name="parcel2go_option_name[dimension_width]" value="%s" class="regular-text" />',
                isset( $this->options['dimension_width'] ) ? esc_attr( $this->options['dimension_width']) : ''
            );
        }   
    }
    public function dimension_height_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="number" min="1" step="1" id="dimension_height" name="parcel2go_option_name[dimension_height]" value="%s" class="regular-text" />',
                isset( $this->options['dimension_height'] ) ? esc_attr( $this->options['dimension_height']) : ''
            );
        }
    }
    public function dimension_length_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="number" min="1" step="1" id="dimension_length" name="parcel2go_option_name[dimension_length]" value="%s" class="regular-text" />',
                isset( $this->options['dimension_length'] ) ? esc_attr( $this->options['dimension_length']) : ''
            );
        }
    }
    public function dimension_weight_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="number" min="0.1" step="0.1" id="dimension_weight" name="parcel2go_option_name[dimension_weight]" value="%s" class="regular-text" />',
                isset( $this->options['dimension_weight'] ) ? esc_attr( $this->options['dimension_weight']) : ''
            );
        }
       
    }

    public function default_include_cover_callback()
    {
        if($this->api->hasAccessToken()) {
            if(isset( $this->options['default_include_cover'] ) && $this->options['default_include_cover'] == 'on')
            {
                printf('<input type="checkbox" id="default_include_cover" name="parcel2go_option_name[default_include_cover]" checked="checked" />');
            }
            else {
                printf('<input type="checkbox" id="default_include_cover" name="parcel2go_option_name[default_include_cover]" />');
            }
        }  
    }
    public function default_vat_number_callback()
    {
        if($this->api->hasAccessToken()) {
            printf('<input type="text" id="default_vat_number" name="parcel2go_option_name[default_vat_number]" value="%s" class="regular-text" />',
                isset( $this->options['default_vat_number'] ) ? esc_attr( $this->options['default_vat_number']) : ''
            );
        }
    }
}

class Parcel2Go_OrderOptions
{
    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'woocommerce_order_actions', array( $this, 'addAction' ) );
        add_action( 'woocommerce_order_action_parcel2go_start_shipping', array( $this, 'processAction' ), 10, 1 );
        add_filter( 'wc_order_statuses', array($this,'addWooCommerceStatus') );
        add_filter( 'woocommerce_admin_order_actions', array($this,'addAdminOrderAction'), 100, 2 );
        add_action( 'admin_head', array($this,'addCustomButtonCss') );
        add_filter( 'manage_edit-shop_order_columns', array($this,'addShopOrderColumns') );
        add_action( 'manage_shop_order_posts_custom_column', array($this,'addShopOrderColumn') );
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array($this,'addShippingDetails') );
    }


    function addShippingDetails($order) {

        $p2gOrderMetaData = get_post_meta( $order->get_id(), '_parcel2go_order' );
        if(!is_null($p2gOrderMetaData) && sizeof($p2gOrderMetaData) > 0) {
            
            $p2gOrder = json_decode($p2gOrderMetaData[0]);

            wp_enqueue_script('parcel2go_order', plugin_dir_url(__FILE__) . 'parcel2go-order.js', array('jquery','wc-admin-order-meta-boxes'), "1.2", true);
            wp_localize_script('parcel2go_order', 'parcel2go_settings', array(
                'rootUrl' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'orderId' => $p2gOrder->order->OrderId,
                'namespace' => 'parcel2go/v1',
                'siteUrl' => get_site_url(),
                'orderLineId' => $p2gOrder->order->OrderlineIdMap[0]->OrderLineId,
                'wooId' => $order->get_id(),
                'hash' => $p2gOrder->order->Hash,
            ));

            echo "<p class='form-field form-field-wide'>";
            echo "<span>Parcel2Go.com Order Id : ". $p2gOrder->order->OrderId ."</span>";
            echo "</p>";

            echo "<p class='form-field form-field-wide'>";
            echo "<span>Parcel2Go.com Shipping Price : &pound; ". $p2gOrder->order->TotalPrice ."</span>";
            echo "</span>";

            echo "<p class='form-field form-field-wide'>";
            echo "<span>Parcel2Go.com Tracking : <span id='p2gTracking'></span></span>";
            echo "</p>";

            echo "<p class='form-field form-field-wide'>";
            echo "<span>Parcel2Go.com Label : </span>";            
            echo "</p>";

            echo "<p class='form-field form-field-wide'>
                <label for='label_size'>
                Label Size:</label>
                <select id='label_size' name='label_size' class='wc-enhanced-select enhanced' tabindex='-1' aria-hidden='true' style='width: 50%'>
                    <option value='A4' selected='selected'>Label on A4</option>
                    <option value='Label4X6'>Label on 4x6</option>
                </select> 
                <a id='btnP2gLabel' style='cursor:pointer'>Generate</a>               
                </p>";
        }   
    }

    function addShopOrderColumns( $columns ){
        $columns = (is_array($columns)) ? $columns : array();
        $tracking = array( 'tracking' => 'Tracking' );
        $position = 4;
        $new_columns = array_slice( $columns, 0, $position, true ) +  $tracking;
        return array_merge( $new_columns, $columns );
    }

    function addShopOrderColumn( $column ){
        global $the_order;  
        if ( $column == 'tracking' ) {    
            $trackingId = get_post_meta($the_order->get_id(),'_parcel2go_orderLineId', true);
            if(isset($trackingId) && !is_null($trackingId) && !empty($trackingId)) {
                echo ( 'P2G' . $trackingId);
            }
        }
    }

    // Add your custom order status action button (for orders with "processing" status)
    function addAdminOrderAction( $actions, $order ) {
        // Display the button for all orders that have a 'processing' status
        if ( $order->has_status( array( 'processing' ) ) ) {

            // Set the action button
            $actions['parcel2go'] = array(
                'url'       => wp_nonce_url( admin_url( 'admin.php?page=parcel2go_quoting.php&p2g_order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
                'name'      => __( 'Send with Parcel2Go' ),
                'action'    => __( 'parcel2go' ),
            );
        }
        return $actions;
    }


    function addCustomButtonCss() {
        echo '<style>.wc-action-button-parcel2go::after { font-family: woocommerce !important; content: "\e006" !important; }</style>';
    }

    function addWooCommerceStatus( $order_statuses ) {

        $new_order_statuses = array();
    
        // add new order status after processing
        foreach ( $order_statuses as $key => $status ) {
    
            $new_order_statuses[ $key ] = $status;
    
            if ( 'wc-processing' === $key ) {
                $new_order_statuses['wc-awaiting-shipment'] = 'Shipped With Parcel2Go';
            }
        }
    
        return $new_order_statuses;
    }


    /**
     * Add a custom action to order actions select box on edit order page
     * Only added for paid orders that haven't fired this action yet
     *
     * @param array $actions order actions array to display
     * @return array - updated actions
     */
    function addAction( $actions ) {
        global $theorder;
        // bail if the order has been paid for or this action has been run
        if ( ! $theorder->is_paid() || get_post_meta( $theorder->get_id(), '_wc_shipping_booked', true ) ) {
                return $actions;
        }
        // add "mark printed" custom action
        $actions['parcel2go_start_shipping'] = __( 'Send with Parcel2Go' );
        return $actions;
    }

    /**
     * Add an order note when custom action is clicked
     * Add a flag on the order to show it's been run
     *
     * @param \WC_Order $order
     */
    function processAction( $order ) {

        $url = add_query_arg( array(
            'page' => 'parcel2go_quoting.php',
            'p2g_order_id' => $order->get_id(),
        ), get_site_url() . '/wp-admin/admin.php' );

        parcel2go_write_log('Redirect to ' . $url);
        wp_redirect($url);
        exit;
    }
}

class Parcel2Go_AdminPage
{
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
    }

    public function add_admin_page()
    {
        add_menu_page( 'Parcel2Go', 'Parcel2Go', 'manage_options', 'parcel2go_quoting.php', array( $this, 'admin_page' ), 'dashicons-store', 6  );
    }

    public function admin_page(){

        $orderId = isset($_GET['p2g_order_id']) ? $_GET['p2g_order_id'] : null;
        parcel2go_write_log('Loading Quote Page For Order ' . $orderId);

        $date = new DateTime();

        wp_enqueue_script('parcel2go_moment', plugin_dir_url(__FILE__) . 'moment.min.js', array('jquery'), '2.21.0', true);
        wp_enqueue_script('parcel2go_quote', plugin_dir_url(__FILE__) . 'parcel2go-quoting.js', array('jquery'), '1.2', true);
        wp_localize_script('parcel2go_quote', 'parcel2go_settings', array(
            'rootUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'orderId' => $orderId,
            'namespace' => 'parcel2go/v1',
            'siteUrl' => get_site_url()
        ));
        wp_enqueue_style('parcel2go_styles', plugin_dir_url(__FILE__) . 'parcel2go-plugin-styles.css');

        printf('<div id="p2g" class="wrap">');
        printf('<h1>Parcel2Go.com</h1>');
        printf(isset($_GET['p2g_order_id']) ? '<h2>Order Details</h2>' : '');
        printf('<div class="postbox"><div class="inside"><div class="panel-wrap"><div class="panel"><div class="overview"></div></div></div></div></div>');
        printf('<h2 id="lblQuotes" style="display:none;">Quotes</h2>');
        printf('<div class="results"></div>');
        printf('</div>');
    }
}

add_action('init','parcel2go_start');
function parcel2go_start(){
    if ( current_user_can('administrator') ) {
        $api = new Parcel2Go_Api();
        if( is_admin() ) {
            $admin_page = new Parcel2Go_AdminPage();
            $settings_page = new Parcel2Go_SettingsPage($api);
            $order_options = new Parcel2Go_OrderOptions();
        }
    }
}
?>
