<?php

namespace RPINewsletter\classes\importer\apis\wpjson;

use RPINewsletter\classes\importer\Importer;
use RPINewsletter\classes\importer\ImporterHelper;
use RPINewsletter\traits\RpiLogging;

class WpjsonImporter implements Importer
{
    use RpiLogging;
 function __construct()
 {

 }
    public function fetch_posts($instanz_id)
    {
        $helper = new ImporterHelper();
        $posts = false;

        $api_urls = [];
        $api_url = get_post_meta($instanz_id, 'api_url', true);

        while (have_rows('api_urls', $instanz_id)) {
            the_row();
            $url = get_sub_field('sub_api_url');
            if (wp_http_validate_url($url) || filter_var($url, FILTER_VALIDATE_URL)) {
                $api_urls[] = $url;
            }
        }
        $this->log_message("Processing instance " . get_the_title($instanz_id) . "  with API URLs: " . implode(', ', $api_urls));

//        $standard_terms = get_post_meta($instanz_id, 'standard_terms', true);
//        $standard_user = get_post_meta($instanz_id, 'standard_user', true);
//        $dryrun = get_post_meta($instanz_id, 'dryrun', true);
//        $debugmode = get_post_meta($instanz_id, 'debugmode', true);
        $status_ignorelist = [];

        $term_mapping = [];
        while (have_rows('term_mapping')) {
            the_row();
            $term_mapping[] = [
                'target_tax' => get_sub_field('target_tax'),
                'source_tax' => get_sub_field('source_tax'),
                'default_term' => get_sub_field('default_term')
            ];
        }
        $this->log_message('Start des WPJson Importvorgangs.');

        foreach ($api_urls as $api_url) {

            $url = sanitize_url($api_url);
            var_dump($url);
            // HTTP request to the API
            $response = wp_remote_get($url);

            // Check if the request was successful
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->log_message("Something went wrong: $error_message");
                return false;
            } else {
                // Handle the response
                $this->log_message("Response received");
                $posts = json_decode(wp_remote_retrieve_body($response), true);

            }

            $this->log_message(count($posts) . ' Posts received through remote');

            // Fetch all pages of results
//            $news_items = $this->fetch_all_pages($url, $posts);

            foreach ($posts as $item) {

                if (in_array($item['status'], $status_ignorelist)) {
                    continue;
                }

                // Erstellen eines neuen Beitrags für jede Sprache.

                $post_data = array(
                    'post_author' => 1, // oder einen dynamischen Autor
                    'post_content' => $item['content']['rendered'],
                    'post_excerpt' => $item['excerpt']['rendered'],
                    'post_date' => $item['date'],
                    'post_title' => $item['title']['rendered'],
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    'meta_input' => array(
                        'import_link' => $item['link'],
                        'import_id' => $item['id'], // oder eine andere eindeutige ID
                    ),
                    'categories' => $item['categories'],
                    'tags_input' => $item['tags'],
                );

                if (empty($term_mapping)) {
                    foreach ($item['taxonomy_info'] as $tax) {
                        if (is_array($tax))
                            foreach ($tax as $term) {
                                $post_data['tags_input'][] = $term['label'];
                            }
                    }
                }

                $this->log_message('looking for media links in ' . var_export($item['featured_media'], true));
                if (is_numeric($item['featured_media'])) {
                    if ($helper->multiKeyExists($item, 'full') && is_array($item['featured_image_urls_v2']['full'])) {
//                        foreach ($item['_links']['wp:featuredmedia'] as $featured_media) {
//                            $post_data['featured_media'][] = $featured_media['href'];
//                        }
                        //TODO  featured image urls v2 is potentially unusable since the caption of the image isnt present in this case
//                    $post_data['featured_media'] = reset($item['featured_image_urls_v2']['full']);
//                    $post_data['wp_json_media'] = false;
//                    $this->log_message('Found media under featured_image_urls_v2');
                    } else {
                        if ($helper->multiKeyExists($item, 'wp:featuredmedia') && $helper->multiKeyExists($item['_links']['wp:featuredmedia'], 'href')) {
                            $post_data['featured_media'] = reset($item['_links']['wp:featuredmedia'])['href'];
                            $post_data['wp_json_media'] = true;
                            $this->log_message('Found media under _links');
                        } else {
                            $this->log_message('No viable media Found');
                        }

                    }
                } else {
                    $post_data['featured_media'] = $item['featured_media'];
                    $post_data['wp_json_media'] = false;
                    $this->log_message('media links found under featured_media');
                }


//                if (!is_numeric($item['featured_media']) && wp_http_validate_url($item['featured_media']) || filter_var($item['featured_media'], FILTER_VALIDATE_URL)) {
//
//                } elseif (!empty($item['featured_media']) && key_exists('featured_image_urls_v2', $item)) {
//
//                } else {
//                    $this->log_message('Found no valid media aborting media import ... ' . var_export($item['featured_media'], true));
//                }

                if (isset($item['id'])) {
                    $existing_post = get_posts([
                        'meta_query' => array(
                            array(
                                'key' => 'import_id',
                                'value' => $item['id'],
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
                }

                // Execute Term Mapping or add default Term
                if (isset($post_id)) {
                    $helper->term_mapping($post_id, $term_mapping, $item);

                    $posts[] = $post_id;
                }
            }

        }
        return $posts;
    }

    public function fetch_post($instanz_id, $post_id)
    {
        // TODO: Implement fetch_post() method.
    }
    public function get_configuration_fields()
    {
        // TODO: Implement get_configuration_fields() method.
    }
}