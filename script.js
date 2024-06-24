 jQuery(document).ready(function($) {
    $('#staff-select').on('change', function() {
        var staff_id = $(this).val();
        $.ajax({
            url: sbp_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'sbp_get_services',
                staff_id: staff_id,
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var services = response.data;
                    var serviceSelect = $('#service-select');
                    serviceSelect.empty().append('<option value="">Select Service</option>');
                    services.forEach(function(service) {
                        serviceSelect.append('<option value="' + service.id + '">' + service.service_name + '</option>');
                    });
                }
            }
        });
    });

    $('#service-select').on('change', function() {
        var staff_id = $('#staff-select').val();
        var service_id = $(this).val();
        var dateInput = $('#booking-date');

        dateInput.on('change', function() {
            var date = $(this).val();
            $.ajax({
                url: sbp_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'sbp_get_available_slots',
                    staff_id: staff_id,
                    service_id: service_id,
                    date: date,
                    nonce: sbp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var availableSlots = response.data;
                        var slotsContainer = $('#time-slots-container');
                        slotsContainer.empty();
                        availableSlots.forEach(function(slot) {
                            slotsContainer.append('<div class="sbp-time-slot" data-time="' + slot + '">' + slot + '</div>');
                        });

                        $('.sbp-time-slot').on('click', function() {
                            $('.sbp-time-slot').removeClass('selected');
                            $(this).addClass('selected');
                        });
                    }
                }
            });
        });
    });

    $('#booking-form').on('submit', function(e) {
        e.preventDefault();

        var staff_id = $('#staff-select').val();
        var service_id = $('#service-select').val();
        var date = $('#booking-date').val();
        var time = $('.sbp-time-slot.selected').data('time');
        var name = $('#name').val();
        var email = $('#email').val();
        var telephone = $('#telephone').val();

        $.ajax({
            url: sbp_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'sbp_handle_booking',
                staff_id: staff_id,
                service_id: service_id,
                date: date,
                time: time,
                name: name,
                email: email,
                telephone: telephone,
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                var resultContainer = $('#booking-result');
                if (response.success) {
                    resultContainer.html('<p class="success">' + response.data + '</p>');
                    $('#booking-form')[0].reset();
                    $('#time-slots-container').empty();
                } else {
                    resultContainer.html('<p class="error">' + response.data + '</p>');
                }
            }
        });
    });
});
