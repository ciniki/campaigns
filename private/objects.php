<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_campaigns_objects($ciniki) {
	
	$objects = array();
	$objects['campaign'] = array(
		'name'=>'Campaign',
		'sync'=>'yes',
		'table'=>'ciniki_campaigns',
		'fields'=>array(
			'name'=>array(),
			'tracking_uuid'=>array(),
			'status'=>array(),
			'flags'=>array('default'=>'0'),
			'delivery_time'=>array('default'=>'00:00'),
			),
		'history_table'=>'ciniki_campaign_history',
		);
	$objects['email'] = array(
		'name'=>'Campaign Email',
		'sync'=>'yes',
		'table'=>'ciniki_campaign_emails',
		'fields'=>array(
			'campaign_id'=>array('ref'=>'ciniki.campaigns.campaign'),
			'status'=>array(),
			'flags'=>array('default'=>'0'),
			'delivery_time'=>array('default'=>'00:00'),
			'days_from_start'=>array(),
			'subject'=>array(),
			'html_content'=>array(),
			'text_content'=>array(),
			),
		'history_table'=>'ciniki_campaign_history',
		);
	$objects['customer'] = array(
		'name'=>'Campaign Customer',
		'sync'=>'yes',
		'table'=>'ciniki_campaign_customers',
		'fields'=>array(
			'campaign_id'=>array('ref'=>'ciniki.campaigns.campaign'),
			'customer_id'=>array('ref'=>'ciniki.customers.customer'),
			'utc_start_date'=>array(),
			'start_date'=>array(),
			'status'=>array(),
			),
		'history_table'=>'ciniki_campaign_history',
		);
	$objects['queue'] = array(
		'name'=>'Campaign Queue',
		'sync'=>'yes',
		'table'=>'ciniki_campaign_queue',
		'fields'=>array(
			'campaign_id'=>array('ref'=>'ciniki.campaigns.campaign'),
			'campaign_customer_id'=>array('ref'=>'ciniki.campaigns.customer'),
			'campaign_email_id'=>array('ref'=>'ciniki.campaigns.email'),
			'send_date'=>array(),
			'status'=>array(),
			),
		'history_table'=>'ciniki_campaign_history',
		);

	return array('stat'=>'ok', 'objects'=>$objects);
}
?>
