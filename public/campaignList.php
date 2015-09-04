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
	// Get the distinct list of tags
	//
	$strsql = "SELECT ciniki_campaigns.id, "
		. "ciniki_campaigns.name, "
		. "ciniki_campaigns.tracking_uuid, "
		. "ciniki_campaigns.status "
		. "FROM ciniki_campaigns "
		. "WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "ORDER BY ciniki_campaigns.name "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.campaigns', array(
		array('container'=>'campaigns', 'fname'=>'id', 'name'=>'campaign',
			'fields'=>array('id', 'name', 'tracking_uuid', 'status')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( isset($rc['campaigns']) ) {
		$campaigns = $rc['campaigns'];
	}

	return array('stat'=>'ok', 'campaigns'=>$campaigns);
}
?>
