<?php defined('BASEPATH') or exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Team_management extends AdminController {

    public function __construct() {
        parent::__construct();
        $this->load->model('team_management_model');
        $this->load->model('staff_model');
    }

    public function index() {
        $this->individual_stats();
    }

    public function individual_stats()
    {

        $data['staff_members'] = $this->team_management_model->get_all_staff();
        //$data['timers'] = $this->team_management_model->get_all_timers();

        $this->load->view('individual_stats', $data);
    }

    public function team_stats()
    {
        $data['staff_members'] = $this->team_management_model->get_all_staff();
        $data['timers'] = $this->team_management_model->get_all_timers();

        $this->load->view('team_stats', $data);
    }

    public function test_query()
    {
        echo $now = date('Y-m-d H:i:s');
        echo '<br>';

        // Clock out the staff member by updating the latest open session
        //$this->db->set('clock_out', $now);
        //$this->db->where('staff_id', 19);
        //$this->db->where('clock_out IS NULL', null, false);
        //$this->db->update(db_prefix().'_staff_time_entries');

        //return $this->db->affected_rows() > 0;

        echo $this->team_management_model->test_query("delete FROM `tbl_staff_time_entries`");
        echo $this->team_management_model->test_query("delete FROM `tbl_staff_status_entries`");
    }

    public function staff_shifts() {
        //if (!is_admin()) {
        //    access_denied('You do not have permission to access this page.');
        //}
        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        $this->load->view('staff_shifts', $data);
    }

    public function activity_log($staffId, $month)
    {

        $data['activities'] = $this->team_management_model->get_user_activities($staffId, $month);
        $data['staff'] = $this->team_management_model->id_to_name($staffId, 'tblstaff', 'staffid', 'firstname');

        $this->load->view('activity_log', $data);
    }

    public function save_shift_timings() {

        //if (!is_admin()) {
        //    access_denied('Your custom permission message');
        //}

        $staff_id = $this->input->post('staff_id');
        $month = $this->input->post('month');

        $shifts = [];

        for ($i=1; $i <= 31; $i++) { 
            $shifts[$i][1]['start'] = $this->input->post('start_shift1_day_'.$i);
            $shifts[$i][1]['end'] = $this->input->post('end_shift1_day_'.$i);

            $shifts[$i][2]['start'] = $this->input->post('start_shift2_day_'.$i);
            $shifts[$i][2]['end'] = $this->input->post('end_shift2_day_'.$i);
        }


        $result = $this->team_management_model->save_shift_timings($staff_id, $month, $shifts);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function get_shift_timings($staff_id, $month) {

        $shifts = $this->team_management_model->get_shift_timings($staff_id, $month);

        echo json_encode($shifts);
    }
    
    public function get_shift_status() {
        $staff_id = $this->session->userdata('staff_user_id');

        $shift_info = $this->team_management_model->get_shifts_info($staff_id);

        if ($shift_info) {

            $current_timezone = new DateTimeZone('Asia/Kolkata');
            $current_time_out = new DateTime('now', $current_timezone);
            $current_time_str = $current_time_out->format('Y-m-d H:i:s');
            $current_time = strtotime($current_time_str);

            $current_month = $current_time_out->format('m');
            $current_day = $current_time_out->format('d');
            $current_year = $current_time_out->format('Y');

            $shift_start_time = new DateTime($current_year . '-' .$current_month . '-' . $current_day . ' ' . $shift_info->shift_start_time);
            $shift_end_time = new DateTime($current_year . '-' .$current_month . '-' . $current_day . ' ' . $shift_info->shift_end_time);

            //$shift_start_time = strtotime($shift_info->shift_start_time);
            //$shift_end_time = strtotime($shift_info->shift_end_time);

            $shift_start_time = $shift_start_time->getTimestamp();
            $shift_end_time = $shift_end_time->getTimestamp();


            $shift_info->shift_start_time = $shift_start_time;
            $shift_info->shift_end_time = $shift_end_time;

            $shift_info->current_time = $current_time;

            if ($current_time >= $shift_start_time && $current_time <= $shift_end_time) {
                $shift_info->status = 0;
                $shift_info->statusText = 'Shift Time Ongoing:';
                $shift_info->time_left = $this->convertSecondsToRoundedTime($shift_end_time - $current_time);
            } else if ($current_time < $shift_start_time) {
                $shift_info->status = 1;
                $shift_info->statusText = 'Upcoming shift in:';
                $shift_info->time_left = $this->convertSecondsToRoundedTime($shift_start_time - $current_time);
            } else {
                $shift_info->status = 2;
                $shift_info->statusText = 'none';
                $shift_info->time_left = 0;
            }
        }

        echo json_encode($shift_info);
    }


    public function tasks_table_due() {

        $tasks = $this->team_management_model->get_tasks_records(1);
        echo json_encode(['data' => $tasks]);
    }

    public function tasks_table_due_today() {

        $tasks = $this->team_management_model->get_tasks_records(2);
        echo json_encode(['data' => $tasks]);
    }

    public function tasks_table_all() {

        $tasks = $this->team_management_model->get_tasks_records(3);
        echo json_encode(['data' => $tasks]);
    }

    public function send_shift_reminders() {
        $this->load->library('email');

        $staff_members  = $this->team_management_model->get_staff_with_shifts();
    
        foreach ($staff_members as $staff_member) {
            $subject = 'Your shift timings for today!';

            $message = 'Your shift timings for today are as follows: <br>';
    
            foreach ($staff_member->shifts as $shift) {
                $message .= ' <br> <br>Shift ' . $shift->shift_number . ': ' . $shift->shift_start_time . ' CST - ' . $shift->shift_end_time . ' CST';
            }

            // Send the email using your preferred email library (e.g., PHPMailer, CI Email library, etc.)
            $this->email->initialize();
            $this->email->set_newline(PHP_EOL);
            $this->email->from(get_option('smtp_email'), get_option('companyname'));
            $this->email->to($staff_member->email);
            $this->email->subject($subject);
            $this->email->message($message);
            if(!empty($staff_member->shifts)){
                $this->email->send();
            }
                

            if (!$this->email->send()) {
                log_activity ('Email failed to send. Error: ' . $this->email->print_debugger(), null);
            }

        }

    }

    public function mail_shift_timings() {

        $this->load->library('email');

        $staff_id = $this->input->post('staff_id');
        $staff_email = $this->team_management_model->id_to_name($staff_id, 'tblstaff', 'staffid', 'email');
        $staff_name = $this->team_management_model->id_to_name($staff_id, 'tblstaff', 'staffid', 'firstname');
        $month = $this->input->post('month');
        $month_name = date('F', mktime(0, 0, 0, $month, 1));

        $shift_data = $this->team_management_model->get_staff_shift_details($staff_id, $month);
        

        $email_subject = 'Your shift timings for ' . $month_name;

        $template = file_get_contents(base_url('modules/team_management/assets/template/pdf_temp.html'));

        $template = str_replace('{month_name}', $month_name , $template);
        $template = str_replace('{staff_name}', $staff_name, $template);
 
        $html = '';

        // Loop through the days of the month and generate table rows
        for ($day = 1; $day <= date('t', mktime(0, 0, 0, $month, 1)); $day++) {

            $year = date('Y');
            $thisMonth = date('F', mktime(0, 0, 0, $month, 1));
            $timestamp = mktime(0, 0, 0, $month, $day, $year);

            $date_formatted = date('l, F jS', $timestamp);

            $html .= '

            <tr class="bg-gray-100/80 my-2">
                <th class="border px-4 py-2" colspan="2">'.$date_formatted.'</th>
            </tr>
            <tr class="bg-gray-100/30 my-2">
                <td class="border px-4 py-2 text-center">' . $shift_data[$day][1]['start_time'] . ' - ' . $shift_data[$day][1]['end_time'] . '</td>
                <td class="border px-4 py-2 text-center">' . $shift_data[$day][2]['start_time'] . ' - ' . $shift_data[$day][2]['end_time'] . '</td>
            </tr>
            ';
        }

        $template = str_replace('{shift_rows}', $html, $template);

        $this->email->initialize();
        $this->email->set_newline(PHP_EOL);
        $this->email->from(get_option('smtp_email'), get_option('companyname'));
        $this->email->to($staff_email);
        $this->email->subject($email_subject);
        $this->email->message($template);

        if ($this->email->send()) {
            echo json_encode(['success' => true, 'mail' => $staff_email]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function mail_weekly($staff_id) {

        $this->load->library('email');

        $staff_email = $this->team_management_model->id_to_name($staff_id, 'tblstaff', 'staffid', 'email');
        $staff_name = $this->team_management_model->id_to_name($staff_id, 'tblstaff', 'staffid', 'firstname');
        $month = date('n');
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
    
        $shift_data = $this->team_management_model->get_staff_shift_details($staff_id, $month);
    
        $email_subject = 'Your shift timings for this week';
    
        $template = file_get_contents(base_url('modules/team_management/assets/template/pdf_temp.html'));
    
        $template = str_replace('{month_name}', $month_name , $template);
        $template = str_replace('{staff_name}', $staff_name, $template);
    
        $html = '';
    
        // Find the first and last day of the current week
        $today = date('Y-m-d');
        $first_day_of_week = date('Y-m-d', strtotime('this week', strtotime($today)));
        $last_day_of_week = date('Y-m-d', strtotime('this week +6 days', strtotime($today)));
    
        // Loop through the days of the current week and generate table rows
        for ($date = $first_day_of_week; $date <= $last_day_of_week; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
            
            $day = date('j', strtotime($date));
            $date_formatted = date('l, F jS', strtotime($date));

            if (date('m', strtotime($date)) == $month) {
            
                if (isset($shift_data[$day][1]['start_time']) && isset($shift_data[$day][1]['end_time'])) {
                $shift1_start_time = $shift_data[$day][1]['start_time'];
                $shift1_end_time = $shift_data[$day][1]['end_time'];
                } else {
                $shift1_start_time = 'N/A';
                $shift1_end_time = 'N/A';
                }
            
                if (isset($shift_data[$day][2]['start_time']) && isset($shift_data[$day][2]['end_time'])) {
                $shift2_start_time = $shift_data[$day][2]['start_time'];
                $shift2_end_time = $shift_data[$day][2]['end_time'];
                } else {
                $shift2_start_time = 'N/A';
                $shift2_end_time = 'N/A';
                }
            
                $html .= '
                <tr class="bg-gray-100/80 my-2">
                    <th class="border px-4 py-2" colspan="2">' . $date_formatted . '</th>
                </tr>
                <tr class="bg-gray-100/30 my-2">
                    <td class="border px-4 py-2 text-center">' . $shift1_start_time . ' - ' . $shift1_end_time . '</td>
                    <td class="border px-4 py-2 text-center">' . $shift2_start_time . ' - ' . $shift2_end_time . '</td>
                </tr>
                ';

            }

        }
    
        $template = str_replace('{shift_rows}', $html, $template);
    
        $this->email->initialize();
        $this->email->set_newline(PHP_EOL);
        $this->email->from(get_option('smtp_email'), get_option('companyname'));
        $this->email->to($staff_email);
        $this->email->subject($email_subject);
        $this->email->message($template);
    
        if ($this->email->send()) {
            echo json_encode(['success' => true, 'mail' => $staff_email]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
    
    public function mail_weekly_all()
    {
        $all_staff = $this->team_management_model->get_all_staff();

        // Loop through all staff members
        foreach ($all_staff as $staff_member) {
            // Get the staff member's ID
            $staff_id = $staff_member->staffid;
    
            // Call the mail_weekly function for the current staff member
            if($staff_id == 1 || $staff_id == 20){
                $this->mail_weekly($staff_id);
            }
            
        }
    }

    public function export_shift_details_to_pdf($staff_id, $month)
    {
        $shift_data = $this->team_management_model->get_staff_shift_details($staff_id, $month);
        $staff_name = $this->team_management_model->id_to_name($staff_id, 'tblstaff', 'staffid', 'firstname');
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $mpdf = new \Mpdf\Mpdf();
        // Load the pre-modified PDF as a template.
        
        $template = file_get_contents(base_url('modules/team_management/assets/template/pdf_temp.html'));

        $template = str_replace('{month_name}', $monthName , $template);
        $template = str_replace('{staff_name}', $staff_name, $template);
 
        $html = '';

        // Loop through the days of the month and generate table rows
        for ($day = 1; $day <= date('t', mktime(0, 0, 0, $month, 1)); $day++) {

            $year = date('Y');
            $thisMonth = date('F', mktime(0, 0, 0, $month, 1));
            $timestamp = mktime(0, 0, 0, $month, $day, $year);

            $date_formatted = date('l, F jS', $timestamp);

            $html .= '

            <tr class="bg-gray-100/80 my-2">
                <th class="border px-4 py-2" colspan="2">'.$date_formatted.'</th>
            </tr>
            <tr class="bg-gray-100/30 my-2">
                <td class="border px-4 py-2 text-center">' . $shift_data[$day][1]['start_time'] . ' - ' . $shift_data[$day][1]['end_time'] . '</td>
                <td class="border px-4 py-2 text-center">' . $shift_data[$day][2]['start_time'] . ' - ' . $shift_data[$day][2]['end_time'] . '</td>
            </tr>
            ';
        }

        $template = str_replace('{shift_rows}', $html, $template);


        $mpdf->WriteHTML($template);
        $mpdf->Output($staff_name.'\'s_staff_shifts.pdf', 'D');
        //echo $template;
    }

    public function export_all_shift_details_to_pdf($month)
    {
        // Retrieve all staff members from the database.
        $all_staff_members = $this->team_management_model->get_all_staff();

        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $year = date('Y');

        $mpdf_config = [
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'orientation' => 'L',
        ];

        $mpdf = new \Mpdf\Mpdf($mpdf_config);

        // Load the pre-modified PDF as a template.
        $template = file_get_contents(base_url('modules/team_management/assets/template/pdf_temp_L.html'));

        $final_html = '';

        $template = str_replace('{month_name}', $monthName , $template);
        $template = str_replace('{staff_name}', "All member", $template);

        $html = '';

        // Get the total number of weeks in the month.
        $weeks_in_month = date('W', mktime(0, 0, 0, $month + 1, 0, $year)) - date('W', mktime(0, 0, 0, $month, 1, $year)) + 1;

        // Loop through each week.
        for ($week = 1; $week <= $weeks_in_month; $week++) {
            
            $html .= '<br><table class="week-table w-full border-collapse" style="page-break-after: always;">
            <thead>
                <tr class="myRow">
                    <th class="myTd">Staff</th>';

            // Generate table head with dates.
            for ($day_of_week = 1; $day_of_week <= 7; $day_of_week++) {
                $day = ($week - 1) * 7 + $day_of_week;
                $timestamp = mktime(0, 0, 0, $month, $day, $year);
                $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
                $offset = (date('N', $first_day_of_month) - 1) % 7;

                // Adjust the day to start the week from Monday.
                $day -= $offset;
                $timestamp = mktime(0, 0, 0, $month, $day, $year);

                    $date_formatted = date('D, M jS', $timestamp);
                    $html .= '<th class="myTd" colspan="2">' . $date_formatted . '</th>';       
            }

            $html .= '</tr>

            <tr class="myRow">
                    <th class="myTd"></th>';

            // Generate table head with dates.
            for ($day_of_week = 1; $day_of_week <= 7; $day_of_week++) {
                $html .= '<th class="myTd"> Shift 1 </th>';  
                $html .= '<th class="myTd"> Shift 2 </th>';       
            }

            $html .= '</tr>


            </thead>
            <tbody>';

            // Loop through each staff member and generate their shift details.
            foreach ($all_staff_members as $staff) {
                $staff_id = $staff->staffid;
                $shift_data = $this->team_management_model->get_staff_shift_details($staff_id, $month);
                $staff_name = $staff->firstname;

                $html .= '<tr>
                <th class="myTd">' . $staff_name . '</th>';

                // Loop through the days of the week and generate table cells.
                for ($day_of_week = 1; $day_of_week <= 7; $day_of_week++) {
                    $day = ($week - 1) * 7 + $day_of_week;
                    
                    // Adjust the day to start the week from Monday.
                    $day -= $offset;
                    $timestamp = mktime(0, 0, 0, $month, $day, $year);

                    if ($day > 0 && $day <= date('t', $first_day_of_month) && date('m', $timestamp) == $month) {
                        $shift_data_day = isset($shift_data[$day]) ? $shift_data[$day] : [];
                        
                        $shift_1_start = isset($shift_data_day[1]) ? date('ga', strtotime($shift_data_day[1]['start_time'])) : '';
                        $shift_1_end = isset($shift_data_day[1]) ? date('ga', strtotime($shift_data_day[1]['end_time'])) : '';
                        $shift_2_start = isset($shift_data_day[2]) ? date('ga', strtotime($shift_data_day[2]['start_time'])) : '';
                        $shift_2_end = isset($shift_data_day[2]) ? date('ga', strtotime($shift_data_day[2]['end_time'])) : '';
                    
                        $time_entries = $this->team_management_model->get_staff_time_entries($staff_id, date('Y-m-d', $timestamp));
                        
                        $clock_in_1 = isset($time_entries[0]) ? date('ga', strtotime($time_entries[0]['clock_in'])) : '-';
                        $clock_out_1 = isset($time_entries[0]) ? date('ga', strtotime($time_entries[0]['clock_out'])) : '-';

                        $clock_in_2 = isset($time_entries[1]) ? date('ga', strtotime($time_entries[1]['clock_in'])) : '-';
                        $clock_out_2 = isset($time_entries[1]) ? date('ga', strtotime($time_entries[1]['clock_out'])) : '-';

                        $html .= '<td class=" myTd">' . ($shift_1_start && $shift_1_end ? $shift_1_start . '-' . $shift_1_end : '') . '</td>';
                        $html .= '<td class=" myTd">' . ($shift_2_start && $shift_2_end ? $shift_2_start . '-' . $shift_2_end : '') . '</td>';

                    } else {
                        $html .= '<td class="myTd">-</td>';
                        $html .= '<td class="myTd">-</td>';
                    }
                    
                }

                $html .= '</tr>';
            }

            $html .= '</tbody>
            </table>';
        }





        $final_html .= str_replace('{shift_rows}', $html, $template);

        $mpdf->WriteHTML($final_html);
        $mpdf->Output('All_staff_shifts.pdf', 'D');

        //echo $final_html;
    }


    

    function widget()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        $stats = $this->team_management_model->get_stats($staff_id);

        $data['stats'] = $stats;

        $this->load->view('dashboard_widget');
    }
    
    public function clock_in()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        $clock_in_result = $this->team_management_model->clock_in($staff_id);

        echo json_encode(['success' => $clock_in_result]);
    }

    public function clock_out()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        $clock_out_result = $this->team_management_model->clock_out($staff_id);

        echo json_encode(['success' => $clock_out_result]);
    }

    public function update_status()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        $status = $this->input->post('statusValue');
        $current_time = date('Y-m-d H:i:s');
    
        // End the previous status
        $this->team_management_model->update_status($staff_id, $status);

        $this->team_management_model->end_previous_status($staff_id, $current_time);
    
        if ($status === 'Online') {
            // Do not insert a new status entry for the 'online' status
        } else {
            // Insert a new status entry for 'afk' or 'offline'
            $this->team_management_model->insert_status_entry($staff_id, $status, $current_time);
        }
    
        echo json_encode(['success' => true]);
    }

    public function fetch_stats()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        $stats = $this->team_management_model->get_stats($staff_id);

        echo json_encode($stats);
    }

    function convertSecondsToRoundedTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

}


?>