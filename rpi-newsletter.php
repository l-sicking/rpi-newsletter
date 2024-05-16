<?php

/*
Plugin Name: Rpi Newsletter
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: RPI Newsletter Plugin for Newsletter Mainserver
Version: 1.0
Author: reintanz
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

class RpiNewsletter
{


    public function __construct()
    {
        add_action('save_post', [$this, 'addInstanceTermOnSave'], 10, 3);

    }

    private function addInstanceTermOnSave($post_id, $post, $update)
    {

        if (is_a($post, 'WP_Post') && $post->post_type == 'instanz' && !has_term($post->post_name, 'term_instanz', $post->ID)) {



            $result = wp_create_term($post->post_name, 'term_instanz');
            if (is_wp_error($result)) {
                echo $result->get_error_message();
            }
            wp_set_post_terms($post->ID, $post->post_name, 'term_instanz', true);

        }
    }

}

new RpiNewsletter();