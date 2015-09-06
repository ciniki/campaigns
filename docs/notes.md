

Get the list of emails to send, current status of the customer, campaign and email

Unsubscribe should remove all queued emails.



****************************************************************************************************************
****** The following was train of thought and didn't get used, went with ciniki_campaign_queue instead ********* 
****************************************************************************************************************
Calculating customer current day of campaign.

- Emails to go out a predetermined time of day
- Need to calculate rollover based on time to send emails
- mysql to_days  doesn't work, as it doesn't take into account time.
- datediff doesn't take into account time either

- Emails on day 0 go out immediately and don't care about sending time
- Emails on day 0 are kicked off via api when customer is attached to campaign


- Campaign manager needs it's own cron job as it could spend a lot of time dealing with emails and sending, should only be run every 30 minutes or maybe every hour.
- More advanced manager may be needed in the future with multi threading etc, database row locking, etc.


set timezone to business timezone
get cur_date in timezone
get cur_time in timezone
// Figure out what day on
select 
((to_days(cur_date) - to_days(ciniki_campaign_customers.start_date))
// Subtract one from current days, as not ready to send yet
- IF(cur_time < ciniki_campaigns.delivery_time, -1, 0)) > last_sent_day

//
// Get the list of customers who need a campaign email sent
//

SELECT (TO_DAYS($cur_date) - TO_DAYS(ciniki_campaign_customers.start_date) - IF($cur_time < ciniki_campaigns.delivery_time, -1, 0)) AS customer_day,
FROM ciniki_campaigns, ciniki_campaign_customers
WHERE ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
AND ciniki_campaigns.status = 10
AND ciniki_campaigns.id = ciniki_campaign_customers.campaign_id "
AND ciniki_campaign_customers.
AND ciniki_campaign_customers.status = 10



SELECT 
SELECT (TO_DAYS($cur_date) - TO_DAYS(ciniki_campaign_customers.start_date) - IF($cur_time < ciniki_campaigns.delivery_time, -1, 0)) AS current_customer_day,
FROM ciniki_campaign_customers
. "INNER JOIN ciniki_campaigns ON (
	. "ciniki_campaign_customers.campaign_id = ciniki_campaigns.id
	. "AND ciniki_campaigns.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
	. "AND ciniki_campaigns.status = '10' "
	. ") "
. "LEFT JOIN ciniki_campaign_emails ON ("
	. "ciniki_campaigns.id = ciniki_campaign_emails.campaign_id "
	. "AND ciniki_campaign_emails.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
	. "AND ciniki_campaign_emails.days_from_start > ciniki_campaign_customers.last_days_from_start, "
	. "AND ciniki_campaign_emails.days_from_start < current_customer_day "

	. ") "
. "WHERE ciniki_campaign_customers.next_check <= UTC_TIMESTAMP() "
. "AND ciniki_campaign_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
. "AND ciniki_campaign_customers.status = '10' "
. "";

//
// If customer is returned in the result but no emails are assigned, then that customer should be updated
// to be ignored till the next day (UPDATE set next_check = tomorrow) (based in business timezone, calculations in php) and when next time for next email
// also update last_days_from_start to be current_customer_day
//


