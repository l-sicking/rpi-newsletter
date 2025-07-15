<?php

namespace Rpi_ls_Newsletter\classes;

use Rpi_ls_Newsletter\traits\Rpi_ls_Logging;

/**
 * @deprecated
 * This Class has been split up into importer_helper and importer_controller
 */
class RPI_ls_PostImporter
{
    use Rpi_ls_Logging;

    public function __construct()
    {
    }

    /**
     * @param $api_url
     * @param $status_ignorelist
     * @param $term_mapping
     * @param $dryrun
     * @param $graphql
     * @param $graphql_body
     * @return array|false|mixed
     *
     *  This function is being replaced by the corresponding importer files of each api to reduce parameters
     * and improve readability
     * @see \ImporterController::run_import()
     *
     * @deprecated
     */
    public function rpi_import_post($api_url = '', $status_ignorelist = [], $term_mapping = [], $dryrun = false, $graphql = false, $graphql_body = '')
    {


        $posts = [];

        if (boolval($graphql)) {

            $this->log_message('Start des Graphql Importvorgangs.');


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
                    $post_exists = $this->check_if_post_exists($existing_post);
                    if (!$dryrun) {
                        if ($post_exists) {
                            $post_data['ID'] = $post_exists;
                            $post_id = $this->create_post($post_data, true);
                        } else {
                            $post_id = $this->create_post($post_data);
                        }
                    }
                } else {
                    $this->log_message('Import ID not found aborting post import');
                }
//                if (!$dryrun) {
//                    $post_id = $this->create_post($post_data, true);
//                }
                // Execute Term Mapping or add default Term
                $this->term_mapping($post_id, $term_mapping, $post_data);
                if ($post_id && !is_wp_error($post_id)) {
                    $posts[] = $post_id;
                } else {
                    $this->log_message('An Error occured whilst creating a post.' . is_wp_error($post_id) ? $post_id->get_error_message() : 'No error Message');
                }
            }


        } else {
            $this->log_message('Start des Importvorgangs.');

            $url = sanitize_url($api_url);

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
                    if ($this->multiKeyExists($item, 'full') && is_array($item['featured_image_urls_v2']['full'])) {
//                        foreach ($item['_links']['wp:featuredmedia'] as $featured_media) {
//                            $post_data['featured_media'][] = $featured_media['href'];
//                        }
                        $post_data['featured_media'] = reset($item['featured_image_urls_v2']['full']);
                        $post_data['wp_json_media'] = false;
                        $this->log_message('Found media under featured_image_urls_v2');
                    } else {
                        if ($this->multiKeyExists($item, 'wp:featuredmedia') && $this->multiKeyExists($item['_links']['wp:featuredmedia'], 'href')) {
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
                    $post_exists = $this->check_if_post_exists($existing_post);
                    if (!$dryrun) {
                        if ($post_exists) {
                            $post_data['ID'] = $post_exists;
                            $post_id = $this->create_post($post_data, true);
                        } else {
                            $post_id = $this->create_post($post_data);
                        }
                    }
                }

                // Execute Term Mapping or add default Term
                if (isset($post_id)) {
                    $this->term_mapping($post_id, $term_mapping, $item);

                    $posts[] = $post_id;
                }
            }
        }

        $this->log_message('Importvorgang abgeschlossen.');
        return $posts;


    }

    /**
     * @param $existing_post
     * @return false
     *
     * @deprecated
     * @see \ImporterHelper::check_if_post_exists()
     */

    public function check_if_post_exists($existing_post)
    {
        $post_exists = false;
        if (count($existing_post) > 0) {
            $existing_post = reset($existing_post);
            $this->log_message("Imported Post $existing_post->ID already exists! Updating ...");
            $post_exists = $existing_post->ID;
        }
        return $post_exists;
    }

    /**
     * @param $item
     * @param $update
     * @return false|int|\WP_Error
     * @see \ImporterHelper::create_post()
     *
     * @deprecated
     */
    private function create_post($item, $update = false)
    {

        if (!empty($item['post_title']) && !empty($item['post_content'])) {
            // Annahme: $item enthält alle notwendigen Informationen

            $this->log_message("Trying to create Post with provided Data");
            // Erstellen des Beitrags
            $post_id = wp_insert_post($item, true);
            if (is_wp_error($post_id)) {
                $this->log_message($post_id->get_error_message());
                return false;
            }
            // Überprüfen, ob der Beitrag erfolgreich erstellt wurde.
            if ($post_id && !is_wp_error($post_id)) {

                // Medieninhalte hinzufügen


                if (!empty($item['featured_media']) && !has_post_thumbnail($post_id)) {
                    $this->log_message('Trying to import media: ' . var_export($item['featured_media'], true));
                    if (!$item['wp_json_media']) {
                        $this->log_message('Importing via url in featured media');
                        $media_id = $this->import_media($item['featured_media'], $post_id, false);
                    } else {
                        $media_id = $this->import_media($item['featured_media'], $post_id);

                    }

                    if ($media_id) {
                        $this->log_message('Media Imported');
                    } else {
                        $this->log_message('An Error has occurred while importing Media');
                    }

                } else {
                    $this->log_message('No media provided:(' . var_export($item['featured_media'], true) . ') or thumbnail already exists: (' . var_export(has_post_thumbnail($post_id), true)) . ')';
                    $this->log_message('Attachment ID ' . var_export(wp_get_attachment_url(get_post_thumbnail_id($post_id)), true));

                }


                // Kategorien und Tags hinzufügen
                if (!empty($item['categories'])) {
                    $this->log_message('Trying to assign Categories Terms: ' . var_export($item['categories'], true));
                    $this->assign_terms($post_id, $item['categories'], 'category');
                    $this->log_message('Assignment of Categories Terms complete');
                }
                if (!empty($item['tags'])) {
                    $this->log_message('Trying to assign Tags Terms: ' . var_export($item['tags'], true));
                    $this->assign_terms($post_id, $item['tags'], 'post_tag');
                    $this->log_message('Assignment of Tags Terms complete');

                }

                $this->log_message("Beitrag (ID: $post_id) erstellt: '{$item['post_title']}'");
                return $post_id;
            }
        }
        return false;

    }

    /**
     * @param $media_url
     * @param $post_id
     * @param $wp_json
     * @return array|bool|int|\WP_Error
     * @deprecated
     *
     */
    private function import_media($media_url, $post_id = 0, $wp_json = true)
    {

        if ($wp_json) {
            $this->log_message('Trying to import Media via wp_json');
        } else {
            $this->log_message('Trying to import Media via direct Download');
        }
        if (!$media_url) {
            $this->log_message('No Media URL provided');
            return false;
        }

        if (is_array($media_url)) {

            if ($wp_json) {
                $this->log_message('Multiple media urls received handling them as via wp_json API request');
                $attachment_id = array();
                foreach ($media_url as $url) {
                    $attachment_id[] = $this->wp_insert_attachment_from_url($url, $post_id, $wp_json);
                }
                $this->log_message('Added ' . count($attachment_id) . ' Attachments to post');
                return $attachment_id;
            } else {
                $this->log_message('Multiple media urls received but no wp_json urls cant compute! returning ...');
                return false;
            }


        } else {
            // Der Dateiname wird aus der URL extrahiert.
            $this->log_message('Single Media File download via URL' . var_export($media_url, true));
            $file_name = basename($media_url);

            // Temporärdatei erstellen.
            $temp_file = download_url($media_url);
            if (is_wp_error($temp_file)) {
                $this->log_message('Trying to download the media caused the following Error: ' . $temp_file->get_error_message());
                return false;
            }

            // Dateiinformationen vorbereiten.
            $file = array(
                'name' => $file_name,
                'type' => mime_content_type($temp_file),
                'tmp_name' => $temp_file,
                'error' => 1,
                'size' => filesize($temp_file),
            );


            if (!function_exists('media_handle_sideload')) {
                $this->log_message('function media_handle_sideload not found, using wp_insert_attachment instead');
                $attachment_id = $this->wp_insert_attachment_from_url($media_url, $post_id, $wp_json);

                if (!$attachment_id) {
                    $this->log_message("Attachment of '$media_url' to Post '$post_id' failed");
                    return false;

                } else {
                    $media_id = set_post_thumbnail($post_id, $attachment_id);
                    $this->log_message("Added Thumbnail of '$media_url' to Post:'$post_id', with Attachment ID:'$attachment_id'");
                }

            } else {
                // Die Datei wird der Medienbibliothek hinzugefügt.
                $media_id = media_handle_sideload($file, $post_id);
            }


            if (is_wp_error($media_id)) {
                $this->log_message('Media import Failed' . $media_id->get_error_message());
                @unlink($temp_file);
                return false;
            }

            return $media_id;
        }

    }

    /**
     * @param $url
     * @param $parent_post_id
     * @param $wp_json
     * @return false|int|\WP_Error
     * @deprecated
     * @see \ImporterHelper::wp_insert_attachment_from_url()
     *
     */
    function wp_insert_attachment_from_url($url, $parent_post_id = null, $wp_json = true)
    {
        $this->log_message('running wp_insert_attachment() as wpjson: ' . var_export($wp_json, true) . ' url ' . var_export($url, true));
        if ($wp_json) {

            $url = sanitize_url($url);

            if (function_exists('wp_remote_get')) {
                $this->log_message('wp_remote_get found ... running function...' . var_export($url, true));
                // HTTP request to the API
                $response = wp_remote_get($url);
            } else {
                $this->log_message('ERROR function wp_remote_get not found');
                return false;
            }

            $this->log_message('WP_REMOTE_GET result ' . var_export($response, true));


            if (is_wp_error($response)) {
                $this->log_message('Error response: ' . $response->get_error_message());
                return false;
            }
            $attachment = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($attachment)) {

                if (!key_exists('guid', $attachment) && !key_exists('rendered', $attachment['guid'])) {
                    $this->log_message("RESPONSE ERROR: Array Path ['guid']['rendered'] not found in " . var_export($attachment, true));
                    return false;
                }

                $http = new \WP_Http();
                $response = $http->request($attachment['guid']['rendered']);
                if (200 !== $response['response']['code']) {
                    $this->log_message('WP_Http returned HTTP code ' . var_export($response['response'], true));
                    return false;
                }

                $upload = wp_upload_bits(basename($attachment['guid']['rendered']), null, $response['body']);
                if (empty($upload['file'])) {
                    $this->log_message('wp_upload_bits returned error: ' . var_export($upload, true) . 'while trying to upload file' . var_export($response['body'], true));

                    return false;
                }

                $file_path = $upload['file'];
                $file_name = basename($file_path);
                $file_type = wp_check_filetype($file_name, null);
                $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
                $wp_upload_dir = wp_upload_dir();

                $post_info = array(
                    'guid' => $wp_upload_dir['url'] . '/' . $file_name,
                    'post_mime_type' => $file_type['type'],
                    'post_title' => sanitize_text_field($response['title']['rendered']),
                    'post_excerpt' => sanitize_text_field($response['caption']['rendered']),
                    'post_content' => sanitize_text_field($response['description']['rendered']),
                    'post_status' => 'inherit',
                );

                // Create the attachment.
                $attach_id = wp_insert_attachment($post_info, $file_path, $parent_post_id);

                // Include image.php.
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // Generate the attachment metadata.
                $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

                // Assign metadata to attachment.
                wp_update_attachment_metadata($attach_id, $attach_data);

                return $attach_id;

            } else {
                $this->log_message('Error unexpected attachment received' . var_export($attachment, true));
            }

        } else {

            $this->log_message('inserting file via direct download');

//            TODO ADD check if description and caption of thumbnail are present if not abort
            if (!class_exists('\WP_Http')) {
                require_once ABSPATH . WPINC . '/class-http.php';
            }


            $http = new \WP_Http();
            $this->log_message('requesting http body via url');
            $response = $http->request($url);
            if (200 !== $response['response']['code']) {
                $this->log_message('WP_Http returned HTTP code ' . var_export($response['response'], true));
                return false;
            }

            $upload = wp_upload_bits(basename($url), null, $response['body']);
            if (!empty($upload['error'])) {
                $this->log_message('wp_upload_bits returned error: ' . var_export($upload['error'], true) . 'while trying to upload file' . var_export($response['body'], true));
                return false;
            }

            $file_path = $upload['file'];
            $file_name = basename($file_path);
            $file_type = wp_check_filetype($file_name, null);
            $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
            $wp_upload_dir = wp_upload_dir();
            $this->log_message('wp_upload_dir returned ' . var_export($wp_upload_dir, true));

            $post_info = array(
                'guid' => $wp_upload_dir['url'] . '/' . $file_name,
                'post_mime_type' => $file_type['type'],
                'post_title' => sanitize_text_field($attachment_title),
                'post_status' => 'inherit',
            );

            // Create the attachment.
            $attach_id = wp_insert_attachment($post_info, $file_path, $parent_post_id);

            // Include image.php.
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Generate the attachment metadata.
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

            // Assign metadata to attachment.
            $success = wp_update_attachment_metadata($attach_id, $attach_data);
            if ($success) {
                $this->log_message('updating attachment meta data successful');
            } else {
                $this->log_message('updating attachment meta data failed');
            }

            return $attach_id;
        }

        return false;
    }

    /**
     * @param $post_id
     * @param array $term_ids
     * @param $taxonomy
     * @return void
     * @deprecated
     * @see \ImporterHelper::assign_terms()
     *
     */
    private function assign_terms($post_id, array $term_ids, $taxonomy)
    {
        if (taxonomy_exists($taxonomy)) {
            $this->log_message('Sanitizing imported post terms');
//        $term_ids = $this->sanitize_imported_post_terms($term_ids);

            if (is_array(reset($term_ids))) {
                $term_ids = array_column(reset($term_ids), 'name');
            }
            $this->log_message("Assigning terms with IDs " . var_export($term_ids, true));
            foreach ($term_ids as $term_id) {

                if (is_numeric($term_id)) {
                    $this->log_message('provided Array contains Id Import URL for Terms required');
                } else {
                    $existing_term = term_exists($term_id, $taxonomy);
                    if (!$existing_term) {
                        $this->log_message('Provided Term:' . $term_id . ' doesn\'t exist. creating term');
                        wp_insert_term($term_id, $taxonomy);
                    }
                    $new_term = get_term_by('name', $term_id, $taxonomy);

                    $this->log_message('Passed Term Array ' . var_export($new_term, true));
                    wp_set_post_terms($post_id, array($new_term->term_id), $taxonomy, true);;

                }
            }

        } else {
            $this->log_message('Error Taxonomy' . $taxonomy . ' does not exist');
        }


    }

    /**
     * @param $post_id
     * @param $term_mapping
     * @param $item
     * @return void
     * @deprecated
     * @see \ImporterHelper::term_mapping()
     *
     */
    private function term_mapping($post_id, $term_mapping, $item)
    {
        if (is_array($term_mapping)) {
            foreach ($term_mapping as $term) {
                if (!empty($term['default_term']) && !empty($term['target_tax'])) {
                    if (!empty($term['source_tax']) && array_key_exists($term['source_tax'], $item)) {
                        wp_set_post_terms($post_id, $item[$term['source_tax']], $term['target_tax']);
                    } else {
                        wp_set_post_terms($post_id, $term['default_term'], $term['source_tax']);
                    }
                }

            }
        }
    }

    /**
     * @param array $arr
     * @param $key
     * @return bool
     * @see \ImporterHelper::multiKeyExists()
     *
     * @deprecated
     */
    private function multiKeyExists(array $arr, $key): bool
    {

        // is in base array?
        if (array_key_exists($key, $arr)) {
            return true;
        }

        // check arrays contained in this array
        foreach ($arr as $element) {
            if (is_array($element)) {
                if ($this->multiKeyExists($element, $key)) {
                    return true;
                }
            }

        }

        return false;
    }

    /**
     * @param $api_url
     * @param $data
     * @param $page
     * @return array|mixed
     * @deprecated this function is currently not used
     */
    public function fetch_all_pages($api_url, $data, $page = 1)
    {
        $per_page = 10; // Number of items per page
        $args = array(
            'per_page' => $per_page,
            'page' => $page,
        );

        $request_url = add_query_arg($args, $api_url);
        $response = wp_remote_get($request_url);

        if (is_wp_error($response)) {
            return $data; // Return existing data if request fails
        }

        $next = json_decode(wp_remote_retrieve_body($response), true);
        $data = array_merge($data, $next);

        // Check if there are more pages to fetch
        if (count($next) == $per_page) {
            $page++;
            $data = $this->fetch_all_pages($api_url, $data, $page); // Recursively fetch next page
        }

        return $data;
    }

    /**
     * @param $term_ids
     * @return array|mixed
     * @deprecated this function is currently not used
     *
     */
    private function sanitize_imported_post_terms($term_ids)
    {
        foreach ($term_ids as $key => $term_id) {
            if (is_array($term_id)) {
                array_walk_recursive($term_ids, function ($item, $key) use (&$results) {
                    $results[$key] = $item;
                });
                $term_ids = array_merge($term_ids, $results);
            } else {
                $term_ids[$key] = $term_id;
            }
        }
        return $term_ids;
    }

    /**
     * @param $source_term_name
     * @param $taxonomy
     * @return false|int|mixed
     * @deprecated this function is currently not used
     */
    private function check_term_if_exists_and_create($source_term_name, $taxonomy)
    {
        // Überprüfen, ob ein Term mit diesem Namen im Zielblog existiert.
        $term = get_term_by('name', $source_term_name, $taxonomy);

        // Wenn der Term existiert, gibt die ID zurück.
        if (is_a($term, 'WP_Term')) {
            return $term->term_id;
        }

        // Wenn der Term nicht existiert, erstelle einen neuen Term.
        $new_term = wp_insert_term($source_term_name, $taxonomy);

        // Überprüfen, ob die Erstellung erfolgreich war.
        if (is_wp_error($new_term)) {
            // Fehlerbehandlung, eventuell Logging.
            $this->log_message("Failed to translate term '$source_term_name' to '$taxonomy' " . $new_term->get_error_message());
            return false;
        }

        // Gibt die ID des neu erstellten Terms zurück.
        return $new_term['term_id'];
    }


}
