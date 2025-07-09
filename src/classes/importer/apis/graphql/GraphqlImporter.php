<?php
namespace RPINewsletter\classes\importer\apis\graphql;

use RPINewsletter\classes\importer\Importer;
use RPINewsletter\classes\importer\ImporterHelper;
use RpiNewsletter\traits\RpiLogging;

//require_once plugin_dir_path(__FILE__) . 'src/classes/importer/importer_helper.php';
//require_once plugin_dir_path(__FILE__) . 'src/classes/importer/whitelist_domain_enum.php';

class GraphqlImporter implements Importer
{
    use RpiLogging;

    function __construct()
    {
$this->log_message('Graphql Importer loaded');
    }
    public function fetch_posts($instanz_id)
    {
        $helper = new ImporterHelper();

        $api_urls = [];
        $api_url = get_post_meta($instanz_id, 'api_url', true);

        $dryrun = get_post_meta($instanz_id, 'dryrun', true);
        $debugmode = get_post_meta($instanz_id, 'debugmode', true);


        $graphql = get_post_meta($instanz_id, 'graphql_import', true);
        $graphql_body = get_post_meta($instanz_id, 'graphql_request_body', true);


        $term_mapping = [];
        while (have_rows('term_mapping')) {
            the_row();
            $term_mapping[] = [
                'target_tax' => get_sub_field('target_tax'),
                'source_tax' => get_sub_field('source_tax'),
                'default_term' => get_sub_field('default_term')
            ];
        }
        while (have_rows('api_urls', $instanz_id)) {
            the_row();
            $url = get_sub_field('sub_api_url');
            if (wp_http_validate_url($url) || filter_var($url, FILTER_VALIDATE_URL)) {
                $api_urls[] = $url;
            }
        }
        $this->log_message("Processing instance " . get_the_title($instanz_id) . "  with API URLs: " . implode(', ', $api_urls));


        $this->log_message('Start des Graphql Importvorgangs.');


        $graphql_body = get_post_meta($instanz_id, 'graphql_request_body', true);

        foreach ($api_urls as $api_url) {

            $data = json_encode(array('query' => $graphql_body));
            $response = wp_remote_post($api_url, array(
                'body' => $data,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->log_message("Something went wrong: $error_message");
                return false;
            } else {
                // Handle the response
                $response_body = wp_remote_retrieve_body($response);
                $this->log_message("Response received");
            }

            $response = json_decode($response['body'], true);

            while (is_array($response)) {
                if (key_exists('posts', $response)) {
                    $response = reset($response);
                    break;
                } else {
                    $response = reset($response);
                }
            }


            foreach ($response as $item) {


                $post_data = [];
                if (!empty($item["post"]['content'])) {
                    $post_data['post_content'] = $item["post"]['content'];
                }
                if (!empty($item["post"]['excerpt'])) {
                    $post_data['post_excerpt'] = $item["post"]['excerpt'];
                }
                if (!empty($item["post"]['title'])) {
                    $post_data['post_title'] = $item["post"]['title'];
                }
                if (!empty($item['date'])) {
                    $post_data['post_date'] = $item['date'];
                }
                if (!empty($item['url']) && !empty($item['import_id'])) {
                    $post_data['meta_input'] = array(
                        'import_link' => $item['url'],
                        'import_id' => $item['import_id']);
                }
                if (!empty($item['categories'])) {
                    $post_data['categories'] = $item['categories'];
                }
                if (!empty($item['tags'])) {
                    $post_data['tags'] = $item['tags'];
                }

                $post_data['wp_json_media'] = false;
                if (is_array($item['image'])) {
                    if (key_exists('url', $item['image'])) {


                        if (in_array($item['image']['url'], whitelist_domain_enum::values())) {
                            //case given image url is considered copyright safe and can be downloaded

                        }

//                    $post_data['featured_media'] = $item['image']['url'];
                        $post_data['featured_media'] = $item['image']['url'];
                    } elseif (is_array($item['altimages']) && key_exists('altimage', $item['altimages']) && key_exists('url', $item['altimage'])) {
                        $post_data['featured_media'] = $item['image']['altimages']['altimage']['url'];
                    }

                    $this->log_message('Added featured media' . var_export($post_data['featured_media'], true));

                }


                $post_data['post_author'] = 1;
                $post_data['post_status'] = 'publish';
                $post_data['post_type'] = 'post';

                if (isset($item['import_id'])) {
                    $this->log_message('import id found : ' . $item['import_id']);
                    $existing_post = get_posts([
                        'meta_query' => array(
                            array(
                                'key' => 'import_id',
                                'value' => $item['import_id'],
                                'compare' => '=',
                            )
                        )
                    ]);
                    $post_exists = $helper->check_if_post_exists($existing_post);
                    if (!$dryrun) {
                        if ($post_exists) {
                            $post_data['ID'] = $post_exists;
                            $post_id = $helper->create_post($post_data, true);
                        } else {
                            $post_id = $helper->create_post($post_data);
                        }
                    }
                } else {
                    $this->log_message('Import ID not found aborting post import');
                }
//                if (!$dryrun) {
//                    $post_id = $this->create_post($post_data, true);
//                }
                // Execute Term Mapping or add default Term
                $helper->term_mapping($post_id, $term_mapping, $post_data);
                if ($post_id && !is_wp_error($post_id)) {
                    $posts[] = $post_id;
                } else {
                    $this->log_message('An Error occured whilst creating a post.' . is_wp_error($post_id) ? $post_id->get_error_message() : 'No error Message');
                }
            }


        }

    }

    public function fetch_post($instanz_id, $post_id): mixed
    {
        // TODO: Implement fetch_post() method.
        return null;
    }
    public function get_configuration_fields(){
        return [
            'graphql_query' => 'Graphql Query',
        ];
    }
}