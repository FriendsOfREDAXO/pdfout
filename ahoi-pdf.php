<?php
/*
Plugin Name: ahoi PDF
Description: Integrates the PDF generator Dompdf
Author: studio ahoi
Author URI: https://studio-ahoi.de
Text Domain: ahoi-pdf
Domain Path: /languages/
Version: 1.0.0
*/

use ahoi\Pdf\Generator;

require_once __DIR__.'/vendor/autoload.php';
require_once ABSPATH.'wp-admin/includes/file.php';

add_action('plugins_loaded', function () {
    global $wp_filesystem;

    $font_path = WP_CONTENT_DIR.'/cache/ahoi-pdf/fonts';

    $credentials = request_filesystem_credentials(wp_nonce_url('plugins.php'), '', false, false);
    if (WP_Filesystem($credentials) && !$wp_filesystem->is_dir($font_path)) {
        $wp_filesystem->mkdir($font_path);
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $control = '';#'rias-hessen.de';
    $action = $_REQUEST['pdf_action'] ?? '';

    if (!in_array($action, ['print', /*'save', 'html'*/])) {
        return;
    }
    if (!str_contains($referer, $control)) {
        return;
    }

    ob_clean();
    ob_start();

    switch ($action) {
        case 'save':
            add_action('shutdown', function() {
                $html = apply_filters('ahoi_pdf_get_html', ob_get_flush());

                $generator = new Generator();
                $generator
                    ->setPath(WP_CONTENT_DIR.'/uploads/ahoi-pdf/')
                    ->setHtml($html)
                    ->save();
                }, 0);
            break;

        case 'print':
            add_action('send_headers', function () {
                header('Content-Type: application/pdf');
            });
            add_action('shutdown', function() {
                $html = apply_filters('ahoi_pdf_get_html', ob_get_flush());

                $generator = new Generator();
                $generator->setHtml($html)->send();
            }, 0);
            break;

        case 'html':
            add_action('shutdown', function() {
                $html = apply_filters('ahoi_pdf_get_html', ob_get_clean());

                $generator = new Generator();
                $generator
                    ->setPath(WP_CONTENT_DIR.'/uploads/ahoi-pdf/')
                    ->setHtml($html)
                    ->save();

                dump($generator->getDompdf());
                dump($generator->getDompdf()->outputHtml());
            }, 0);
            break;

        default:
            // void
    }
});
