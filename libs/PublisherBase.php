<?php

namespace MPL\Publisher;

use WP_Http;
use WP_Query;
use Exception;
use simplehtmldom\HtmlDocument;
use Intervention\Image\ImageManagerStatic;
use Intervention\Image\Exception\NotReadableException;

class PublisherBase {

    public $data;

    public $filter;

    public static $allowed_tags = array(
        // Desktop version
        'p' => array(
            'id' => array(),
            'class' => array(),
        ),
        'div' => array(
            'id' => array(),
            'class' => array()
        ),
        'span' => array(
            'id' => array(),
            'class' => array(),
        ),
        // Headings
        'h1' => array(
            'id' => array(),
        ),
        'h2' => array(
            'id' => array(),
        ),
        'h3' => array(
            'id' => array(),
        ),
        'h4' => array(
            'id' => array(),
        ),
        'h5' => array(
            'id' => array(),
        ),
        'h6' => array(
            'id' => array(),
        ),
        // Global
        'a' => array(
            'href' => array(),
            'class' => array(),
            'title' => array(),
        ),
        'img' => array(
            'src' => array(),
            'class' => array(),
            'alt' => array(),
            // 'width' => array(), // Fix MS Word
            // 'height' => array() // Fix MS Word
        ),
        'blockquote' => array(),
        'q' => array(),
        'cite' => array(),
        'hr' => array(),
        'br' => array(),
        'figure' => array(
            'class' => array()
        ),
        'figcaption' => array(),
        // Styles
        'u' => array(),
        'i' => array(),
        'b' => array(),
        'em' => array(),
        'small' => array(),
        'strong' => array(),
        'strike' => array(),
        // Lists
        'ul' => array(),
        'ol' => array(
            'start' => array(),
        ),
        'li' => array(),
        // Tables
        'table' => array(),
        'tbody' => array(),
        'thead' => array(),
        'tfoot' => array(),
        'tr' => array(),
        'td' => array(),
        'th' => array()
    );

    public function __construct()
    {
        $this->filter = mpl_sanitize_array($_GET);
        $this->data = array_merge($this->getPluginDefaults(), $this->getBookDefaults());

        list('time' => $time, 'data' => $data) = $this->getStatus($this->data['book_id']);

        if ($time and $data)
        {
            $this->data = array_merge($this->data, $data);

            $format = get_option('date_format') . ' ' . get_option('time_format');

            $this->data['message'] = sprintf(__('Submitted on %s' , "publisher"), date($format, $time));

            $cover_src = wp_get_attachment_image_src($this->data['cover'], 'full');

            if (is_array($cover_src) and count($cover_src) == 4 and array_key_exists(0, $cover_src))
            {
                $this->data['cover_src'] = $cover_src[0];
            }

            if ( ! empty($this->data['cat_selected']))    $this->filter['cat']         = implode(',', $this->data['cat_selected']);
            if ( ! empty($this->data['author_selected'])) $this->filter['author']      = implode(',', $this->data['author_selected']);
            if ( ! empty($this->data['tag_selected']))    $this->filter['tag']         = implode(',', $this->data['tag_selected']);
            if ( ! empty($this->data['status_selected'])) $this->filter['post_status'] = implode(',', $this->data['status_selected']);
            if ( ! empty($this->data['year_selected']))   $this->filter['year']        = implode(',', $this->data['year_selected']);
            if ( ! empty($this->data['month_selected']))  $this->filter['month']       = implode(',', $this->data['month_selected']);
        }

        if ( ! empty($this->data['post_type']) and is_array($this->data['post_type']))
        {
            // If the selected post type has been disabled, we reset the filters
            $array_diff = array_diff($this->data['post_type'], mpl_all_post_types());

            $this->filter['post_type'] = count($array_diff) ? mpl_all_post_types() : $this->data['post_type'];
        }
        else
        {
            $this->filter['post_type'] = mpl_all_post_types();
        }
    }

    public function view($file, $data = array())
    {
        $template = new TemplateEngine(MPL_BASEPATH . "/views/", []);

        return $template->render($file, $data);
    }

    public function getPluginDefaults()
    {
        $all_books = $this->getAllBooks();
        // If no param, we select the first book as default
        $book_id = array_key_exists('book_id', $_GET) ?
            sanitize_text_field($_GET['book_id']) :
            key($all_books);

        return array(
            'license'         => mpl_premium_license(),
            'max_posts'       => mpl_max_posts(),
            'book_id'         => $book_id,
            'all_books'       => $all_books,
            'mpl_is_premium'  => mpl_is_premium(),
            'admin_notice'    => get_transient('mpl_msg'),
            'marketplace_url' => MPL_MARKETPLACE . '?' . http_build_query([
                'is_premium'  => mpl_is_premium() ? 'true' : 'false',
                'locale'      => get_locale(),
                'utm_medium'  => 'plugin',
            ])
        );
    }

    public function getBookDefaults()
    {
        return array(
            'identifier'  => '',
            'title'       => get_bloginfo('site_name'),
            'subtitle'    => '',
            'description' => get_bloginfo('site_description'),
            'authors'     => wp_get_current_user()->display_name,
            'language'    => get_locale(),
            'date'        => '',
            'cover'       => false,
            'cover_src'   => false,
            'editor'      => '',
            'copyright'   => '',
            'landing_url' => false,
            'amazon_url'  => false,
            'ibooks_url'  => false,
            'root_url'    => false,
            'theme_id'    => 0,
            'custom_css'  => '',
            'author_load' => 'default',
            'thumbnail_load'  => 'default',
            'images_load'     => 'insert',
            'voice_name'      => '',
            'cat_selected'    => array(),
            'author_selected' => array(),
            'tag_selected'    => array(),
            'status_selected' => array(),
            'post_type'       => array(),
            'year_selected'   => array(),
            'month_selected'  => array(),
            'selected_posts'  => false,
            'order_asc'       => true,
            'validate_html'   => false,
            'format'          => 'epub2'
        );
    }

    public function getDefaultThemes()
    {
        return array(
            array(
                'id'    => 'default',
                'name'  => __('Lato & Merriweather', 'publisher'),
                'image' => MPL_BASEURL . 'assets/imgs/theme-default.png',
                'style' => MPL_BASEPATH . '/assets/css/theme-default.css',
                'fonts' => array(
                    'merriweather-regular' => MPL_BASEPATH . '/assets/fonts/merriweather-regular.ttf',
                    'merriweather-bold'    => MPL_BASEPATH . '/assets/fonts/merriweather-bold.ttf',
                    'merriweather-italic'  => MPL_BASEPATH . '/assets/fonts/merriweather-italic.ttf',
                    'lato-bold'            => MPL_BASEPATH . '/assets/fonts/lato-bold.ttf'
                )
            ),
            array(
                'id'    => 'crimson',
                'name'  => __('Montserrat & Crimson', 'publisher'),
                'image' => MPL_BASEURL . 'assets/imgs/theme-crimson.png',
                'style' => MPL_BASEPATH . '/assets/css/theme-crimson.css',
                'fonts' => array(
                    'crimson-regular' => MPL_BASEPATH . '/assets/fonts/crimson-regular.ttf',
                    'crimson-bold'    => MPL_BASEPATH . '/assets/fonts/crimson-bold.ttf',
                    'crimson-italic'  => MPL_BASEPATH . '/assets/fonts/crimson-italic.ttf',
                    'montserrat-bold' => MPL_BASEPATH . '/assets/fonts/montserrat-bold.ttf'
                )
            ),
            array(
                'id'    => 'premium-romance',
                'name'  => __('Romance ⭐', 'publisher'),
                'image' => MPL_BASEURL . 'assets/imgs/theme-romance.png',
                'style' => MPL_BASEPATH . '/assets/css/theme-romance.css',
                'fonts' => array(
                    'sourcesans-regular' => MPL_BASEPATH . '/assets/fonts/sourcesans-regular.ttf',
                    'sourcesans-bold'    => MPL_BASEPATH . '/assets/fonts/sourcesans-bold.ttf',
                    'sourcesans-italic'  => MPL_BASEPATH . '/assets/fonts/sourcesans-italic.ttf',
                    'playfair-bold'      => MPL_BASEPATH . '/assets/fonts/playfair-bold.ttf'
                )
            ),
            array(
                'id'    => 'premium-future',
                'name'  => __('Future ⭐', 'publisher'),
                'image' => MPL_BASEURL . 'assets/imgs/theme-future.png',
                'style' => MPL_BASEPATH . '/assets/css/theme-future.css',
                'fonts' => array(
                    'poppins-regular' => MPL_BASEPATH . '/assets/fonts/poppins-regular.ttf',
                    'poppins-bold'    => MPL_BASEPATH . '/assets/fonts/poppins-bold.ttf',
                    'poppins-italic'  => MPL_BASEPATH . '/assets/fonts/poppins-italic.ttf',
                    'orbitron-bold'   => MPL_BASEPATH . '/assets/fonts/orbitron-bold.ttf'
                )
            ),
        );
    }

    public function getCategories()
    {
        return get_categories('orderby=post_count&order=DESC');
    }

    public function getStatuses()
    {
        return get_post_stati();
    }

    public function getMonths()
    {
        return [
            1 => __('January', 'publisher'),
            2 => __('February', 'publisher'),
            3 => __('March', 'publisher'),
            4 => __('April', 'publisher'),
            5 => __('May', 'publisher'),
            6 => __('June', 'publisher'),
            7 => __('July', 'publisher'),
            8 => __('August', 'publisher'),
            9 => __('September', 'publisher'),
            10 => __('October', 'publisher'),
            11 => __('November', 'publisher'),
            12 => __('December', 'publisher')
        ];
    }

    public function getYears()
    {
        $years = [];
        $post_types = array_key_exists('post_type', $this->filter) ? $this->filter['post_type'] : mpl_all_post_types();

        foreach ($post_types as $post_type)
        {
            $archive = wp_get_archives(array(
                'type' => 'yearly',
                'echo' => false,
                'format' => 'custom',
                'before' => '',
                'after' => '%',
                'post_type' => $post_type
            ));

            $stripped = preg_replace('/\s+/', '', strip_tags($archive));

            $years = array_merge($years, explode('%', $stripped));
        }

        return array_unique(array_filter($years));
    }

    public function getQuery($order_asc = true, $selected_posts = array())
    {
        if (array_key_exists('month', $this->filter))
        {
            $this->filter['date_query'] = ['relation' => 'OR'];

            array_map(function($key)
            {
                array_push($this->filter['date_query'], ['month' => $key]);

            }, explode(',', $this->filter['month']));
        }

        $posts_query = new WP_Query(array(
            'ignore_sticky_posts' => 1,
            'post__in'       => $selected_posts,
            'orderby'        => 'post__in',
            'posts_per_page' => '-1',
            'post_status'    => 'any',
            'post_type'      => 'any'
        ));

        $search_query = new WP_Query(array_merge(array(
            'post__not_in' => $selected_posts,
            'posts_per_page' => mpl_max_posts() - $posts_query->post_count,
            'order' => $order_asc ? 'ASC' : 'DESC'
        ), $this->filter));

        $wp_query = new WP_Query();
        $wp_query->posts = array_merge($posts_query->posts, $search_query->posts);
        $wp_query->post_count = $posts_query->post_count + $search_query->post_count;
        $wp_query->found_posts = $posts_query->found_posts + $search_query->found_posts;

        return $wp_query;
    }

    public function generateBook($book_id = false)
    {
        if (is_string($book_id))
        {
            list('time' => $time, 'data' => $data) = $this->getStatus($book_id);
        }
        else
        {
            $data = mpl_sanitize_array($_POST);
        }

        $data = stripslashes_deep($data);
        $data = apply_filters('mpl_publisher_generate_book', $data);

        $publisher = false;

        $language = isset($data['language']) ? mpl_xml_entities($data['language']) : get_locale();
        $language = substr($language, 0, 2);

        switch ($data['format'])
        {
            case 'audio':
                $publisher = new AudiobookPublisher();
                $publisher->setEmail(wp_get_current_user()->user_email);
                $publisher->setTmpPath(get_temp_dir());

                if (mpl_is_premium() and array_key_exists('voice_name', $data))
                {
                    $publisher->setVoiceName($data['voice_name']);
                }
            break;
            case 'epub2':
            case 'epub3':
                $publisher = new EpubPublisher();
                $publisher->setFormat($data['format'], $language, is_rtl() ? 'rtl' : 'ltr');
            break;
            case 'flipbook':
                $publisher = new FlipbookPublisher();
                $publisher->setEmail(wp_get_current_user()->user_email);
                $publisher->setTmpPath(get_temp_dir());
            break;
            case 'online':
                $publisher = new OnlinePublisher();
                $publisher->setEmail(wp_get_current_user()->user_email);
                $publisher->setTmpPath(get_temp_dir());
            break;
            case 'markd':
                $publisher = new MarkdownPublisher();
            break;
            case 'plain':
                $publisher = new PlainPublisher();
            break;
            case 'print':
                $publisher = new PrintPublisher();
                $publisher->setEmail(wp_get_current_user()->user_email);
                $publisher->setTmpPath(get_temp_dir());
            break;
            case 'quick':
                $publisher = new QuickPublisher();
                $publisher->setEmail(wp_get_current_user()->user_email);
                $publisher->setTmpPath(get_temp_dir());
            break;
            case 'wdocx':
                $publisher = new WordPublisher();
                $publisher->setTmpPath(get_temp_dir());
            break;
        }

        if ( ! $publisher) throw new Exception('⚠️ ' . __('No valid output format selected.', 'publisher'));

        $publisher->setIdentifier(mpl_xml_entities($data['identifier']));
        $publisher->setTitle(mpl_xml_entities($data['title']));
        $publisher->setSubtitle(mpl_xml_entities($data['subtitle']));
        $publisher->setAuthor(mpl_xml_entities($data['authors']));
        $publisher->setPublisher(mpl_xml_entities($data['editor']));
        $publisher->setDescription(mpl_xml_entities($data['description']));
        $publisher->setDate(mpl_xml_entities($data['date']));
        $publisher->setLanguage($language);

        if ( ! empty($data['cover']) and $imageId = intval($data['cover']))
        {
            $publisher->setCoverImage('cover.jpg', file_get_contents(get_attached_file($imageId)));
        }

        $publisher->setRights(mpl_xml_entities($data['copyright']));
        $publisher->setTheme(
            $this->getTheme(sanitize_text_field($data['theme_id'])),
            sanitize_text_field($data['custom_css'])
        );

        $query = new WP_Query(array(
            'ignore_sticky_posts' => 1,
            'post__in'       => isset($data['selected_posts']) ? $data['selected_posts'] : [0],
            'orderby'        => 'post__in',
            'posts_per_page' => '-1',
            'post_status'    => 'any',
            'post_type'      => 'any',
            'no_found_rows'  => true
        ));

        if ($query->have_posts())
        {
            $chapter = 1;

            while ($query->have_posts()): $query->the_post();
                $post = get_post(get_the_ID());
                $content = $post->post_content;
                $image = null;

                // @see https://developer.wordpress.org/reference/hooks/the_content/
                $content = apply_filters('the_content', $content);

                // Cleans tags, spaces, comments, attributes...
                $content = $this->parseText($content);
                $content = $this->parseLinks($publisher, $content, $post, (array_key_exists('root_url', $data) ? $data['root_url'] : false));

                // Include author, date info before chapter title
                if (array_key_exists('author_load', $data) and $data['author_load'] != 'default')
                {
                    $author_load = [];

                    if (in_array($data['author_load'], ['author', 'both'])) $author_load[] = get_the_author_meta('display_name', $post->post_author);
                    if (in_array($data['author_load'], ['date', 'both']))   $author_load[] = get_the_date('', $post);

                    $content = "<p>" . implode(' — ', $author_load) . "</p>{$content}";
                }

                // Embed featured image before chapter title
                if (array_key_exists('thumbnail_load', $data) and $data['thumbnail_load'] == 'before')
                {
                    $post_thumbnail_id = get_post_thumbnail_id($post);

                    if ($post_thumbnail_id)
                    {
                        $image_data = wp_get_attachment_image_src($post_thumbnail_id, 'full');

                        if (is_array($image_data)) $image = $image_data[0];
                    }
                }

                // Embeds images as base64 into the book content
                list($publisher, $content) = $this->parseImages($publisher, $content, $data['images_load']);

                if (array_key_exists('validate_html', $data))
                {
                    $html_errors = HtmlValidator::validate($content, $language);

                    if ($html_errors)
                    {
                        $validation_notice = [
                            '_VALIDATION_ERROR_',
                            __('On chapter', 'publisher') . ": ",
                            "{$post->post_title}\n",
                            $html_errors
                        ];

                        throw new Exception(implode('', $validation_notice));
                    }
                }

                // Replace amperstand
                $content = str_replace(' & ', " &amp; ", $content);

                $publisher->addChapter($chapter, mpl_xml_entities($post->post_title), $content, $image);

                $chapter++;
            endwhile;
        }
        else
        {
            throw new Exception('⚠️ ' . __('Please, select at least one chapter to publish your book.', 'publisher'));
        }

        return $publisher->send(mpl_xml_entities($data['title']));
    }

    public function saveLicense($license)
    {
        if (is_string($license)) return update_option(MPL_OPTION_LICENSE, $license);

        return false;
    }

    public function saveMaxPosts($max_posts)
    {
        if ( ! mpl_is_premium()) return false;

        if (is_numeric($max_posts)) return update_option(MPL_OPTION_MAX_POSTS, $max_posts);

        return false;
    }

    public function saveStatus($data, $book_id)
    {
        $all_books = $this->getAllBooks();

        $all_books[$book_id] = array(
            'time' => current_time('timestamp'),
            'data' => apply_filters('mpl_publisher_save_status', $data)
        );

        return update_option(MPL_OPTION_NAME, $all_books);
    }

    public function getStatus($book_id)
    {
        $all_books = $this->getAllBooks();
        $status = [
            'time' => current_time('timestamp'),
            'data' => $this->getBookDefaults()
        ];

        if (array_key_exists($book_id, $all_books))
        {
            $status = array_merge($status, $all_books[$book_id]);
        }

        return apply_filters('mpl_publisher_get_status', $status);
    }

    public function removeStatus($book_id)
    {
        $all_books = $this->getAllBooks();

        if (array_key_exists($book_id, $all_books))
        {
            unset($all_books[$book_id]);

            update_option(MPL_OPTION_NAME, $all_books);
        }

        return true;
    }

    public function getAllBooks()
    {
        $all_books = get_option(MPL_OPTION_NAME, null);

        // If first time, creates a dummy entry
        if (is_null($all_books))
        {
            $all_books = array(
                uniqid() => array(
                    'time' => current_time('timestamp'),
                    'data' => $this->getBookDefaults()
                )
            );

            update_option(MPL_OPTION_NAME, $all_books);
        }
        // Updates v1 format to v2
        else if (array_key_exists('time', $all_books) and array_key_exists('data', $all_books))
        {
            if (isset($all_books['data']['license']))
            {
                update_option(MPL_OPTION_LICENSE, $all_books['data']['license']);
            }

            $all_books = array(
                uniqid() => $all_books
            );

            update_option(MPL_OPTION_NAME, $all_books);
        }

        return apply_filters('mpl_publisher_all_books', $all_books);
    }

    public function getThemes()
    {
        return apply_filters('mpl_publisher_get_themes', $this->getDefaultThemes());
    }

    public function getTheme($themeId)
    {
        $themes = $this->getThemes();

        if (array_key_exists($themeId, $themes)) return $themes[$themeId];

        return reset($themes);
    }

    private function parseText($content)
    {
        $content = str_replace(']]>', ']]&gt;', $content);
        // TODO: Find a way to remove all shortcodes (only for DIVI)
        $content = preg_replace('/\[\/?et_pb.*?\]/', '', $content);
        // Remove HTML comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);
        // Remove inline style, script and noscript inline tags
        $content = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $content);
        $content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $content);
        $content = preg_replace('#<noscript(.*?)>(.*?)</noscript>#is', '', $content);
        // Remove properties from allowed HTML tags (except <p>)
        $content = wp_kses($content, self::$allowed_tags);
        // Remove unnecesary spaces
        $content = str_replace('&nbsp;', ' ', $content);
        $content = str_replace(' ', ' ', $content); // &nbsp;
        // Remove new lines: https://stackoverflow.com/a/3760830
        $content = preg_replace('/\s+/', ' ', $content);
        // Convierte múltiples espacios en uno solo (por error o caracteres eliminados)
        $content = preg_replace('!\s+!', ' ', $content);
        // Relative images load from https by default
        $content = str_replace("src=\"//", "src=\"https://", $content);
        // Close img tags: https://forums.phpfreaks.com/topic/163409-regex-or-preg_replace-to-close-img-tags
        $content = preg_replace('/(<img[^>]+)(?<!\/)>/' , '$1 />', $content);
        $content = preg_replace('/<(hr|br)>/', '<$1 />', $content);

        return $this->fixHtmlDocument($content);
    }

    private function parseLinks($publisher, $content, $post, $root_url = null)
    {
        $content = new HtmlDocument($content);

        foreach ($content->find('a') as $a)
        {
            // Fix relative URLs to absolute (fix mpl-publisher.com links on PDF)
            if (Str::startsWith($a->href, '/') or ! $a->href)
            {
                $a->href = WP_Http::make_absolute_url($a->href, get_bloginfo('url'));
            }

            $permalink = rtrim(get_permalink($post), '/') . '/';

            // Check if a href is a post URL and convert it to a relative URL
            if ($publisher instanceof EpubPublisher and Str::startsWith($a->href, "{$permalink}#"))
            {
                $a->href = str_replace($permalink, '', $a->href);
            }

            // URL Substitution for external links
            if ($root_url)
            {
                $a->href = str_replace(get_bloginfo('url'), $root_url, $a->href);
            }
        }

        return $this->fixHtmlDocument((string) $content);
    }

    private function parseImages($publisher, $content, $images_load = 'default')
    {
        if ( ! extension_loaded('iconv') or ! extension_loaded('fileinfo'))
        {
            return array($publisher, $content);
        }

        $content = new HtmlDocument($content);

        foreach ($content->find('img') as $img)
        {
            // "remove" will remove the image from the content
            if ($images_load == 'remove')
            {
                $img->remove();

                continue;
            }

            $file_id = "image_" . time() . '_' . rand();

            // Always: Make sure alt is not empty
            if ( ! $img->alt) $img->alt = $file_id;

            // Always: Remove the "-{width}x{height}." part from the image src to get original image
            $img->src = preg_replace('/-\d+x\d+\./', '.', $img->src);

            // Always: Convert relative image URLs to absolute
            $img->src = WP_Http::make_absolute_url($img->src, get_bloginfo('url'));

            // Override: PremiumPublisher will override "insert" with "default" (load from original URL)
            if ($publisher instanceof PremiumPublisher and $images_load == 'insert')
            {
                $images_load = 'default';
            }

            // Handling: "default": If images are loaded from original and nothing to do, continue
            if ($images_load == 'default') continue;

            try
            {
                $image = ImageManagerStatic::make($img->src);
            }
            catch (NotReadableException $e)
            {
                continue;
            }

            // Override: If not "default" WordPublisher will override always with "embed"
            // Fixes https://wordpress.org/support/topic/could-not-load-image-when-exporting-docx/
            if ($publisher instanceof WordPublisher) $images_load = 'embed';

            // Handling: "embed" will resize and update original image src
            if ($images_load == 'embed')
            {
                if ($image->width() > 640) $image->resize(640, null, function ($constraint)
                {
                    $constraint->aspectRatio();
                });

                $img->src = $image->encode('data-url');
            }

            // Handling: "insert" will update original image src + add file into the ouput
            if ($images_load == 'insert')
            {
                $img->src = "{$file_id}.jpg";

                $publisher->addFile($file_id, "{$file_id}.jpg", $image->encode('jpg'), 'image/jpg');
            }
        }

        return array($publisher, $this->fixHtmlDocument((string) $content));
    }

    private function fixHtmlDocument($content)
    {
        $content = str_replace('<', '&lt;', html_entity_decode($content));

        foreach (self::$allowed_tags as $tag => $attributes)
        {
            $content = str_replace(
                ['&lt;' . $tag, '&lt;/' . $tag],
                ['<' . $tag, '</' . $tag],
                $content
            );
        }

        return $content;
    }

    public static function getContentStats($content)
    {
        $words = str_word_count(strip_tags($content));
        $read_time = ceil($words / 200);

        return "{$words} words • {$read_time} min read";
    }

}