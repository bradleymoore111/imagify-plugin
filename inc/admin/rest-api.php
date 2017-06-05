<?php
/**
 * REST API functions and routes
 *
 * @package imagify-plugin
 */
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

//error_log("this statement is false"); // Yea I think we're done with this
add_action( 'rest_api_init', function () {
	// error_log("...and yet, true?");
	register_rest_route( 'imagify/v1', 'bulk_upload/', array(
		'methods'	 => 'POST',
		'callback' => 'imagify_rest_bulk_upload',
	));
});

add_action( 'rest_api_init', function () {
	register_rest_route( 'imagify/v1', 'send_supplemental/', array(
		'methods'	 => 'POST',
		'callback' => 'send_supplemental',
	));
});


/**
 * Imagify Bulk Upload through REST API, takes in a single image ID and url.
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function imagify_rest_bulk_upload( $data ) {
	// error_log(print_r( $data, true ) );
	// FUCKING. NEAT.

	error_log("bulk upload: " . $data['image'] . ", " . $data['context']);
	//check_ajax_referer( 'imagify-bulk-upload', 'imagifybulkuploadnonce' ); // shit I missed this

	// Oh for fucks sake.

	if ( ! isset( $data['image'], $data['context'] ) ){ //  || ! current_user_can( 'upload_files' ) ) {
		error_log("		gosh darnit1: " . current_user_can( 'upload_files' ) );
		return rest_ensure_response( array( 'success' => false ) );
		//wp_send_json_error();
	}

	$class_name         = get_imagify_attachment_class_name( $data['context'] );
	error_log("	classname: " . $class_name);
	$attachment         = new $class_name( $data['image'] ); // pretty much always Imagify_Attachment
	$optimization_level = get_transient( 'imagify_bulk_optimization_level' );

	error_log('	before restore');
	// Restore it if the optimization level is updated
	if ( $optimization_level !== $attachment->get_optimization_level() ) {
		$attachment->restore();
	}

	// Optimize it!!!!!
	$attachment->optimize( $optimization_level );

	error_log('	before return');
	// Return the optimization statistics
	$fullsize_data         = $attachment->get_size_data();
	$stats_data            = $attachment->get_stats_data();
	$user		   		   = new Imagify_User();
	$result                  = array();

	if ( ! $attachment->is_optimized() ) { // Not optimized?
		$result['success'] = false;
		$result['error']   = $fullsize_data['error'];
		error_log("		gosh darnit2");
		return rest_ensure_response( array( 'error' => $fullsize_data['error'] , 'data' => $result ) );
		wp_send_json_error( $result );
	}

	$result['success']               = true;
	$result['original_size']         = $fullsize_data['original_size'];
	$result['new_size']              = $fullsize_data['optimized_size'];
	$result['percent']               = $fullsize_data['percent'];
	$result['overall_saving'] 	   = $stats_data['original_size'] - $stats_data['optimized_size'];
	$result['original_overall_size'] = $stats_data['original_size'];
	$result['new_overall_size']      = $stats_data['optimized_size'];
	$result['thumbnails']            = $attachment->get_optimized_sizes_count();

	error_log("		we did it bois, we saved the city"); // WAIT QUE. OK so it gets here, and it returns a response, but the response isn't being interpeted correctly. Hm. OH I think I know why...?

	return rest_ensure_response( array( 'error' => false, 'success' => true, 'data' => $result ) ); // so it looks like responses aren't being read properly?

	wp_send_json_success( $result );

	return $this->send_response( $result );
}


function imagify_rest_send_supplemental() {
	$result['msg'] = 'Success';

	return $this->send_response( $result );
}

/**
 * Send the respone back to the caller
 *
 * @param  [Mixed] $result : Array with the server response.
 */
function imagify_rest_send_response( $result ) {
	$requested_with = filter_input( INPUT_SERVER, 'HTTP_X_REQUESTED_WITH' );
	$http_referer = filter_input( INPUT_SERVER, 'HTTP_REFERER' );
	if ( ! empty( $requested_with ) && 'xmlhttprequest' === strtolower( $requested_with ) ) {
		return rest_ensure_response( $result );
	} else {
		header( 'Location: '. $http_referer );
	}
}

function imagify_rest_permission_check() {
	return current_user_can( 'edit_posts' );
}
