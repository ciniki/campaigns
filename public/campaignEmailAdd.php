<?php
//
// Description
// -----------
// This method will add a new campaign for the business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to add the campaign to.
// name:			The name of the campaign.
// url:				(optional) The URL for the campaign website.
// description:		(optional) The description for the campaign.
// start_date:		(optional) The date the campaign starts.  
// end_date:		(optional) The date the campaign ends, if it's longer than one day.
//
// Returns
// -------
// <rsp stat="ok" id="42">
//
function ciniki_campaigns_campaignEmailAdd(&$ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'campaign_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Campaign'), 
		'subject'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Subject'), 
		'status'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Status'), 
		'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Flags'), 
		'delivery_time'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Delivery Time'), 
		'days_from_start'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Days From Start'), 
		'subject'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Subject'), 
		'html_content'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'HTML Content'), 
		'text_content'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Text Content'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id as owner
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'private', 'checkAccess');
	$rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignAdd');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Check to make sure either html or text content is supplied
	//
	if( (!isset($args['html_content']) || $args['html_content'] == '') && (!isset($args['text_content']) || $args['text_content'] == '') ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2515', 'msg'=>'No content specified'));
	}
	if( !isset($args['text_content']) || $args['text_content'] == '' ) {
		$args['text_content'] = strip_tags($args['html_content']);
	}
	if( !isset($args['html_content']) || $args['html_content'] == '' ) {
		$args['html_content'] = $args['text_content'];
	}

	//
	// Start transaction
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Add the campaign email to the database
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
	$rc = ciniki_core_objectAdd($ciniki, $args['business_id'], 'ciniki.campaigns.email', $args, 0x04);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}
	$campaign_id = $rc['id'];

	//
	// Commit the transaction
	//
	$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.campaigns');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'campaigns');

	return array('stat'=>'ok', 'id'=>$campaign_id);
}
?>
