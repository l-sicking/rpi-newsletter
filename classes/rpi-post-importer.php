<?php

class RPIPostImporter
{

    public function rpi_import_post($api_url = '', $status_ignorelist = [], $term_mapping = [], $dryrun = false, $logging = true, $graphql = false, $graphql_body = '')
    {

        $posts = [];

        if ($graphql) {

            $this->log('Start des Graphql Importvorgangs.', $logging);


            $data = json_encode(array('query' => $graphql_body));
            $response = wp_remote_post($api_url, array(
                'body' => $data,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->log("Something went wrong: $error_message");
                return false;
            } else {
                // Handle the response
                $response_body = wp_remote_retrieve_body($response);
                $this->log("Response received");
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
                if (!empty($item['image'])) {
                    $post_data['featured_media'] = $item['image'];
                }
                $post_data['post_author'] = 1;
                $post_data['post_status'] = 'publish';
                $post_data['post_type'] = 'post';

                if (isset($item['import_id'])) {
                    $existing_post = get_posts([
                        'meta_query' => array(
                            array(
                                'key' => 'import_id',
                                'value' => $item['import_id'],
                            )
                        )
                    ]);
                    if (count($existing_post) < 0) {
                        $existing_post = reset($existing_post);
                        $this->log("Imported Post $existing_post->ID already exists updating ...");

                        $post_id = $this->create_post($post_data, true, $logging);
                    } else {
                        $post_id = $this->create_post($post_data, false, $logging);
                    }
                }

                // Execute Term Mapping or add default Term
                $this->term_mapping($post_id, $term_mapping, $post_data);

                $posts[] = $post_id;
            }


        }
//        else {
//            $this->log('Start des Importvorgangs.', $logging);
//
//            $url = sanitize_url($api_url);
//
//            // HTTP request to the API
//            $response = wp_remote_get($url);
//
//            // Check if the request was successful
//            if (is_wp_error($response)) {
//
//                $this->log($response, $logging);
//                return; // Skip to the next URL in case of an error
//            }
//
//            // Parse the JSON response
//            $posts = json_decode(wp_remote_retrieve_body($response), true);
//
//            // Fetch all pages of results
//            $news_items = $this->fetch_all_pages($url, $posts);
//
//            foreach ($news_items as $item) {
//
//                if (in_array($item['status'], $status_ignorelist)) {
//                    continue;
//                }
//
//                if (!$dryrun) {
//                    // Erstellen eines neuen Beitrags für jede Sprache.
//
//                    $post_data = array(
//                        'post_author' => 1, // oder einen dynamischen Autor
//                        'post_content' => $item['content']['rendered'],
//                        'post_date' => $item['date'],
//                        'post_title' => $item['title']['rendered'],
//                        'post_status' => 'publish',
//                        'post_type' => 'newsletter-post',
//                        'meta_input' => array(
//                            'import_link' => $item['link'],
//                            'import_id' => $item['id'], // oder eine andere eindeutige ID
//                        ),
//                        'categories' => $item['categories'],
//                        'tags' => $item['tags'],
//                        'featured_media' => $item['featured_media'],
//                    );
//
//
//                    $post_id = $this->create_post($post_data, $logging);
//
//                    // Execute Term Mapping or add default Term
//
//                    $this->term_mapping($post_id, $term_mapping, $item);
//
//                    $posts[] = $post_id;
//                }
//
//            }
//        }

        $this->log('Importvorgang abgeschlossen.', $logging);
        return $posts;


    }

    private function log($message, $log = true)
    {
        if ($log) {

            $log_file = WP_CONTENT_DIR . '/rpi_post_importer_log.txt'; // Pfad zur Log-Datei
            $timestamp = current_time('mysql');
            $entry = "{$timestamp}: {$message}\n";

            file_put_contents($log_file, $entry, FILE_APPEND);
        }
    }

    private function create_post($item, $update = false, $logging = true)
    {

        if (!empty($item['post_title']) && !empty($item['post_content'])) {
            // Annahme: $item enthält alle notwendigen Informationen

            $this->log("Trying to create Post : " . var_export($item, true), $logging);
            // Erstellen des Beitrags
            $post_id = wp_insert_post($item, true);
            if (is_wp_error($post_id)) {
                $this->log($post_id->get_error_message(), $logging);
                return false;
            }
            // Überprüfen, ob der Beitrag erfolgreich erstellt wurde.
            if ($post_id && !is_wp_error($post_id)) {

                // Medieninhalte hinzufügen


                if (!empty($item['featured_media']) && !$update) {
                    $this->log('Trying to import media: ' . var_export($item['featured_media'], true));
                    $this->import_media($item['featured_media']['url'], $post_id);
                    $this->log('Media Imported');

                }

                // Kategorien und Tags hinzufügen
                if (!empty($item['categories'])) {
                    $this->log('Trying to assign Categories Terms: ' . var_export($item['categories'], true));
                    $this->assign_terms($post_id, $item['categories'], 'category');
                    $this->log('Assignment of Categories Terms complete');
                }
                if (!empty($item['tags'])) {
                    $this->log('Trying to assign Tags Terms: ' . var_export($item['tags'], true));
                    $this->assign_terms($post_id, $item['tags'], 'post_tag');
                    $this->log('Assignment of Tags Terms complete');

                }

                $this->log("Beitrag (ID: $post_id) erstellt: '{$item['post_title']}'", $logging);

            }

            return $post_id;
        } else {
            return false;
        }

    }

    private function import_media($media_url, $post_id = 0)
    {
        if (!$media_url) {
            return null;
        }

        // Der Dateiname wird aus der URL extrahiert.
        $file_name = basename($media_url);

        $this->log(var_export($file_name, true));
        // Temporärdatei erstellen.
        $temp_file = download_url($media_url);
        $this->log(var_export($temp_file, true));


        // Dateiinformationen vorbereiten.
        $file = array(
            'name' => $file_name,
            'type' => mime_content_type($temp_file),
            'tmp_name' => $temp_file,
            'error' => 1,
            'size' => filesize($temp_file),
        );
        $this->log(var_export($file, true));


        if (!function_exists('media_handle_sideload')) {
            $this->log('function media_handle_sideload not found, using wp_insert_attachment instead');

            $attachment_id = $this->wp_insert_attachment_from_url($media_url, $post_id);

            if (!$attachment_id) {
                $this->log("Attachment of '$media_url' to Post '$post_id' failed");
                return false;

                ///TODO  check logging var
            } else {
                set_post_thumbnail($post_id, $attachment_id);
                $this->log("Added Thumbnail of '$media_url' to '$post_id', with '$attachment_id'");
            }

        } else {


            // Die Datei wird der Medienbibliothek hinzugefügt.
            $media_id = media_handle_sideload($file, $post_id);
        }


        if (is_wp_error($media_id)) {
            $this->log('Media import Failed' . $media_id->get_error_message());
            @unlink($temp_file);
            return null;
        }

        return $media_id;
    }

    function wp_insert_attachment_from_url($url, $parent_post_id = null)
    {

        if (!class_exists('WP_Http')) {
            require_once ABSPATH . WPINC . '/class-http.php';
        }

        $http = new WP_Http();
        $response = $http->request($url);
        if (200 !== $response['response']['code']) {
            return false;
        }

        $upload = wp_upload_bits(basename($url), null, $response['body']);
        if (!empty($upload['error'])) {
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
            'post_title' => $attachment_title,
            'post_content' => '',
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

    }

    private function assign_terms($post_id, $term_ids, $taxonomy)
    {
        foreach ($term_ids as $term_id) {
            // Namen des Terms aus der Quelle holen
            $term_name = get_term_by('id', $term_id, $taxonomy)->name;

            // Term-ID im Zielblog übersetzen
            $translated_term_id = $this->translate_term_id($term_name, $taxonomy);
            if ($translated_term_id) {
                wp_set_object_terms($post_id, $translated_term_id, $taxonomy, true);
            }
        }
    }

    private function translate_term_id($source_term_name, $taxonomy)
    {
        // Überprüfen, ob ein Term mit diesem Namen im Zielblog existiert.
        $term = get_term_by('name', $source_term_name, $taxonomy);

        // Wenn der Term existiert, gibt die ID zurück.
        if ($term) {
            return $term->term_id;
        }

        // Wenn der Term nicht existiert, erstelle einen neuen Term.
        $new_term = wp_insert_term($source_term_name, $taxonomy);

        // Überprüfen, ob die Erstellung erfolgreich war.
        if (is_wp_error($new_term)) {
            // Fehlerbehandlung, eventuell Logging.
            return null;
        }

        // Gibt die ID des neu erstellten Terms zurück.
        return $new_term['term_id'];
    }

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


}
