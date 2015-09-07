<?php
//
// Description
// -----------
// This function will add a customer to the campaign and setup the campaign queue for the emails.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_campaigns_customerAdd(&$ciniki, $business_id, $campaign_id, $customer_id) {

	//
	// Load the business intl settings
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
	$rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$intl_timezone = $rc['settings']['intl-default-timezone'];
	$utc_tz = new DateTimeZone('UTC');
	$business_tz = new DateTimeZone($intl_timezone);

	//
	// Load the module settings
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
	$rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_campaign_settings', 'business_id', $business_id, 'ciniki.campaigns', 'settings', '');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$settings = isset($rc['settings'])?$rc['settings']:array();

	//
	// Load the campaign and emails
	//
	$strsql = "SELECT name, tracking_uuid, flags, delivery_time "
		. "FROM ciniki_campaigns "
		. "WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_campaigns.id = '" . ciniki_core_dbQuote($ciniki, $campaign_id) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'campaign');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['campaign']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2526', 'msg'=>'Unable to find campaign'));
	}
	$campaign = $rc['campaign'];

	//
	// Setup delivery time
	//
	if( ($campaign['flags']&0x01) == 0x01 ) {
		$default_delivery_time = $campaign['delivery_time'];
	} elseif( isset($settings['default-delivery-time']) && $settings['default-delivery-time'] != '' ) {
		$default_delivery_time = $settings['default-delivery-time'];
	} else {
		$default_delivery_time = '06:00:00';
	}

	//
	// Load the emails for the campaign
	//
	$strsql = "SELECT status, flags, delivery_time, days_from_start "
		. "FROM ciniki_campaign_emails "
		. "WHERE ciniki_campaign_emails.campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
		. "AND ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_campaign_emails.status = '10' "
		. "ORDER BY ciniki_campaign_emails.days_from_start "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'email');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$emails = array();
	if( isset($rc['rows']) ) {
		$emails = $rc['rows'];
	}

	//
	// Check to make sure customer exists
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerStatus');
	$rc = ciniki_customers_hooks_customerStatus($ciniki, $business_id, $args['customer_id']);
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2527', 'msg'=>'Customer does not exist'));
	}
	if( !isset($rc['customer']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2528', 'msg'=>'Customer does not exist'));
	}

	//
	// Setup default dates
	//
	$utc_start_date = new DateTime('now', $utc_tz);
	$start_date = new DateTime('now', $business_tz);
	$delivery_start_date = new DateTime($default_delivery_time, $business_tz);

	//
	// Attach the customer to the campaign
	//
	$customer_args = array(
		'campaign_id'=>$args['campaign_id'],
		'customer_id'=>$args['customer_id'],
		'utc_start_date'=>$utc_start_date->format('Y-m-d H:i:s'),
		'start_date'=>$start_date->format('Y-m-d'),
		'status'=>'10',
		);
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
	$rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.campaigns.customer', $args, 0x04);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$campaign_customer_id = $rc['id'];

	//
	// Add the emails to the queue
	//
	$send_immediately = array();
	foreach($campaign['emails'] as $email) {
		//
		// Setup the object args
		//
		$queue_args = array(
			'campaign_id'=>$args['campaign_id'],
			'campaign_customer_id'=>$campaign_customer_id,
			'campaign_email_id'=>$email['id'],
			'status'=>'10',
			);

		//
		// day 0 emails should ignore delivery time and be queued to send immediately
		//
		if( $email['days_from_start'] == '0' ) {
			$queue_args['send_date'] = $utc_start_date->format('Y-m-d H:i:s');
		} 
		
		//
		// Calculate when the send date should be for the email in UTC
		//
		else {
			//
			// email has a specific delivery time
			//
			if( ($email['flags']&0x01) == 0x01 && $email['delivery_time'] != '' ) {
				$send_date = new DateTime($delivery_start_date->format('Y-m-d') . ' ' . $email['delivery_time'], $business_tz);
			} 
			//
			// Use campaign or default settings determined earlier
			//
			else {
				$send_date = clone $delivery_start_date;
				$send_date->add(new DateInterval('P' . $email['days_from_start'] . 'D'));
			}

			//
			// Convert to UTC and format for insert
			//
			$send_date->setTimezone($utc_tz);
			$queue_args['send_date'] = $send_date->format('Y-m-d H:i:s');
		}

		//
		// Insert into the queue
		//
		$rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.campaigns.queue', $queue_args, 0x04);
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		$queue_id = $rc['id'];

		//
		// Send any emails that are marked as day 0
		//
		if( $email['days_from_start'] == '0' ) {
			$send_immediately[] = $queue_id;
		}
	}

	//
	// Check for emails to send immediately
	//
	foreach($send_immediately AS $queue_id) {
		$rc = ciniki_campaigns_sendMail($ciniki, $business_id, $queue_id);
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
	}

	return array('stat'=>'ok');
}
?>
