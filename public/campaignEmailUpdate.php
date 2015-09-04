<?php
//
// Description
// ===========
// This method will update an campaign in the database.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business the campaign is attached to.
// 
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_campaigns_campaignEmailUpdate(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'email_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Email'), 
		'name'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Name'), 
		'status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Status'), 
		'days_from_start'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Days From Start'), 
		'subject'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Subject'), 
		'html_content'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'HTML Content'), 
		'text_content'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Text Content'), 
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
    $rc = ciniki_campaigns_checkAccess($ciniki, $args['business_id'], 'ciniki.campaigns.campaignEmailUpdate'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

	//
	// Get the current content
	//
	$strsql = "SELECT id, html_content, text_content "
		. "FROM ciniki_campaign_emails "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['email_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.campaigns', 'email');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['email']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2516', 'msg'=>'Email does not exist'));
	}
	$email = $rc['email'];

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
	// Update the campaign email in the database
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
	$rc = ciniki_core_objectUpdate($ciniki, $args['business_id'], 'ciniki.campaigns.email', $args['email_id'], $args, 0x04);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.campaigns');
		return $rc;
	}

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

	return array('stat'=>'ok');
}
?>
