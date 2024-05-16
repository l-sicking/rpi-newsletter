<?php

class  RpiNewsletterCron
{
    public function getAllInstancesAndImportPosts()
    {


        if (!function_exists('rpi_import_post')) {
            error_log('Newletter Import Funktion (rpi_import_news) not found');
            return;
        }

        $args = ['post_type' => 'instanz',
            'numberposts' => -1];

        $instances = get_posts($args);
        foreach ($instances as $instance) {
            $api_url = get_post_meta($instance->ID, 'api_url', true);
            $status_ignorelist = get_post_meta($instance->ID, 'status_ignorelist', true);
            $standard_terms = get_post_meta($instance->ID, 'standard_terms', true);
            $standard_user = get_post_meta($instance->ID, 'standard_user', true);
            $dryrun = get_post_meta($instance->ID, 'dryrun', true);
            $debugmode = get_post_meta($instance->ID, 'debugmode', true);
            $term_mapping = get_post_meta($instance->ID, 'term_mapping', true);


            $post_ids = rpi_import_post($api_url, $status_ignorelist , $dryrun, $debugmode);
            //try run

            //pass API url
            foreach ($post_ids as $post_id){

                $post_arr = ['post_author' => $standard_user];

                $result = wp_update_post($post_arr,true);
                if (is_wp_error($result)){
                    // TODO add error handling
                }
                wp_set_post_terms($post_id, $standard_terms, 'post_tag', true);

            }

            //TODO term mapping


        }


    }

}