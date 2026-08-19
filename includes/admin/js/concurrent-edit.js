// Beer Festival Concurrent Editing System
jQuery(document).ready(function($) {

    let lastActivityCheck = 0;
    const pollInterval = 15000; // 15 seconds
    
    // Initialize SweetAlert2
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true
    });

    // Poll server for updates
    function checkForUpdates() {
        wp.apiFetch({
            url: BFTL.activity_rest_url + '?since=' + lastActivityCheck
        }).then(function(response) {
            response.activities.forEach(activity => {
                showActivityNotification(activity);
            });
            lastActivityCheck = response.current_time;
        });
    }

    // Show notification for activity
    function showActivityNotification(activity) {
        const actions = {
            'assign': 'assigned a beer to',
            'clear': 'cleared',
            'update': 'updated'
        };
        
        Toast.fire({
            icon: 'info',
            title: `${activity.user_name} ${actions[activity.action]} Tap ${activity.tap}`
        });
        
        // Highlight changed tap row
        $(`tr:has(select[name="tap_beer[${activity.tap}]"])`)
            .addClass('recent-activity')
            .delay(2000)
            .queue(function() {
                $(this).removeClass('recent-activity').dequeue();
            });
    }

    // Start polling
    setInterval(checkForUpdates, pollInterval);

    // Conflict detection before save
    $('#bftl-tap-form').on('submit', function(e) {
        const currentData = $(this).serialize();
        const originalData = window.originalFormData || '';
        
        if (originalData !== currentData) {
            Swal.fire({
                title: 'Possible Conflict Detected',
                text: 'The tap list has changed since you loaded this page. Do you want to overwrite these changes?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Overwrite',
                cancelButtonText: 'Review Changes'
            }).then((result) => {
                if (!result.isConfirmed) {
                    e.preventDefault();
                    checkForUpdates(); // Force immediate update check
                }
            });
        }
    });

    // Store initial form state
    window.originalFormData = $('#bftl-tap-form').serialize();
});
