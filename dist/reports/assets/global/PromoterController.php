<?php
/**
 * Created by PhpStorm.
 * User: bhavikrathod
 * Date: 4/20/17
 * Time: 6:59 PM
 */

require_once('sql_query_helper.php');

class PromoterController extends Controller
{
    public function filters()
    {
        return array(
            'ext.starship.RestfullYii.filters.ERestFilter +
                REST.GET, REST.PUT, REST.POST, REST.DELETE',
        );
    }

    /**
     * 42nite PRO API
     * Get Affiliated Promoters
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */
    public function actionGetAffiliatedPromoters()
    {
        Yii::log(var_export($_POST, true), "warning", "Promoter/GetAffiliatedPromoters POST");
        $validation = Application_helper::check_validation($_POST, ['user_id']);

        if ($validation && ($validation['user_role'] == 'Admin' || $validation['user_role'] == 'VenueOwner')) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
            $whereVenueId = " ";
            if(isset($_POST['venue_id']) && !empty($_POST['venue_id'])){
            	$whereVenueId = "AND `promoter_connects`.`venue_id` = $venueId";
            }
           

            if ($validation['user_role'] == 'VenueOwner') {
                $wherePromoter = " AND `venue_detail`.`id` IN (SELECT `venue_detail`.`id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $userId)";
            } else {
                $wherePromoter = "";
            }

            $search_name = "";
            if (!empty($_POST['keyword'])) {
                $keywords = $_POST['keyword'];
                $search_name = " AND `users`.`name` LIKE '%$keywords%' ";
            }

            $getPromotersSql = "SELECT `promoter_connects`.`id` AS `affiliation_id`, `users`.`id` AS `promoter_id`, `venue_detail`.`id` AS `venue_id`,
                                        `users`.`name` AS `promoter_name`, `venue_detail`.`name` AS `venue_name`, IFNULL(get_profile_pic(`users`.`id`), '') AS `promoter_pic`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                RIGHT JOIN `promoter_connects` ON `promoter_connects`.`promoters_id` = `users`.`id`
                                RIGHT JOIN `venue_detail` ON `venue_detail`.`id` = `promoter_connects`.`venue_id`
                                WHERE `user_login`.`role` LIKE '%Promoter%' $search_name $whereVenueId $wherePromoter ORDER BY `users`.`name`";

            $getPromoters = Application_helper::getData($getPromotersSql);

            if (!empty($getPromoters)) {
                $response['status'] = 'success';
                $response['message'] = 'Promoters fetched successfully';
                $response['data'] = $getPromoters;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No Promoters available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        $this->renderJSON($response);
        Yii::log(var_export($response, true), "warning", "Promoter/GetAffiliatedPromoters RESPONSE");
    }

    /**
     * 42nite PRO API
     * Get Affiliated Promoters
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */
    public function actionGetNonaffiliatedPromoters()
    {
        Yii::log(var_export($_POST, true), "warning", "Promoter/GetNonaffiliatedPromoters POST");
        $validation = Application_helper::check_validation($_POST, ['user_id']);

        if ($validation && ($validation['user_role'] == 'Admin' || $validation['user_role'] == 'VenueOwner')) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
			$whereVenueId = " ";
            if(isset($_POST['venue_id']) && !empty($_POST['venue_id'])){
            	$whereVenueId = " WHERE `promoter_connects`.`venue_id` = $venueId";
            }
            $search = "";
            if ($_POST['keyword']) {
                $search_name = $_POST['keyword'];
                $search = " AND ( `users`.`name` LIKE '%$search_name%' OR `users`.`address` LIKE '%$search_name%' OR `users`.`country` LIKE '%$search_name%' OR `users`.`city` LIKE '%$search_name%' ) ";
            }

            $getPromotersSql = "SELECT `users`.`id` AS `promoter_id`, `users`.`name` AS `promoter_name`, get_profile_pic(`users`.`id`) AS `promoter_pic`,`promoter_connects`.`venue_id` AS `venue_id`,
                                       (SELECT COUNT(`promoter_connects`.`id`) FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = `users`.`id`) AS `affiliated_venues_count`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                LEFT JOIN `promoter_connects` ON `promoter_connects`.`promoters_id` = `users`.`id`
                                WHERE `user_login`.`role` LIKE '%Promoter%' $search AND `users`.`id` NOT IN (SELECT `promoter_connects`.`promoters_id` FROM `promoter_connects` $whereVenueId)";
            $getPromoters = Application_helper::getData($getPromotersSql);

            if (!empty($getPromoters)) {
                $response['status'] = 'success';
                $response['message'] = 'Promoters fetched successfully';
                $response['data'] = $getPromoters;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No Promoters available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        $this->renderJSON($response);
        Yii::log(var_export($response, true), "warning", "Promoter/GetNonaffiliatedPromoters RESPONSE");
    }

    /**
     * 42nite PRO API
     * Affiliate Promoter
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionAffiliatePromoter()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/affiliatePromoter POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'promoter_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];
            $promoterId = $_POST['promoter_id'];
            $venueIdsList = $_POST['venue_ids'];
			if (!empty($venueIdsList)) {
				$venue_id = explode(",", $venue_ids);
				 foreach ($venue_id as $id) {
					$checkPromoter = PromoterConnects::model()->findByAttributes(['venue_id' => $id, 'promoters_id' => $promoterId]);

	            	if (empty($checkPromoter)) {
		                $promoterConnect = new PromoterConnects();
		
		                $promoterConnect->promoters_id = $promoterId;
		                $promoterConnect->venue_id = $id;
		
		                if ($promoterConnect->save()) {
		                    $response['status'] = 'success';
		                    $response['message'] = 'Promoter successfully affiliated with this venue';
		                } else {
		                    $response['status'] = 'failure';
		                    $response['message'] = 'There is some error while inserting data';
		                }
		            } else {
		                $response['status'] = 'failure';
		                $response['message'] = 'Promoter already affiliated with this venue';
		            }
				 }
			}else{
				$checkPromoter = PromoterConnects::model()->findByAttributes(['venue_id' => $venueId, 'promoters_id' => $promoterId]);

            if (empty($checkPromoter)) {
                $promoterConnect = new PromoterConnects();

                $promoterConnect->promoters_id = $promoterId;
                $promoterConnect->venue_id = $venueId;

                if ($promoterConnect->save()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Promoter successfully affiliated with this venue';
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'There is some error while inserting data';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Promoter already affiliated with this venue';
            }
			}
            
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/affiliatePromoter RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Unaffiliate Promoter
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionUnaffiliatePromoter()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/unaffiliatePromoter POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'promoter_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];
            $promoterId = $_POST['promoter_id'];

            $checkPromoter = PromoterConnects::model()->findByAttributes(['venue_id' => $venueId, 'promoters_id' => $promoterId]);

            if (!empty($checkPromoter)) {

                PromoterConnects::model()->deleteByPk($checkPromoter['id']);

                $response['status'] = 'success';
                $response['message'] = 'Promoter successfully unaffiliated with this venue';
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Promoter is not affiliated with this venue';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/unaffiliatePromoter RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Unaffiliate Venue
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionUnaffiliateVenue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/unaffiliateVenue POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id']);

        if ($validate['status'] && $validate['user_role'] == 'Promoter') {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];

            $affiliatedVenue = PromoterConnects::model()->findByAttributes(['promoters_id' => $userId, 'venue_id' => $venueId]);

            PromoterConnects::model()->deleteByPk($affiliatedVenue['id']);

            //TODO WHAT TO DO WITH EVENTS CREATED BY PROMOTER AFTER UNAFFILIATION

            $response['status'] = 'success';
            $response['message'] = 'Venue unaffiliated successfully';

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/unaffiliateVenue RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Get Unaffiliated venues
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionGetUnaffiliatedVenues()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/getUnaffiliatedVenues POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];

            $offset = isset($_POST['offset']) ? $_POST['offset'] : null;
            $limit = 20;

            $keywords = null;
            if (!empty($_POST['keyword'])) {
                $keywords = $_POST['keyword'];
                $limit = null;
                $offset = null;
            }

            $unaffiliatedVenuesSql = Sql_query_helper::get_unaffiliated_venues($userId, $keywords, $limit, $offset);

            $unaffiliatedVenues = Application_helper::getData($unaffiliatedVenuesSql);

            if (!empty($unaffiliatedVenues)) {

                $next_offset = null;
                if (count($unaffiliatedVenues) == $limit) {
                    $next_offset = isset($_POST['offset']) ? $offset + count($unaffiliatedVenues) : count($unaffiliatedVenues);
                }

                $response['status'] = 'success';
                $response['message'] = 'Unaffiliated venues fetched successfully';
                $response['data'] = $unaffiliatedVenues;
                $response['pagination']['offset'] = (string)$next_offset;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Requested venues are not available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/getUnaffiliatedVenues RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Get Affiliation requests
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionGetAffiliationRequests()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/getAffiliationRequests POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);
		
        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {
			$userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
			
			$whereVenueId = " ";
            if(isset($_POST['venue_id']) && !empty($_POST['venue_id'])){
            	$whereVenueId = "WHERE `affiliation_requests`.`venue_id` = $venueId";
            }else{
            	$whereVenueId = "WHERE `affiliation_requests`.`venue_id` IN (SELECT `venue_detail`.`id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $userId)";
            }
            $getRequestsSql = "SELECT `users`.`id` AS `promoter_id`, `users`.`name` AS `promoter_name`, get_promoter_followers(`users`.`id`) AS `promoter_followers`,`affiliation_requests`.`venue_id` AS `venue_id`,
                                      get_profile_pic(`users`.`id`) As `promoter_pic`
                               FROM `affiliation_requests`
                               LEFT JOIN `users` ON `users`.`id` = `affiliation_requests`.`promoter_id`
                               $whereVenueId";

            $getRequests = Application_helper::getData($getRequestsSql);

            if (!empty($getRequests)) {
                $response['status'] = 'success';
                $response['message'] = 'Pending affiliation requests fetched successfully';
                $response['data'] = $getRequests;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No pending requests for affiliation are available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/getAffiliationRequests RESPONSE");
        $this->renderJSON($response);
    }

        /**
     * 42nite PRO API
     * Get Affiliation requests
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionGetVenueAffiliationRequests()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/getVenueAffiliationRequests POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'Promoter')) {

            $userId = $_POST['user_id'];

            $search_name = '';
        if ($searchBy) {
            $search_name = " AND `venue_detail`.`name` LIKE '%$searchBy%' ";
        }

        

        $getRequestsSql = "SELECT `venue_detail`.`id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`,
                                              get_venue_pic(`venue_detail`.`id`) AS `venue_pic`,
                                              IFNULL((SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = `venue_detail`.`created_by` LIMIT 1),0) AS `w9_status`,
                                              (CASE WHEN `venue_detail`.`venue_type` = 1 THEN 'club' ELSE 'bar' END) AS `venue_type`,
                                              (SELECT count(`promoter_connects`.`id`) FROM `promoter_connects` WHERE `promoter_connects`.`venue_id` = `venue_detail`.`id`) AS `affiliated_venues`,
                                              IFNULL((SELECT `affiliation_requests`.`request_status` FROM `affiliation_requests` WHERE `affiliation_requests`.`promoter_id` = $userId AND `affiliation_requests`.`venue_id` = `venue_detail`.`id`),0) AS `request_status`
                                      FROM `venue_detail`
                                      WHERE `venue_detail`.`id` IN (SELECT `affiliation_requests`.`venue_id` FROM `affiliation_requests` WHERE `affiliation_requests`.`promoter_id` =                                       $userId AND `affiliation_requests`.`request_status` = 1 )
                                      $search_name ORDER BY `venue_detail`.`name` ASC
                                      ";

            $getRequests = Application_helper::getData($getRequestsSql);

            if (!empty($getRequests)) {
                $response['status'] = 'success';
                $response['message'] = 'Pending affiliation requests fetched successfully';
                $response['data'] = $getRequests;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No pending requests for affiliation are available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/getVenueAffiliationRequests RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Send / Cancel Affiliation requests
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionSendAffiliationRequest()
    {
        Yii::log(var_export($_POST, true), "warning", "promoter/sendAffiliationRequest POST");
        $validate = Application_helper::check_validation($_POST, ['promoter_id', 'venue_id']);

        if ($validate['status']) {

            $promoterId = $_POST['promoter_id'];
            $venueId = $_POST['venue_id'];

            $promoterConnect = PromoterConnects::model()->findByAttributes(['promoters_id' => $promoterId, 'venue_id' => $venueId]);

            if (empty($promoterConnect)) {
                $promoterRequest = AffiliationRequests::model()->findByAttributes(['promoter_id' => $promoterId, 'venue_id' => $venueId]);

                if (empty($promoterRequest)) {
                    $requestAffiliation = new AffiliationRequests();

                    $requestAffiliation->promoter_id = $promoterId;
                    $requestAffiliation->venue_id = $venueId;

                    if ($requestAffiliation->save()) {

                        //**** add logic for notification to venue owner ******
                           $notificationType = "promoter_requested_affiliation";

                           $venue_detail = $this->_get_venue_user_id($venueId);
                           $userName = $this->_get_user_name($promoterId);

                           // notification send to user
                           $venue_create_user_id = $venue_detail[0]['created_by'];
                           $venue_name = $venue_detail[0]['name'];

                           $notificationMessage = "$userName requested affiliation to $venue_name";

                           $notificationData = [
                              'users_id' => $promoterId,
                              'venue_id' => $venueId,
                              'post_user_id'=>$promoterId
                           ];

                           Posts_helper::save_post_notification_data($venue_create_user_id, $venueId, $notificationData, $notificationMessage, $notificationType);

                           // send notification to user
                           Application_helper::Notification_to_user($venue_create_user_id, $notificationMessage, $notificationType, $notificationData);

                        //*****************************************************
                        $response['status'] = 'success';
                        $response['message'] = 'Venue requested successfully for affiliation';
                    } else {
                        $response['status'] = 'failure';
                        $response['message'] = 'There is some error while requesting affiliation to venue';
                    }
                } else {
                    AffiliationRequests::model()->deleteByPk($promoterRequest['id']);
                    $response['status'] = 'success';
                    $response['message'] = 'Venue affiliation request cancelled successfully';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Promoter already affiliated with this venue';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "promoter/sendAffiliationRequest RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Reject Affiliation request
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionRejectAffiliationRequest()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/rejectAffiliationRequest POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'promoter_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];
            $promoterId = $_POST['promoter_id'];

            $promoterConnects = PromoterConnects::model()->findByAttributes(['promoters_id' => $promoterId, 'venue_id' => $venueId]);

            if (empty($promoterConnects)) {

                $promoterRequest = AffiliationRequests::model()->findByAttributes(['promoter_id' => $promoterId, 'venue_id' => $venueId]);

                if (!empty($promoterRequest)) {

                    AffiliationRequests::model()->deleteByPk($promoterRequest['id']);


                    //**** add logic for reject notification to promoter ******
                           $notificationType = "venue_rejected_affiliation";

                           $venue_detail = $this->_get_venue_user_id($venueId);
                           $userName = $this->_get_user_name($promoterId);

                           // notification send to user
                           $venue_create_user_id = $venue_detail[0]['created_by'];
                           $venue_name = $venue_detail[0]['name'];

                           $notificationMessage = " $venue_name rejected your affiliation request";

                           $notificationData = [
                              'users_id' => $promoterId,
                              'venue_id' => $venueId,
                              'venue_name'=> $venue_name,
                              'post_venue_id'=> $venueId
                           ];

                           Posts_helper::save_post_notification_data($promoterId, $venueId, $notificationData, $notificationMessage, $notificationType);

                           // send notification to user
                           Application_helper::Notification_to_user($promoterId, $notificationMessage, $notificationType, $notificationData);

                    $response['status'] = 'success';
                    $response['message'] = 'Request rejected successfully';

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Promoter haven\'t requested affiliation for this venue';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Venue already affiliated with this promoter';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/rejectAffiliationRequest RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Accept Affiliation request
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionAcceptAffiliationRequest()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/acceptAffiliationRequest POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'promoter_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];
            $promoterId = $_POST['promoter_id'];

            $promoterConnects = PromoterConnects::model()->findByAttributes(['promoters_id' => $promoterId, 'venue_id' => $venueId]);

            if (empty($promoterConnects)) {

                $promoterRequest = AffiliationRequests::model()->findByAttributes(['promoter_id' => $promoterId, 'venue_id' => $venueId]);

                if (!empty($promoterRequest)) {

                    $promoterConnect = new PromoterConnects();

                    $promoterConnect->promoters_id = $promoterId;
                    $promoterConnect->venue_id = $venueId;

                    if ($promoterConnect->save()) {
                        AffiliationRequests::model()->deleteByPk($promoterRequest['id']);

                        //**** add logic for approval notification to promoter ******
                           $notificationType = "venue_accepted_affiliation";

                           $venue_detail = $this->_get_venue_user_id($venueId);
                           $userName = $this->_get_user_name($promoterId);

                           // notification send to user
                           $venue_create_user_id = $venue_detail[0]['created_by'];
                           $venue_name = $venue_detail[0]['name'];

                           $notificationMessage = " $venue_name accepted your affiliation request.";

                           $notificationData = [
                              'users_id' => $promoterId,
                              'venue_id' => $venueId,
                              'venue_name'=> $venue_name,
                              'post_venue_id'=> $venueId
                           ];

                           Posts_helper::save_post_notification_data($promoterId, $venueId, $notificationData, $notificationMessage, $notificationType);

                           // send notification to user
                           Application_helper::Notification_to_user($promoterId, $notificationMessage, $notificationType, $notificationData);

                        $response['status'] = 'success';
                        $response['message'] = 'Request accepted successfully';
                    } else {
                        $response['status'] = 'failure';
                        $response['message'] = 'There is some error while inserting data';
                    }
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Promoter haven\'t requested affiliation for this venue';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Venue already affiliated with this promoter';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/acceptAffiliationRequest RESPONSE");
        $this->renderJSON($response);
    }

   /**
     * get venue detail
     *
     * @param $venue_id
     * @return mixed
     */
    private function _get_venue_user_id($venue_id)
    {
        $get_venue_detail_sql = "SELECT `created_by`, `name` FROM `venue_detail` WHERE `venue_detail`.`id` = $venue_id";
        $venue_detail = Application_helper::getData($get_venue_detail_sql);

        return $venue_detail;
    }

/**
     * Get User name
     *
     * @param $user_id
     * @return mixed
     */
    private function _get_user_name($user_id)
    {

        $get_user_name_sql = "SELECT `name` FROM `users` WHERE `users`.`id` = $user_id";
        $get_user_name = Application_helper::getData($get_user_name_sql);

        return $get_user_name[0]['name'];
    }


    /**
     * 42nite API
     * Follow Promoter By Venue
     *
     * Last Update Details
     * For : 42nite PRO Support available
     * By : jenish
     * On : 11/08/2017
     */
    public function actionFollow_Promoter()
    {
        Yii::log(var_export($_POST, true), "warning", "Follow User By Venue");

        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'promoter_id']);

        if ($validate['status']) {

            //Initialise venue_id, promoter_id
            $user_id = $_POST['user_id'];
            $venue_id = $_POST['venue_id'];
            $promoter_id = $_POST['promoter_id'];

            //Check if venue has already added promoter to fav list
            $criteria = new CDbCriteria;
            $criteria->select = 'id';
            $criteria->condition = "venue_id = $venue_id AND other_promoter_id = $promoter_id";
            $check_data = UsersFollowers::model()->findAll($criteria);

            //If already not added to promoter_fav
            if (empty($check_data)) {

                $model = new UsersFollowers();
                $model->other_users_id = $promoter_id;
                $model->venue_id = $venue_id;
                $model->status = 1;
                $model->action_user_id = $user_id;
                $model->unix_timestamp = time();

                //Successfully saved into table
                if ($model->save()) {
                    //get followers_count
                    $followers_count = Application_helper::get_followers_count($promoter_id, null);

                    $response['status'] = "Success";
                    $response['message'] = "Promoter has been successfully added to your favorite list";
                    $response['data'] = $followers_count[0];
                } else {
                    $response['status'] = "Failure";
                    $response['message'] = "There is some error while adding Promoter to favorites";
                }
            } else {

                $criteria = new CDbCriteria;
                $criteria->condition = 'venue_id = ' . $venue_id . ' AND other_promoter_id = ' . $promoter_id;

                //Successfully removed
                if (UsersFollowers::model()->deleteAll($criteria)) {
                    //get followers_count
                    $followers_count = Application_helper::get_followers_count($promoter_id, null);

                    $response['status'] = "Success";
                    $response['message'] = "Promoter has been successfully removed from your favorite list";
                    $response['data'] = $followers_count[0];
                } else {
                    $response['status'] = "Failure";
                    $response['message'] = "There is some error while removing Promoter from favorites";
                }
            }
        } else {
            $response['status'] = 'Failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "Follow User By venue");
        $this->renderJSON($response);
    }

    /**
     * Private Function
     * Return data as JSON and end application.
     * @param array $data
     */
    protected function renderJSON($data)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json');
        echo CJSON::encode($data);

        foreach (Yii::app()->log->routes as $route) {
            if ($route instanceof CWebLogRoute) {
                $route->enabled = false; // disable any weblogroutes
            }
        }
        Yii::app()->end();
    }
}