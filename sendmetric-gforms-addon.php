<?php
/*
Plugin Name: Gravity Forms - SendMetric Add-On
Plugin URI: http://www.gravityforms.com
Description: Adds form submissions to SendMetric
Version: 1.0
Author: TimS @ Ninthlink
Author URI: http://www.ninthlink.com
Documentation: http://www.gravityhelp.com/documentation/page/GFAddOn

------------------------------------------------------------------------
Copyright 2012-2013 Rocketgenius Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


//------------------------------------------
if (class_exists("GFForms")) {
    GFForms::include_feed_addon_framework();

    class GFsendmetric-gforms-addon extends GFFeedAddOn {

        protected $_version = "1.0";
        protected $_min_gravityforms_version = "1.7.9999";
        protected $_slug = "sendmetric-gforms-addon";
        protected $_path = "sendmetric-gforms-addon/sendmetric-gforms-addon.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms to SendMetric Add-On";
        protected $_short_title = "SendMetric";

        public function __construct() {
            parent::__construct();
            $this->_sendmetric_post_url = $this->get_plugin_setting('smPostUrl');
        }

        public function plugin_page() {
            wp_redirect( 'admin.php?page=gf_settings&subview=' );
        }

        public function feed_settings_fields() {
            $a = array(
                array(
                    "title"  => "SendMetric Feed Settings",
                    "fields" => array(
                        array(
                            "label"   => "SendMetric Feed Name",
                            "type"    => "text",
                            "name"    => "smFeedName",
                            "tooltip" => "This is the tooltip",
                            "class"   => "small"
                        ),
                        array(
                            "name" => "smMappedFields",
                            "label" => "Map Contact Fields",
                            "type" => "field_map",
                            "tooltip" => "Map each SendMetric Field to Gravity Form Field",
                            "field_map" => array(
                                array("name" => "firstname","label" => "First Name","required" => 0),
                                array("name" => "lastname","label" => "Last Name","required" => 0),
                                array("name" => "email","label" => "Email Address","required" => 1),
                                array("name" => "postal_code","label" => "Postal Code","required" => 0),
                            )
                        ),
                        /*
                        array(
                            "name" => "smMappedFieldOptIn",
                            "label" => "Map Opt-In Field",
                            "type" => "field_map",
                            "tooltip" => "Map the field used to Opt-In/Opt-Out of SendMetric",
                            "field_map" => array(
                                array("name" => "sm_opt_in","label" => "SendMetric Opt-In","required" => 0),
                            )
                        ),
                        */
                    )
                )
            );
            return $a;
        }

        protected function feed_list_columns() {
            return array(
                'feedName' => __('Name', 'sendmetric-gforms-addon'),
            );
        }

        public function plugin_settings_fields() {
            return array(
                array(
                    "title"  => "SendMetric Settings",
                    "fields" => array(
                        array(
                            "name"    => "smFeedUrl",
                            "tooltip" => "POST URL for SendMetric Form \"action\"",
                            "label"   => "SendMetric POST URL",
                            "type"    => "text",
                            "class"   => "medium"
                        ),
                        array(
                            "name"    => "smGroup",
                            "label"   => "Group ID",
                            "type"    => "text",
                            "class"   => "small"
                        ),
                    ),
                ),
            );
        }

        public function scripts() {
            $scripts = array(
                array("handle"  => "sendmetric_script_js",
                      "src"     => $this->get_base_url() . "/js/sendmetric_script.js",
                      "version" => $this->_version,
                      "deps"    => array("jquery"),
                      // [strings] An array of strings that can be accessed in JavaScript through the global variable [script handle]_strings
                      "strings" => array(
                          'url'  => __("URL", "sendmetric-gforms-addon"),
                          'groupId' => __("Group ID", "sendmetric-gforms-addon"),
                      ),
                      "enqueue" => array(
                          array(
                              array("post" => "posted_field=val")
                          )
                      )
                ),

            );

            return array_merge(parent::scripts(), $scripts);
        }

        public function styles() {

            $styles = array(
                array("handle"  => "avala_api_styles_form_edit_css",
                      "src"     => $this->get_base_url() . "/css/avala_api_styles_form_edit.css",
                      "version" => $this->_version,
                      "enqueue" => array(
                          array("admin_page" => array("form_editor"))
                      )
                )
            );

            return array_merge(parent::styles(), $styles);
        }

        public function process_feed($feed, $entry, $form){

            $apiFeedSubmit = $feed['meta']['apiFeedSubmit'];
            $url = null;

            if ( $apiFeedSubmit == 0 ) :
                return false; // do nothing
            elseif ( $apiFeedSubmit == 1 ) :
                $url = $this->get_plugin_setting('liveApiUrl'); // submit to live
            else :
                $url = $this->get_plugin_setting('devApiUrl'); // submit to dev
            endif;

            if ( isset($_COOKIE['__utmz']) && !empty($_COOKIE['__utmz']) )
                $ga_cookie = $this->parse_ga_cookie( $_COOKIE['__utmz'] );


            $jsonArray = array(
                'LeadSourceName'                => $feed['meta']['leadsourcename'],
                'LeadTypeName'                  => $feed['meta']['leadtypename'],
                'LeadCategoryName'              => $feed['meta']['leadcategoryname'],
                //mapped fields - contact
                'FirstName'                     => '',
                'LastName'                      => '',
                'EmailAddress'                  => '',
                'HomePhone'                     => '',
                'MobilePhone'                   => '',
                'WorkPhone'                     => '',
                'Comments'                      => '',
                //mapped fields - address
                'Address1'                      => '',
                'Address2'                      => '',
                'City'                          => '',
                'State'                         => '',
                'County'                        => '',
                'District'                      => '',
                'CountryCode'                   => $this->get_plugin_setting('defaultCountry'),
                'PostalCode'                    => ( $this->get_plugin_setting('defaultPostalCode') != '' ) ? $this->get_plugin_setting('defaultPostalCode') : '00000',
                //mapped fields - subscription
                'RecieveEmailCampaigns'         => '',
                'ReceiveNewsletter'             => '',
                'ReceiveSmsCampaigns'           => '',
                //mapped fields - addl data
                'AccountId'                     => '',
                'Brand'                         => '',
                'Campaign'                      => '',
                'CampaignId'                    => '',
                'DealerId'                      => '',
                'DealerNumber'                  => '',
                'Event'                         => '',
                'ExactTargetOptInListIds'       => ( $this->get_plugin_setting('defaultOptInListId') ) ? $this->get_plugin_setting('defaultOptInListId') : '',
                'ExactTargetCustomAttributes'   => '',
                'LeadDate'                      => '',
                'ProductCode'                   => '',
                'ProductIdList'                 => '',
                'TriggeredSend'                 => '',
                //mapped fields - custom data
                'CustomData'                    => array(
                    'BuyTimeFrame'              => '',
                    'Condition'                 => '',
                    'CurrentlyOwn'              => '',
                    'HomeOwner'                 => '',
                    'InterestedInOwning'        => '',
                    'PayoffLeft'                => '',
                    'ProductUse'                => '',
                    'TradeInMake'               => '',
                    'TradeInYear'               => '',
                    ),
                //mapped fields - websession data
                'WebSessionData'                => array(
                    'DeliveryMethod'            => '',
                    'FormPage'                  => $entry['source_url'],
                    'IPaddress'                 => $entry['ip'],
                    'KeyWords'                  => ( isset($ga_cookie['keyword']) && !empty($ga_cookie['keyword']) ) ? $ga_cookie['keyword'] : '',
                    'Medium'                    => ( isset($ga_cookie['medium']) && !empty($ga_cookie['medium']) ) ? $ga_cookie['medium'] : '',
                    'PagesViewed'               => '',
                    'PageViews'                 => '',
                    'TimeOnSite'                => '',
                    'Useragent'                 => $entry['user_agent'],
                    'VisitCount'                => ( isset($ga_cookie['visits']) && !empty($ga_cookie['visits']) ) ? $ga_cookie['visits'] : 1,
                    ),
            );
    
            $avala_field = array();

            foreach ($feed['meta'] as $k => $v) {
                $l = explode("_", $k);
                if ( $l[0] == 'mappedFields' ) {
                    if ( $l[1] == 'CustomData' && array_key_exists( $l[2], $jsonArray['CustomData'] ) && !empty( $v ) ) :
                        $jsonArray['CustomData'][ $l[2] ] = $entry[ $v ];
                    elseif ( $l[1] == 'WebSession' && array_key_exists( $l[2], $jsonArray['WebSessionData'] ) && !empty( $v ) ) :
                        $jsonArray['WebSessionData'][ $l[2] ] = $entry[ $v ];
                    elseif ( array_key_exists( $l[2], $jsonArray ) && !empty( $v ) ) :
                        $jsonArray[ $l[2] ] = $entry[ $v ];
                    endif;
                }
            }
            
            // Remove empty ARRAY fields so we do not submit blank data
            $jsonArray['CustomData'] = array_filter( $jsonArray['CustomData'] );
            $jsonArray['WebSessionData'] = array_filter( $jsonArray['WebSessionData'] );
            $jsonArray = array_filter( $jsonArray );
            
            $jsonString = '[' . json_encode( $jsonArray ) . ']';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonString);
            curl_setopt($ch, CURLOPT_PROXY, null);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen($jsonString) ) );
            $apiResult = curl_exec($ch);
            $httpResult = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $result = array( 0 => $httpResult, 1 => $apiResult );
            //return $result;

            // debug things
            /*
            print('<pre>');
            var_dump($result);
            var_dump($entry);
            var_dump($feed);
            var_dump($jsonArray);
            var_dump($print);
            var_dump($ga_cookie);
            print('</pre>');
            */
            
        }

    }

    // Instantiate the class
    $gfa = new GFAvalaAPIAddOn();

}