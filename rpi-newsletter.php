<?php

/*
Plugin Name: Rpi Newsletter
Plugin URI: https://github.com/rpi-virtuell/rpi-newsletter
Description: RPI Newsletter Plugin for Newsletter Mainserver
Version: 1.0
Author: reintanz
Author URI: https://github.com/FreelancerAMP
License: A "Slug" license name e.g. GPL2
*/

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';


use RPINewsletter\classes\RPIPostImporter;
use RPINewsletter\classes\importer\ImporterController;
use RPINewsletter\traits\RpiLogging;

// Ensure this file is accessed within WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


class RpiNewsletter
{

    use RpiLogging;

    public function __construct()
    {

        //Check if composer has been installed
        add_action('admin_notices', [$this, 'check_composer_install_status']);

        // Add custom actions and filters for plugin functionality
        add_shortcode('post_import_newsletter', [$this, 'getAllInstancesAndImportPosts']);

        add_action('cron_post_import_newsletter', [$this, 'getAllInstancesAndImportPosts']);
        add_action('save_post', [$this, 'addInstanceTermOnSave'], 10, 3);

        add_filter('manage_instanz_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_instanz_posts_custom_column', [$this, 'custom_columns_content'], 10, 2);

        add_filter('manage_post_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_post_posts_custom_column', [$this, 'custom_columns_content'], 10, 2);

        add_action('restrict_manage_posts', [$this, 'add_force_import_button_to_post_archive']);
        add_action('load-edit.php', [$this, 'run_import_force']);

        // Handle frontend redirection to origin page
        add_filter('the_content', [$this, 'redirect_or_add_origin_link']);

        new ImporterController();

    }


    function check_composer_install_status()
    {
        // Adjust this to point to the correct path if needed
        $autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';

        if (!file_exists($autoload_path)) {
            echo '<div class="notice notice-error"><p>';
            echo '⚠️ <strong>Composer dependencies not installed.</strong> Please run <code>composer install</code> in the plugin directory.';
            echo '</p></div>';
        }
    }


    /**
     * @param $content
     * @return mixed|string|void
     */
    function redirect_or_add_origin_link($content)
    {
        if (is_single() && !is_admin()) {

            switch (get_post_type()) {
                case 'post':
                    $origin_link = get_post_meta(get_the_ID(), 'import_link', true);
                    if (!empty($origin_link)) {
//                $this->log_message("Redirecting to origin page: {$origin_link}");
                        wp_redirect($origin_link);
                        exit();
                    }
                    break;

                case 'instanz':

                    $homepage = get_post_meta(get_the_ID(), 'homepage', true);
                    if (!empty($homepage)) {
                        ob_start();
                        ?>
                        <div style="align-content: center">

                            <a class="button" href="<?php echo $homepage ?>">Zur Website</a>
                        </div>
                        <br>
                        <div>

                            <?php
                            if (have_rows('newsletter_listen_ids')) {
                                ?>
                                <p><b>Abonnieren Sie diesen Newsletter!</b> Bleiben Sie auf dem neuesten Stand über die
                                    aktuellen Neuigkeiten und erhalten Sie exklusives Material direkt in Ihr Postfach.
                                    Mit
                                    diesem Newsletter informiert man Sie regelmäßig über die neuesten Entwicklungen und
                                    stellt Ihnen nützliche Ressourcen zur Verfügung – einfach anmelden und bleiben Sie
                                    informiert!</p>
                                <br>
                                <?php
                                while (have_rows('newsletter_listen_ids')) {
                                    the_row();
                                    $list_id = get_sub_field('newsletter_list_id');
                                    if (!empty($list_id)) {
//                                        echo do_shortcode('[newsletter_field name="list" number="' . $list_id . '" label="List Name"]');
                                        echo do_shortcode('[newsletter_form list="' . $list_id . '" /]');
                                    }
                                }
                            }
                            ?>
                        </div>
                        <?php
                        $content = ob_get_clean() . $content;
                    }
                    break;

            }

        }
        return $content;
    }

    // Redirect single posts to their origin page if an import link is available

    /**
     * @param $columns
     * @return mixed
     */
    function add_custom_columns($columns)
    {
        $columns['term_instanz'] = __('Term Instanzen', 'rpi-newsletter');
        $columns['trigger_import'] = __('Import anstoßen', 'rpi-newsletter');
        return $columns;
    }

    // Add custom columns to post type admin views

    /**
     * @param $column
     * @param $post_id
     * @return void
     */
    function custom_columns_content($column, $post_id)
    {

        switch ($column) {
            case 'term_instanz':
                $terms = wp_get_post_terms($post_id, 'term_instanz', ['field' => 'names']);
                foreach ($terms as $term) {
                    echo '<a href="' . get_home_url() . '/wp-admin/edit.php?term_instanz=' . $term->slug.'">' . $term->name . '</a>';
                }
                break;
            case 'trigger_import':
                if (!isset($_GET['post_import'])) {
                    echo '<a class="button" href="' . get_home_url() . '/wp-admin/edit.php?post_import=' . $post_id . '"> update </a>';

                } else {
                    echo '<a class="button disabled" href="' . get_home_url() . '/wp-admin/edit.php?post_import=' . $post_id . '"> update </a>';

                }
                break;
        }
    }


    public function add_force_import_button_to_post_archive()
    {

        if (!isset($_GET['force_update'])) {
            echo '<a class="button-primary" href="' . get_home_url() . '/wp-admin/edit.php?force_update=1 ">Alle Instanzen importieren</a>';

        } else {
            echo '<a class="button-primary disabled" href="' . get_home_url() . '/wp-admin/edit.php?force_update=1 ">Alle Instanzen importieren</a>';

        }

    }


    public function run_import_force()
    {
        if (isset($_GET['force_update']) && $_GET['force_update'] == 1) {
            $this->getAllInstancesAndImportPosts();
            wp_redirect(admin_url('edit.php'));
            exit();
        }
    }


    // Populate custom columns with relevant data


    /**
     * @depecated
     * @return void
     * @see ImporterController::run_import()
     * @deprecated
     */
    public function getAllInstancesAndImportPosts()
    {
        $args = [
            'post_type' => 'instanz',
            'numberposts' => -1
        ];

        $instances = get_posts($args);


        $this->log_message("Found " . count($instances) . " instances to process.");


        foreach ($instances as $instance) {

            $api_urls = [];
            $api_url = get_post_meta($instance->ID, 'api_url', true);

            while (have_rows('api_urls', $instance->ID)) {
                the_row();
                $url = get_sub_field('sub_api_url');
                if (wp_http_validate_url($url) || filter_var($url, FILTER_VALIDATE_URL)) {
                    $api_urls[] = $url;
                }
            }

            $this->log_message("Processing instance '{$instance->post_title}' with API URLs: " . implode(', ', $api_urls));


//            $standard_terms = get_post_meta($instance->ID, 'standard_terms', true);
//            $standard_user = get_post_meta($instance->ID, 'standard_user', true);
//            $dryrun = get_post_meta($instance->ID, 'dryrun', true);

            $debugmode = get_post_meta($instance->ID, 'debugmode', true);



            //TODO DEBUG DELETE BEFORE
            $debugmode = true;

            $status_ignorelist = [];

            $this->setDebugMode($debugmode);
//
//            $graphql = get_post_meta($instance->ID, 'graphql_import', true);
//            $graphql_body = get_post_meta($instance->ID, 'graphql_request_body', true);

//            $term_mapping = [];
//            while (have_rows('term_mapping')) {
//                the_row();
//                $term_mapping[] = [
//                    'target_tax' => get_sub_field('target_tax'),
//                    'source_tax' => get_sub_field('source_tax'),
//                    'default_term' => get_sub_field('default_term')
//                ];
//            }


            $importer = new RPIPostImporter();
$this->log_message('Importer Started');
//
//            var_dump($importer);
//            exit();

            if (is_array($api_urls) && count($api_urls) > 0) {
                foreach ($api_urls as $api_url) {
                    $result = $importer->rpi_import_post($api_url, $status_ignorelist, $term_mapping, $dryrun, $graphql, $graphql_body);


                    if (is_array($result) && count($result) > 0) {

                        foreach ($result as $value) {
                            $new_post_id = 0;
                            if (is_array($value) && key_exists('id', $value)) {
                                $new_post_id = $value['id'];
                            } elseif (is_a($value, 'WP_Post')) {
                                $new_post_id = $value->id;
                            } else {
                                $new_post_id = $value;
                            }

                            $this->log_message("Imported post ID: {$new_post_id}.");

                            if (!empty($standard_user)) {
                                wp_update_post(['ID' => $new_post_id, 'post_author' => $standard_user]);
                                $this->log_message("Updated author for post ID {$new_post_id} to user ID {$standard_user}.");
                            }


                            if (empty(wp_get_post_terms($new_post_id, 'term_instanz'))) {
                                $target_instanz_term = wp_get_post_terms($instance->ID, 'term_instanz', ['fields' => 'ids']);
                                wp_set_post_terms($new_post_id, $target_instanz_term, 'term_instanz', true);
                                $this->log_message("Set Instanz term for post ID {$new_post_id}.");
                            }


                            if (!empty($standard_terms)) {
                                wp_set_post_terms($new_post_id, $standard_terms, 'post_tag', true);
                                $this->log_message("Set tags for post ID {$new_post_id}.");
                            }

                        }
                    }
                }
            } else {
                $this->log_message("No valid API URLs found for instance '{$instance->post_title}'.", 'WARNING');


            }
        }

        $this->log_message("Cron job completed.");

    }

    public function addInstanceTermOnSave($post_id, $post, $update)
    {
        if (is_a($post, 'WP_Post') && $post->post_type == 'instanz' && !has_term($post->post_name, 'term_instanz', $post->ID)) {
            $result = wp_create_term($post->post_name, 'term_instanz');
            if (!is_wp_error($result)) {
                wp_set_post_terms($post->ID, $post->post_name, 'term_instanz', true);
//                $this->log_message("Added term '{$post->post_name}' to post ID {$post->ID}.");
            } else {
//                $this->log_message("Failed to create term '{$post->post_name}': " . $result->get_error_message(), 'ERROR');
            }
        }
    }
}

// Initialize the plugin
new RpiNewsletter();



