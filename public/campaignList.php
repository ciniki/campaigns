<?php
//
// Description
// -----------
// This method will return the list of campaigns for a business.  It is restricted
// to business owners and sysadmins.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to get campaigns for.
//
// Returns
// -------
//
function ciniki_campaigns_campaignList($ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
    //  
    // Check access to business_id as owner, or sys admin. 
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
    $rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignList');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

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
	// Get the list of campaigns
	//
	$strsql = "SELECT ciniki_campaigns.id, "
		. "ciniki_campaigns.name, "
		. "ciniki_campaigns.tracking_uuid, "
		. "ciniki_campaigns.flags, "
		. "ciniki_campaigns.delivery_time, "
		. "ciniki_campaigns.status AS campaign_status, "
		. "ciniki_campaigns.status AS campaign_status_text, "
		. "ciniki_campaign_customers.status AS campaing_customer_status, "
		. "ciniki_campaign_customers.status AS campaing_customer_status_text, "
		. "COUNT(ciniki_campaign_customers.customer_id) AS num_customers "
		. "FROM ciniki_campaigns "
		. "LEFT JOIN ciniki_campaign_customers ON ("
			. "ciniki_campaigns.id = ciniki_campaign_customers.campaign_id "
			. "AND ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. ") "
		. "WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "GROUP BY ciniki_campaigns.id, ciniki_campaign_customers.status "
		. "ORDER BY ciniki_campaigns.name "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
		array('container'=>'campaigns', 'fname'=>'id', 'name'=>'campaign',
			'fields'=>array('id', 'name', 'tracking_uuid', 'status'=>'campaign_status', 'status_text'=>'campaign_status_text'),
			'maps'=>array('campaign_status_text'=>$maps['campaign']['status']),
			),
		array('container'=>'stats', 'fname'=>'id', 'name'=>'stat',
			'fields'=>array('status'=>'campaing_customer_status', 'status_text'=>'campaing_customer_status_text', 'num_customers'),
			'maps'=>array('campaign_customer_status_text'=>$maps['customer']['status']),
			),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( isset($rc['campaigns']) ) {
		$campaigns = $rc['campaigns'];
		foreach($campaigns as $cid => $campaign) {
			if( isset($campaign['campaign']['stats']) ) {
				foreach($campaign['campaign']['stats'] as $stat) {
					switch($stat['stat']['status']) {
						case '10': $campaigns[$cid]['campaign']['num_active'] = $stat['stat']['num_customers'];
						case '20': $campaigns[$cid]['campaign']['num_stopped'] = $stat['stat']['num_customers'];
						case '30': $campaigns[$cid]['campaign']['num_successful'] = $stat['stat']['num_customers'];
						case '40': $campaigns[$cid]['campaign']['num_completed'] = $stat['stat']['num_customers'];
						case '50': $campaigns[$cid]['campaign']['num_unsubscribed'] = $stat['stat']['num_customers'];
					}
				}
			}
		}
	} else {
		$campaigns = array();
	}

	return array('stat'=>'ok', 'campaigns'=>$campaigns);
}
?>
