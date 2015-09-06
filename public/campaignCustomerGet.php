<?php
//
// Description
// ===========
// This method will return all the information about an campaign customer.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business the campaign is attached to.
// campaign_id:		The ID of the campaign to get the details for.
// 
// Returns
// -------
//
function ciniki_campaigns_campaignCustomerGet($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'campaign_customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Campaign Customer'), 
        'emails'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Emails'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
    $rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignCustomerGet'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
	$modules = $rc['modules'];

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');

	//
	// Load the business intl settings
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
	$rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$intl_timezone = $rc['settings']['intl-default-timezone'];

	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
	$datetime_format = ciniki_users_dateFormat($ciniki, 'php');
	$date_format = ciniki_users_dateFormat($ciniki, 'mysql');

	//
	// Load campaign maps
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'maps');
	$rc = ciniki_campaigns_maps($ciniki);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$maps = $rc['maps'];

	//
	// Get the campaign customer
	//
	$strsql = "SELECT ciniki_campaign_customers.id, "
		. "ciniki_campaign_customers.campaign_id, "
		. "ciniki_campaign_customers.customer_id, "
		. "ciniki_campaign_customers.utc_start_date, "
		. "DATE_FORMAT(ciniki_campaign_customers.start_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') AS start_date, "
		. "ciniki_campaign_customers.status, "
		. "ciniki_campaign_customers.status AS status_text "
		. "FROM ciniki_campaign_customers "
		. "WHERE ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND ciniki_campaign_customers.id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_customer_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
		array('container'=>'customers', 'fname'=>'id', 'name'=>'customer',
			'fields'=>array('id', 'campaign_id', 'customer_id', 'utc_start_date', 'start_date', 'status', 'status_text'),
			'maps'=>array('status_text'=>$maps['campaign']['status']),
			'utctotz'=>array('utc_start_date'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format)),
			),
	));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['customers']) || !isset($rc['customers'][0]) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2529', 'msg'=>'Unable to find campaign customer'));
	}
	$customer = $rc['customers'][0]['customer'];

	//
	// Get the customer details
	//
	if( $customer['customer_id'] > 0 ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
		$rc = ciniki_customers_hooks_customerDetails($ciniki, $args['business_id'],
		array('customer_id'=>$customer['customer_id'], 'addresses'=>'yes', 'phones'=>'yes', 'emails'=>'yes'));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		$customer['customer_details'] = $rc['details'];
	} else {
		$customer['customer_details'] = array();
	}

	//
	// Get the list of emails sent and queued for the customer
	//
	if( isset($args['emails'] && $args['emails'] == 'yes' ) {
		//
		// Get the list of send emails
		//
		$customer['sent_messages'] = array();
		$rc = ciniki_mail_hooks_objectMessages($ciniki, $args['business_id'], 
			array('object'=>'ciniki.campaigns.campaign', 'object_id'=>$customer['campaign_id']));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( isset($rc['messages']) ) {
			$customer['sent_messages'] = $rc['messages'];
		}
	
		//
		// Get the messages queued
		//
		$campaign['queued_messages'] = array();
		$strsql = "SELECT ciniki_campaign_queue.id, "
			. "ciniki_campaign_emails.id, "
			. "ciniki_campaign_emails.status, "
			. "ciniki_campaign_emails.status AS status_text, "
			. "ciniki_campaign_emails.days_from_start, "
			. "ciniki_campaign_emails.subject "
			. "FROM ciniki_campaign_queue, ciniki_campaign_emails "
			. "WHERE ciniki_campaign_queue.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND ciniki_campaign_queue.campaign_id = '" . ciniki_core_dbQuote($ciniki, $customer['campaign_id']) . "' "
			. "AND ciniki_campaign_queue.campaign_customer_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_customer_id']) . "' "
			. "AND ciniki_campaign_queue.campaign_email_id = ciniki_campaign_emails.id "
			. "AND ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "ORDER BY ciniki_campaign_queue.send_date "
			. "";
		$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
			array('container'=>'messages', 'fname'=>'id', 'name'=>'message',
				'fields'=>array('id', 'status', 'status_text', 'days_from_start', 'subject'),
				'maps'=>array('status_text'=>$maps['email']['status'])),
			));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( isset($rc['messages']) ) {
			$campaign['queued_messages'] = $rc['messages'];
		}
	}

	return array('stat'=>'ok', 'customer'=>$customer);
}
?>
