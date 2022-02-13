<?php


class iAmirAPIWCFile
{

    var $bid;
    var $url;
    var $title;
    var $description;
    var $post_id;

    public static $meta_key_file_id = 'i_amir_remote_wc_file_id';
    public static $base_url = 'https://cdn.dsmcdn.com';

    public static function find($id) {
        global $wpdb;
        $result = $wpdb->get_results("select post_id from {$wpdb->prefix}postmeta where meta_key = '".static::$meta_key_file_id."' and meta_value = '$id'");
        return count($result) && isset($result[0]->post_id) ? $result[0]->post_id : null;
    }

    function __construct($bid, $url, $title = null, $description = null, $post_id = null)
    {
        $this->bid = $bid;
        $this->url = static::$base_url . $url;
        $this->title = $title;
        $this->description = i_amir_remote_wc_clear_text($description);
        $this->post_id = $post_id;
        global $wp_version;

        if ($wp_version < 3.5) {
            if (basename($_SERVER['PHP_SELF']) != "media-upload.php") return;
        } else {
            if (basename($_SERVER['PHP_SELF']) != "media-upload.php" && basename($_SERVER['PHP_SELF']) != "post.php" && basename($_SERVER['PHP_SELF']) != "post-new.php") return;
        }
    }

    function fetch_image($url)
    {
        if (function_exists("curl_init")) {
            return $this->curl_fetch_image($url);
        } elseif (ini_get("allow_url_fopen")) {
            return $this->fopen_fetch_image($url);
        }
    }

    function curl_fetch_image($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $image = curl_exec($ch);
        curl_close($ch);
        return $image;
    }

    function fopen_fetch_image($url)
    {
        $image = file_get_contents($url, false);
        return $image;
    }

    function media_process()
    {

        if ($this->url) {
            $imageurl = $this->url;
            $imageurl = stripslashes($imageurl);
            $uploads = wp_upload_dir();

            $post_id = $this->post_id ? (int)$this->post_id : 0;

            $newfilename = basename($imageurl);

            $filename = wp_unique_filename($uploads['path'], $newfilename, $unique_filename_callback = null);
            $wp_filetype = wp_check_filetype($filename, null);
            $fullpathfilename = $uploads['path'] . "/" . $filename;

            try {
                if (!substr_count($wp_filetype['type'], "image")) {
                    return (object)['status' => false, 'message' => basename($imageurl) . ' is not a valid image. ' . $wp_filetype['type'] . ''];
                }

                $image_string = $this->fetch_image($imageurl);
                $fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $image_string);
                if (!$fileSaved) {
                    return (object)['status' => false, 'message' => "The file cannot be saved."];
                }

                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => $this->title ?: preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content' => $this->description ?: '',
                    'post_status' => 'inherit',
                    'guid' => $uploads['url'] . "/" . $filename
                );
                $attach_id = wp_insert_attachment($attachment, $fullpathfilename, $post_id);
                if (!$attach_id) {
                    return (object) ['status' => false, 'message' => "Failed to save record into database."];
                }
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $fullpathfilename);
                wp_update_attachment_metadata($attach_id, $attach_data);
                update_post_meta($attach_id, static::$meta_key_file_id, $this->bid);

            } catch (Exception $e) {
                $error = $e->getMessage();
            }

        }

        if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
            return (object)['status' => false, 'message' => 'cURL or allow_url_fopen needs to be enabled. Please consult your server Administrator.'];
        } elseif ($error) {
            return (object)['status' => false, 'message' => $error];
        } else if ($fileSaved && $attach_id) {
            return (object)['status' => true, 'message' => 'File saved.', 'attach_id' => $attach_id];
        }

    }
}

?>