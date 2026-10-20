jQuery(document).ready(function($) {
    'use strict';
    
    let selectedDate = '';
    let selectedTime = '';
    
    // Tab Switching
    $('.tab-button').on('click', function() {
        const tab = $(this).data('tab');
        
        // Update button states
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update tab content
        $('.tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
        
        // Clear errors
        hideError();
        hideErrorAnfrage();
    });
    
    // Initialize date picker
    if (document.getElementById("appointment-date")) flatpickr("#appointment-date", {
        locale: "de",
        dateFormat: "Y-m-d",
        minDate: "today",
        disable: [
            function(date) {
                // Disable weekends (Saturday = 6, Sunday = 0)
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ],
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr) {
                selectedDate = dateStr;
                loadAvailableTimeSlots(dateStr);
            }
        }
    });
    
    // Load available time slots for selected date
    function loadAvailableTimeSlots(date) {
        showLoading(true);
        hideError();
        
        $.ajax({
            url: appointmentBooking.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_available_slots',
                nonce: appointmentBooking.nonce,
                date: date
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    populateTimeSlots(response.data.slots);
                } else {
                    showError(response.data.message || 'Fehler beim Laden der Zeitslots.');
                }
            },
            error: function() {
                showLoading(false);
                showError('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
            }
        });
    }
    
    // Populate time slot selector
    function populateTimeSlots(availableSlots) {
        const timeSelect = $('#appointment-time');
        timeSelect.empty();
        timeSelect.append($('<option>', {
            value: '',
            text: 'Wählen Sie eine Uhrzeit'
        }));
        
        if (availableSlots.length === 0) {
            timeSelect.prop('disabled', true);
            showError('Für dieses Datum sind keine Termine verfügbar.');
            return;
        }
        
        availableSlots.forEach(function(slot) {
            timeSelect.append($('<option>', {
                value: slot,
                text: slot
            }));
        });
        
        timeSelect.prop('disabled', false);
    }
    
    // Handle time selection
    $('#appointment-time').on('change', function() {
        selectedTime = $(this).val();
    });
    
    // Handle form submission
    $('#appointment-booking-form').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = {
            action: 'book_appointment',
            nonce: appointmentBooking.nonce,
            date: selectedDate,
            time: selectedTime,
            name: $('#customer-name').val(),
            company: $('#customer-company').val(),
            investor_type: $('#investor-type').val(),
            email: $('#customer-email').val(),
            phone: $('#customer-phone').val(),
            message: $('#customer-message').val(),
            website: $('#customer-website').val()
        };
        
        // Validate
        if (!formData.date || !formData.time || !formData.name || !formData.company || !formData.investor_type || !formData.email) {
            showError('Bitte füllen Sie alle Pflichtfelder aus.');
            return;
        }
        
        if (!$('#privacy-consent').is(':checked')) {
            showError('Bitte akzeptieren Sie die Datenschutzerklärung.');
            return;
        }
        
        // Show loading
        showLoading(true);
        hideError();
        
        // Send AJAX request
        $.ajax({
            url: appointmentBooking.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showSuccess(selectedDate, selectedTime);
                } else {
                    showError(response.data.message || 'Ein Fehler ist aufgetreten.');
                }
            },
            error: function() {
                showLoading(false);
                showError('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
            }
        });
    });
    
    // Show loading overlay
    function showLoading(show) {
        if (show) {
            $('#loading-overlay').addClass('visible');
        } else {
            $('#loading-overlay').removeClass('visible');
        }
    }
    
    // Show error message
    function showError(message) {
        const errorDiv = $('#error-message');
        errorDiv.find('.error-text').text(message);
        errorDiv.addClass('visible');
    }
    
    // Hide error message
    function hideError() {
        $('#error-message').removeClass('visible');
    }
    
    // Show error message for anfrage
    function showErrorAnfrage(message) {
        const errorDiv = $('#error-message-anfrage');
        errorDiv.find('.error-text').text(message);
        errorDiv.addClass('visible');
    }
    
    // Hide error message for anfrage
    function hideErrorAnfrage() {
        $('#error-message-anfrage').removeClass('visible');
    }
    
    // Show loading overlay for anfrage
    function showLoadingAnfrage(show) {
        if (show) {
            $('#loading-overlay-anfrage').addClass('visible');
        } else {
            $('#loading-overlay-anfrage').removeClass('visible');
        }
    }
    
    // Close error message
    $('.close-error').on('click', function() {
        hideError();
        hideErrorAnfrage();
    });
    
    // Show success message
    function showSuccess(date, time) {
        // Format date for display
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('de-DE');
        
        $('#success-date').text(formattedDate);
        $('#success-time').text(time);
        
        $('#appointment-booking-form').hide();
        $('#error-message').hide();
        $('#success-message').addClass('visible');
    }
    
    // Reset form for new booking
    $('#btn-new-booking').on('click', function() {
        location.reload();
    });
    
    // Handle anfrage form submission
    $('#anfrage-form').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = {
            action: 'submit_anfrage',
            nonce: appointmentBooking.nonce,
            name: $('#anfrage-name').val(),
            company: $('#anfrage-company').val(),
            investor_type: $('#anfrage-investor-type').val(),
            email: $('#anfrage-email').val(),
            phone: $('#anfrage-phone').val(),
            message: $('#anfrage-message').val(),
            website: $('#anfrage-website').val()
        };
        
        // Validate
        if (!formData.name || !formData.company || ($('#anfrage-investor-type').length && !formData.investor_type) || !formData.email || !formData.message) {
            showErrorAnfrage('Bitte füllen Sie alle Pflichtfelder aus.');
            return;
        }
        
        if (!$('#privacy-consent-anfrage').is(':checked')) {
            showErrorAnfrage('Bitte akzeptieren Sie die Datenschutzerklärung.');
            return;
        }
        
        // Show loading
        showLoadingAnfrage(true);
        hideErrorAnfrage();
        
        // Send AJAX request
        $.ajax({
            url: appointmentBooking.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                showLoadingAnfrage(false);
                
                if (response.success) {
                    $('#anfrage-form').hide();
                    $('#success-message-anfrage').addClass('visible');
                } else {
                    showErrorAnfrage(response.data.message || 'Ein Fehler ist aufgetreten.');
                }
            },
            error: function() {
                showLoadingAnfrage(false);
                showErrorAnfrage('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
            }
        });
    });
    
    // Reset anfrage form
    $('#btn-new-anfrage').on('click', function() {
        location.reload();
    });
});
