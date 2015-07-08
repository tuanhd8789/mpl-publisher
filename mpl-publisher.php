<?php
/**
 * Plugin Name: MPL - Publisher
 * Plugin URI: https://ferranfigueredo.com/mpl-publisher/
 * Description: MPL - Publisher is a plugin to create an ebook from your WordPress posts. You can publish your ebook like: ePub, pdf, kindle books, iPad ebook, Mobi
 * Version: 1.9.0
 * Author: Ferran Figueredo
 * Author URI: https://ferranfigueredo.com
 * License: MIT
 */

require 'vendor/autoload.php';

$controller = new \MPL\Publisher\PublisherController(__DIR__);

add_action('init', function ()
{
    load_plugin_textdomain('publisher', false, basename(dirname(__FILE__)) . '/languages');

	register_post_type('mpl_chapter', array(
        'labels' => array(
            'name'          => __('Book Chapters', 'publisher'),
            'singular_name' => __('Book Chapter', 'publisher'),
            'add_new'       => __('Add New Book Chapter', 'publisher'),
            'add_new_item' 	=> __('Add New Book Chapter', 'publisher'),
            'new_item'      => __('Add New Book Chapter', 'publisher'),
            'edit_item'     => __('Edit Book Chapter', 'publisher'),
            'view_item'     => __('View Book Chapter', 'publisher')
        ),
        'public'        => true,
        'show_in_menu'  => false,
        'supports'      => array('title', 'editor', 'author', 'revisions')
    ));
});

add_action('add_meta_boxes', function ()
{
    add_meta_box('mpl_chapter_back', "&nbsp;", function ()
    {
        echo '<p class="mpl"><a href="' . admin_url('tools.php?page=publisher') . '"><span class="dashicons dashicons-arrow-left-alt2"></span>' . __("Back to Book Settings", "publisher") . '</a></p>';
    },
    'mpl_chapter', 'side', 'high');

    add_meta_box('mpl_chapter_help', __("How book chapters works", "publisher"), function ()
    {
        echo '<p>' . __("MPL - Publisher allows authors to write custom content specific for your book without the needed to be accessible from the public. You just have to publish your chapter and it will be visible only from the Book Settings page.", "publisher") . '</p>';
    },
    'mpl_chapter', 'side', 'high');

    remove_meta_box('slugdiv', 'mpl_chapter', 'normal');
});

add_action('admin_menu', function () use ($controller)
{
    add_management_page('MPL - Publisher', 'MPL - Publisher', 'manage_options', 'publisher', function () use ($controller)
    {
        return $controller->getIndex();
    });
});

add_action('admin_post_publish_ebook', function () use ($controller)
{
	return $controller->postIndex();
});

add_action('admin_enqueue_scripts', function ()
{
	wp_enqueue_script('jquery-ui-sortable');
	wp_enqueue_media();

	wp_enqueue_script('chosen', plugin_dir_url(__FILE__) . 'assets/js/chosen.jquery.min.js');
	wp_enqueue_style('chosen', plugin_dir_url(__FILE__) . 'assets/css/chosen.min.css');

	wp_enqueue_script('bootstrap', plugin_dir_url(__FILE__) . 'assets/js/bootstrap.js');

	$own = get_plugin_data(__FILE__);

	wp_enqueue_style('mpl-publisher', plugin_dir_url(__FILE__) . 'assets/css/mpl-publisher.css?mpl=' . $own['Version']);
	wp_enqueue_script('mpl-publisher', plugin_dir_url(__FILE__) . 'assets/js/mpl-publisher.js?mpl=' . $own['Version']);
});