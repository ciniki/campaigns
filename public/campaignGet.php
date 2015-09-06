<?php
//
// Description
// ===========
// This method will return all the information about an campaign.
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
function ciniki_campaigns_campaignGet($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'campaign_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Campaign'), 
        'stats'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Statistics'), 
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
    $rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignGet'); 
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

	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
	$date_format = ciniki_users_dateFormat($ciniki, 'php');
	$mysql_time_format = ciniki_users_timeFormat($ciniki, 'mysql');
	$php_time_format = ciniki_users_timeFormat($ciniki, 'php');

	//
	// Load the module settings
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
	$rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_campaign_settings', 'business_id', $args['business_id'], 'ciniki.campaigns', 'settings', '');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$settings = isset($rc['settings'])?$rc['settings']:array();

	//
	// Load campaign maps
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'maps');
	$rc = ciniki_campaigns_maps($ciniki);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$maps = $rc['maps'];

	
	if( $args['campaign_id'] == '0' ) {
		$campaign = array('id'=>0,
			'name'=>'',
			'status'=>'0',
			'flags'=>'0',
			'delivery_time'=>(isset($settings['default-delivery-time'])?$settings['default-delivery-time']:'00:00'),
			);
	} else {
		$strsql = "SELECT ciniki_campaigns.id, "
			. "ciniki_campaigns.name, "
			. "ciniki_campaigns.tracking_uuid, "
			. "ciniki_campaigns.status, "
			. "ciniki_campaigns.status AS status_text, "
			. "ciniki_campaigns.flags, "
			. "DATE_FORMAT(ciniki_campaigns.delivery_time, '" . ciniki_core_dbQuote($ciniki, $mysql_time_format) . "') AS delivery_time "
			. "FROM ciniki_campaigns "
			. "WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND ciniki_campaigns.id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
			array('container'=>'campaigns', 'fname'=>'id', 'name'=>'campaign',
				'fields'=>array('id', 'name', 'tracking_uuid', 'status', 'status_text', 'flags', 'delivery_time'),
				'maps'=>array('status_text'=>$maps['campaign']['status'])),
		));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( !isset($rc['campaigns']) || !isset($rc['campaigns'][0]) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2513', 'msg'=>'Unable to find campaign'));
		}
		$campaign = $rc['campaigns'][0]['campaign'];

		if( ($campaign['flags']&0x01) == 0 ) {
			if( isset($settings['default-delivery-time']) && $settings['default-delivery-time'] != '' ) {
				$campaign['delivery_time'] = $settings['default-delivery-time'];
			} else {
				$campaign['delivery_time'] = '12:00 am';
			}
		}

		//
		// Get the list of emails
		//
		if( isset($args['emails']) && $args['emails'] == 'yes' ) {
			$campaign['emails'] = array();
			$strsql = "SELECT ciniki_campaign_emails.id, "
				. "ciniki_campaign_emails.status, "
				. "ciniki_campaign_emails.status AS status_text, "
				. "ciniki_campaign_emails.days_from_start, "
				. "ciniki_campaign_emails.subject "
				. "FROM ciniki_campaign_emails "
				. "WHERE ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
				. "AND ciniki_campaign_emails.campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
				. "ORDER BY days_from_start "
				. "";
			$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
				array('container'=>'emails', 'fname'=>'id', 'name'=>'email',
					'fields'=>array('id', 'status', 'status_text', 'days_from_start', 'subject'),
					'maps'=>array('status_text'=>$maps['email']['status'])),
				));
			if( $rc['stat'] != 'ok' ) {
				return $rc;
			}
			if( isset($rc['emails']) ) {
				$campaign['emails'] = $rc['emails'];
			}
		}

		//
		// Get the statistics for the campaign, if requested
		//
		if( isset($args['stats']) && $args['stats'] == 'yes' ) {
			$strsql = "SELECT ciniki_campaign_customers.status, "		
				. "ciniki_campaign_customers.status AS status_text, "
				. "COUNT(ciniki_campaign_customers.customer_id) AS num_customer "
				. "FROM ciniki_campaign_customers "
				. "WHERE ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
				. "AND ciniki_campaign_customers.campaign_id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
				. "GROUP BY ciniki_campaign_customers.status "
				. "ORDER BY ciniki_campaign_customers.status "
				. "";
			$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
				array('container'=>'customers', 'fname'=>'status', 'name'=>'stat',
					'fields'=>array('status', 'status_text', 'num_customers')),
				));
			if( $rc['stat'] != 'ok' ) {
				return $rc;
			}
			if( isset($rc['customers']) ) {
				$campaign['customer_stats'] = $rc['customers'];
			}
		}
	}

	return array('stat'=>'ok', 'campaign'=>$campaign);
}
?>
