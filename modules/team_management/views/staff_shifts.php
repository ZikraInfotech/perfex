<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper" class="wrapper">
    <div class="content flex flex-col">

        <div class="bg-white flex flex-col gap-4 rounded">
            <h2 class="text-xl text-center py-4">Set Shifts <a class="btn-primary p-2 rounded float-right mr-4" href="export_all_shift_details_to_pdf/<?php echo date('n') ?>" id="exportPDF" >PDF</a><br></h2>
            <div class="align-middle inline-block min-w-full">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Id</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Set Shifts</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($staff_members as $staff) { ?>
                            <tr class="hover:bg-gray-200/30 transition-all">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $staff['staffid']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <select class="p-2 form-select block w-full mt-1 text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" id="monthSelection<?php echo $staff['staffid']; ?>">
                                    <?php
                                    $currentMonth = date('m');
                                    for ($i = 1; $i <= 12; $i++):
                                        $selected = ($i == $currentMonth) ? 'selected' : '';
                                        $monthName = date('F', mktime(0, 0, 0, $i, 10)); // Get the month name
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $monthName; ?></option>
                                    <?php endfor; ?>
                                </select>

                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-toggle="modal" data-target="#shiftsModal" data-staff-id="<?php echo $staff['staffid']; ?>" data-staff-name="<?php echo $staff['firstname']; ?>" data-table-btn="idk">Set Shifts</button>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="<?php echo admin_url();?>team_management/activity_log/<?php echo $staff['staffid']; ?>/<?php echo date('n'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white hover:text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="activity-btn-<?php echo $staff['staffid']; ?>" data-staff-id="<?php echo $staff['staffid']; ?>">View</button>
                                </td>

                            </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="shiftsModal" tabindex="-1" aria-labelledby="shiftsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-xl" id="shiftsModalLabel">Set Shifts for Staff</h5>
            </div>
            <div class="modal-body">
    
            <form id="shiftsForm">

                <div class="flex flex-row p-2 gap-2 ">

                    <div class="w-1/2">
                        <label for="all_shift_start1">Shift 1 Start</label>
                        <input type="time" class="form-control" id="all_shift_start1" name="all_shift_start1">
                    </div>
                    <div class="w-1/2">
                        <label for="all_shift_end1">Shift 1 End</label>
                        <input type="time" class="form-control" id="all_shift_end1" name="all_shift_end1">
                    </div>

                </div>

                <div class="flex flex-row p-2 gap-2">

                    <div class="w-1/2">
                        <label for="all_shift_start2">Shift 2 Start</label>
                        <input type="time" class="form-control" id="all_shift_start2" name="all_shift_start2">
                    </div>
                    <div class="w-1/2">
                        <label for="all_shift_end2">Shift 2 End</label>
                        <input type="time" class="form-control" id="all_shift_end2" name="all_shift_end2">
                    </div>

                </div>

                <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-primary" onclick="changeTimes();">Change All Times</button>
                        <button type="button" class="btn btn-primary" id="toggleIndividualShifts">Toggle All</button>
                </div>
                

                <div class="individual-shifts mt-4 max-h-[50vh] overflow-y-scroll" style="display:none;">
                    <?php for ($i = 1; $i <= 31; $i++): ?>

                        <div class="flex flex-row p-2 gap-2 ">

                            <div class="w-1/2">
                                <label for="start_shift1_day_<?php echo $i; ?>">Day <?php echo $i; ?> - Shift 1 Start</label>
                                <input required type="time" class="form-control" id="start_shift1_day_<?php echo $i; ?>" name="start_shift1_day_<?php echo $i; ?>">
                            </div>
                            <div class="w-1/2">
                                <label for="end_shift1_day_<?php echo $i; ?>">Day <?php echo $i; ?> - Shift 1 End</label>
                                <input required type="time" class="form-control" id="end_shift1_day_<?php echo $i; ?>" name="end_shift1_day_<?php echo $i; ?>">
                            </div>

                        </div>

                        <div class="flex flex-row p-2 gap-2 ">

                            <div class="w-1/2">
                                <label for="start_shift2_day_<?php echo $i; ?>">Day <?php echo $i; ?> - Shift 2 Start</label>
                                <input required type="time" class="form-control" id="start_shift2_day_<?php echo $i; ?>" name="start_shift2_day_<?php echo $i; ?>">
                            </div>
                            <div class="w-1/2">
                                <label for="end_shift2_day_<?php echo $i; ?>">Day <?php echo $i; ?> - Shift 2 End</label>
                                <input required type="time" class="form-control" id="end_shift2_day_<?php echo $i; ?>" name="end_shift2_day_<?php echo $i; ?>">
                            </div>

                        </div>

                        <div class="border-b my-4 border-black border-dashed"></div>

                    <?php endfor; ?>
                </div>
            </form>

            </div>

            <div class="modal-footer flex justify-between">

                <div class="btn-group mr-auto">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Export
                    </button>
                    <div class="dropdown-menu flex-col p-2 gap-2">
                    <a class="dropdown-item p-2" href="#" id="exportPDF" >PDF</a><br>
                    <button class="dropdown-item p-2" id="exportMail" onclick="exportMail();">Mail</button>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveShifts" data-staff-id="" onclick="setShifts();">Save Changes</button>
                </div>
                
            </div>
        </div>
    </div>
</div>



<?php init_tail(); ?>

<script>

document.getElementById('toggleIndividualShifts').addEventListener('click', function() {
    var individualShifts = document.querySelector('.individual-shifts');
    individualShifts.style.display = individualShifts.style.display === 'none' ? '' : 'none';
});


// Add a click event listener to the 'Set Shifts' buttons
document.querySelectorAll('button[data-table-btn]').forEach(function (button) {
    button.addEventListener('click', function () {
        const staffId = button.getAttribute('data-staff-id');
        const staffName = button.getAttribute('data-staff-name');
        document.getElementById("shiftsModalLabel").textContent = "Set shifts for : "+staffName;
        let month = document.getElementById("monthSelection"+staffId).value;        

        showShiftsModal(staffId, month);
    });
});

function changeTimes() {

    if (confirm("Are you sure?") == true) {
        for (let i = 1; i <= 31; i++){
            if(document.getElementById('start_shift1_day_'+i) != null){

                document.getElementById('start_shift1_day_'+i).value = document.getElementById('all_shift_start1').value;
                document.getElementById('end_shift1_day_'+i).value = document.getElementById('all_shift_end1').value;

                document.getElementById('start_shift2_day_'+i).value = document.getElementById('all_shift_start2').value;
                document.getElementById('end_shift2_day_'+i).value = document.getElementById('all_shift_end2').value;
            
            }
        }
    } 
}

function showShiftsModal(staffId, month) {
    // Fetch the existing shifts for the staff member and populate the input fields
    // You can make an AJAX request to your server to fetch the shifts data and then update the input fields
    $.ajax({
        url: '<?php echo base_url('team_management/get_shift_timings/'); ?>' + staffId + '/' + month,
        type: 'GET',
        dataType: 'json',
        success: function(shiftTimings) {
            for (var i = 0; i < shiftTimings.length; i++) {
                var day = shiftTimings[i].day;

                if(shiftTimings[i].shift_number == 1){
                    var shift1_start_time = shiftTimings[i].shift_start_time;
                    var shift1_end_time = shiftTimings[i].shift_end_time;

                    $('#start_shift1_day_' + day).val(shift1_start_time);
                    $('#end_shift1_day_' + day).val(shift1_end_time);  
                }else{
                    var shift2_start_time = shiftTimings[i].shift_start_time;
                    var shift2_end_time = shiftTimings[i].shift_end_time;

                    $('#start_shift2_day_' + day).val(shift2_start_time);
                    $('#end_shift2_day_' + day).val(shift2_end_time);  
                }

                
            }
            for (var i = 0; i <= 31; i++) {
                if(!shiftTimings[i]){
                    $('#start_shift1_day_' + i).val('');
                    $('#end_shift1_day_' + i).val('');

                    $('#start_shift2_day_' + i).val('');
                    $('#end_shift2_day_' + i).val('');
                }    
            }

            document.getElementById("saveShifts").setAttribute("data-staff-id", staffId);
            document.getElementById("saveShifts").setAttribute("data-month", month);
            document.getElementById("exportPDF").setAttribute("href", "export_shift_details_to_pdf/"+staffId+"/"+month);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('Error fetching shift timings:', textStatus, errorThrown);
        }
    });
}

function setShifts() {

    var _staffid = document.getElementById("saveShifts").getAttribute("data-staff-id");
    var _month = document.getElementById("saveShifts").getAttribute("data-month");

    const formData = new FormData(shiftsForm);
    formData.append('staff_id', _staffid);
    formData.append(csrfData.token_name, csrfData.hash);
    formData.append('month', _month);
    fetch('save_shift_timings', {
        method: 'POST',
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Shift timings saved successfully.');
            } else {
                alert('An error occurred while saving the shift timings.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the shift timings.');
        });
}

function exportMail() {
    var _staffid = document.getElementById("saveShifts").getAttribute("data-staff-id");
    var _month = document.getElementById("saveShifts").getAttribute("data-month");

    const formData = new FormData();
    formData.append('staff_id', _staffid);
    formData.append(csrfData.token_name, csrfData.hash);
    formData.append('month', _month);

    fetch('mail_shift_timings', {
        method: 'POST',
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Mail sent successfully.');
            } else {
                alert('An error occurred while mailing.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while mailing.');
        });
}

function updateSelect(element) {
    var staffId = element.getAttribute("data-staff-id");
    document.getElementById("activity-btn-"+staffId).setAttribute("href", "<?php echo admin_url();?>team_management/activity_log/"+staffId+"/"+element.value);
}


</script>

</body>
</html>