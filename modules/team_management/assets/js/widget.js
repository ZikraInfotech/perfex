document.addEventListener('DOMContentLoaded', function () {
    
    //setTimeout(function(){

    
    if (document.body.classList.contains('dashboard')) {
        
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = xhr.responseText;

                var widgetsContainer = document.querySelector('.content');

                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = response;

                widgetsContainer.insertBefore(tempDiv, widgetsContainer.firstChild);

                //Start of the view Scripting
                
                var timerInterval;
                var clockedIn = false;
                var startTime;

                const clockInBtn = document.getElementById('clock-in');
                const clockOutBtn = document.getElementById('clock-out');
                const statusSelect = document.getElementById('status');

                const liveTimer = document.getElementById('live-timer');
                const todayTimer = document.getElementById('today-timer');
                const yesterdayTimer = document.getElementById('yesterday-timer');
                const currentWeekTimer = document.getElementById('current-week-timer');
                const lastWeekTimer = document.getElementById('last-week-timer');

                function convertDateTimeZone(getDateObject) {

                    let timeZone = myZone;

                    var options = { timeZone: timeZone, hour: 'numeric', minute: 'numeric', second: 'numeric' };
                    var localTime = getDateObject.toLocaleString('en-US', options);
                    var localTimeArray = localTime.split(/[:\s]/);
                    var localDate = new Date(getDateObject.toLocaleDateString('en-US', { timeZone: timeZone }));
                    
                    // Convert the hours to 24-hour format if needed
                    if (localTimeArray[3] === 'PM') {
                        localTimeArray[0] = parseInt(localTimeArray[0], 10) + 12;
                    }
                    
                    localDate.setHours(localTimeArray[0], localTimeArray[1], localTimeArray[2]);
                    return localDate;
                }

                function updateLiveTimer() {
                    if (clockedIn) {

                        var currentTime = convertDateTimeZone(new Date());

                        var elapsedTime = Math.floor((currentTime - startTime) / 1000);

                        //console.log(currentTime);
                        //console.log(startTime);

                        document.getElementById('live-timer').textContent = formatTime(elapsedTime );
                    }
                }

                function formatTime(seconds) {
                    const hours = Math.floor(seconds / 3600);
                    seconds %= 3600;
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                
                    return hours.toString().padStart(2, '0') + ':' +
                           minutes.toString().padStart(2, '0') + ':' +
                           remainingSeconds.toString().padStart(2, '0');
                }

                function fetchStats() {

                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', admin_url + 'team_management/fetch_stats', true);

                    xhr.onload = function() {

                    if (this.status === 200) {

                        var stats = JSON.parse(this.responseText);

                        liveTimer.textContent = formatTime(stats.total_time);
                        todayTimer.textContent = formatTime(stats.todays_total_time);
                        yesterdayTimer.textContent = formatTime(stats.yesterdays_total_time);
                        currentWeekTimer.textContent = formatTime(stats.this_weeks_total_time);
                        lastWeekTimer.textContent = formatTime(stats.last_weeks_total_time);

                        if(stats.status == "Online"){
                            
                            if (stats.clock_in_time) {
                                clockInBtn.disabled = true;
                                clockInBtn.style.opacity = 0.7;
                                clockOutBtn.disabled = false;
                                clockOutBtn.style.opacity = 1;
                                clockedIn = true;
                                console.log(stats.clock_in_time);
                                startTime = new Date(stats.clock_in_time);
                                timerInterval = setInterval(updateLiveTimer, 1000);
                            }else{
                                clockInBtn.disabled = false;
                                clockInBtn.style.opacity = 1;
                                clockOutBtn.disabled = true;
                                clockOutBtn.style.opacity = 0.7;
                                clearInterval(timerInterval);
                                clockedIn = false;
                            }

                        }else{
                            clockInBtn.disabled = true;
                            clockInBtn.style.opacity = 0.7;
                            clockOutBtn.disabled = true;
                            clockOutBtn.style.opacity = 0.7;
                            clearInterval(timerInterval);
                            clockedIn = false;

                        }

                        if (stats.clock_in_time) {
                            document.getElementById('Leave').disabled = true;
                        }else{
                            document.getElementById('Leave').disabled = false;
                        }

                        

                        statusSelect.value = stats.status;
                        statusSelectColors(statusSelect);
                        
                    } 
                    else {
                        alert('Unable to fetch stats. Please try again.');
                    }
                    
                    }

                    xhr.send();
                }

                clockInBtn.addEventListener('click', () => {
                    
                    

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', admin_url + 'team_management/clock_in');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrf_token);
                    xhr.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            var response = JSON.parse(this.responseText);
                            if (response.success) {
                                clockedIn = true;
                                fetchStats();
                                clockInBtn.disabled = true;
                                timerInterval = setInterval(updateLiveTimer, 1000);
                            } else {
                                alert('Unable to clock in. Please try again.');
                            }
                        }
                    };
                    var requestData = csrf_token_name + '=' + encodeURIComponent(csrf_token);
                    xhr.send(requestData);

                });

                clockOutBtn.addEventListener('click', () => {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', admin_url + 'team_management/clock_out');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrf_token);
                    xhr.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            var response = JSON.parse(this.responseText);
                            if (response.success) {
                                fetchStats();
                            } else {
                                alert('Unable to clock out. Please try again.');
                            }
                        }
                    };
                
                    // Include the CSRF token in the request data
                    var requestData = csrf_token_name + '=' + encodeURIComponent(csrf_token);
                    xhr.send(requestData);
                });
                
                let previousValue = statusSelect.value;
                statusSelect.addEventListener('change', (event) => {
                    
                    var statusText = statusSelect.value;
                    
                    if (statusSelect != previousValue) {

                        //UI Timers
                        //if (statusText == 'Online') {
                        //    if (!clockedIn) {
                        //        clockInBtn.disabled = true;
                        //        clockInBtn.style.opacity = 0.7;
                        //        clockOutBtn.disabled = false;
                        //        clockOutBtn.style.opacity = 1;
                        //        clockedIn = true;
                        //        timerInterval = setInterval(updateLiveTimer, 1000);
                        //    }
                        //} else {
                        //    clockInBtn.disabled = true;
                        //    clockInBtn.style.opacity = 0.7;
                        //    clockOutBtn.disabled = true;
                        //    clockOutBtn.style.opacity = 0.7;
                        //    clearInterval(timerInterval);
                        //    clockedIn = false;
                        //}
                        
                        //Backend Timers
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', admin_url + 'team_management/update_status');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('X-CSRF-TOKEN', csrf_token);
                        xhr.onreadystatechange = function () {
                            if (this.readyState == 4 && this.status == 200) {
                                var response = JSON.parse(this.responseText);
                                if (!response.success) {
                                    alert('Unable to update status. Please try again.');
                                }
                                fetchStats();
                            }
                        };
                
                        // Include the CSRF token and status in the request data
                        var requestData = csrf_token_name + '=' + encodeURIComponent(csrf_token) + '&statusValue=' + encodeURIComponent(statusText);
                        xhr.send(requestData);
                    }
                });
                

                fetchStats();

                //Setting current shift status
                
                var xhrShift = new XMLHttpRequest();
                xhrShift.open("GET", admin_url + "team_management/get_shift_status", true);
                xhrShift.responseType = "json";
                xhrShift.onload = function () {
                    if (xhrShift.status === 200) {
                        
                        var response = xhrShift.response;
                        var shiftInfo = "";
                        if (response.status == 0) {
                            shiftInfo = "It's shift time. Time remaining: " + response.time_left;
                        } else if (response.status == 1) {
                            shiftInfo = "Upcoming shift in " + response.time_left;
                        } else {
                            shiftInfo = "No shift currently.";
                        }
                        document.getElementById("shiftInfo").textContent = shiftInfo;
                    } else {
                        console.error("Error retrieving shift information:", xhrShift.statusText);
                    }
                };
                xhrShift.onerror = function (error) {
                    console.error("Error retrieving shift information:", error);
                };
                xhrShift.send();

            }
        };
        xhr.open('GET', admin_url + 'team_management/widget', true);
        xhr.send();
    }

    //}, 3000);


});

function statusSelectColors(element){
    element.classList.remove('text-lime-500');
    element.classList.remove('text-blue-500');
    element.classList.remove('text-pink-500');
    element.classList.add(element.options.namedItem(element.value).classList.item(0));
}
