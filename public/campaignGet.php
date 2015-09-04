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
	$date_format = ciniki_users_dateFormat($ciniki, 'php');

	//
	// Load campaign maps
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'maps');
	$rc = ciniki_campaigns_maps($ciniki);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$maps = $rc['maps'];

	if( $args['campaign_id'] == 0 ) {
		$campaign = array('id'=>0,
			'name'=>'',
			'status'=>'10',
			);
	} else {
		$strsql = "SELECT ciniki_campaigns.id, "
			. "ciniki_campaigns.name, "
			. "ciniki_campaigns.tracking_uuid, "
			. "ciniki_campaigns.status, "
			. "ciniki_campaigns.status AS status_text "
			. "FROM ciniki_campaigns ";
			. "WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND ciniki_campaigns.id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
			array('container'=>'campaigns', 'fname'=>'id', 'name'=>'campaign',
				'fields'=>array('id', 'name', 'tracking_uuid', 'status', 'status_text'),
				'maps'=>array('status_text'=>$maps['campaign']['status'])),
		));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( !isset($rc['campaigns']) || !isset($rc['campaigns'][0]) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2513', 'msg'=>'Unable to find campaign'));
		}
		$campaign = $rc['campaigns'][0]['campaign'];
	}


	//
	// Get the list of emails
	//
	if( isset($args['emails'] && $args['emails'] == 'yes' ) {
		$campaign['emails'] = array();
		$strsql = "SELECT ciniki_campaign_emails.id, "
			. "ciniki_campaign_emails.status, "
			. "ciniki_campaign_emails.status AS status_text, "
			. "ciniki_campaign_emails.days_from_start, "
			. "ciniki_campaign_emails.subject "
			. "FROM ciniki_campaign_emails "
			. "WHERE ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND ciniki_campaign_emails.id = '" . ciniki_core_dbQuote($ciniki, $args['campaign_id']) . "' "
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

	return array('stat'=>'ok', 'campaign'=>$campaign);
}
?>
