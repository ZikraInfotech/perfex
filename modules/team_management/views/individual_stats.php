<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); 

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

?>
<style>
    .row-options{
        display: none;
    }
</style>
<div id="wrapper">

   <div class="content flex flex-col gap-10">


        <!--\\\\\\\ Individual Stats \\\\\\\\-->

        <div class="w-full">
            <div class="w-full mb-4">
                <h2 class="text-3xl font-bold text-center">Individual Stats</h2>
            </div>

            <div class="w-full grid xl:grid-cols-5 lg:grid-cols-3 grid-cols-2 justify-center gap-4">

                <?php foreach ($staff_members as $staff): ?>

                <div class="w-full mx-auto transition-all bg-white hover:shadow-sm shadow-[4px_4px_0_0] border border-solid rounded-xl overflow-hidden md:max-w-2xl p-4">
                    <div class="flex flex-col">
                        <div class="flex-shrink-0 flex justify-center max-w-fit mx-auto">

                            <?php echo staff_profile_image($staff->staffid, ['h-48 border-2 border-solid', 'w-full', 'object-cover', 'md:h-full' , 'md:w-48' , 'staff-profile-image-thumb'], 'thumb'); ?>
                        </div>
                        <div class="p-3 flex flex-col mt-4 gap-2">
                            
                            <div class="uppercase tracking-wide text-xl text-center text-gray-700 font-bold"><?php echo $staff->firstname ?></div>
                            
                          
                            <div class="text-gray-500 text-center flex flex-row justify-center gap-2">
                                
                                <div>Current Status: </div>
                                
                                <div class="px-1 rounded bg-<?php echo $staff->statusColor; ?>"> <?php echo $staff->status; ?> \ <?php if($staff->working) {echo 'Working';} else {echo 'Not working';} ?> 
                                </div>

                            </div>

                            <div class="text-gray-500 text-center flex flex-row justify-center gap-2"><div>Time spent today: </div><div class="px-1 rounded bg-sky-400 text-white"> <?php echo convertSecondsToRoundedTime($staff->live_time_today); ?> </div> </div>

                            <div class="text-gray-500 text-center flex flex-row justify-center gap-2"><div>Current Task: </div><div class="px-1 rounded bg-sky-400 text-white"> <?php echo $staff->currentTaskName; ?> </div> </div>

                            <div class="text-gray-500 text-center flex flex-row justify-center gap-2"><div>Current Project: </div><div class="px-1 rounded bg-sky-400 text-white"> <?php echo isset($staff->currentTaskProject) ? $staff->currentTaskProject : 'None' ; ?> </div> </div>

                            <div class="text-gray-500 text-center flex flex-row justify-center gap-2"><div>Task Timer: </div><div class="px-1 rounded bg-sky-400 text-white"> <?php echo convertSecondsToRoundedTime($staff->currentTaskTime); ?> </div> </div>
                        
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>
        </div>

      
   </div>

</div>


<?php init_tail(); ?>

</body>
</html>