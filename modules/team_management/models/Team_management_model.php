<?php defined('BASEPATH') or exit('No direct script access allowed');

class Team_management_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param  integer (optional)
     * @return object
     * Get single goal
     */

    public function get_all_staff()
    {
        $CI =& get_instance();

        $this->db->select('*');
        $this->db->from(''.db_prefix().'staff');
        $this->db->join(''.db_prefix().'_staff_status', ''.db_prefix().'staff.staffid = '.db_prefix().'_staff_status.staff_id');
        $this->db->where('is_not_staff', 0);
        $query = $this->db->get();
        $result = $query->result();
     
        // Loop through each row in the result
        foreach ($result as $staff) {

            //Today's Timer Counter
            $staff->live_time_today = $this->get_today_live_timer($staff->staff_id);

            //Task Assigned
            $allTasks = $this->get_tasks_of_staff($staff->staff_id);
            if($allTasks){
                $staff->all_tasks = $allTasks;
            }
            

            //Get current task
            $taskId = $this->get_current_task_by_staff_id($staff->staff_id);
            if($taskId){
                $task = $this->get_task_by_taskid($taskId);
                $staff->currentTaskName = $task->name;
                if($task->rel_type == "project"){
                    $CI->load->model('projects_model');
                    $task_project = $CI->projects_model->get($task->rel_id);
                    $staff->currentTaskProject = $task_project->name;
                }
                
                $currentTaskTime = $this->get_timers($taskId, $staff->staff_id);
                
                if($currentTaskTime){

                    $timestamp = $currentTaskTime->start_time;

                    $given_date = new DateTime();
                    $given_date->setTimestamp($timestamp);

                    $now = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));

                    $interval = $now->diff($given_date);
                    $seconds_passed = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->days * 86400);

                    $staff->currentTaskTime = $seconds_passed;

                }else{
                    $staff->currentTaskTime = "0";
                }

                $staff->currentTaskId = $task->id;

            }else{
                $staff->currentTaskId = 0;
                $staff->currentTaskName = "None";
                $staff->currentTaskTime = "0";
            }
            
            //Check if Shift is Active or Not
            $current_entry = $this->db->where('staff_id', $staff->staff_id)
                            ->where('clock_out IS NULL', null, false)
                            ->get(''.db_prefix().'_staff_time_entries')
                            ->row();
            if ($current_entry) {
                $staff->working = true;
            }else{
                $staff->working = false;
            }

            //Set Status Color Class
            if($staff->status == "Online"){
                $staff->statusColor = "emerald-200";
            }else if ($staff->status == "AFK"){
                $staff->statusColor = "sky-200";
            }
            else if ($staff->status == "Leave"){
                $staff->statusColor = "amber-200";
            }
            else{
                $staff->statusColor = "gray-200";
            }

         }
     
         return $result;
    }

    public function get_all_timers(){
        $timers = new stdClass();
        $yesterdayTime = 0;
        $weekTime = 0;
        $todayTime = 0;

        $this->db->select('staffid');
        $this->db->from(''.db_prefix().'staff');
        $this->db->where('is_not_staff', 0);
        $query = $this->db->get();

        $staff_members = $query->result();

        foreach ($staff_members as $staff) {
            $yesterdayTime += $this->get_yesterdays_total_time($staff->staffid);
            $weekTime += $this->get_this_weeks_total_time($staff->staffid);
            $todayTime += $this->get_today_live_timer($staff->staffid);
        }

        $timers->todayTime = $todayTime;
        $timers->yesterdayTime = $yesterdayTime;
        $timers->weekTime = $weekTime;

        $timers->totalOngoingTasks = $this->get_ongoing_tasks();
        $maxTasksCompleted = $this->get_staff_with_most_tasks_completed_today();

        if($maxTasksCompleted){
            $timers->maxTasksCompletedName = $maxTasksCompleted->lastname;
            $timers->maxTasksCompletedId = $maxTasksCompleted->staffid;
        }else{
            $timers->maxTasksCompletedName = "None :(";
            $timers->maxTasksCompletedId = null;
        }

        $maxHours = $this->get_staff_with_highest_today_live_timer();
        if($maxHours){
            $timers->maxHoursPutInName = $maxHours->lastname;
            $timers->maxHoursPutInId = $maxHours->staffid;
        }else{
            $timers->maxHoursPutInName = "Nan";
            $timers->maxHoursPutInId = null;
        }
        

        return $timers;
    }

    public function get_ongoing_tasks()
    {
        $this->db->select(''.db_prefix().'tasks.*');
        $this->db->from(''.db_prefix().'tasks');
        $this->db->join(''.db_prefix().'taskstimers', ''.db_prefix().'taskstimers.task_id = '.db_prefix().'tasks.id');
        $this->db->where(''.db_prefix().'taskstimers.end_time IS NULL', NULL, FALSE);
        $query = $this->db->get();

        return $query->num_rows();
    }

    public function get_tasks_records($type) {

        $current_date = date('Y-m-d');

        $this->db->select(''.db_prefix().'tasks.*, '.db_prefix().'projects.name as project_name');
        $this->db->from(''.db_prefix().'tasks');

        
        if($type == 1){
            $this->db->where('duedate <', $current_date);
            $this->db->where(''.db_prefix().'tasks.status !=', 5);
        }else if ($type == 2){
            $this->db->where('duedate', $current_date);
        }else{
            $this->db->where(''.db_prefix().'tasks.status !=', 5);
            $this->db->group_start();
            $this->db->where('duedate >', $current_date);
            $this->db->or_where('duedate IS NULL', null, false);     
            $this->db->order_by('id DESC');
            $this->db->group_end();
        }

        $this->db->join(''.db_prefix().'projects', ''.db_prefix().'tasks.rel_id = '.db_prefix().'projects.id AND '.db_prefix().'tasks.rel_type = "project"', 'left');

        $query = $this->db->get();
        $allTasks = $query->result();

        foreach ($allTasks as $task) {

            $task->assigned = array();

            $this->db->select('staffid');
            $this->db->from(''.db_prefix().'task_assigned');
            $this->db->where('taskid', $task->id);
            $query = $this->db->get();
            $allStaff = $query->result();
            foreach ($allStaff as $staff) {
                array_push($task->assigned, staff_profile_image($staff->staffid, ['object-cover', 'md:h-full' , 'md:w-10 inline' , 'staff-profile-image-thumb'], 'thumb'));
            }
            if($task->duedate == null){
                $task->duedate = "None";
            }

            $task->priority = $this->id_to_name($task->priority, ''.db_prefix().'tickets_priorities', 'priorityid', 'name');

            if($task->status == 1){
                $task->status = "Not Started";
            }
            if($task->status == 2){
                $task->status = "Awaiting Feedback";
            }
            if($task->status == 3){
                $task->status = "Testing";
            }
            if($task->status == 4){
                $task->status = "In Progress";
            }
            if($task->status == 5){
                $task->status = "Completed";
            }
        }

        return $allTasks;
    }

    public function id_to_name($id, $tableName, $idName, $nameName) {
        $this->db->select($nameName);
        $this->db->from($tableName);
        $this->db->where($idName, $id);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->$nameName;
        } else {
            return 'Unknown';
        }
    }

    public function get_tasks_of_staff($staff_id)
    {
        $this->db->select(''.db_prefix().'tasks.*, '.db_prefix().'projects.name as project_name');
        $this->db->from(''.db_prefix().'tasks');
        $this->db->join(''.db_prefix().'task_assigned', ''.db_prefix().'task_assigned.taskid = '.db_prefix().'tasks.id');
        $this->db->join(''.db_prefix().'projects', ''.db_prefix().'tasks.rel_id = '.db_prefix().'projects.id AND '.db_prefix().'tasks.rel_type = "project"', 'left');
        $this->db->where(''.db_prefix().'task_assigned.staffid', $staff_id);
        $this->db->where(''.db_prefix().'tasks.status !=', 5);
        $query = $this->db->get();

        return $query->result();

    }
    
    

    public function get_staff_with_highest_today_live_timer() {
        $all_staff = $this->get_all_staff();

        $highest_timer_staff = null;
        $highest_timer = 0;

        foreach ($all_staff as $staff) {
            $timer = $this->get_today_live_timer($staff->staffid);

            if ($timer > $highest_timer) {
                $highest_timer = $timer;
                $highest_timer_staff = $staff;
            }
        }

        return $highest_timer_staff;
    }

    public function get_staff_with_most_tasks_completed_today()
    {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');

        $this->db->select(''.db_prefix().'staff.*, COUNT('.db_prefix().'tasks.id) as tasks_completed')
            ->from(''.db_prefix().'tasks')
            ->join(''.db_prefix().'task_assigned', ''.db_prefix().'tasks.id = '.db_prefix().'task_assigned.taskid')
            ->join(''.db_prefix().'staff', ''.db_prefix().'task_assigned.staffid = '.db_prefix().'staff.staffid')
            ->where(''.db_prefix().'tasks.status', 5)
            ->where(''.db_prefix().'tasks.datefinished >=', $today_start)
            ->where(''.db_prefix().'tasks.datefinished <=', $today_end)
            ->group_by(''.db_prefix().'task_assigned.staffid')
            ->order_by('tasks_completed', 'DESC')
            ->limit(1);

        $query = $this->db->get();
        $result = $query->row();

        return $result;
    }

    public function get_today_live_timer($staff_id)
    {
        $totalTime = 0;

        $totalTime = $this->get_todays_total_time($staff_id);
        
        $current_entry = $this->db->where('staff_id', $staff_id)
                            ->where('clock_out IS NULL', null, false)
                            ->get(''.db_prefix().'_staff_time_entries')
                            ->row();
        if ($current_entry) {
            //$adjusted_clock_in_string = $current_entry->clock_in;
            //$adjusted_clock_in = DateTime::createFromFormat('Y-m-d H:i:s', $adjusted_clock_in_string);
            $current_shift_start = strtotime($current_entry->clock_in);

            //$adjusted_date_string = date('Y-m-d H:i:s');
            //$adjusted_date = DateTime::createFromFormat('Y-m-d H:i:s', $adjusted_date_string);
            $current_unix_timestamp = strtotime(date('Y-m-d H:i:s'));

            $elapsed_time = $current_unix_timestamp - $current_shift_start;
            $afk_and_offline_time = $this->get_total_afk_and_offline_time($staff_id, $current_entry->clock_in);
            $totalTime += $elapsed_time - $afk_and_offline_time;
        }

        return $totalTime;
    }

    public function get_timers($taskId, $staff_id) {
        $this->db->select('*');
        $this->db->from(''.db_prefix().'taskstimers');
        $this->db->where('task_id', $taskId);
        $this->db->where('staff_id', $staff_id);
        $this->db->where('end_time IS NULL', null, false);
        $query = $this->db->get();
        return $query->row();
    }
    
    
    public function get_current_task_by_staff_id($staff_id) {
        $this->db->select('task_id');
        $this->db->from(''.db_prefix().'taskstimers');
        $this->db->where('staff_id', $staff_id);
        $this->db->where('end_time IS NULL');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get();
        $result = $query->row();
    
        if ($result) {
            return $result->task_id;
        } else {
            return null;
        }
    }

    public function get_task_by_taskid($taskid) {
        $this->db->select('*');
        $this->db->from(''.db_prefix().'tasks');
        $this->db->where('id', $taskid);
        $query = $this->db->get();
        return $query->row();
    }

    public function clock_in($staff_id)
    {
        // Check if there's an existing open session for the staff member
        $this->db->where('staff_id', $staff_id);
        $this->db->where('clock_out IS NULL', null, false);
        $query = $this->db->get(db_prefix().'_staff_time_entries');
        
        if ($query->num_rows() > 0) {
            // If there's an open session, return false
            return false;
        }

        // Clock in the staff member
        $data = [
            'staff_id' => $staff_id,
            'clock_in' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix().'_staff_time_entries', $data);

        return $this->db->affected_rows() > 0;
    }

    public function clock_out($staff_id)
    {
        $now = date('Y-m-d H:i:s');

        // Clock out the staff member by updating the latest open session
        $this->db->set('clock_out', $now);
        $this->db->where('staff_id', $staff_id);
        $this->db->where('clock_out IS NULL', null, false);
        $this->db->update(db_prefix().'_staff_time_entries');

        return $this->db->affected_rows() > 0;
    }

    public function update_status($staff_id, $status)
    {
        // Check if the staff_id already exists in the table
        $query = $this->db->select('*')
                          ->from(''.db_prefix().'_staff_status')
                          ->where('staff_id', $staff_id)
                          ->get();

        // If staff_id exists, update the status
        if ($query->num_rows() > 0) {
            $this->db->set('status', $status)
                     ->where('staff_id', $staff_id)
                     ->update(''.db_prefix().'_staff_status');
        } 
        // Otherwise, insert a new row with staff_id and status
        else {
            $data = array(
                'staff_id' => $staff_id,
                'status' => $status
            );
            $this->db->insert(''.db_prefix().'_staff_status', $data);
        }

        return $this->db->affected_rows() > 0;
    }

    public function get_stats($staff_id)
    {
        $stats = new stdClass();

        $current_entry = $this->db->where('staff_id', $staff_id)
                                ->where('clock_out IS NULL', null, false)
                                ->get(''.db_prefix().'_staff_time_entries')
                                ->row();

        if ($current_entry) {

            // Adjust clock_in time to the user's timezone
            //$adjusted_clock_in_string = $current_entry->clock_in;
            //$adjusted_clock_in = DateTime::createFromFormat('Y-m-d H:i:s', $adjusted_clock_in_string);
            $current_shift_start = strtotime($current_entry->clock_in);

            $total_afk_and_offline_time = $this->get_total_afk_and_offline_time($staff_id, $current_entry->clock_in);

            // Convert total_afk_and_offline_time to seconds and add to the current_shift_start
            $new_clock_in_time = $current_shift_start + $total_afk_and_offline_time;

            $stats->clock_in_time = date('Y-m-d H:i:s', $new_clock_in_time);

            //$adjusted_date_string = date('Y-m-d H:i:s');
            //$adjusted_date = DateTime::createFromFormat('Y-m-d H:i:s', $adjusted_date_string);
            $current_unix_timestamp = strtotime(date('Y-m-d H:i:s'));

            $elapsed_time = $current_unix_timestamp - $current_shift_start;


            $stats->total_afk_time = $total_afk_and_offline_time;
            $stats->total_time = $elapsed_time - $total_afk_and_offline_time;

        } else {
            $stats->clock_in_time = null;
            $stats->total_time = 0;
        }

        $current_entry = $this->db->where('staff_id', $staff_id)
                                ->get(''.db_prefix().'_staff_status')
                                ->row();
        if($current_entry){
            $stats->status = $current_entry->status;
        }else{
            $stats->status = "Status record not found!";
        }

        
        $stats->todays_total_time = $this->get_todays_total_time($staff_id);
        $stats->yesterdays_total_time = $this->get_yesterdays_total_time($staff_id);
        $stats->this_weeks_total_time = $this->get_this_weeks_total_time($staff_id);
        $stats->last_weeks_total_time = $this->get_last_weeks_total_time($staff_id);

        return $stats;
    }


    public function end_previous_status($staff_id, $end_time)
    {
        $this->db->set('end_time', $end_time)
                ->where('staff_id', $staff_id)
                ->where('end_time IS NULL', null, false)
                ->update(''.db_prefix().'_staff_status_entries');
    }

    public function insert_status_entry($staff_id, $status, $start_time)
    {
        $data = [
            'staff_id' => $staff_id,
            'status' => $status,
            'start_time' => $start_time,
        ];

        $this->db->insert(''.db_prefix().'_staff_status_entries', $data);
    }

    public function get_total_afk_and_offline_time($staff_id, $current_shift_start)
    {   
        $nowDateTime = new DateTime('now');
        $nowDate = $nowDateTime->format('Y-m-d H:i:s');

        $this->db->select_sum('TIMESTAMPDIFF(SECOND, start_time, IFNULL(end_time, "'.$nowDate.'"))', 'total_time')
        ->where('staff_id', $staff_id)
        ->where('start_time >=', $current_shift_start)
        ->where_in('status', ['AFK', 'Offline']);
        $result = $this->db->get(''.db_prefix().'_staff_status_entries')->row();

        return $result->total_time;
        //return $this->db->last_query();
    }

    public function test_query($query) {
        $result = $this->db->query($query);
        return $result;
    }

    public function get_todays_total_time($staff_id)
    {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        //error_log("Today start: {$today_start} - Today end: {$today_end}");
        return $this->get_total_time_within_range($staff_id, $today_start, $today_end);
    }

    public function get_yesterdays_total_time($staff_id)
    {
        $yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterday_end = date('Y-m-d 23:59:59', strtotime('-1 day'));
        return $this->get_total_time_within_range($staff_id, $yesterday_start, $yesterday_end);
    }

    public function get_this_weeks_total_time($staff_id)
    {
        $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        return $this->get_total_time_within_range($staff_id, $week_start, $week_end);
    }

    public function get_last_weeks_total_time($staff_id)
    {
        $last_week_start = date('Y-m-d 00:00:00', strtotime('monday last week'));
        $last_week_end = date('Y-m-d 23:59:59', strtotime('sunday last week'));
        return $this->get_total_time_within_range($staff_id, $last_week_start, $last_week_end);
    }

    public function get_total_time_within_range($staff_id, $start_date, $end_date)
    {
        // Calculate total working time
        $this->db->select_sum('TIMESTAMPDIFF(SECOND, clock_in, clock_out)', 'total_time')
        ->where('staff_id', $staff_id)
        ->where('clock_in >=', $start_date)
        ->where('clock_out <=', $end_date);
        $result = $this->db->get(''.db_prefix().'_staff_time_entries')->row();
        $total_working_time = $result->total_time;

        // Calculate total AFK and offline time
        $this->db->select_sum('TIMESTAMPDIFF(SECOND, '.db_prefix().'_staff_status_entries.start_time, '.db_prefix().'_staff_status_entries.end_time)', 'total_time')
        ->from(''.db_prefix().'_staff_status_entries')
        ->join(''.db_prefix().'_staff_time_entries', ''.db_prefix().'_staff_time_entries.staff_id = '.db_prefix().'_staff_status_entries.staff_id')
        ->where(''.db_prefix().'_staff_status_entries.staff_id', $staff_id)
        ->where(''.db_prefix().'_staff_status_entries.start_time >=', $start_date)
        ->where(''.db_prefix().'_staff_status_entries.end_time <=', $end_date)
        ->where(''.db_prefix().'_staff_status_entries.start_time >= '.db_prefix().'_staff_time_entries.clock_in')
        ->where(''.db_prefix().'_staff_status_entries.end_time <= '.db_prefix().'_staff_time_entries.clock_out')
        ->group_start()
            ->where(''.db_prefix().'_staff_status_entries.status', 'AFK')
            ->or_where(''.db_prefix().'_staff_status_entries.status', 'OFFLINE')
        ->group_end();
        $result = $this->db->get()->row();
        $total_afk_and_offline_time = $result->total_time;

        // Return the difference between total working time and total AFK and offline time
        return $total_working_time - $total_afk_and_offline_time;

    }

    public function save_shift_timings($staff_id, $month, $shift_timings) {
        // Delete existing shift timings for the staff member and month
        $this->db->where('staff_id', $staff_id)->where('month', $month)->delete(''.db_prefix().'_staff_shifts');
    
        // Insert new shift timings
        foreach ($shift_timings as $day => $shifts) {
            foreach ($shifts as $shift_number => $shift_time) {
                $this->db->insert(''.db_prefix().'_staff_shifts', [
                    'staff_id' => $staff_id,
                    'month' => $month,
                    'day' => $day,
                    'shift_number' => $shift_number,
                    'shift_start_time' => $shift_time['start'],
                    'shift_end_time' => $shift_time['end'],
                ]);
            }
        }
        
        return $this->db->affected_rows() > 0;
    }
    
    public function get_shift_timings($staff_id, $month) {
        $query = $this->db->where('staff_id', $staff_id)->where('month', $month)->get(''.db_prefix().'_staff_shifts');
        return $query->result_array();
    }

    public function get_staff_shift_details($staff_id, $month) {
        $this->db->select('day, shift_number, shift_start_time, shift_end_time');
        $this->db->from('tbl_staff_shifts');
        $this->db->where('staff_id', $staff_id);
        $this->db->where('month', $month);
        $this->db->order_by('day', 'ASC');
        $this->db->order_by('shift_number', 'ASC');
        $query = $this->db->get();
    
        $shift_data = array();
        foreach ($query->result() as $row) {
            $shift_data[$row->day][$row->shift_number] = array(
                'start_time' => $row->shift_start_time,
                'end_time' => $row->shift_end_time,
            );
        }
    
        return $shift_data;
    }
    

    public function get_shifts_info($staff_id)
    {
        $dateTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        
        $current_month = $dateTime->format('m');
        $current_day = $dateTime->format('d');
        
        $current_time = $dateTime->format('H:i:s');

        $this->db->select('*');
        $this->db->from(''.db_prefix().'_staff_shifts');
        $this->db->where('staff_id', $staff_id);
        $this->db->where('month', $current_month);
        $this->db->where('day', $current_day);
        $this->db->where('shift_end_time >=', $current_time);
        $this->db->order_by('shift_start_time', 'ASC');
        $this->db->limit(1);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->row();
        } else {
            return false;
        }
    }

    public function get_staff_with_shifts() {

        $dateTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $current_month = $dateTime->format('m');
        $current_day = $dateTime->format('d');

        $this->db->select(''.db_prefix().'staff.staffid, '.db_prefix().'staff.email, '.db_prefix().'_staff_shifts.shift_number, '.db_prefix().'_staff_shifts.shift_start_time, '.db_prefix().'_staff_shifts.shift_end_time');
        $this->db->from(''.db_prefix().'staff');
        $this->db->join(''.db_prefix().'_staff_shifts', ''.db_prefix().'_staff_shifts.staff_id = '.db_prefix().'staff.staffid');
        $this->db->where(''.db_prefix().'_staff_shifts.month', $current_month);
        $this->db->where(''.db_prefix().'_staff_shifts.day', $current_day);
        $query = $this->db->get();
    
        $staff_members = array();
        foreach ($query->result() as $row) {
            if (!isset($staff_members[$row->staffid])) {
                $staff_members[$row->staffid] = new stdClass();
                $staff_members[$row->staffid]->email = $row->email;
                $staff_members[$row->staffid]->shifts = array();
            }
            $shift = new stdClass();
            $shift->shift_number = $row->shift_number;
            $shift->shift_start_time = $row->shift_start_time;
            $shift->shift_end_time = $row->shift_end_time;
            $staff_members[$row->staffid]->shifts[] = $shift;
        }
    
        return $staff_members;
    }

    public function get_staff_shifts_for_month($staff_id, $month) {
        $this->db->select('tbl_staff_shifts.*, tblstaff.*');
        $this->db->from('tbl_staff_shifts');
        $this->db->join('tblstaff', 'tblstaff.staffid = tbl_staff_shifts.staff_id');
        $this->db->where('tbl_staff_shifts.staff_id', $staff_id);
        $this->db->where('tbl_staff_shifts.month', $month);
        $this->db->order_by('tbl_staff_shifts.day', 'ASC');
        $this->db->order_by('tbl_staff_shifts.shift_number', 'ASC');
        $query = $this->db->get();

        return $query->num_rows() > 0 ? $query->result() : false;
    }
    
    function back_to_user($date_string) {

        $this->db->select('value');
        $this->db->from('tbloptions');
        $this->db->where('name', 'default_timezone');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $default_timezone = $query->row()->value;
            // For example, you can use the DateTime class to format the date:
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $date_string);
            $date->setTimezone(new DateTimeZone($default_timezone));
            return $date->format('Y-m-d H:i:s');
        }
    }
    
    public function get_user_activities($staffId, $month) {
        // Fetch shift start times from tbl_staff_time_entries
        $this->db->select("clock_in as time, 'Started Shift' as activity_type");
        $this->db->from('tbl_staff_time_entries');
        $this->db->where('staff_id', $staffId);
        $this->db->where('MONTH(clock_in)', $month);
        $this->db->order_by('clock_in', 'ASC');
        $query1 = $this->db->get_compiled_select();

        // Fetch AFK start and end times from tbl_staff_status_entries
        $this->db->select("start_time as time, CONCAT('Set ', status) as activity_type");
        $this->db->from('tbl_staff_status_entries');
        $this->db->where('staff_id', $staffId);
        $this->db->where('MONTH(start_time)', $month);
        $this->db->order_by('start_time', 'ASC');
        $query2 = $this->db->get_compiled_select();

        $this->db->select("end_time as time, 'Back to Online' as activity_type");
        $this->db->from('tbl_staff_status_entries');
        $this->db->where('staff_id', $staffId);
        $this->db->where('MONTH(end_time)', $month);
        $this->db->order_by('end_time', 'ASC');
        $query3 = $this->db->get_compiled_select();

        // Fetch shift end times from tbl_staff_time_entries
        $this->db->select("clock_out as time, 'Ended Shift' as activity_type");
        $this->db->from('tbl_staff_time_entries');
        $this->db->where('staff_id', $staffId);
        $this->db->where('MONTH(clock_out)', $month);
        $this->db->order_by('clock_out', 'ASC');
        $query4 = $this->db->get_compiled_select();

        // Combine queries using UNION
        $query = $this->db->query("($query1) UNION ($query2) UNION ($query3) UNION ($query4) ORDER BY time ASC");

        return $query->result_array();

    }

    public function get_staff_time_entries($staff_id, $date) {
        $this->db->where('staff_id', $staff_id);
        $this->db->where('DATE(clock_in)', $date);
        $query = $this->db->get('tbl_staff_time_entries');
        return $query->result_array();
    }
    


}
