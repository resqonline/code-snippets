<?php
/**
 * Get GF entries via GF Web API
 */
function calculate_signature( $string, $private_key ) {
    $hash = hash_hmac( 'sha1', $string, $private_key, true );
    $sig = rawurlencode( base64_encode( $hash ) );
    return $sig;
}

function get_rp_gf_entries(){

	$base_url = get_field('url_rp', 'options');
	$api_key = get_field('gf_api_key', 'options');
	$private_key = get_field('gf_private_key', 'options');

	$method  = 'GET';
	$route = 'forms/1;2/entries';
	$expires = strtotime( '+60 mins' );
	$string_to_sign = sprintf( '%s:%s:%s:%s', $api_key, $method, $route, $expires );
	$sig = calculate_signature( $string_to_sign, $private_key );

	$page_size = 5;
	$offset = 0;
	if ( isset( $_GET['paging']['offset'] ) ){
	    $offset = $_GET['paging']['offset'];
	}
	
	$url = $base_url . 'gravityformsapi/' . $route . '/?api_key=' . $api_key . '&signature=' . $sig . '&expires=' . $expires . '&paging[page_size]=' . $page_size . '&paging[offset]=' . $offset;

	$search = array(
        'field_filters' => array (
			array(
			    'key'       => 'fgmarke',
			    'operator'  => 'is',
			    'value'     => '24seniorcare'
			),
		),
    );
	$search_json = urlencode( json_encode( $search ) );
	$url .= '&search=' . $search_json;

	$response = wp_remote_request( $url, array( 'method' => $method ) );	 
	if ( is_array( $response ) ) {
	  $response_code = wp_remote_retrieve_response_code( $response );
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
	    <div class="lead-liste">
        <?php
        if ( $total_retrieved > 0 ) {
            foreach ( $entries as $entry ){
            	$leadname = $entry['1.2'] .' ' . $entry['1.3'] .' ' . $entry['1.6'];
            	$firstlast = $entry['1.3'] .' ' . $entry['1.6'];
            	$leadadresse = $entry['9.1'] .', '. $entry['9.3'] .', '. $entry['9.5'] .', '. $entry['9.6'];
            	$leadposttitle = '';
            	if ( '1' == $entry['form_id']) {
            		$formname = 'Exposé';
            		$leadpost = 'Kampagne';
            		$pdfexport = get_field('gravity_pdf_entry_export_1', 'options');
            		$leadexporturl = $base_url.'pdf/'.$pdfexport.'/'.$entry['id'];
            	}
           		if ( '2' == $entry['form_id']) {
            		$formname = 'Datenblatt';
            		$leadpost = 'Marke';
            		$leadposttitle = $entry['fgmarke'];
            		$pdfexport = get_field('gravity_pdf_entry_export_2', 'options');
            		$leadexporturl = $base_url.'pdf/'.$pdfexport.'/'.$entry['id'];	
            	}

                ?>
                <div class="lead-entry">
	                <div class="lead-item-title" data-content-id="lead-<?php echo $entry['id'] ?>">
		                <span class="lead-name"><?php echo $leadname ?></span>
		                <span class="lead-ort"><?php echo $entry['kampagneort'] ?></span>
		                <span class="lead-datum"><?php echo date("d.m.y", strtotime($entry['date_created'])); ?></span>
	                </div>
	                <div id="lead-<?php echo $entry['id'] ?>" class="lead-item-content">
	                <?php printf('<p>%s hat Ihr %s auf UNTERNEHMER-GESUCHT.COM zu Ihrer %s <b>%s</b> angefordert.</p>', $leadname, $formname, $leadpost, $leadposttitle); ?>
	                <p><?php _e('Folgende Kontaktangaben wurden hinterlassen:', 'fpu-dashboard'); ?></p>
	                <?php printf('<p><span class="label">Name</span>%s<br><span class="label">Adresse</span>%s</p>', $firstlast, $leadadresse);?>
	                <?php printf('<p><span class="label">E-Mail</span>%s<br><span class="label">Telefon</span>%s</p>', $entry['2'], $entry['8']);?>
	                <?php printf('<p>%s</p>', wpautop($entry['13'])); ?>
	                	<div class="action-links"><a class="leadexport" href="<?php echo $leadexporturl ?>" target="_blank">Anfrage exportieren/drucken</a></div>
	                </div>
            	</div>
            <?}
        }
        if ( $total > $total_retrieved ){
            //paging in effect
            $query_string = $_SERVER['QUERY_STRING'];
            parse_str( $query_string, $params );
            $paging_link = '';
            if ( $total_retrieved == $page_size ){
                //see if previous link needs to be built
                $page_offset = isset( $params['paging']['offset'] ) ? $params['paging']['offset'] : 0;
                if ( $page_offset <> 0 ) {
                    $previous_page_offset = $params['paging']['offset'] - $page_size;
                    if ( $previous_page_offset < 0 ){
                        $previous_page_offset = 0;
                    }
                    $params['paging']['offset'] = $previous_page_offset;
                    $page_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?' . http_build_query( $params );
                    $paging_link = '<a href="' . $page_url . '"><i class="fa fa-chevron-left"></i></a>';
                }
                $params['paging']['offset'] = $page_offset + $page_size;
                $page_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?' . http_build_query( $params );
                $paging_link .= '<a href="' . $page_url . '"><i class="fa fa-chevron-right"></i></a>';
            }
            else {
                $page_offset = $params['paging']['offset'] - $page_size;
                if ( $page_offset < 0 ){
                    $page_offset = 0;
                }
                $params['paging']['offset'] = $page_offset;
                $page_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?' . http_build_query( $params );
                $paging_link = '<a href="' . $page_url . '"><i class="fa fa-chevron-left"></i></a>';
            }
 
            echo $paging_link;
        }
        ?>
	    </div>
	<?
}

echo '<section id="rpleads" class="dashboard-section"><h2>Ihre Partner-Anfragen für ...</h2><div class="section-content">';
	get_rp_gf_entries();
echo '</div></section>';

?>
