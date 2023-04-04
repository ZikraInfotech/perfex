<?php 

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module Name: Team Management
 * Description: A module for displaying a dashboard with all staff members.
 * Version: 2.3.0
 *Requires at least: 2.3.*
*/

require(__DIR__ . '/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$CI = &get_instance();

hooks()->add_action('admin_init', 'team_management_init_menu_items');

hooks()->add_action('app_admin_head', 'widget');


function widget()
{   
    $CI = &get_instance();
    echo '<script>';
    echo 'var base_url = "' . site_url() . '";';
    echo 'var myZone = "' . get_option('default_timezone') . '";';
    echo 'var csrf_token_name = "'.$CI->security->get_csrf_token_name().'";';
    echo 'var csrf_token = "'.$CI->security->get_csrf_hash().'";';
    echo 'var admin_url = "' . admin_url() . '";';
    echo '</script>';
    echo '<script src="' . base_url('modules/team_management/assets/js/widget.js') . '?v=1.0.1"></script>';

}

if (!$CI->db->table_exists(db_prefix() . '_staff_time_entries')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "_staff_time_entries` (
      `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `staff_id` INT(11) UNSIGNED,
      `clock_in` DATETIME,
      `clock_out` DATETIME NULL
      )
    ");
}
if (!$CI->db->table_exists(db_prefix() . '_staff_status_entries')) {

    $CI->db->query('CREATE TABLE `' . db_prefix() . "_staff_status_entries` (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id INT(11) UNSIGNED,
        status VARCHAR(100),
        start_time DATETIME,
        end_time DATETIME NULL
        );
    ");
}
if (!$CI->db->table_exists(db_prefix() . '_staff_status')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "_staff_status` (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id INT(11) UNSIGNED,
        status VARCHAR(100)
        );
  ");
}

if (!$CI->db->table_exists(db_prefix() . '_staff_shifts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "_staff_shifts` (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id INT(11) UNSIGNED,
        month INT(2) UNSIGNED,
        day INT(2) UNSIGNED,
        shift_number INT(2) UNSIGNED,
        shift_start_time TIME,
        shift_end_time TIME
        );
  ");
}


$staff_id = $CI->session->userdata('staff_user_id');

$staff_id_exists = $CI->db->select('*')
                          ->from(''.db_prefix().'_staff_status')
                          ->where('staff_id', $staff_id)
                          ->get()
                          ->num_rows();

if (!$staff_id_exists) {
    $data = array(
        'staff_id' => $staff_id,
        'status' => 'Online'
    );
    $CI->db->insert(''.db_prefix().'_staff_status', $data);
}

function team_management_init_menu_items(){
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('team_management', [
        'name'     => 'Management', // The name if the item
        'position' => 2, // The menu position, see below for default positions.
        'icon'     => 'fa fa-users', // Font awesome icon
    ]);

    $CI->app_menu->add_sidebar_children_item('team_management', [
        'slug'     => 'individual_stats', // Required ID/slug UNIQUE for the child menu
        'name'     => 'Individual Stats', // The name if the item
        'href'     => admin_url('team_management/individual_stats'), // URL of the item
        'position' => 1, // The menu position
        'icon'     => 'fa fa-user-cog', // Font awesome icon
    ]);

    $CI->app_menu->add_sidebar_children_item('team_management', [
        'slug'     => 'team_stats', // Required ID/slug UNIQUE for the child menu
        'name'     => 'Team Stats', // The name if the item
        'href'     => admin_url('team_management/team_stats'), // URL of the item
        'position' => 2, // The menu position
        'icon'     => 'fa fa-user-friends', // Font awesome icon
    ]);

    if (is_admin()) {
    
        $CI->app_menu->add_sidebar_children_item('team_management', [
            'slug'     => 'staff_shifts', // Required ID/slug UNIQUE for the child menu
            'name'     => 'Staff Shifts', // The name if the item
            'href'     => admin_url('team_management/staff_shifts'), // URL of the item
            'position' => 2, // The menu position
            'icon'     => 'fa fa-user-clock', // Font awesome icon
        ]);

    }

}



?>