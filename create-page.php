<?php
require_once '../../../wp-blog-header.php';

function create_page($title, $parent = 0, $page_template = '', $menu_order = 0, $content = '') {
    $args = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_parent' => $parent,
        'menu_order' => $menu_order,
        'post_status' => 'publish',
        'post_type' => 'page'
    );

    $post_id = wp_insert_post($args);

    if ($post_id > 0 && $page_template != '') {
        update_post_meta($post_id, '_wp_page_template', $page_template);
    }

    return $post_id;
}

echo create_page('09 - 2012', 2891, 'page-river.php');
echo create_page('09 - 2012', 2896, 'page-wing1.php');
echo create_page('09 - 2012', 2898, 'page-wing2.php');
echo create_page('09 - 2012', 2900, 'page-wing3.php');
echo create_page('09 - 2012', 2902, 'page-wing4.php');

/*
echo create_page('FTOTW Level 1 River - May - 2012', 2896, 'page-wing1.php');
echo create_page('FTOTW Level 1 River - October - 2011', 2896, 'page-wing1.php');

echo create_page('FTOTW Level 2 River - October - 2011', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - November - 2011', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - December - 2011', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - January - 2012', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - February - 2012', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - March - 2012', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - April - 2012', 2898, 'page-wing2.php');
echo create_page('FTOTW Level 2 River - May - 2012', 2898, 'page-wing2.php');

echo create_page('FTOTW Level 3 River - October - 2011', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - November - 2011', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - December - 2011', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - January - 2012', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - February - 2012', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - March - 2012', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - April - 2012', 2900, 'page-wing3.php');
echo create_page('FTOTW Level 3 River - May - 2012', 2900, 'page-wing3.php');

echo create_page('FTOTW Level 4 River - October - 2011', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - November - 2011', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - December - 2011', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - January - 2012', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - February - 2012', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - March - 2012', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - April - 2012', 2902, 'page-wing4.php');
echo create_page('FTOTW Level 4 River - May - 2012', 2902, 'page-wing4.php');

echo create_page('FTOTW River - October - 2011', 2896, 'page-river.php');
echo create_page('FTOTW River - November - 2011', 2896, 'page-river.php');
echo create_page('FTOTW River - December - 2011', 2896, 'page-river.php');
echo create_page('FTOTW River - January - 2012', 2896, 'page-river.php');
echo create_page('FTOTW River - February - 2012', 2896, 'page-river.php');
echo create_page('FTOTW River - March - 2012', 2896, 'page-river.php');
echo create_page('FTOTW River - April - 2012', 2896, 'page-river.php');
echo create_page('FTOTW River - May - 2012', 2896, 'page-river.php');
 */


function ftotw_get_month_from_title($title) {
    $parts = explode(' ', $title);
    $month = $parts[count($parts) - 3];
    return date('m', strtotime($month . '-2012')); // small hack which I got from http://stackoverflow.com/a/9941819/24949
}

function ftotw_get_year_from_title($title) {
    $parts = explode(' ', $title);
    return $parts[count($parts) - 1];
}

//echo ftotw_get_month_from_title('FTOTW Level 1 River – December – 2011');
//echo ftotw_get_year_from_title('FTOTW Level 1 River – December – 2011');
//echo ftotw_get_year_from_title('FTOTW Level 1 River – December – 2011');
//echo ftotw_get_year_from_title('FTOTW Level 1 River – December – 2011');
?>
