<?php

class  RpiNewsletterCron
{


    private function log($message)
    {
        $log_file = WP_CONTENT_DIR . '/rpi_post_importer_log.txt'; // Pfad zur Log-Datei
        $timestamp = current_time('mysql');
        $entry = "{$timestamp}: {$message}\n";

        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    public function getAllInstancesAndImportPosts()
    {

        $this->log('Cron Triggered');

        if (!function_exists('rpi_import_post')) {
            $this->log('rpi_import_post function not found');
            error_log('Newsletter Import Funktion (rpi_import_post) of rpi_post_importer not found');
            return;
        }

        $this->log('rpi_import_post function found. Preparing data');

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


            $post_ids = rpi_import_post($api_url, $status_ignorelist, $dryrun, $debugmode);
            //try run

            //pass API url
            foreach ($post_ids as $post_id) {

                if (!empty($standard_user)) {
                    $post_arr = ['post_author' => $standard_user];

                    $result = wp_update_post($post_arr, true);
                    if (is_wp_error($result)) {
                        // TODO add error handling
                    }
                }
                if (!empty($standard_terms)) {
                    wp_set_post_terms($post_id, $standard_terms, 'post_tag', true);

                }


            }

            //TODO term mapping


        }


    }

}