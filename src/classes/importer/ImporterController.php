<?php

namespace RPINewsletter\classes\importer;
//require_once plugin_dir_path(__FILE__) . 'ImporterHelper.php';
//require_once plugin_dir_path(__FILE__) . 'ImporterSetup.php';


use RPINewsletter\classes\importer\ImporterSetup;
use RPINewsletter\classes\importer\ImporterHelper;
use RPINewsletter\classes\RPIPostImporter;
use RPINewsletter\traits\RpiLogging;
use RPINewsletter\classes\importer\apis\wpjson;
use RPINewsletter\classes\importer\Importer;
/**
 * @TODO
 * Controller Class that provides Logic for Importer Core Logic
 */
class ImporterController
{


    use RpiLogging;

    private object $importerHelper;

    public function __construct()
    {
        new ImporterSetup();
        $this->importerHelper = new ImporterHelper();


//TODO this shortcode is for testing purposes
        add_shortcode('post_importer', [$this, 'run_import']);

    }

    public function run_import()
    {

        $args = [
            'post_type' => 'instanz',
            'numberposts' => -1
        ];

        $instances = get_posts($args);
        $this->log_message("Found " . count($instances) . " instances to process.");


        foreach ($instances as $instance) {


//            $api_urls = [];
//
//            // This is just checking deprecated custom fields for api_urls
//            $api_url[] = get_post_meta($instance->ID, 'api_url', true);
//
//            while (have_rows('api_urls', $instance->ID)) {
//                the_row();
//                $url = get_sub_field('sub_api_url');
//                if (wp_http_validate_url($url) || filter_var($url, FILTER_VALIDATE_URL)) {
//                    $api_urls[] = $url;
//                }
//            }

//            $this->log_message("Processing instance '{$instance->post_title}' with API URLs: " . implode(', ', $api_urls));
//
//            $standard_terms = get_post_meta($instance->ID, 'standard_terms', true);
//
//            $standard_user = get_post_meta($instance->ID, 'standard_user', true);
//            //TODO move to respective Importer
//            $dryrun = get_post_meta($instance->ID, 'dryrun', true);
            $debugmode = get_post_meta($instance->ID, 'debugmode', true);
//            $this->log_message("Getting Instance Properties: Standard Terms  " . var_export($standard_terms, true) . " -  Standard User " . var_export($standard_user, true) . "  -  debugmode " . var_export($debugmode, true) . " - ");
            //TODO is this even necessary
            $status_ignorelist = [];

            $this->setDebugMode($debugmode);


            //TODO term mapping is still not implemented properly
            $term_mapping = [];
            while (have_rows('term_mapping')) {
                the_row();
                $term_mapping[] = [
                    'target_tax' => get_sub_field('target_tax'),
                    'source_tax' => get_sub_field('source_tax'),
                    'default_term' => get_sub_field('default_term')
                ];
            }

            $api = get_post_meta($instance->ID, 'api', true);

//            $api_controller = plugin_dir_path(__FILE__) . 'src/classes/importer/' . $api . '/' . $api . '_importer.php';
            $api_controller ='\\'. __NAMESPACE__ . '\\apis\\'.$api .'\\'. $api . '_Importer';
            $this->log_message('Trying to use Importer API: ' . print_r($api_controller, true));
                $importer = new $api_controller();

            if ($importer instanceof \RPINewsletter\classes\importer\Importer) {



                        $result = $importer->fetch_posts($instance->ID);


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

            } else {
                throw new \LogicException($api_controller . 'must implement Importer Interface');
            }

        }

        $this->log_message("Cron job completed.");

    }


}