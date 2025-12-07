<?php
/**
 * Template Name: Elementor Canvas
 * 
 * A blank template for Elementor to provide a full-width, header-free, and footer-free layout.
 * 
 * @package maaleen
 */

if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
}