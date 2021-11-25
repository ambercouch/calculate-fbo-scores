(function($) {

	function calculate_scores(current_request, total_requests, rows_per_request) {

		// current_request = 345;
		// total_requests = 345;


		var rows_offset = ( current_request == 1 ? 0 : ( ( current_request - 1 ) * rows_per_request ) );
		var first_request = ( current_request == 1 ? true : false );
		var last_request = ( current_request == total_requests ? true : false );
		var percentage = ( current_request / total_requests ) * 100;

		var refresh_url = $('#calculate-fbo-scores').attr('data-page-url');

		var data = {
			'nonce': calculate_fbo_scores.nonce,
			'action': 'fbo_calculate_scores',
			'rows_offset': rows_offset,
			'rows_limit': rows_per_request,
			'first_request': first_request,
			'last_request': last_request
		};

		$.ajax({
			url: calculate_fbo_scores.ajax_url,
			data: data,
			type: 'post',
			success: function(response) {
				console.log('Some success current');
				console.log(current_request);
				console.log(response);
				if (response.success) {

						$('#progress-holder .progress span').text( current_request + ' Of ' + total_requests + '=' + percentage.toFixed(2) );

					if(last_request) {
						window.location = refresh_url + '&success';
					}else{
						calculate_scores(current_request + 1, total_requests, rows_per_request);
					}
				}else{
					window.location = refresh_url + '&error=' + encodeURI(response.data);
				}
			},
			error: function (response) {
				console.log('Some error current');
				console.log(current_request);
				console.log(response);
				if(last_request) {
					window.location = refresh_url + '&success';
				}else{
					calculate_scores(current_request + 1, total_requests, rows_per_request);
				}
			}
		});

	}

	$(document).on('click', '#calculate-fbo-scores[data-active="0"]', function() {

		var $button = $(this);
		var $progress_holder = $('#progress-holder');

		var total_rows = parseInt($progress_holder.attr('data-total-users'));
		var rows_per_request = 50;

		var total_requests = Math.ceil(total_rows / rows_per_request);

		$button.attr('data-active', 1);
		$progress_holder.removeClass('hidden');

		calculate_scores( 1, total_requests, rows_per_request );

	});

})(jQuery);
