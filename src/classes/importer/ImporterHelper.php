<?php
namespace RPI_ls_Newsletter\classes\importer;



//require_once plugin_dir_path(__FILE__) . 'src/traits/Rpi_ls_Logging.php';

use Rpi_ls_Newsletter\traits\Rpi_ls_Logging;


class ImporterHelper{

 use  Rpi_ls_Logging;


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

    public function create_post($item, $update = false): WP_Error|bool|int
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
                    try {
                        $attachment_id[] = $this->wp_insert_attachment_from_url($url, $post_id, $wp_json) ;
                    } catch (Exception $e) {
                        $this->log_message('An Error has occurred while importing media: ' . $e->getMessage());
                        continue;
                    }
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

            if (is_wp_error($attach_id)) {
                $this->log_message($attach_id->get_error_message());

                throw new Exception($attach_id->get_error_message());
            }
            else{
                $this->log_message('Created Attachment with ID'. var_export($attach_id, true));

            }
            // Include image.php.
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Generate the attachment metadata.
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

            $this->log_message('Generated attachment meta data'. var_export($attach_data, true));

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

    public function term_mapping($post_id, $term_mapping, $item)
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

    public function multiKeyExists(array $arr, $key): bool
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

}