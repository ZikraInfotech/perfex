<?php

class WordPress_Store_Locator_Listing
{
    private $plugin_name;
    private $version;
    private $options;

    /**
     * Store Locator Plugin Construct
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.0.0
     * @link    https://welaunch.io/plugins
     * @param   string                         $plugin_name 
     * @param   string                         $version    
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }


    /**
     * Get Options
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.0.0
     * @link    https://welaunch.io/plugins
     * @param   mixed                         $option The option key
     * @return  mixed                                 The option value
     */
    private function get_option($option)
    {
        if(!is_array($this->options)) {
            return false;
        }

        if (!array_key_exists($option, $this->options)) {
            return false;
        }

        return $this->options[$option];
    }

    /**
     * Init the Store Locator
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.0.0
     * @link    https://welaunch.io/plugins
     * @return  boolean
     */
    public function init()
    {
        global $wordpress_store_locator_options;

        $this->options = apply_filters('wordpress_store_locator_options', $wordpress_store_locator_options);

        if (!$this->get_option('enable')) {
            return false;
        }

        add_shortcode('wordpress_store_locator_listing', array($this, 'get_store_locator_listing'));
    }


        /**
     * Create the store locator
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.0.0
     * @link    https://welaunch.io/plugins
     */
    public function get_store_locator_listing($atts = array())
    {
        $args = shortcode_atts(array(
            'open_by_default' => 'yes',

            'key' => '',
            'key_orderby' => 'pm.meta_value',
            'value' => '',
            'heading_prefix' => 'Stores in ',

            'subkey' => '',
            'subkey_orderby' => 'pm.meta_value',
            'subvalue' => '',
            'subheading_prefix' => '',
        ), $atts);

        if(empty($args['key']) || !isset($args['key'])) {
            return 'Key argument missing';
        }

        $open_by_default = $args['open_by_default'];

        $key = $args['key'];
        $original_key = $key;

        $value = isset($args['value']) && !empty($args['value']) ? $args['value'] : '';
        $subvalue = isset($args['subvalue']) && !empty($args['subvalue']) ? $args['subvalue'] : '';

        $key_orderby = isset($args['key_orderby']) && !empty($args['key_orderby']) ? $args['key_orderby'] : '';
        $subkey_orderby = isset($args['subkey_orderby']) && !empty($args['subkey_orderby']) ? $args['subkey_orderby'] : '';

        $heading_prefix = isset($args['heading_prefix']) ? $args['heading_prefix'] : '';
        $subheading_prefix = isset($args['subheading_prefix']) ? $args['subheading_prefix'] : '';
        $isTax = false;

        if($key == "store_category" || $key == "store_filter") {
            $isTax = true;
            $unique_values = $this->get_unique_post_term_values($key, 'stores', $value, $key_orderby);
        } else {
            $prefix = "wordpress_store_locator_";
            $key = $prefix . $key;
            $unique_values = $this->get_unique_post_meta_values($key, 'stores', $value, $subkey_orderby);
        }

        if(empty($unique_values)) {
            return __('No stores found', 'wordpress-store-locator');
        }

        if($original_key == "country") {
            $countries = $this->get_countries();
        }

        ob_start();

        echo '<div class="store-locator-listing">';
        $first_meta = true;
        $subKey = false;

        foreach ($unique_values as $unique_value) {
                
            // When key is tax
            if($isTax) {
                $unique_value = (array) $unique_value;

                $query_args = array(
                    'posts_per_page' => -1,
                    'post_type'  => 'stores',
                    'tax_query' => array(
                        array (
                            'taxonomy' => $key,
                            'field' => 'id',
                            'terms' => $unique_value['term_id']
                        )
                    ),
                );

                $unique_value = $unique_value['name'];

            // when key is post meta
            } else {

                $query_args = array(
                    'posts_per_page' => -1,
                    'post_type'  => 'stores',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key'   => $key,
                            'value' => $unique_value,
                        ),
                    )
                );
            }

            if(isset($args['subkey']) && !empty($args['subkey'])) {

                $subKey = $args['subkey'];
                $subKeyisTax = false;

                if($subKey == "store_category" || $subKey == "store_filter") {
                    $subKeyisTax = true;
                    $uniqueSubKeyValues = $this->get_unique_post_term_values($subKey, 'stores', $subvalue);
                } else {
                    $prefix = "wordpress_store_locator_";
                    $subKey = $prefix . $subKey;
                    $uniqueSubKeyValues = $this->get_unique_post_meta_values($subKey, 'stores', $subvalue);
                }

                if(empty($uniqueSubKeyValues)) {
                    continue;
                }
                
                
                $posts = array();
                foreach ($uniqueSubKeyValues as $uniqueSubKeyValue) {

                    $subKeyQuery = $query_args;

                    // When key is tax
                    if($subKeyisTax) {

                        $uniqueSubKeyValue = (array) $uniqueSubKeyValue;
                        $subKeyQuery['tax_query'][] = array (
                            'taxonomy' => $subKey,
                            'field' => 'id',
                            'terms' => $uniqueSubKeyValue['term_id']
                        );
        
                        $uniqueSubKeyValue = $uniqueSubKeyValue['name'];

                    // when key is post meta
                    } else {
                        $subKeyQuery['meta_query'][] = array(
                            'key'   => $subKey,
                            'value' => $uniqueSubKeyValue,
                        );
                    }
                    
                    $subKeyQueryPosts = get_posts($subKeyQuery);
                    if(empty($subKeyQueryPosts)) {
                        continue;
                    }

                    $posts[] = array(
                        'subkey' => $uniqueSubKeyValue,
                        'posts' => $subKeyQueryPosts
                    );
                }
            } else {
                $posts = get_posts($query_args);
            }

            if(empty($posts)) {
                continue;
            }

            if($original_key == "country") {
                $unique_value = $countries[$unique_value];
            }

            $unique_value_selector = $this->slugify($unique_value);

            echo '<div class="store-locator-listing-item">';
                

                if($first_meta && $open_by_default == "yes") {
                    echo '<a href="#" data-id="' . $unique_value_selector . '" class="store-locator-listing-heading store-locator-listing-heading-open"><h2>' . $heading_prefix . $unique_value . '</h2></a>';

                    if($subKey) {
                        echo '<div id="store-locator-listing-content-' . $unique_value_selector . '" class="store-locator-listing-content store-locator-listing-content-subkey">';
                    } else {
                        echo '<div id="store-locator-listing-content-' . $unique_value_selector . '" class="store-locator-listing-content">';
                    }
                    $first_meta = false;
                } else {
                    echo '<a href="#" data-id="' . $unique_value_selector . '" class="store-locator-listing-heading store-locator-listing-heading-closed"><h2>' . $heading_prefix . $unique_value . '</h2></a>';

                    if($subKey) {
                        echo '<div id="store-locator-listing-content-' . $unique_value_selector . '" class="store-locator-listing-content store-locator-listing-content-subkey store-locator-listing-hidden">';
                    } else {
                        echo '<div id="store-locator-listing-content-' . $unique_value_selector . '" class="store-locator-listing-content store-locator-listing-hidden">';
                    }
                    
                }

                if($subKey) {

                    $first_sub_meta = true;

                    foreach ($posts as $subKeyData) {

                        $title = $subKeyData['subkey'];
                        $posts = $subKeyData['posts'];

                        $unique_subkey_value_selector = $this->slugify($title);

                        // readd when auto open first
                        if($first_sub_meta  && $open_by_default == "yes") {
                            echo '<a href="#" data-id="' . $unique_subkey_value_selector . '" class="store-locator-listing-subheading store-locator-listing-subheading-open"><h2>' . $subheading_prefix . $title . '</h2></a>';
                            echo '<div id="store-locator-listing-content-' . $unique_subkey_value_selector . '" class="store-locator-listing-content">';
                            $first_sub_meta = false;
                        } else {
                            echo '<a href="#" data-id="' . $unique_subkey_value_selector . '" class="store-locator-listing-subheading store-locator-listing-subheading-closed"><h2>' . $subheading_prefix . $title . '</h2></a>';
                            echo '<div id="store-locator-listing-content-' . $unique_subkey_value_selector . '" class="store-locator-listing-content store-locator-listing-hidden">';

                        }

                        $sidebar = '<ul class="store-locator-listing-sidebar">';
                        $content = '<div class="store-locator-listing-content-posts">';

                        $first_post = true;
                        foreach ($posts as $post) {

                            if($first_post) {
                                $sidebar .= '<a href="#" class="store-locator-listing-post-link store-locator-listing-open" data-id="' . $unique_subkey_value_selector . $post->ID . '" ><li>' . $post->post_title . '</li></a>';
                                $content .= '<div id="store-locator-content-post-' . $unique_subkey_value_selector . $post->ID . '" class="store-locator-content-post">';
                                $first_post = false;
                            } else {
                                $sidebar .= '<a href="#" class="store-locator-listing-post-link" data-id="' . $unique_subkey_value_selector . $post->ID . '" ><li>' . $post->post_title . '</li></a>';
                                $content .= '<div id="store-locator-content-post-' . $unique_subkey_value_selector . $post->ID . '" class="store-locator-content-post store-locator-listing-hidden">';
                            }

                                $content .= $this->get_content($post);
                            $content .= '</div>';                    
                        }
                        $sidebar .= '</ul>';
                        $content .= '</div>';

                        echo $sidebar;
                        echo $content;

                        echo '</div>';
                    }

                } else {

                    $sidebar = '<ul class="store-locator-listing-sidebar">';
                    $content = '<div class="store-locator-listing-content-posts">';

                    $first_post = true;
                    foreach ($posts as $post) {

                        if($first_post) {
                            $sidebar .= '<a href="#" class="store-locator-listing-post-link store-locator-listing-open" data-id="' . $unique_value_selector . $post->ID . '" ><li>' . $post->post_title . '</li></a>';
                            $content .= '<div id="store-locator-content-post-' . $unique_value_selector . $post->ID . '" class="store-locator-content-post">';
                            $first_post = false;
                        } else {
                            $sidebar .= '<a href="#" class="store-locator-listing-post-link" data-id="' . $unique_value_selector . $post->ID . '" ><li>' . $post->post_title . '</li></a>';
                            $content .= '<div id="store-locator-content-post-' . $unique_value_selector . $post->ID . '" class="store-locator-content-post store-locator-listing-hidden">';
                        }

                            $content .= $this->get_content($post);
                        $content .= '</div>';                    
                    }
                    $sidebar .= '</ul>';
                    $content .= '</div>';

                    echo $sidebar;
                    echo $content;

                }
                

                echo '<div class="store_locator_single_clear"></div>';

                echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        $output = ob_get_contents();
        ob_end_clean();
        return $output;

    }

    /**
     * Single store page
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.1.0
     * @link    https://welaunch.io/plugins
     */
    public function get_content($post) 
    {

        if(!isset($post->post_type)) {
            return $content;
        }

        if ($post->post_type != 'stores') {
            return $content;
        }        

        $args = array('fields' => 'names', 'orderby' => 'name', 'order' => 'ASC');
        $prefix = "wordpress_store_locator_";
        $meta = get_post_meta($post->ID);

        if ($this->get_option('showFilterCategoriesAsImage') ){
            $tmp = array();
            $store_cats = wp_get_object_terms($post->ID, 'store_category');
            if(!empty($store_cats)) {
                foreach ($store_cats as $store_category) {

                    $category_icon = get_term_meta($store_category->term_id, 'wordpress_store_locator_icon');
                    if(isset($category_icon[0]) && !empty($category_icon[0]['url'])) {
                        $tmp[] = '<img src="' . $category_icon[0]['url'] . '">';
                    } else {
                        $tmp[] = '<img src="' . $this->get_option('mapDefaultIcon') . '">';
                    }
                }
            }
            $store_cats = $tmp;
        } else {
            $store_cats = wp_get_object_terms($post->ID, 'store_category', $args);
        }

        $title = '<h3>' . $post->post_title . '</h3>';

        if(!empty($store_cats)) {
            $categories = '<div class="store_locator_single_categories">';
                $categories .= '<strong class="store_locator_single_categories_title">' . __('Categories: ', 'wordpress-store-locator') . '</strong>' . implode(', ', $store_cats);
            $categories .= '</div>';
        }

        $store_filter = wp_get_object_terms($post->ID, 'store_filter', array() );
        $filter = "";
        if(!empty($store_filter)) {

            $temp = array();
            $this->sort_terms_hierarchicaly($store_filter, $temp);
            $store_filter = $temp;

            foreach ($store_filter as $single_store_filter) {

                if(isset($single_store_filter->children) && !empty($single_store_filter->children)) {

                    $filter .= '<div class="store_locator_single_filter">';
                        $filter .= '<strong class="store_locator_single_filter_title">' . $single_store_filter->name . ': </strong>';

                            $tmp = array();
                            foreach ($single_store_filter->children as $singel_store_child_filter) {
                                $tmp[] = $singel_store_child_filter->name;
                            }
                            
                            $filter .= implode(', ', $tmp);
                                
                    $filter .= '</div>';
                } 
            }

        }

        $address1 = isset($meta[ $prefix . 'address1' ][0]) ? $meta[ $prefix . 'address1' ][0] : '';
        $address2 = isset($meta[ $prefix . 'address2' ][0]) ? $meta[ $prefix . 'address2' ][0] : '';
        $zip = isset($meta[ $prefix . 'zip' ][0]) ? $meta[ $prefix . 'zip' ][0] : '';
        $city = isset($meta[ $prefix . 'city' ][0]) ? $meta[ $prefix . 'city' ][0] : '';
        $region = isset($meta[ $prefix . 'region' ][0]) ? $meta[ $prefix . 'region' ][0] : '';
        $country = isset($meta[ $prefix . 'country' ][0]) ? $meta[ $prefix . 'country' ][0] : '';
        $telephone = isset($meta[ $prefix . 'telephone' ][0]) ? $meta[ $prefix . 'telephone' ][0] : '';
        $mobile = isset($meta[ $prefix . 'mobile' ][0]) ? $meta[ $prefix . 'mobile' ][0] : '';
        $fax = isset($meta[ $prefix . 'fax' ][0]) ? $meta[ $prefix . 'fax' ][0] : '';
        $email = isset($meta[ $prefix . 'email' ][0]) ? $meta[ $prefix . 'email' ][0] : '';
        $website = isset($meta[ $prefix . 'website' ][0]) ? $meta[ $prefix . 'website' ][0] : '';

        $description = "";
        if($this->get_option('showAddressStyle') == "american") {
            $address = '<div class="store_locator_single_address">';
                $address .=  '<h4>' . __('Address ', 'wordpress-store-locator') . '</h4>';
                $address .= !empty($address1) ? $address1 . '<br/>' : '';
                $address .= !empty($address2) ? $address2 . '<br/>' : '';
                $address .= !empty($city) ? $city . ', ' : '';
                $address .= !empty($region) ? $region . ' ' : '';
                $address .= !empty($zip) ? $zip . '<br/>' : '';
                if($this->get_option('showCountry')) {
                    $address .= !empty($country) ? $country : '';
                }
            $address .= '</div>';
        } else {
            $address = '<div class="store_locator_single_address">';
                $address .=  '<h4>' . __('Address ', 'wordpress-store-locator') . '</h4>';
                $address .= !empty($address1) ? $address1 . '<br/>' : '';
                $address .= !empty($address2) ? $address2 . '<br/>' : '';
                $address .= !empty($zip) ? $zip . ', ' : '';
                $address .= !empty($city) ? $city . ', ' : '';
                $address .= !empty($region) ? $region . ', ' : '';
                if($this->get_option('showCountry')) {
                    $address .= !empty($country) ? $country : '';
                }
            $address .= '</div>';
        }

        $contact = '<div class="store_locator_single_contact">';
            $contact .=  '<h4>' . __('Contact ', 'wordpress-store-locator') . '</h4>';
            $contact .= !empty($telephone) && $this->get_option('showTelephone') ? 
                        $this->get_option('showTelephoneText') . ': <a href="tel:' .  $telephone  . '">' . $telephone . '</a><br/>' : '';
            $contact .= !empty($mobile) && $this->get_option('showMobile') ? 
                        $this->get_option('showMobileText') . ': <a href="tel:' .  $mobile  . '">' . $mobile . '</a><br/>' : '';
            $contact .= !empty($fax) && $this->get_option('showFax') ? 
                        $this->get_option('showFaxText') . ': <a href="tel:' .  $fax  . '">' . $fax . '</a><br/>' : '';
            $contact .= !empty($email) && $this->get_option('showEmail') ? 
                        $this->get_option('showEmailText') . ': <a href="mailto:' .  $email  . '">' . $email . '</a><br/>' : '';
            $contact .= !empty($website) && $this->get_option('showWebsite') ? 
                        $this->get_option('showWebsiteText') . ': <a href="' .  $website  . '" target="_blank">' . $website . '</a><br/>' : '';
        $contact .= '</div>
                    <div class="store_locator_single_clear"></div>';

        $additional_information = '';
        $customFields = $this->get_option('showCustomFields');
        if(!empty($customFields)) {
            
            $additional_information .= '<div class="store_locator_single_additional_information">';
            $additional_information .=  '<h4>' . __('Additional Information ', 'wordpress-store-locator') . '</h4>';

            foreach ($customFields as $customFieldKey => $customFieldName) {

                $customFieldKey = $prefix . $customFieldKey;
                $customFieldValue = get_post_meta($post->ID, $customFieldKey, true);
                if(!empty($customFieldValue)) {
                    $additional_information .= '<p class="store_locator_single_additional_information_item ' . $customFieldKey . '"><b>' . $customFieldName . ':</b> ' . $customFieldValue . '</p>';
                }
            }

            $additional_information .= '</div>';
        }

        $map = "";
        $opening_hours = "";
        $opening_hours2 = "";
        $contactStore = "";

        $weekdays = array(
            'Monday' => __('Monday', 'wordpress-store-locator'),
            'Tuesday' => __('Tuesday', 'wordpress-store-locator'),
            'Wednesday' => __('Wednesday', 'wordpress-store-locator'),
            'Thursday' => __('Thursday', 'wordpress-store-locator'),
            'Friday' => __('Friday', 'wordpress-store-locator'),
            'Saturday' => __('Saturday', 'wordpress-store-locator'),
            'Sunday' => __('Sunday', 'wordpress-store-locator'),
        );
        
        foreach ($weekdays as $key => $weekday) {
            $open = isset($meta[ $prefix . $key . "_open"]) ? $meta[ $prefix . $key . "_open"][0] : '';
            $close = isset($meta[ $prefix . $key . "_close"]) ? $meta[ $prefix . $key . "_close"][0] : '';
            
            if(!empty($open) && !empty($close)) {
                $opening_hours .= $weekday . ': ' . $open . ' – ' . $close . ' ' . $this->get_option('showOpeningHoursClock') . '<br/>';
            } elseif(!empty($open)) {
                $opening_hours .= $weekday . ': ' . $open . ' ' . $this->get_option('showOpeningHoursClock') . '<br/>';
            } elseif(!empty($close)) {
                $opening_hours .= $weekday . ': ' . $close . ' ' . $this->get_option('showOpeningHoursClock') . '<br/>';
            }
        }
        if(!empty($opening_hours)) {
            $opening_hours = '<div class="store_locator_single_opening_hours">' . 
                                '<h4>' . __('Opening Hours ', 'wordpress-store-locator') . '</h4>' .
                                $opening_hours . 
                            '</div>';
        }

        foreach ($weekdays as $key => $weekday) {
            $open = isset($meta[ $prefix . $key . "_open2"]) ? $meta[ $prefix . $key . "_open2"][0] : '';
            $close = isset($meta[ $prefix . $key . "_close2"]) ? $meta[ $prefix . $key . "_close2"][0] : '';
            
            if(!empty($open) && !empty($close)) {
                $opening_hours2 .= $weekday . ': ' . $open . ' – ' . $close . ' ' . $this->get_option('showOpeningHours2Clock') . '<br/>';
            } elseif(!empty($open)) {
                $opening_hours2 .= $weekday . ': ' . $open . ' ' . $this->get_option('showOpeningHours2Clock') . '<br/>';
            } elseif(!empty($close)) {
                $opening_hours2 .= $weekday . ': ' . $close . ' ' . $this->get_option('showOpeningHours2Clock') . '<br/>';
            }
        }

        if(!empty($opening_hours2)) {
            $opening_hours2 = '<div class="store_locator_single_opening_hours2">' . 
                                '<h4>' . $this->get_option('showOpeningHours2Text') . '</h4>' .
                                $opening_hours2 . 
                            '</div>';

            $opening_hours = 
            '<div class="store-locator-row">
                <div class="store-locator-col-sm-6">
                    ' . $opening_hours . '
                </div>
                <div class="store-locator-col-sm-6">
                    ' . $opening_hours2 . '
                </div>
            </div>';
            $opening_hours2 = "";
        }

        if($this->get_option('showContactStore')) {
            $contactStorePage = $this->get_option('showContactStorePage');
            $contactStoreText = $this->get_option('showContactStoreText');
            if(!empty($contactStorePage)) {
                $contactStorePage = get_permalink($contactStorePage) . '?store_id=' . $post->ID;
            }
            $contactStore = '<div class="store_locator_single_contact_store">' . 
                                '<a href="' . $contactStorePage . '" class="store_locator_contact_store_button btn button et_pb_button btn-primary theme-button btn-lg center">' . $contactStoreText . '</a>'. 
                            '</div>';
        }

        $review = "";
        if($this->get_option('showRating')) {

            $storeReviews = get_comments(array('post_id' => $post->ID));
            $storeReviewsCount = count($storeReviews);
            $storeReviewClass = "";
            if(!empty($storeReviews)) {
                $storeReviewClass = " store-locator-col-md-6";
            }

            $review = 

            '
            <div id="store-locator-review" class="store-locator-row store-locator-review-container">

                <div class="store-locator-col-sm-12' . $storeReviewClass . '">

                    <div class="store-locator-review-form-container">
                        <form action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post" class="store-locator-review-form">
                            <input type="hidden" name="store_locator_review_form" value="true">
                            <input type="hidden" name="store_locator_review_post_id" value="' . $post->ID . '">
                            
                            <div class="store-locator-row">
                                <div class="store-locator-col-sm-12">
                                    <h4 class="store-locator-review-form-title">' . __('Review Store', 'wordpress-store-locator') . '</h4>
                                </div>
                            </div>

                            <div class="store-locator-row">
                                <div class="store-locator-col-sm-6 store-locator-review-form-name-container">
                                    <label for="store_locator_review_name">' . __('Your Name', 'wordpress-store-locator') . ' *</label>
                                    <input name="store_locator_review_name" class="store-locator-review-field store-locator-review-field-name" type="text" placeholder="' . __('Your Name', 'wordpress-store-locator') . '" required>
                                </div>
                                <div class="store-locator-col-sm-6 store-locator-review-form-email-container">
                                    <label for="store_locator_review_email">' . __('Your Email', 'wordpress-store-locator') . ' *</label>
                                    <input name="store_locator_review_email" class="store-locator-review-field store-locator-review-field-email" type="email" placeholder="' . __('Your Email', 'wordpress-store-locator') . '" required>
                                </div>
                            </div>

                            <div class="store-locator-row">
                                <div class="store-locator-col-sm-12">
                                    <div class="store-locator-review-form-rating">
                                        <label>
                                            <input type="radio" name="store_locator_rating" value="1" />
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="store_locator_rating" value="2" />
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="store_locator_rating" value="3" />
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>   
                                        </label>
                                        <label>
                                            <input type="radio" name="store_locator_rating" value="4" />
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="store_locator_rating" value="5" />
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                            <span class="store-locator-review-form-rating-icon">★</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="store-locator-row">
                                <div class="store-locator-col-sm-12 store-locator-review-form-text-container">
                                    <label for="store_locator_review_text">' . __('Your Review', 'wordpress-store-locator') . ' *</label>
                                    <textarea name="store_locator_review_comment" class="store-locator-review-field store-locator-review-field-text" placeholder="' . __('Write your review ...', 'wordpress-store-locator') . '" required></textarea>
                                </div>
                            </div>

                            <div class="store-locator-row">
                                <div class="store-locator-col-sm-12 store-locator-review-form-text-container">
                                    <input name="submit" type="submit" id="submit" class="submit" value="' . __('Submit', 'wordpress-store-locator') . '">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>';

                if(!empty($storeReviews)) {

                    $averageRating = get_post_meta($post->ID, 'wordpress_store_locator_average_rating', true);
                    $review .=  
                    '<div class="store-locator-reviews-container store-locator-col-sm-12' . $storeReviewClass . '">

                        <div class="store-locator-row">
                            <div class="store-locator-col-sm-12">
                                <h2 class="store-locator-reviews-title">' . __('Reviews', 'wordpress-store-locator') . ' (' . $averageRating . ' / 5)</h2>
                            </div>
                        </div>

                        <div class="store-locator-row store-locator-reviews-listing">';

                        foreach ($storeReviews as $key => $storeReview) {
                            $id = $storeReview->comment_ID;
                            $author = $storeReview->comment_author;
                            $comment = $storeReview->comment_content;
                            $rating = get_comment_meta( $id, 'store_locator_rating', true );

                            $review .= 
                            '<div class="store-locator-col-sm-12">
                                    <div id="store-locator-review-' . $id . '" class="store-locator-review-listing">
                                        <h3 class="store-locator-reviews-listing-author">' . $author . '</h3>
                                        <div class="store-locator-reviews-listing-icons">';

                                            for ($i=0; $i < $rating; $i++) { 
                                                $review .= '<span class="store-locator-reviews-listing-rating-icon">★</span>';
                                            }

                            $review .= 
                                    '</div>
                                    <div class="store-locator-reviews-listing-comment">' . $comment . '</div>
                                </div>
                            </div>';    

                        }
                    
                    $review .=  
                        '</div>
                    </div>';

                }
            $review .= 
            '</div>';
        }

        if(isset($meta[ $prefix . 'lat' ]) && isset($meta[ $prefix . 'lng' ])) {
            $map .= '<div id="store_locator_single_map" class="store_locator_single_map" 
                            data-lat="' . $meta[ $prefix . 'lat' ][0] . '" 
                            data-lng="' . $meta[ $prefix . 'lng' ][0] . '"></div>';
        }

        $content = $title . $categories . $filter . $address . $contact . $additional_information . $opening_hours . $opening_hours2 . $contactStore . $review . $map;

        return $content;
    }

    /**
     * Sort Wordpress Terms Hierarchicaly
     * @author Daniel Barenkamp
     * @version 1.0.0
     * @since   1.0.0
     * @link    https://welaunch.io/plugins
     * @param   array                          &$cats
     * @param   array                          &$into
     * @param   integer                        $parentId
     * @return  array
     */
    private function sort_terms_hierarchicaly(array &$cats, array &$into, $parentId = 0)
    {
        foreach ($cats as $i => $cat) {
            if ($cat->parent == $parentId) {
                $into[$cat->term_id] = $cat;
                unset($cats[$i]);
            }
        }

        foreach ($into as $topCat) {
            $topCat->children = array();
            $this->sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
        }
    }


    public function get_unique_post_meta_values( $key = '', $type = 'post', $value = "", $orderby = "pm.meta_value", $status = 'publish' ) 
    {
        global $wpdb;
        if( empty( $key ) )
            return;

        if(!empty($value)) {
            $query =  $wpdb->prepare( 
                "SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p 
                ON p.ID = pm.post_id
                WHERE pm.meta_key = '%s'
                AND pm.meta_value = '%s'
                AND p.post_status = '%s'
                AND p.post_type = '%s'
                ORDER BY " . $orderby, 
                $key, 
                $value, 
                $status, 
                $type,
                $orderby
            );
        } else {
            $query =  $wpdb->prepare( 
                "SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p 
                ON p.ID = pm.post_id
                WHERE pm.meta_key = '%s'
                AND p.post_status = '%s'
                AND p.post_type = '%s'
                ORDER BY " . $orderby, 
                $key, 
                $status, 
                $type
            );
        }

        $results = $wpdb->get_col( $query );

        return $results;
    }

    public function get_unique_post_term_values($key, $type, $value = "", $orderby = "t.name") 
    {
        global $wpdb;

        if($orderby == "pm.meta_value") {
            $orderby = "t.name";
        }

        if(!empty($value)) {

            $query = $wpdb->prepare(
                "SELECT t.*, COUNT(*) as count from $wpdb->terms AS t
                INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                INNER JOIN $wpdb->term_relationships AS r ON r.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN $wpdb->posts AS p ON p.ID = r.object_id
                WHERE p.post_type = '%s' AND tt.taxonomy = '%s' AND t.name = '%s' AND count > 0
                GROUP BY t.term_id ORDER BY " . $orderby,
                $type,
                $key,
                $value
            );
        } else {

            $query = $wpdb->prepare(
                "SELECT t.*, COUNT(*) as count from $wpdb->terms AS t
                INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                INNER JOIN $wpdb->term_relationships AS r ON r.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN $wpdb->posts AS p ON p.ID = r.object_id
                WHERE p.post_type = '%s' AND tt.taxonomy = '%s' AND count > 0
                GROUP BY t.term_id ORDER BY " . $orderby,
                $type,
                $key
            );
        }

        $results = $wpdb->get_results( $query );

        return $results;
    }

    private function get_countries()
    {
        $countries = array( 
            "AF" => __("Afghanistan", 'wordpress-store-locator'),"AL" => __("Albania", 'wordpress-store-locator'),"DZ" => __("Algeria", 'wordpress-store-locator'),"AS" => __("American Samoa", 'wordpress-store-locator'),"AD" => __("Andorra", 'wordpress-store-locator'),"AO" => __("Angola", 'wordpress-store-locator'),"AI" => __("Anguilla", 'wordpress-store-locator'),"AQ" => __("Antarctica", 'wordpress-store-locator'),"AG" => __("Antigua and Barbuda", 'wordpress-store-locator'),"AR" => __("Argentina", 'wordpress-store-locator'),"AM" => __("Armenia", 'wordpress-store-locator'),"AW" => __("Aruba", 'wordpress-store-locator'),"AU" => __("Australia", 'wordpress-store-locator'),"AT" => __("Austria", 'wordpress-store-locator'),"AZ" => __("Azerbaijan", 'wordpress-store-locator'),"BS" => __("Bahamas", 'wordpress-store-locator'),"BH" => __("Bahrain", 'wordpress-store-locator'),"BD" => __("Bangladesh", 'wordpress-store-locator'),"BB" => __("Barbados", 'wordpress-store-locator'),"BY" => __("Belarus", 'wordpress-store-locator'),"BE" => __("Belgium", 'wordpress-store-locator'),"BZ" => __("Belize", 'wordpress-store-locator'),"BJ" => __("Benin", 'wordpress-store-locator'),"BM" => __("Bermuda", 'wordpress-store-locator'),"BT" => __("Bhutan", 'wordpress-store-locator'),"BO" => __("Bolivia", 'wordpress-store-locator'),"BA" => __("Bosnia and Herzegovina", 'wordpress-store-locator'),"BW" => __("Botswana", 'wordpress-store-locator'),"BV" => __("Bouvet Island", 'wordpress-store-locator'),"BR" => __("Brazil", 'wordpress-store-locator'),"BQ" => __("British Antarctic Territory", 'wordpress-store-locator'),"IO" => __("British Indian Ocean Territory", 'wordpress-store-locator'),"VG" => __("British Virgin Islands", 'wordpress-store-locator'),"BN" => __("Brunei", 'wordpress-store-locator'),"BG" => __("Bulgaria", 'wordpress-store-locator'),"BF" => __("Burkina Faso", 'wordpress-store-locator'),"BI" => __("Burundi", 'wordpress-store-locator'),"KH" => __("Cambodia", 'wordpress-store-locator'),"CM" => __("Cameroon", 'wordpress-store-locator'),"CA" => __("Canada", 'wordpress-store-locator'),"CT" => __("Canton and Enderbury Islands", 'wordpress-store-locator'),"CV" => __("Cape Verde", 'wordpress-store-locator'),"KY" => __("Cayman Islands", 'wordpress-store-locator'),"CF" => __("Central African Republic", 'wordpress-store-locator'),"TD" => __("Chad", 'wordpress-store-locator'),"CL" => __("Chile", 'wordpress-store-locator'),"CN" => __("China", 'wordpress-store-locator'),"CX" => __("Christmas Island", 'wordpress-store-locator'),"CC" => __("Cocos [Keeling] Islands", 'wordpress-store-locator'),"CO" => __("Colombia", 'wordpress-store-locator'),"KM" => __("Comoros", 'wordpress-store-locator'),"CG" => __("Congo - Brazzaville", 'wordpress-store-locator'),"CD" => __("Congo - Kinshasa", 'wordpress-store-locator'),"CK" => __("Cook Islands", 'wordpress-store-locator'),"CR" => __("Costa Rica", 'wordpress-store-locator'),"HR" => __("Croatia", 'wordpress-store-locator'),"CU" => __("Cuba", 'wordpress-store-locator'),"CY" => __("Cyprus", 'wordpress-store-locator'),"CZ" => __("Czech Republic", 'wordpress-store-locator'),"CI" => __("Côte d’Ivoire", 'wordpress-store-locator'),"DK" => __("Denmark", 'wordpress-store-locator'),"DJ" => __("Djibouti", 'wordpress-store-locator'),"DM" => __("Dominica", 'wordpress-store-locator'),"DO" => __("Dominican Republic", 'wordpress-store-locator'),"NQ" => __("Dronning Maud Land", 'wordpress-store-locator'),"DD" => __("East Germany", 'wordpress-store-locator'),"EC" => __("Ecuador", 'wordpress-store-locator'),"EG" => __("Egypt", 'wordpress-store-locator'),"SV" => __("El Salvador", 'wordpress-store-locator'),"GQ" => __("Equatorial Guinea", 'wordpress-store-locator'),"ER" => __("Eritrea", 'wordpress-store-locator'),"EE" => __("Estonia", 'wordpress-store-locator'),"ET" => __("Ethiopia", 'wordpress-store-locator'),"FK" => __("Falkland Islands", 'wordpress-store-locator'),"FO" => __("Faroe Islands", 'wordpress-store-locator'),"FJ" => __("Fiji", 'wordpress-store-locator'),"FI" => __("Finland", 'wordpress-store-locator'),"FR" => __("France", 'wordpress-store-locator'),"GF" => __("French Guiana", 'wordpress-store-locator'),"PF" => __("French Polynesia", 'wordpress-store-locator'),"TF" => __("French Southern Territories", 'wordpress-store-locator'),"FQ" => __("French Southern and Antarctic Territories", 'wordpress-store-locator'),"GA" => __("Gabon", 'wordpress-store-locator'),"GM" => __("Gambia", 'wordpress-store-locator'),"GE" => __("Georgia", 'wordpress-store-locator'),"DE" => __("Germany", 'wordpress-store-locator'),"GH" => __("Ghana", 'wordpress-store-locator'),"GI" => __("Gibraltar", 'wordpress-store-locator'),"GR" => __("Greece", 'wordpress-store-locator'),"GL" => __("Greenland", 'wordpress-store-locator'),"GD" => __("Grenada", 'wordpress-store-locator'),"GP" => __("Guadeloupe", 'wordpress-store-locator'),"GU" => __("Guam", 'wordpress-store-locator'),"GT" => __("Guatemala", 'wordpress-store-locator'),"GG" => __("Guernsey", 'wordpress-store-locator'),"GN" => __("Guinea", 'wordpress-store-locator'),"GW" => __("Guinea-Bissau", 'wordpress-store-locator'),"GY" => __("Guyana", 'wordpress-store-locator'),"HT" => __("Haiti", 'wordpress-store-locator'),"HM" => __("Heard Island and McDonald Islands", 'wordpress-store-locator'),"HN" => __("Honduras", 'wordpress-store-locator'),"HK" => __("Hong Kong SAR China", 'wordpress-store-locator'),"HU" => __("Hungary", 'wordpress-store-locator'),"IS" => __("Iceland", 'wordpress-store-locator'),"IN" => __("India", 'wordpress-store-locator'),"ID" => __("Indonesia", 'wordpress-store-locator'),"IR" => __("Iran", 'wordpress-store-locator'),"IQ" => __("Iraq", 'wordpress-store-locator'),"IE" => __("Ireland", 'wordpress-store-locator'),"IM" => __("Isle of Man", 'wordpress-store-locator'),"IL" => __("Israel", 'wordpress-store-locator'),"IT" => __("Italy", 'wordpress-store-locator'),"JM" => __("Jamaica", 'wordpress-store-locator'),"JP" => __("Japan", 'wordpress-store-locator'),"JE" => __("Jersey", 'wordpress-store-locator'),"JT" => __("Johnston Island", 'wordpress-store-locator'),"JO" => __("Jordan", 'wordpress-store-locator'),"KZ" => __("Kazakhstan", 'wordpress-store-locator'),"KE" => __("Kenya", 'wordpress-store-locator'),"KI" => __("Kiribati", 'wordpress-store-locator'),"KW" => __("Kuwait", 'wordpress-store-locator'),"KG" => __("Kyrgyzstan", 'wordpress-store-locator'),"LA" => __("Laos", 'wordpress-store-locator'),"LV" => __("Latvia", 'wordpress-store-locator'),"LB" => __("Lebanon", 'wordpress-store-locator'),"LS" => __("Lesotho", 'wordpress-store-locator'),"LR" => __("Liberia", 'wordpress-store-locator'),"LY" => __("Libya", 'wordpress-store-locator'),"LI" => __("Liechtenstein", 'wordpress-store-locator'),"LT" => __("Lithuania", 'wordpress-store-locator'),"LU" => __("Luxembourg", 'wordpress-store-locator'),"MO" => __("Macau SAR China", 'wordpress-store-locator'),"MK" => __("Macedonia", 'wordpress-store-locator'),"MG" => __("Madagascar", 'wordpress-store-locator'),"MW" => __("Malawi", 'wordpress-store-locator'),"MY" => __("Malaysia", 'wordpress-store-locator'),"MV" => __("Maldives", 'wordpress-store-locator'),"ML" => __("Mali", 'wordpress-store-locator'),"MT" => __("Malta", 'wordpress-store-locator'),"MH" => __("Marshall Islands", 'wordpress-store-locator'),"MQ" => __("Martinique", 'wordpress-store-locator'),"MR" => __("Mauritania", 'wordpress-store-locator'),"MU" => __("Mauritius", 'wordpress-store-locator'),"YT" => __("Mayotte", 'wordpress-store-locator'),"FX" => __("Metropolitan France", 'wordpress-store-locator'),"MX" => __("Mexico", 'wordpress-store-locator'),"FM" => __("Micronesia", 'wordpress-store-locator'),"MI" => __("Midway Islands", 'wordpress-store-locator'),"MD" => __("Moldova", 'wordpress-store-locator'),"MC" => __("Monaco", 'wordpress-store-locator'),"MN" => __("Mongolia", 'wordpress-store-locator'),"ME" => __("Montenegro", 'wordpress-store-locator'),"MS" => __("Montserrat", 'wordpress-store-locator'),"MA" => __("Morocco", 'wordpress-store-locator'),"MZ" => __("Mozambique", 'wordpress-store-locator'),"MM" => __("Myanmar [Burma]", 'wordpress-store-locator'),"NA" => __("Namibia", 'wordpress-store-locator'),"NR" => __("Nauru", 'wordpress-store-locator'),"NP" => __("Nepal", 'wordpress-store-locator'),"NL" => __("Netherlands", 'wordpress-store-locator'),"AN" => __("Netherlands Antilles", 'wordpress-store-locator'),"NT" => __("Neutral Zone", 'wordpress-store-locator'),"NC" => __("New Caledonia", 'wordpress-store-locator'),"NZ" => __("New Zealand", 'wordpress-store-locator'),"NI" => __("Nicaragua", 'wordpress-store-locator'),"NE" => __("Niger", 'wordpress-store-locator'),"NG" => __("Nigeria", 'wordpress-store-locator'),"NU" => __("Niue", 'wordpress-store-locator'),"NF" => __("Norfolk Island", 'wordpress-store-locator'),"KP" => __("North Korea", 'wordpress-store-locator'),"VD" => __("North Vietnam", 'wordpress-store-locator'),"MP" => __("Northern Mariana Islands", 'wordpress-store-locator'),"NO" => __("Norway", 'wordpress-store-locator'),"OM" => __("Oman", 'wordpress-store-locator'),"PC" => __("Pacific Islands Trust Territory", 'wordpress-store-locator'),"PK" => __("Pakistan", 'wordpress-store-locator'),"PW" => __("Palau", 'wordpress-store-locator'),"PS" => __("Palestinian Territories", 'wordpress-store-locator'),"PA" => __("Panama", 'wordpress-store-locator'),"PZ" => __("Panama Canal Zone", 'wordpress-store-locator'),"PG" => __("Papua New Guinea", 'wordpress-store-locator'),"PY" => __("Paraguay", 'wordpress-store-locator'),"YD" => __("People's Democratic Republic of Yemen", 'wordpress-store-locator'),"PE" => __("Peru", 'wordpress-store-locator'),"PH" => __("Philippines", 'wordpress-store-locator'),"PN" => __("Pitcairn Islands", 'wordpress-store-locator'),"PL" => __("Poland", 'wordpress-store-locator'),"PT" => __("Portugal", 'wordpress-store-locator'),"PR" => __("Puerto Rico", 'wordpress-store-locator'),"QA" => __("Qatar", 'wordpress-store-locator'),"RO" => __("Romania", 'wordpress-store-locator'),"RU" => __("Russia", 'wordpress-store-locator'),"RW" => __("Rwanda", 'wordpress-store-locator'),"RE" => __("Réunion", 'wordpress-store-locator'),"BL" => __("Saint Barthélemy", 'wordpress-store-locator'),"SH" => __("Saint Helena", 'wordpress-store-locator'),"KN" => __("Saint Kitts and Nevis", 'wordpress-store-locator'),"LC" => __("Saint Lucia", 'wordpress-store-locator'),"MF" => __("Saint Martin", 'wordpress-store-locator'),"PM" => __("Saint Pierre and Miquelon", 'wordpress-store-locator'),"VC" => __("Saint Vincent and the Grenadines", 'wordpress-store-locator'),"WS" => __("Samoa", 'wordpress-store-locator'),"SM" => __("San Marino", 'wordpress-store-locator'),"SA" => __("Saudi Arabia", 'wordpress-store-locator'),"SN" => __("Senegal", 'wordpress-store-locator'),"RS" => __("Serbia", 'wordpress-store-locator'),"CS" => __("Serbia and Montenegro", 'wordpress-store-locator'),"SC" => __("Seychelles", 'wordpress-store-locator'),"SL" => __("Sierra Leone", 'wordpress-store-locator'),"SG" => __("Singapore", 'wordpress-store-locator'),"SK" => __("Slovakia", 'wordpress-store-locator'),"SI" => __("Slovenia", 'wordpress-store-locator'),"SB" => __("Solomon Islands", 'wordpress-store-locator'),"SO" => __("Somalia", 'wordpress-store-locator'),"ZA" => __("South Africa", 'wordpress-store-locator'),"GS" => __("South Georgia and the South Sandwich Islands", 'wordpress-store-locator'),"KR" => __("South Korea", 'wordpress-store-locator'),"ES" => __("Spain", 'wordpress-store-locator'),"LK" => __("Sri Lanka", 'wordpress-store-locator'),"SD" => __("Sudan", 'wordpress-store-locator'),"SR" => __("Suriname", 'wordpress-store-locator'),"SJ" => __("Svalbard and Jan Mayen", 'wordpress-store-locator'),"SZ" => __("Swaziland", 'wordpress-store-locator'),"SE" => __("Sweden", 'wordpress-store-locator'),"CH" => __("Switzerland", 'wordpress-store-locator'),"SY" => __("Syria", 'wordpress-store-locator'),"ST" => __("São Tomé and Príncipe", 'wordpress-store-locator'),"TW" => __("Taiwan", 'wordpress-store-locator'),"TJ" => __("Tajikistan", 'wordpress-store-locator'),"TZ" => __("Tanzania", 'wordpress-store-locator'),"TH" => __("Thailand", 'wordpress-store-locator'),"TL" => __("Timor-Leste", 'wordpress-store-locator'),"TG" => __("Togo", 'wordpress-store-locator'),"TK" => __("Tokelau", 'wordpress-store-locator'),"TO" => __("Tonga", 'wordpress-store-locator'),"TT" => __("Trinidad and Tobago", 'wordpress-store-locator'),"TN" => __("Tunisia", 'wordpress-store-locator'),"TR" => __("Turkey", 'wordpress-store-locator'),"TM" => __("Turkmenistan", 'wordpress-store-locator'),"TC" => __("Turks and Caicos Islands", 'wordpress-store-locator'),"TV" => __("Tuvalu", 'wordpress-store-locator'),"UM" => __("U.S. Minor Outlying Islands", 'wordpress-store-locator'),"PU" => __("U.S. Miscellaneous Pacific Islands", 'wordpress-store-locator'),"VI" => __("U.S. Virgin Islands", 'wordpress-store-locator'),"UG" => __("Uganda", 'wordpress-store-locator'),"UA" => __("Ukraine", 'wordpress-store-locator'),"SU" => __("Union of Soviet Socialist Republics", 'wordpress-store-locator'),"AE" => __("United Arab Emirates", 'wordpress-store-locator'),"GB" => __("United Kingdom", 'wordpress-store-locator'),"US" => __("United States", 'wordpress-store-locator'),"ZZ" => __("Unknown or Invalid Region", 'wordpress-store-locator'),"UY" => __("Uruguay", 'wordpress-store-locator'),"UZ" => __("Uzbekistan", 'wordpress-store-locator'),"VU" => __("Vanuatu", 'wordpress-store-locator'),"VA" => __("Vatican City", 'wordpress-store-locator'),"VE" => __("Venezuela", 'wordpress-store-locator'),"VN" => __("Vietnam", 'wordpress-store-locator'),"WK" => __("Wake Island", 'wordpress-store-locator'),"WF" => __("Wallis and Futuna", 'wordpress-store-locator'),"EH" => __("Western Sahara", 'wordpress-store-locator'),"YE" => __("Yemen", 'wordpress-store-locator'),"ZM" => __("Zambia", 'wordpress-store-locator'),"ZW" => __("Zimbabwe", 'wordpress-store-locator'),"AX" => __("Åland Islands", 'wordpress-store-locator'));

        return $countries;
    }

    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
        return 'n-a';
        }

        return $text;
    }
}
