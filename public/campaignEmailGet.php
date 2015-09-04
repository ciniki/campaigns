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
function ciniki_campaigns_campaignEmailGet($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'email_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Email'), 
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
    $rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignEmailGet'); 
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

	if( $args['email_id'] == 0 ) {
		$campaign = array('id'=>0,
			'status'=>'10',
			'days_from_start'=>'',
			'subject'=>'',
			'html_content'=>'',
			'text_content'=>'',
			);
	} else {
		$strsql = "SELECT ciniki_campaigns.id, "
			. "ciniki_campaigns.status, "
			. "ciniki_campaigns.status AS status_text "
			. "ciniki_campaigns.days_from_start, "
			. "ciniki_campaigns.subject, "
			. "ciniki_campaigns.html_content, "
			. "ciniki_campaigns.text_content "
			. "FROM ciniki_campaigns ";
			. "WHERE ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND ciniki_campaign_emails.id = '" . ciniki_core_dbQuote($ciniki, $args['email_id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
			array('container'=>'emails', 'fname'=>'id', 'name'=>'email',
				'fields'=>array('id', 'status', 'status_text', 'days_from_start', 'subject', 'html_content', 'text_content'),
				'maps'=>array('status_text'=>$maps['email']['status'])),
		));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( !isset($rc['emails']) || !isset($rc['emails'][0]) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2517', 'msg'=>'Unable to find campaign email'));
		}
		$email = $rc['emails'][0]['email'];
	}

	return array('stat'=>'ok', 'email'=>$email);
}
?>
