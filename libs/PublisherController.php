<?php

namespace MPL\Publisher;

use Illuminate\View\View;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Events\Dispatcher;
use Illuminate\View\Engines\PhpEngine;

class PublisherController {

    private $statusOptionName = 'mpl_publisher_status';

    private function view($file, $data = array())
    {
        $viewFinder = new FileViewFinder(new Filesystem, array(MPL_BASEPATH));
        $factory    = new Factory(new EngineResolver, $viewFinder, new Dispatcher);

        return new View($factory, new PhpEngine, $file, MPL_BASEPATH . "/views/" . $file, $data);
    }

    public function getIndex()
    {
        $data   = $this->getBookDefaults();
        $status = $this->getStatus();

        $filter = $_GET;

        if ($status and isset($status['data']))
        {
            $data = array_merge($data, $status['data']);

            $format = get_option('date_format') . ' ' . get_option('time_format');

            $data['message'] = sprintf(__('Last modification: %s' , "publisher"), date($format, $status['time']));

            if (!empty($data['cat_selected']))    $filter['cat']    = implode(',', $data['cat_selected']);
            if (!empty($data['author_selected'])) $filter['author'] = implode(',', $data['author_selected']);
            if (!empty($data['tag_selected']))    $filter['tag']    = implode(',', $data['tag_selected']);
        }

        $filter['post_type'] = !empty($data['post_type']) ? $data['post_type'] : array('post', 'mpl_chapter');

        $query = http_build_query(array_merge(array(
            'posts_per_page' => '-1',
            'post_status'    => 'publish,private'
        ), $filter));

        $data['query'] = new \WP_Query($query);

        $data['blog_categories'] = $this->getCategories();
        $data['blog_authors']    = $this->getAuthors();
        $data['blog_tags']       = get_tags();
        
        $data['form_action']     = admin_url('admin-post.php');
        $data['wp_nonce_field']  = wp_nonce_field('publish_ebook', '_wpnonce', true, false);

        wp_reset_postdata();

    	echo $this->view('index.php', $data);
    }

    public function postIndex()
    {
        // http://codex.wordpress.org/Function_Reference/check_admin_referer
        if (empty($_POST) || !check_admin_referer('publish_ebook', '_wpnonce')) return;

        $this->saveStatus($_POST);

        if (isset($_POST['generate'])) return $this->generateBook();

        return wp_redirect(admin_url('tools.php?page=publisher'));
    }

    private function getBookDefaults()
    {
        return array(
            'identifier'  => '',
            'title'       => get_bloginfo('site_name'),
            'description' => get_bloginfo('site_description'),
            'authors'     => wp_get_current_user()->display_name,
            'language'    => '',
            'date'        => '',
            'cover'       => false,
            'editor'      => '',
            'copyright'   => '',
            'cat_selected'    => array(),
            'author_selected' => array(),
            'tag_selected'    => array(),
            'post_type'       => array(),
            'selected_posts'  => false,
            'format'          => 'epub2'
        );
    }

    private function getCategories()
    {
        return get_categories('orderby=post_count&order=DESC');
    }

    private function getAuthors()
    {
        return get_users('orderby=post_count&order=DESC&who=authors');
    }

    private function generateBook()
    {
        $publisher = false;

        switch ($_POST['format'])
        {
            case 'epub2':
            case 'epub3':
                $publisher = new EpubPublisher($_POST['format'], MPL_BASEPATH);
                break;
            case 'markd':
                $publisher = new MarkdownPublisher();
                break;
        }

        if (!$publisher) return;

        $publisher->setIdentifier(sanitize_text_field($_POST['identifier']));
        $publisher->setTitle(sanitize_text_field($_POST['title']));
        $publisher->setAuthor(sanitize_text_field($_POST['authors']));
        $publisher->setPublisher(sanitize_text_field($_POST['editor']));
        $publisher->setDescription(sanitize_text_field($_POST['description']));
        $publisher->setDate(sanitize_text_field($_POST['date']));

        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : substr(get_locale(), 0, 2);
        $publisher->setLanguage($language);

        if (!empty($_POST['cover']) and $imageId = intval($_POST['cover']))
        {
            $publisher->setCoverImage('cover.jpg', file_get_contents(get_attached_file($imageId)));
        }

        $publisher->setRights(sanitize_text_field($_POST['copyright']));

        $query = new \WP_Query(array(
            'post__in'       => $_POST['selected_posts'],
            'orderby'        => 'post__in',
            'posts_per_page' => '-1',
            'post_status'    => 'any',
            'post_type'      => 'any'
        ));

        if ($query->have_posts())
        {
            while ($query->have_posts())
            {
                $query->the_post();

                $publisher->addChapter(get_the_ID(), get_the_title(), wpautop(get_the_content()));
            }
        }

        return $publisher->send(get_bloginfo('name') . ' - ' . wp_get_current_user()->display_name);
    }

    private function saveStatus($data)
    {
        return update_option($this->statusOptionName, array(
            'time' => current_time('timestamp'),
            'data' => $data
        ));
    }

    private function getStatus()
    {
        return get_option($this->statusOptionName);
    }
}