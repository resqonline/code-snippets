function calculate_signature( $string, $private_key ) {
    $hash = hash_hmac( 'sha1', $string, $private_key, true );
    $sig = rawurlencode( base64_encode( $hash ) );
    return $sig;
}

function get_rp_gf_entries(){

	$base_url = get_field('url_rp', 'options');
	$api_key = get_field('gf_api_key', 'options');
	$private_key = get_field('gf_private_key');

	$method  = 'GET';
	$route = 'forms';
	$expires = strtotime( '+60 mins' );
	$string_to_sign = sprintf( '%s:%s:%s:%s', $api_key, $method, $route, $expires );
	$sig = calculate_signature( $string_to_sign, $private_key );
	
	$url = $base_url . 'gravityformsapi/' . $route . '/?api_key=' . $api_key . '&signature=' . $sig . '&expires=' . $expires;

	echo '<pre>'.$url.'</pre>';

	$response = wp_remote_request( $url, array( 'method' => $method ) );	 
	if ( is_array( $response ) ) {
	  $response_code = wp_remote_retrieve_response_code( $response );
	  echo '<pre>Status:'.$response_code.'</pre>';
	}
	 
	$body_json = wp_remote_retrieve_body( $response );
	//results are in the "body" and are json encoded, decode them and put into an array
	$body = json_decode( $body_json, true );
	 
	$data            = $body['response'];
	$status_code     = $body['status'];
	$total           = 0;
	$total_retrieved = 0;
	 
	if ( $status_code <= 202 ){
	    //entries retrieved successfully
	    $entries = $data['entries'];
	    $status  = $status_code;
	    $total              = $data['total_count'];
	    $total_retrieved    = count( $entries );
	}
	else {
	    //entry retrieval failed, get error information
	    $error_code         = $data['code'];
	    $error_message      = $data['message'];
	    $error_data         = isset( $data['data'] ) ? $data['data'] : '';
	    $status             = $status_code . ' - ' . $error_code . ' ' . $error_message . ' ' . $error_data;
	}
	//display results in a simple page
	?>
	    <div>Status Code: <?php echo $status ?></div>
	    <div>Total Count: <?php echo $total; ?></div>
	    <div>Total Retrieved: <?php echo $total_retrieved; ?></div>
	    <div>JSON Response:<br/><textarea style="vertical-align: top" cols="125" rows="10"> <?php echo $response['body']; ?></textarea></div>
	    <br/>
	    <div>
	        <?php
	        if ( $total_retrieved > 0 ) {
	            echo '<table border="1"><th>Form ID</th><th>Entry ID</th><th>Date Created</th>';
	            foreach ( $entries as $entry ){
	                echo '<tr><td>' . $entry['form_id'] . '</td><td>' . $entry['id'] . '</td><td>' . $entry['date_created'] . '</td></tr>';
	            }
	            echo '</table>';
	        }
	        ?>
	    </div>
	<?
}
