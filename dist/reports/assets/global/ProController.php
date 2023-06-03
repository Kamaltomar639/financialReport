<?php

require_once('sql_query_helper.php');
require(dirname(__FILE__) . '/Stripe.php');

class ProController extends Controller
{

    // public function filters()
    // {
    //     return array(
    //         'ext.starship.RestfullYii.filters.ERestFilter +
    //             REST.GET, REST.PUT, REST.POST, REST.DELETE',
    //     );
    // }

// ************************************************* VENUE RELATED APIs *************************************************************************

   public function actionGetTimeZones()
   {
      Yii::log(var_export($_POST, true), "warning", "pro/getVenues POST");

      $country = $_POST['country'];
      if (isset($country)) {

         $getTimezoneSql = "SELECT `country`,`timezone_name`,`timezone_code` FROM `timezone_master` Where `country` = '$country'";
         $timeZones = Application_helper::getData($getTimezoneSql);
         if ($timeZones)
         {
            $response['status'] = 'success';
            $response['message'] = 'timezones fetched Successfully';
            $response['data'] = $timgeeZones;
         } else {
            $response['status'] = 'failure';
            $response['message'] = 'No TimeZones found';
         }
      }
      else
      {
         $response['status'] = 'failure';
         $response['message'] = 'Please provide country';
      }
      Yii::log(var_export($response, true), "warning", "pro/getTimeZones RESPONSE");
      $this->renderJSON($response);
   }
    
   
   

    /**
     * 42nite PRO API
     * Get Venues
     *rsrList
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */
    public function actionGetVenues()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getVenues POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin' || $validate['user_role'] == 'Promoter')) {
            $userId = $_POST['user_id'];


            $W9status = '0';

            if ($validate['user_role'] == 'VenueOwner') {

                $whereVenues = " WHERE `venue_detail`.`created_by` = $userId ";

                $userW9StatusSql = "SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = $userId";
                $userW9status = Application_helper::getData($userW9StatusSql);

                if (!empty($userW9status)) {
                    $W9status = $userW9status[0]['request_status'];
                }
            } else if ($validate['user_role'] == 'Promoter') {
                $whereVenues = " WHERE `venue_detail`.`id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoters_id` = $userId) ";
            } else {
                $whereVenues = "";
            }

            $search = "";
            $orderBy = "";
            $offsetlimit = "";
            if ($_POST['keyword']) {
                $search_name = $_POST['keyword'];
                $condition = " AND ";

                if ($validate['user_role'] == 'Admin') {
                    $condition = " WHERE ";
                }
                $search = " $condition (`venue_detail`.`name` LIKE '%$search_name%' OR `venue_detail`.`address` LIKE '%$search_name%' OR `venue_detail`.`country` LIKE '%$search_name%' OR `venue_detail`.`city` LIKE '%$search_name%' OR `venue_detail`.`state` LIKE '%$search_name%' ) ";
            }


            if($_POST['orderby']){
                $orderBy = " ORDER BY `venue_detail`.`created_at` DESC "    ;
            }
            if($_POST['offset'] && $_POST['records']){
                $offsetlimit = " LIMIT ". $_POST['offset'] .", ".$_POST['records'];
            }

            $venueDataSql = "SELECT `venue_detail`.`id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`, get_venue_pic(`venue_detail`.`id`) AS `venue_pic`,
                                    `venue_detail`.`address` AS `address`, `venue_detail`.`description`,  `venue_detail`.`latitude`, `venue_detail`.`longitude`,
                                    `venue_detail`.`venue_rating`, `venue_detail`.`venue_followers`, `venue_detail`.`city`,
                                    REPLACE(REPLACE(REPLACE(REPLACE(`venue_detail`.`address`, `venue_detail`.`zipcode`, ''), `venue_detail`.`country`, ''), `venue_detail`.`state`, ''),`venue_detail`.`city`, '') AS `trim_address`,
                                    IFNULL((SELECT 1 FROM `venue_ratings` WHERE `user_id` = $userId AND `venue_id` = `venue_detail`.`id` LIMIT 1),0) AS `is_rated`,
                                    IFNULL((SELECT 1 FROM `venue_followers` WHERE `user_id` = $userId AND `venue_id` =  `venue_detail`.`id` LIMIT 1),0) AS `is_follow`,
                                    `venue_detail`.`country`, `venue_detail`.`contact_no` AS `phone_no`, generate_phone_no(`venue_detail`.`contact_no`) AS `formatted_phone_no`, `venue_detail`.`email`, `venue_detail`.`state`,
                                    IFNULL((SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = `venue_detail`.`created_by` LIMIT 1),0) AS `w9_status`,
                                    (SELECT IFNULL(SUM(`order`.`quantity`),0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_tickets_sold`,
                                    (SELECT IFNULL((SUM(`order`.`amount`)*0.9),0.0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_total_sell`,
                                    (SELECT COUNT(`event_meta`.`id`) FROM `event_meta` WHERE `event_meta`.`event_id` IN (SELECT `event_detail`.`id` FROM `event_detail` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`)) AS `venue_event_created`,
                                    (CASE WHEN `venue_detail`.`venue_type` = 1 THEN 'club' ELSE 'bar' END) AS `venue_type`,
                                    `venue_detail`.`created_by` AS `venue_created_by`,`venue_detail`.`status`
                             FROM `venue_detail` $whereVenues $search $orderBy $offsetlimit";
            Yii::log(var_export($venueDataSql, true), "warning", "pro/getVenues SQL");
            $venueData = Application_helper::getData($venueDataSql);
             Yii::log(var_export($venueData, true), "warning", "pro/getVenues Data");
            $count = 0;
            foreach ($venueData as $venue) {

                $venueId = $venue['venue_id'];

                $venueProfile = Application_helper::get_venue_profile_images($venueId);
                $venueData[$count]['profile_images'] = $venueProfile['images'];
                $venueData[$count]['profile_image_indexes'] = $venueProfile['indexes'];

                $venueSchedule = Application_helper::get_venue_schedue($venueId);

                $venueData[$count]['is_closed'] = $venueSchedule['is_closed'];
                $venueData[$count]['opening_hours'] = $venueSchedule['opening_hours'];
                $venueData[$count]['opening_hours2'] = json_encode($venueSchedule['opening_hours']);
                $venueData[$count]['profile_images2'] = json_encode($venueProfile['images']);
//                $venueData[$count]['address'] = Application_helper::generate_address($venue['address'], $venue['city'], $venue['country']);
                $venueData[$count]['address'] = array_shift(explode(', ,', rtrim($venue['trim_address'], ", , , , ,")));

                unset($venueData[$count]['trim_address']);

                $count++;
            }


            if (!empty($venueData)) {
                $totalCountQuery = "SELECT COUNT(*) as total FROM `venue_detail` $whereVenues $search";
                $totalCountData = Application_helper::getData($totalCountQuery);
                $response['status'] = 'success';
                $response['message'] = 'venues fetched Successfully';
                $response['data'] = $venueData;
                $response['total_count'] = $totalCountData[0]['total'];
                $response['user_w9_status'] = $W9status;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No Venues Available';
                $response['user_w9_status'] = $W9status;
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getVenues RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Create Venue
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionCreateVenue()
    {
        Yii::log(var_export($_POST, true), 'warning', 'pro/createVenue POST');
        Yii::log(var_export($_FILES, true), 'warning', 'pro/createVenue FILES');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_name', 'description', 'email', 'latitude', 'longitude', 'contact_no', 'venue_type']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $venueName = $_POST['venue_name'];
            $venueDescription = $_POST['description'];
            $venueEmail = $_POST['email'];
            $venueLatitude = $_POST['latitude'];
            $venueLongitude = $_POST['longitude'];
            $venueContactNo = $_POST['contact_no'];
            $venueType = $_POST['venue_type'];
            $state = "";

            $venue = new VenueDetail();

            $venue->name = $venueName;
            $venue->email = $venueEmail;

            if (isset($_POST['address'])) {
                $venue->address = $_POST['address'];
            }

            if (isset($_POST['city'])) {
                $venue->city = $_POST['city'];
            }

            if (isset($_POST['country'])) {
                $venue->country = $_POST['country'];
            }

            if (isset($_POST['zipcode'])) {
                $venue->zipcode = $_POST['zipcode'];
            }

            if (isset($_POST['state'])) {
                $venue->state = $state = $_POST['state'];
            }

            if (!empty($_POST['time_zone'])) {
                $venue->time_zone = $time_zone = $_POST['time_zone'];
            }

            $venue->latitude = $venueLatitude;
            $venue->longitude = $venueLongitude;
            $venue->description = $venueDescription;
            $venue->contact_no = $venueContactNo;

            if (isset($_POST['is_verified']) && $validation['user_role'] == 'Admin') {
                $venue->is_verified = $_POST['is_verified'];
            }

            $venue->venue_type = $venueType;
            $venue->created_by = $userId;

            if ($venue->save(false)) {
                //Inserted venue id
                $venueId = Yii::app()->db->getLastInsertID();

                if (isset($_POST['opening_schedule'])) {
                    $openingSchedule = json_decode($_POST['opening_schedule'], true);
                    Application_helper::create_schedule($venueId, $openingSchedule, TRUE);
                }

                //Save venue images
                if (!empty($_FILES)) {
                    Application_helper::set_venue_images($venueId, $_FILES, 'image');
                }

                $venueDetailSql = "SELECT generate_phone_no('$venueContactNo') AS `formatted_no`, get_venue_pic($venueId) AS `venue_pic`, IFNULL((SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = $userId LIMIT 1),0) AS `w9_status`";
                $venueDetail = Application_helper::getData($venueDetailSql);
                $scheduleData = Application_helper::get_venue_schedue($venueId);
                $venueProfileImages = Application_helper::get_venue_profile_images($venueId);
                $venueProfileImages2 = Application_helper::get_venue_profile_images2($venueId);

                $response['status'] = 'success';
                $response['message'] = "Venue Created Successfully";

                $response['data']['venue_id'] = $venueId;
                $response['data']['venue_name'] = $venueName;
                $response['data']['venue_pic'] = $venueDetail[0]['venue_pic'];
                $response['data']['address'] = isset($_POST['address']) ? $_POST['address'] : '';
                $response['data']['description'] = $_POST['description'];
                $response['data']['latitude'] = $_POST['latitude'];
                $response['data']['longitude'] = $_POST['longitude'];
                $response['data']['venue_rating'] = '0.0';
                $response['data']['venue_followers'] = '0';
                $response['data']['city'] = $venue->city;
                $response['data']['state'] = $state;
                $response['data']['country'] = $venue->country;
                $response['data']['phone_no'] = $_POST['contact_no'];
                $response['data']['formatted_phone_no'] = $venueDetail[0]['formatted_no'];
                $response['data']['email'] = $_POST['email'];
                $response['data']['w9_status'] = $venueDetail[0]['w9_status'];
                $response['data']['venue_tickets_sold'] = '0';
                $response['data']['venue_total_sell'] = '0.0';
                $response['data']['venue_event_created'] = '0';
                $response['data']['venue_type'] = $venueType == 1 ? 'Club' : 'Bar';
                $response['data']['venue_created_by'] = $userId;
                $response['data']['profile_images'] = $venueProfileImages['images'];
                $response['data']['profile_image_indexes'] = $venueProfileImages['indexes'];
                $response['data']['is_closed'] = $scheduleData['is_closed'];
                $response['data']['opening_hours'] = $scheduleData['opening_hours'];

                $response['data']['opening_hours2'] = json_encode($scheduleData['opening_hours']);
                $response['data']['profile_images2'] = json_encode($venueProfileImages);
                $response['data']['profile_images2'] = json_encode($venueProfileImages2);
                $venueEmailData['name'] = $venueName;
                $venueEmailData['email'] = $_POST['email'];
                $venueEmailData['contact_no'] = $_POST['contact_no'];
                $venueEmailData['address'] = $_POST['address'];
                $venueEmailData['city'] = $venue->city;
                $venueEmailData['country'] = $venue->country;
                $venueEmailData['venue_type'] = $venueType;
                Email_helper::send_new_venue_mail($venueEmailData,date("Y-m-d H:i:s"));
            } else {
                $response['status'] = 'failure';
                $response['message'] = "There is some error while saving venue data";
                $response['error'] = $venue->getErrors();
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), 'warning', 'pro/createVenue RESPONSE');
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Update Venue
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
    public function actionUpdateVenue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/updateVenue POST");
        Yii::log(var_export($_FILES, true), "warning", "pro/updateVenue FILES");

        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];

            $venue = VenueDetail::model()->findByPk($venueId);

            if (isset($_POST['venue_name'])) {
                $venue->name = $_POST['venue_name'];
            }

            if (!empty($_POST['email'])) {
                $venue->email = $_POST['email'];
            }

            if (isset($_POST['description'])) {
                $venue->description = $_POST['description'];
            }

            if (isset($_POST['address'])) {
                $venue->address = $_POST['address'];
            }

            if (isset($_POST['city'])) {
                $venue->city = $_POST['city'];
            }

            if (isset($_POST['country'])) {
                $venue->country = $_POST['country'];
            }

            if (isset($_POST['zipcode'])) {
                $venue->description = $_POST['description'];
            }

            if (isset($_POST['zipcode'])) {
                $venue->zipcode = $_POST['zipcode'];
            }

            if (isset($_POST['latitude'])) {
                $venue->latitude = $_POST['latitude'];
            }

            if (isset($_POST['longitude'])) {
                $venue->longitude = $_POST['longitude'];
            }

            if (isset($_POST['contact_no'])) {
                $venue->contact_no = $_POST['contact_no'];
            }

            if (isset($_POST['state'])) {
                $venue->state = $_POST['state'];
            }

            if (isset($_POST['venue_type'])) {
                $venue->venue_type = $_POST['venue_type'];
            }

            $venue->update();

            if (isset($_POST['opening_schedule'])) {
                $openingSchedule = json_decode($_POST['opening_schedule'], true);

                $venueSchedule = Application_helper::get_venue_schedue($venueId);
                $is_save = FALSE;
                if(empty($venueSchedule['opening_hours'])){
                    $is_save = TRUE;
                }

                Application_helper::create_schedule($venueId, $openingSchedule, $is_save);
            }

            if (isset($_POST['remove_images'])) {
                if ($_POST['remove_images'] != '') {
                    $remove_indexes = str_getcsv($_POST['remove_images']);

                    foreach ($remove_indexes as $index) {

                        $index = trim($index);

                        $imagedata_sql = "SELECT `venue_album`.`image_url` FROM `venue_album` WHERE `venue_album`.`venue_id` = $venueId AND `venue_album`.`position` = $index";
                        $existing_data = Application_helper::getData($imagedata_sql);

                        if ($existing_data) {
                            $basepath = Yii::app()->basePath;
                            if (file_exists($basepath . "/../images/venue/" . $venueId . "/" . $existing_data[0]['image_url'])) {
                                unlink($basepath . "/../images/venue/" . $venueId . "/" . $existing_data[0]['image_url']);
                            }

                            $deleteSql = "DELETE FROM `venue_album` WHERE `venue_album`.`venue_id` = $venueId AND `venue_album`.`position` = $index";
                            $deleteData = Application_helper::updateData($deleteSql);
                        }

                    }
                }
            }

            if (!empty($_FILES)) {
                Application_helper::set_venue_images($venueId, $_FILES, 'image');
            }

            $venueDataSql = "SELECT `venue_detail`.`id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`, get_venue_pic(`venue_detail`.`id`) AS `venue_pic`,
                                    `venue_detail`.`address` AS `address`, `venue_detail`.`description`,  `venue_detail`.`latitude`, `venue_detail`.`longitude`,
                                    REPLACE(REPLACE(REPLACE(REPLACE(`venue_detail`.`address`, `venue_detail`.`zipcode`, ''), `venue_detail`.`country`, ''), `venue_detail`.`state`, ''),`venue_detail`.`city`, '') AS `trim_address`,
                                    `venue_detail`.`venue_rating`, `venue_detail`.`venue_followers`, `venue_detail`.`city`, `venue_detail`.`state`,
                                    `venue_detail`.`country`, `venue_detail`.`contact_no` AS `phone_no`, generate_phone_no(`venue_detail`.`contact_no`) AS `formatted_phone_no`, `venue_detail`.`email`,
                                    `venue_detail`.`created_by` AS `venue_created_by`,
                                    IFNULL((SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = `venue_detail`.`created_by` LIMIT 1),0) AS `w9_status`,
                                    (SELECT IFNULL(SUM(`order`.`quantity`),0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_tickets_sold`,
                                    (SELECT IFNULL(SUM(`order`.`amount`),0.0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_total_sell`,
                                    (SELECT COUNT(`event_detail`.`id`) FROM `event_detail` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_event_created`,
                                    (CASE WHEN `venue_detail`.`venue_type` = 1 THEN 'club' ELSE 'bar' END) AS `venue_type`, `venue_detail`.`status` AS `status`
                             FROM `venue_detail` WHERE `venue_detail`.`id` = $venueId";
            $venueData = Application_helper::getData($venueDataSql);

            $response['status'] = 'success';
            $response['message'] = 'Venue Updated Successfully';

            $response['data']['venue_id'] = $venueId;
            $response['data']['venue_name'] = $venueData[0]['venue_name'];
            $response['data']['venue_pic'] = $venueData[0]['venue_pic'];
//            $response['data']['address'] =  Application_helper::generate_address($venueData[0]['address'], $venueData[0]['city'], $venueData[0]['country']);
            $response['data']['address'] = array_shift(explode(', ,', rtrim($venueData[0]['trim_address'], ", , , , ,")));

            $response['data']['description'] = $venueData[0]['description'];
            $response['data']['latitude'] = $venueData[0]['latitude'];
            $response['data']['longitude'] = $venueData[0]['longitude'];
            $response['data']['venue_rating'] = $venueData[0]['venue_rating'];
            $response['data']['venue_followers'] = $venueData[0]['venue_followers'];
            $response['data']['city'] = $venueData[0]['city'];
            $response['data']['state'] = $venueData[0]['state'];
            $response['data']['country'] = $venueData[0]['country'];
            $response['data']['phone_no'] = $venueData[0]['phone_no'];
            $response['data']['formatted_phone_no'] = $venueData[0]['formatted_phone_no'];
            $response['data']['email'] = $venueData[0]['email'];
            $response['data']['w9_status'] = $venueData[0]['w9_status'];
            $response['data']['venue_tickets_sold'] = $venueData[0]['venue_tickets_sold'];
            $response['data']['venue_total_sell'] = $venueData[0]['venue_total_sell'];
            $response['data']['venue_event_created'] = $venueData[0]['venue_event_created'];
            $response['data']['venue_created_by'] = $venueData[0]['venue_created_by'];
            $response['data']['venue_type'] = $venueData[0]['venue_type'] == 1 ? 'club' : 'bar';
            $response['data']['status'] = $venueData[0]['status'];

            $venueProfile = Application_helper::get_venue_profile_images($venueId);
            $response['data']['profile_images'] = $venueProfile['images'];
            $response['data']['profile_image_indexes'] = $venueProfile['indexes'];

            $venueSchedule = Application_helper::get_venue_schedue($venueId);

            $response['data']['is_closed'] = $venueSchedule['is_closed'];
            $response['data']['opening_hours'] = $venueSchedule['opening_hours'];
            $response['data']['opening_hours2'] = json_encode($venueSchedule['opening_hours']);
            $response['data']['profile_images2'] = json_encode($venueProfile['images']);


        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/updateVenue RESPONSE");
        $this->renderJSON($response);
    }



   /**
     * 42nite PRO API
     * Update Venue
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
    public function actionUpdateVenueStatus()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/updateVenueStatus POST");

        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];

            $venue = VenueDetail::model()->findByPk($venueId);

            $venue_status = $venue['status'];
            if ($venue_status == '1')
            {
               $venue->status = '0';
            }
            else
            {
               $venue->status = '1';
            }


            if ($venue->update(false))
            {
               $venueDataSql = "SELECT `venue_detail`.`id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`,`venue_detail`.`status`
                                 FROM `venue_detail` WHERE `venue_detail`.`id` = $venueId";
               $venueData = Application_helper::getData($venueDataSql);

               $response['status'] = 'success';
               $response['message'] = 'Venue updated successfully';
               $response['data']['venue_id'] = $venueId;
               $response['data']['status'] = $venueData[0]['status'];
            }
            else {
               $response['status'] = 'failure';
               $response['message'] = 'Venue update failed.';
            }
      }
      else {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
      }

         Yii::log(var_export($response, true), "warning", "pro/updateVenueStatus RESPONSE");
         $this->renderJSON($response);
    }

    /**

    /**
     * 42nite PRO API
     * Remove Venue
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
    public function actionRemoveVenue()
    {
        Yii::log(var_export($_POST, true), 'warning', 'pro/removeVenue POST');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];

            VenueDetail::model()->deleteByPk($venueId);

            $response['status'] = 'success';
            $response['message'] = 'Venue Removed Successfully';
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), 'warning', 'pro/removeVenue RESPONSE');
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Get Venue Details
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
    public function actionGetVenueDetail()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getVenueDetail POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status']) {
            $validate2 = Application_helper::check_validation($_POST, ['venue_id']);

            if ($validate2['status']) {

                $userId = $_POST['user_id'];
                $venueId = $_POST['venue_id'];
                $user_venue_id = null;

                // rate and follow based on user id
                $follow_rate = " IFNULL((SELECT 1 FROM `venue_ratings` WHERE `user_id` = $userId AND `venue_id` = $venueId LIMIT 1),0) AS `is_rated`,
                                 IFNULL((SELECT 1 FROM `venue_followers` WHERE `user_id` = $userId AND `venue_id` = $venueId LIMIT 1),0) AS `is_follow`,
                                 IFNULL((SELECT 1 FROM `promoter_connects` WHERE `promoters_id` = $userId AND `venue_id` = $venueId LIMIT 1),0) AS `is_affiliated`, ";

                if (!empty($_POST['user_venue_id'])) {
                    $user_venue_id = $_POST['user_venue_id'];

                    // based of rate and follow on venue id of user
                    $follow_rate = "IFNULL((SELECT 1 FROM `venue_ratings` WHERE `action_venue_id` = $user_venue_id AND `venue_id` = $venueId LIMIT 1),0) AS `is_rated`,
                                    IFNULL((SELECT 1 FROM `venue_followers` WHERE `action_venue_id` = $user_venue_id AND `venue_id` = $venueId LIMIT 1),0) AS `is_follow`,";


                }

                /**
                 * Venue Out reach to the user
                 */
                Application_helper::venueOutreachCount($userId, $venueId, $user_venue_id);


                $venueDetailSql = "SELECT `venue_detail`.`id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`, get_venue_pic(`venue_detail`.`id`) AS `venue_pic`,
                                    `venue_detail`.`address` AS `address`, `venue_detail`.`description`,  `venue_detail`.`latitude`, `venue_detail`.`longitude`,
                                    REPLACE(REPLACE(REPLACE(REPLACE(`venue_detail`.`address`, `venue_detail`.`zipcode`, ''), `venue_detail`.`country`, ''), `venue_detail`.`state`, ''),`venue_detail`.`city`, '') AS `trim_address`,
                                    `venue_detail`.`venue_rating`, `venue_detail`.`venue_followers`, `venue_detail`.`city`, `venue_detail`.`state`,
                                    $follow_rate
                                    `venue_detail`.`country`, `venue_detail`.`contact_no` AS `phone_no`, generate_phone_no(`venue_detail`.`contact_no`) AS `formatted_phone_no`, `venue_detail`.`email`,
                                    IFNULL((SELECT `user_business_requests`.`request_status` FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = `venue_detail`.`created_by` LIMIT 1),0) AS `w9_status`,
                                    (SELECT IFNULL(SUM(`order`.`quantity`),0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_tickets_sold`,
                                    (SELECT IFNULL(((SUM(`order`.`amount`))*(0.9)),0.0) FROM `order` LEFT JOIN `event_detail` ON `event_detail`.`id` = `order`.`event_id` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_total_sell`,
                                    (SELECT COUNT(`event_detail`.`id`) FROM `event_detail` WHERE `event_detail`.`venue_id` = `venue_detail`.`id`) AS `venue_event_created`,
                                    (CASE WHEN `venue_detail`.`venue_type` = 1 THEN 'club' ELSE 'bar' END) AS `venue_type`, `venue_detail`.`time_zone`,
                                    `venue_detail`.`created_by` AS `venue_created_by`, `venue_detail`.`status`
                               FROM `venue_detail`
                               WHERE `venue_detail`.`id` = $venueId";
                $venueDetail = Application_helper::getData($venueDetailSql);


                if (!empty($venueDetail)) {

                    $venueProfile = Application_helper::get_venue_profile_images($venueId);
                    $venueDetail[0]['profile_images'] = $venueProfile['images'];
                    $venueDetail[0]['profile_images2'] = json_encode($venueProfile['images']);
                    $venueDetail[0]['profile_image_indexes'] = $venueProfile['indexes'];

                    $venueSchedule = Application_helper::get_venue_schedue($venueId);
                    $venueDetail[0]['is_closed'] = $venueSchedule['is_closed'];
                    $venueDetail[0]['opening_hours'] = $venueSchedule['opening_hours'];
                    $venueDetail[0]['opening_hours2'] = json_encode($venueSchedule['opening_hours']);

                    $response['status'] = 'success';
                    $response['message'] = 'Venue details fetched Successfully';

                    $response['data'] = $venueDetail[0];
                    $response['data']['address'] = array_shift(explode(', ,', rtrim($venueDetail[0]['trim_address'], ", , , , ,")));
//                   $response['data']['address'] = Application_helper::generate_address($venueDetail[0]['address'], $venueDetail[0]['city'], $venueDetail[0]['country']);
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Venues details are not available';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = $validate2['message'];
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/getVenueDetail RESPONSE");
        $this->renderJSON($response);
    }



// ********************************************** EVENT RELATED APIs *******************************************************************************************
   /**
     * 42nite PRO API
     * Get Upcoming / Past Events
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
   public function actionGetEvents()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getEvents POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'event_type']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];


	  // logic to get count of friends going.
        $viewerId = "";
	    $booked_ticket_sql = "";
        $venue_followers_sql = "";
	    if (isset($_POST['viewer_id'])) {
			$viewerId = $_POST['viewer_id'];
			$booked_ticket_sql = "IFNULL((SELECT COUNT(DISTINCT `order`.`user_id`) FROM `order` LEFT JOIN `users_followers` ON `users_followers`.`other_users_id` = `order`.`user_id` WHERE `event_meta`.`id` = `order`.`event_meta_id` AND `users_followers`.`users_id` = $viewerId AND `order`.`status` = 1),0) AS `booked_tickets`,";
	        $venue_followers_sql = "IFNULL((SELECT 1 FROM `venue_followers` WHERE `venue_followers`.`user_id` = $viewerId AND `venue_followers`.`venue_id` = `venue_detail`.`id` LIMIT 1),0) AS `is_fav`,";
       }

            $EventOption1 = ' (`event_meta`.`event_end` > CURRENT_TIMESTAMP)';         // upcoming events
            $EventOption2 = ' `event_meta`.`event_end` <= CURRENT_TIMESTAMP ';         // past events

         //   $EventOption1 = "((`event_meta`.`event_end_date` > CURRENT_DATE) OR (`event_meta`.`event_end_date` = CURRENT_DATE AND `event_detail`.`event_end_time` > CURRENT_TIME))";
         //   $EventOption2 = "((`event_meta`.`event_end_date` < CURRENT_DATE) OR (`event_meta`.`event_end_date` = CURRENT_DATE AND `event_detail`.`event_end_time` < CURRENT_TIME))";
            $whereEvent = $_POST['event_type'] == '1' ? $EventOption1 : $EventOption2;
            //$whereEvent = $_POST['event_type'] == '1' ? ' `event_meta`.`event_date` >= CURRENT_DATE ' : ' `event_meta`.`event_date` <= CURRENT_DATE ';
            $condition = $_POST['event_type'] == '1' ? '1' : '2';

            $asc = $_POST['event_type'] == '1' ? " ASC " : " DESC ";

            if ($validate['user_role'] == 'Promoter') {
              // $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = $userId);
               $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = $userId) AND `user_login`.`user_id`= $userId";
            } else if ($validate['user_role'] == 'VenueOwner') {
                $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $userId)";
            }

            if ((isset($_POST['venue_id'])) && (!empty($_POST['venue_id']))) {
                $venueId = $_POST['venue_id'];
                $whereEvent .= " AND `event_detail`.`venue_id` = $venueId";
            }

            if ($_POST['event_type'] == '1')
            {  $limit = 50;  }
            else
            {  $limit = 5;   }

            if (!(empty($_POST['limit']))) {
                $limit = $_POST['limit'];
            }


            $userOffset = $_POST['offset'] ? $_POST['offset'] : 0;
            $offset = $userOffset > 0 ? "OFFSET $userOffset" : '';


            if ($limit > 0) {
                  $offset =  " LIMIT ".$limit ." ".$offset;
            }
            if($_POST['client_type']){
                $offset = '';
            }

            $eventsSql = "SELECT `event_detail`.`id` AS `parent_event_id`, `event_meta`.`id` AS `event_id`, `event_detail`.`event_name`,
                                 `event_detail`.`venue_id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`,`venue_detail`.`city` AS `city`,`venue_detail`.`state` AS `state`,`venue_detail`.`country` AS `country`,`user_login`.`role` AS `organised_by`,
                                 `user_login`.`display_name` AS `organiser_name`, `user_login`.`user_id` AS `organiser_id`,
                                 `event_detail`.`event_age_group`, `event_detail`.`event_description`, `event_meta`.`event_view_count`,
                                 (SELECT COUNT(`id`) FROM `event_album` WHERE `event_album`.`event_meta_id` = `event_meta`.`id`) AS `total_upload_images`,
                                 get_event_pic(`event_detail`.`id`) AS `event_pic`, get_event_ticket_type(`event_detail`.`id`) AS `event_ticket_type`,
                                 `event_detail`.`event_fee` AS `event_fee`, `event_detail`.`event_seats` AS `event_tickets`,
                                 get_available_seats(`event_meta`.`id`) AS `available_seats`,
                                 DATE_FORMAT(`event_meta`.`event_date`,'%m/%d/%Y') AS `event_date`,
                                 DATE_FORMAT(`event_meta`.`event_end_date`,'%m/%d/%Y') AS `event_end_date`,
                                 $booked_ticket_sql $venue_followers_sql
                                 IFNULL((SELECT SUM(`order`.`quantity`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id` and `order`.`status` = 1),0) AS `event_ticket_sold`,
                                 IFNULL((SELECT SUM(`order`.`amount`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id` and `order`.`status` = 1),0) AS `event_amount`,
                                 IFNULL((SELECT SUM(`transaction`.`platform_fee`) FROM `transaction` WHERE `transaction`.`order_id` IN (SELECT `order`.`master_order_id` from `order` where `order`.`event_meta_id` = `event_meta`.`id` and `order`.`status` = 1)),0) AS `event_platform_fee`,
                                 IFNULL((SELECT SUM(`transaction`.`stripe_fee`) FROM `transaction` WHERE `transaction`.`order_id` IN (SELECT `order`.`master_order_id` from `order` where `order`.`event_meta_id` = `event_meta`.`id` and `order`.`status` = 1)),0) AS `event_stripe_fee`,
                                 IFNULL((SELECT 1 FROM `payouts` WHERE `payouts`.`event_id` = `event_meta`.`id` LIMIT 1),0) AS `payout_status`,
                                 DATEDIFF(`event_meta`.`event_date`, CURRENT_DATE) AS `event_remained_days`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%d') AS `date_short`, `venue_detail`.`latitude` AS `venue_latitude`,  `venue_detail`.`longitude` AS `venue_longitude`,
                                 CONCAT(`event_meta`.`event_date`, ' ', `event_detail`.`event_start_time`) AS `date_and_time`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%b') AS `date_month`,
                                 DATE_FORMAT(`event_detail`.`event_start_time`, '%h:%i %p') AS `event_start_time`, `venue_detail`.`time_zone`,
                                 DATE_FORMAT(`event_detail`.`event_end_time`, '%h:%i %p') AS `event_end_time`,
                                 (CASE WHEN(`venue_type` = 1) THEN 1 ELSE 0 END) AS `venue_type`, `event_meta`.`tentative_date`
                         FROM `event_meta` , `event_detail`, `user_login`, `venue_detail`
                         WHERE `event_detail`.`id` = `event_meta`.`event_id`
                         AND   `event_meta`.`created_by` = `user_login`.`user_id`
                         AND `venue_detail`.`id` = `event_detail`.`venue_id`
                         AND `event_meta`.`status` = 0
                         AND $whereEvent ORDER BY `event_meta`.`event_date` $asc, `event_detail`.`event_start_time` $asc $offset";
                        /*
                         RIGHT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id`
                         RIGHT JOIN `user_login` ON `event_meta`.`created_by` = `user_login`.`user_id`
                         LEFT OUTER JOIN `venue_detail` ON `venue_detail`.`id` = `event_detail`.`venue_id`
                         WHERE $whereEvent ORDER BY `event_meta`.`event_date` $asc, `event_detail`.`event_start_time` $asc $offset";*/

            Yii::log(var_export($eventsSql, true), "warning", "pro/getEvents SQL");
            $events = Application_helper::getData($eventsSql);
         //   Yii::log(var_export($events, true), "warning", "pro/getEvents RESPONSE");

            if (!empty($events)) {
                $response['status'] = 'success';
                $response['message'] = 'Events fetched Successfully';

                $count = 0;
                foreach ($events as $r) {

                    $time_zone = $r['time_zone'];

                    $event_id = $r['parent_event_id'];
                    $event_meta_id = $r['event_id'];
                    $event_fee = $r['event_fee'];
                    $account_id = $r['organiser_id'];

                    //check event tickets and set mimumum & maximum fee
                    $check_event_tickets_sql = "SELECT MIN(`ticket_fee`) min_fee, MAX(`ticket_fee`) max_fee FROM `event_tickets` where `event_tickets`.`event_id` = $event_id AND `event_tickets`.`event_meta_id` = $event_meta_id";
                    $check_event_tickets = Yii::app()->db->createCommand($check_event_tickets_sql)->queryAll();

                    //  Yii::log(var_export($check_event_tickets_sql, true), "warning", "Get Tickets SQL");
                    //  Yii::log(var_export($check_event_tickets, true), "warning", "Get Tickets IN");

                    if (!empty($check_event_tickets))
                    {
                        $r['minimum_fee'] = $check_event_tickets[0]['min_fee'];
                        $r['maximum_fee'] = $check_event_tickets[0]['max_fee'];
                    }else {
                        $r['minimum_fee'] = $event_fee;
                        $r['maximum_fee'] = $event_fee;
                    }


                    date_default_timezone_set("$time_zone");
                    $time_zone_time = date('Y-m-d H:i:s');
                    $event_start_time = $r['date_and_time'];


                     // get venue fee % for the user
                    $VenueFeeData = AdminConfiguration::model()->findByAttributes(array('setting_enum' => 'venue_fee','account_id' => $account_id));
                    if ($VenueFeeData == null) {
                        $adminConfigurationData = AdminConfiguration::model()->findByAttributes(['setting_enum' => 'venue_fee']);
                        $venue_fee = $adminConfigurationData->data;
                    }
                    else {
                        $venue_fee = $VenueFeeData->data;
                    }

                     // set payout status
                    $payoutStatus = $r['payout_status'];
                    if ($payoutStatus == 1) {
                        $event_paid_status = 'paid';
                     } else {
                        $event_paid_status = 'unpaid'; }

                    if ($condition == 1) {
                     //   if (strtotime($time_zone_time) <= strtotime($event_start_time)) {

                            $response['data'][$count]['parent_event_id'] = $r['parent_event_id'];
                            $response['data'][$count]['event_id'] = $r['event_id'];
                            $response['data'][$count]['event_name'] = $r['event_name'];
                            $response['data'][$count]['venue_id'] = $r['venue_id'];
                            $response['data'][$count]['venue_name'] = $r['venue_name'];
                            $response['data'][$count]['city'] = $r['city'];
                            $response['data'][$count]['state'] = $r['state'];
                            $response['data'][$count]['country'] = $r['country'];
                            $response['data'][$count]['organised_by'] = $r['organised_by'];
                            $response['data'][$count]['organiser_name'] = $r['organiser_name'];
                            $response['data'][$count]['organiser_id'] = $r['organiser_id'];
                            $response['data'][$count]['event_age_group'] = $r['event_age_group'];
                            $response['data'][$count]['event_description'] = $r['event_description'];
                            $response['data'][$count]['event_view_count'] = $r['event_view_count'];
                            $response['data'][$count]['total_upload_images'] = $r['total_upload_images'];
                            $response['data'][$count]['event_pic'] = $r['event_pic'];
                            $response['data'][$count]['event_ticket_type'] = $r['event_ticket_type'];
                            $response['data'][$count]['event_fee'] = $r['event_fee'];
                            $response['data'][$count]['event_tickets'] = $r['event_tickets'];
                            $response['data'][$count]['event_date'] = $r['event_date'];
                            $response['data'][$count]['event_end_date'] = $r['event_end_date'];
                            $response['data'][$count]['event_ticket_sold'] = $r['event_ticket_sold'];
                            $response['data'][$count]['friends_going'] = $r['booked_tickets'];
                            $response['data'][$count]['event_amount'] = $r['event_amount'];
                            $response['data'][$count]['event_platform_fee'] = $r['event_platform_fee'];
                            $response['data'][$count]['event_stripe_fee'] = $r['event_stripe_fee'];
                            $response['data'][$count]['payout_status'] = $event_paid_status;
                            $response['data'][$count]['event_remained_days'] = $r['event_remained_days'];
                            $response['data'][$count]['date_short'] = $r['date_short'];
                            $response['data'][$count]['date_month'] = $r['date_month'];
                            $response['data'][$count]['event_start_time'] = $r['event_start_time'];
                            $response['data'][$count]['event_end_time'] = $r['event_end_time'];
                            $response['data'][$count]['venue_latitude'] = $r['venue_latitude'];
                            $response['data'][$count]['venue_longitude'] = $r['venue_longitude'];
                            $response['data'][$count]['minimum_fee'] = $r['minimum_fee'];
                            $response['data'][$count]['maximum_fee'] = $r['maximum_fee'];
                            $response['data'][$count]['fav'] = $r['is_fav'];
                            $response['data'][$count]['remained_seats'] = $r['available_seats'];
                            $response['data'][$count]['sold_out'] = $r['available_seats'] <= 0 ? "1" : "0";        //1 for true (sold), 0 for not sold
                            $response['data'][$count]['tentative_date'] = $r['tentative_date'];        //1 for tentative date, 0 for confirmed date

                            if (isset($_POST['viewer_id'])) {
                            $response['data'][$count]['is_rsvp_event'] = (string)Application_helper::set_rsvp($r['event_fee'], $r['event_tickets'], $r['event_id'], $userId); //1 for yes rsvp_event, 0 for not rsvp
                            }
                            else
                            {
                              $response['data'][$count]['is_rsvp_event'] = ''; //1 for yes rsvp_event, 0 for not rsvp
                            }
                            $count++;
                       //}
                    }

                    if ($condition == 2) {
                     //   Yii::log(var_export($time_zone_time, true), "warning", "pro/getEvents TimeZoneTime");
                     //   Yii::log(var_export($event_start_time, true), "warning", "pro/getEvents EventTimeZone");
                     //   if (strtotime($time_zone_time) > strtotime($event_start_time)) {

                            $response['data'][$count]['parent_event_id'] = $r['parent_event_id'];
                            $response['data'][$count]['event_id'] = $r['event_id'];
                            $response['data'][$count]['event_name'] = $r['event_name'];
                            $response['data'][$count]['city'] = $r['city'];
                            $response['data'][$count]['state'] = $r['state'];
                            $response['data'][$count]['country'] = $r['country'];
                            $response['data'][$count]['venue_id'] = $r['venue_id'];
                            $response['data'][$count]['venue_name'] = $r['venue_name'];
                            $response['data'][$count]['organised_by'] = $r['organised_by'];
                            $response['data'][$count]['organiser_name'] = $r['organiser_name'];
                            $response['data'][$count]['organiser_id'] = $r['organiser_id'];
                            $response['data'][$count]['event_age_group'] = $r['event_age_group'];
                            $response['data'][$count]['event_description'] = $r['event_description'];
                            $response['data'][$count]['event_view_count'] = $r['event_view_count'];
                            $response['data'][$count]['total_upload_images'] = $r['total_upload_images'];
                            $response['data'][$count]['event_pic'] = $r['event_pic'];
                            $response['data'][$count]['event_ticket_type'] = $r['event_ticket_type'];
                            $response['data'][$count]['event_fee'] = $r['event_fee'];
                            $response['data'][$count]['event_tickets'] = $r['event_tickets'];
                            $response['data'][$count]['event_date'] = $r['event_date'];
                            $response['data'][$count]['event_end_date'] = $r['event_end_date'];
                            $response['data'][$count]['event_ticket_sold'] = $r['event_ticket_sold'];
                            $response['data'][$count]['event_amount'] = $r['event_amount'];
                            $response['data'][$count]['event_platform_fee'] = $r['event_platform_fee'];
                            $response['data'][$count]['event_stripe_fee'] = $r['event_stripe_fee'];
                            $response['data'][$count]['payout_status'] = $event_paid_status;
                            $response['data'][$count]['event_remained_days'] = $r['event_remained_days'];
                            $response['data'][$count]['date_short'] = $r['date_short'];
                            $response['data'][$count]['date_month'] = $r['date_month'];
                            $response['data'][$count]['event_start_time'] = $r['event_start_time'];
                            $response['data'][$count]['event_end_time'] = $r['event_end_time'];
                            $response['data'][$count]['venue_latitude'] = $r['venue_latitude'];
                            $response['data'][$count]['venue_longitude'] = $r['venue_longitude'];
                            $response['data'][$count]['venue_fee'] = $venue_fee;
                            $count++;
                      //  }
                    }
                }

//                $response['pagination']['offset'] = (string)($userOffset ? $userOffset + count($events) : count($events));
                $response['pagination']['offset'] = (string)($userOffset ? $userOffset + $count : $count);
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No events available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        $response['app_version'] = Application_helper::getLatestAppVersion();

        Yii::log(var_export($response, true), "warning", "pro/getEvents RESPONSE");
        $this->renderJSON($response);
    }




   public function actionGetEventData()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getEventData POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'event_type']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $eventId = $_POST['event_id'];



          //  $EventOption1 = ' `event_meta`.`event_date` >= CURRENT_DATE ';
           // $EventOption2 = ' `event_meta`.`event_date` <= CURRENT_DATE ';

            $EventOption1 = "((`event_meta`.`event_end_date` > CURRENT_DATE) OR (`event_meta`.`event_end_date` = CURRENT_DATE AND `event_detail`.`event_end_time` > CURRENT_TIME))";
            $EventOption2 = "((`event_meta`.`event_end_date` < CURRENT_DATE) OR (`event_meta`.`event_end_date` = CURRENT_DATE AND `event_detail`.`event_end_time` < CURRENT_TIME))";
            $whereEvent = $_POST['event_type'] == '1' ? $EventOption1 : $EventOption2;
            //$whereEvent = $_POST['event_type'] == '1' ? ' `event_meta`.`event_date` >= CURRENT_DATE ' : ' `event_meta`.`event_date` <= CURRENT_DATE ';
            $condition = $_POST['event_type'] == '1' ? '1' : '2';

            $asc = $_POST['event_type'] == '1' ? " ASC " : " DESC ";


            if ($validate['user_role'] == 'Promoter') {
              // $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = $userId);
               $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = $userId) AND `user_login`.`user_id`= $userId";
            } else if ($validate['user_role'] == 'VenueOwner') {
                $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $userId)";
            }

            //reseting whereEvent to blank for newsfeed event profile access
            $whereEvent="";

            if (isset($_POST['venue_id'])) {
                $venueId = $_POST['venue_id'];
                $whereEvent .= " AND `event_detail`.`venue_id` = $venueId";
            }

            $userOffset = $_POST['offset'] ? $_POST['offset'] : 0;
            $offset = $userOffset > 0 ? "OFFSET $userOffset" : '';
            $offset =  " LIMIT 20 " . $offset;

            if($_POST['client_type']){
                $offset = '';
            }

            $eventsSql = "SELECT `event_detail`.`id` AS `parent_event_id`, `event_meta`.`id` AS `event_id`, `event_detail`.`event_name`,
                                 `event_detail`.`venue_id` AS `venue_id`, `venue_detail`.`name` AS `venue_name`,`user_login`.`role` AS `organised_by`,
                                 `user_login`.`display_name` AS `organiser_name`, `user_login`.`user_id` AS `organiser_id`,
                                 `event_detail`.`event_age_group`, `event_detail`.`event_description`, `event_meta`.`event_view_count`,
                                 (SELECT COUNT(`id`) FROM `event_album` WHERE `event_album`.`event_meta_id` = `event_meta`.`id`) AS `total_upload_images`,
                                 get_event_pic(`event_detail`.`id`) AS `event_pic`, get_event_ticket_type(`event_detail`.`id`) AS `event_ticket_type`,
                                 `event_detail`.`event_fee` AS `event_fee`, `event_detail`.`event_seats` AS `event_tickets`,
                                 DATE_FORMAT(`event_meta`.`event_date`,'%m/%d/%Y') AS `event_date`,
                                 DATE_FORMAT(`event_meta`.`event_end_date`,'%m/%d/%Y') AS `event_end_date`,
                                 IFNULL((SELECT SUM(`order`.`quantity`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id` and `order`.`status` = 1),0) AS `event_ticket_sold`,
                                 IFNULL((SELECT SUM(`order`.`amount`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id` and `order`.`status` = 1),0) AS `event_amount`,
                                 IFNULL((SELECT SUM(`transaction`.`platform_fee`) FROM `transaction` WHERE `transaction`.`order_id` IN (SELECT `order`.`master_order_id` from `order` where `order`.`event_meta_id` = `event_meta`.`id` and `order`.`status` = 1)),0) AS `event_platform_fee`,
                                 IFNULL((SELECT SUM(`transaction`.`stripe_fee`) FROM `transaction` WHERE `transaction`.`order_id` IN (SELECT `order`.`master_order_id` from `order` where `order`.`event_meta_id` = `event_meta`.`id` and `order`.`status` = 1)),0) AS `event_stripe_fee`,
                                 IFNULL((SELECT 1 FROM `payouts` WHERE `payouts`.`event_id` = `event_meta`.`id` LIMIT 1),0) AS `payout_status`,
                                 DATEDIFF(`event_meta`.`event_date`, CURRENT_DATE) AS `event_remained_days`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%d') AS `date_short`, `venue_detail`.`latitude` AS `venue_latitude`,  `venue_detail`.`longitude` AS `venue_longitude`,
                                 CONCAT(`event_meta`.`event_date`, ' ', `event_detail`.`event_start_time`) AS `date_and_time`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%b') AS `date_month`,
                                 DATE_FORMAT(`event_detail`.`event_start_time`, '%H:%i') AS `event_start_time`, `venue_detail`.`time_zone`,
                                 DATE_FORMAT(`event_detail`.`event_end_time`, '%H:%i') AS `event_end_time`
                         FROM `event_meta` , `event_detail`, `user_login`, `venue_detail`
                         WHERE `event_detail`.`id` = `event_meta`.`event_id`
                         AND   `event_meta`.`created_by` = `user_login`.`user_id`
                         AND `venue_detail`.`id` = `event_detail`.`venue_id`
                         AND `event_meta`.`id` = $eventId
                         $whereEvent ORDER BY `event_meta`.`event_date` $asc, `event_detail`.`event_start_time` $asc $offset";
                        /*
                         RIGHT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id`
                         RIGHT JOIN `user_login` ON `event_meta`.`created_by` = `user_login`.`user_id`
                         LEFT OUTER JOIN `venue_detail` ON `venue_detail`.`id` = `event_detail`.`venue_id`
                         WHERE $whereEvent ORDER BY `event_meta`.`event_date` $asc, `event_detail`.`event_start_time` $asc $offset";*/

            Yii::log(var_export($eventsSql, true), "warning", "pro/getEventData SQL");
            $events = Application_helper::getData($eventsSql);
            Yii::log(var_export($events, true), "warning", "pro/getEventData RESPONSE");

            if (!empty($events)) {
                $response['status'] = 'success';
                $response['message'] = 'Events fetched Successfully';

                $count = 0;
                foreach ($events as $r) {

                    $time_zone = $r['time_zone'];

                    date_default_timezone_set("$time_zone");
                    $time_zone_time = date('Y-m-d H:i:s');
                    $event_start_time = $r['date_and_time'];

                    $event_id = $r['parent_event_id'];
                    $event_meta_id = $r['event_id'];
                    $event_fee = $r['event_fee'];

                    $check_event_tickets_sql = "SELECT MIN(`ticket_fee`) min_fee, MAX(`ticket_fee`) max_fee FROM `event_tickets` where `event_tickets`.`event_id` = $event_id AND `event_tickets`.`event_meta_id` = $event_meta_id";
                    $check_event_tickets = Yii::app()->db->createCommand($check_event_tickets_sql)->queryAll();

                    //  Yii::log(var_export($check_event_tickets_sql, true), "warning", "Get Tickets SQL");
                      Yii::log(var_export($check_event_tickets, true), "warning", "Get Tickets IN");
					// var_dump($check_event_tickets);
                    if (!empty($check_event_tickets))
                    {
                        $r['minimum_fee'] = $check_event_tickets[0]['min_fee'] != NULL ? $check_event_tickets[0]['min_fee'] : $r[0]['event_fee'];
                        $r['maximum_fee'] = $check_event_tickets[0]['max_fee'] != NULL ? $check_event_tickets[0]['max_fee'] : $r[0]['event_fee'];
                     } else {
                           $r['minimum_fee'] = $r['event_fee'];
                           $r['maximum_fee'] = $r['event_fee'];
                     }

                    $payoutStatus = $r['payout_status'];
                    if ($payoutStatus == 1) {
                        $event_paid_status = 'paid';
                     } else {
                        $event_paid_status = 'unpaid'; }

                    if ($condition == 1) {
                     //   if (strtotime($time_zone_time) <= strtotime($event_start_time)) {

                            $response['data'][$count]['parent_event_id'] = $r['parent_event_id'];
                            $response['data'][$count]['event_id'] = $r['event_id'];
                            $response['data'][$count]['event_name'] = $r['event_name'];
                            $response['data'][$count]['venue_id'] = $r['venue_id'];
                            $response['data'][$count]['venue_name'] = $r['venue_name'];
                            $response['data'][$count]['organised_by'] = $r['organised_by'];
                            $response['data'][$count]['organiser_name'] = $r['organiser_name'];
                            $response['data'][$count]['organiser_id'] = $r['organiser_id'];
                            $response['data'][$count]['event_age_group'] = $r['event_age_group'];
                            $response['data'][$count]['event_description'] = $r['event_description'];
                            $response['data'][$count]['event_view_count'] = $r['event_view_count'];
                            $response['data'][$count]['total_upload_images'] = $r['total_upload_images'];
                            $response['data'][$count]['event_pic'] = $r['event_pic'];
                            $response['data'][$count]['event_ticket_type'] = $r['event_ticket_type'];
                            $response['data'][$count]['event_fee'] = $r['event_fee'];
                            $response['data'][$count]['event_tickets'] = $r['event_tickets'];
                            $response['data'][$count]['event_date'] = $r['event_date'];
                            $response['data'][$count]['event_end_date'] = $r['event_end_date'];
                            $response['data'][$count]['event_ticket_sold'] = $r['event_ticket_sold'];
                            $response['data'][$count]['event_amount'] = $r['event_amount'];
                            $response['data'][$count]['event_platform_fee'] = $r['event_platform_fee'];
                            $response['data'][$count]['event_stripe_fee'] = $r['event_stripe_fee'];
                            $response['data'][$count]['payout_status'] = $event_paid_status;
                            $response['data'][$count]['event_remained_days'] = $r['event_remained_days'];
                            $response['data'][$count]['date_short'] = $r['date_short'];
                            $response['data'][$count]['date_month'] = $r['date_month'];
                            $response['data'][$count]['event_start_time'] = $r['event_start_time'];
                            $response['data'][$count]['event_end_time'] = $r['event_end_time'];
                            $response['data'][$count]['venue_latitude'] = $r['venue_latitude'];
                            $response['data'][$count]['venue_longitude'] = $r['venue_longitude'];
                            $response['data'][$count]['minimum_fee'] = $r['minimum_fee'];
                            $response['data'][$count]['maximum_fee'] = $r['maximum_fee'];
                            $count++;
                       // }
                    }

                    if ($condition == 2) {
                        Yii::log(var_export($time_zone_time, true), "warning", "pro/getEventData TimeZoneTime");
                        Yii::log(var_export($event_start_time, true), "warning", "pro/getEventData EventTimeZone");
                        if (strtotime($time_zone_time) > strtotime($event_start_time)) {

                            $response['data'][$count]['parent_event_id'] = $r['parent_event_id'];
                            $response['data'][$count]['event_id'] = $r['event_id'];
                            $response['data'][$count]['event_name'] = $r['event_name'];
                            $response['data'][$count]['venue_id'] = $r['venue_id'];
                            $response['data'][$count]['venue_name'] = $r['venue_name'];
                            $response['data'][$count]['organised_by'] = $r['organised_by'];
                            $response['data'][$count]['organiser_name'] = $r['organiser_name'];
                            $response['data'][$count]['organiser_id'] = $r['organiser_id'];
                            $response['data'][$count]['event_age_group'] = $r['event_age_group'];
                            $response['data'][$count]['event_description'] = $r['event_description'];
                            $response['data'][$count]['event_view_count'] = $r['event_view_count'];
                            $response['data'][$count]['total_upload_images'] = $r['total_upload_images'];
                            $response['data'][$count]['event_pic'] = $r['event_pic'];
                            $response['data'][$count]['event_ticket_type'] = $r['event_ticket_type'];
                            $response['data'][$count]['event_fee'] = $r['event_fee'];
                            $response['data'][$count]['event_tickets'] = $r['event_tickets'];
                            $response['data'][$count]['event_date'] = $r['event_date'];
                            $response['data'][$count]['event_end_date'] = $r['event_end_date'];
                            $response['data'][$count]['event_ticket_sold'] = $r['event_ticket_sold'];
                            $response['data'][$count]['event_amount'] = $r['event_amount'];
                            $response['data'][$count]['event_platform_fee'] = $r['event_platform_fee'];
                            $response['data'][$count]['event_stripe_fee'] = $r['event_stripe_fee'];
                            $response['data'][$count]['payout_status'] = $event_paid_status;
                            $response['data'][$count]['event_remained_days'] = $r['event_remained_days'];
                            $response['data'][$count]['date_short'] = $r['date_short'];
                            $response['data'][$count]['date_month'] = $r['date_month'];
                            $response['data'][$count]['event_start_time'] = $r['event_start_time'];
                            $response['data'][$count]['event_end_time'] = $r['event_end_time'];
                            $count++;
                        }
                    }
                }

//                $response['pagination']['offset'] = (string)($userOffset ? $userOffset + count($events) : count($events));
                $response['pagination']['offset'] = (string)($userOffset ? $userOffset + $count : $count);
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No events available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        $response['app_version'] = Application_helper::getLatestAppVersion();

        Yii::log(var_export($response, true), "warning", "pro/getEventData RESPONSE");
        $this->renderJSON($response);
    }





    /**
     * 42nite PRO API
     * Create Event
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */


   public function actionCreateEventM()
    {
        try{

        Yii::log(var_export($_POST, true), "warning", "pro/createEvent POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_name', 'event_description', 'event_age_group', 'event_start_time', 'event_dates']);
        Yii::log(var_export($_POST, true), "warning", "pro/createEvent request validated");
        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {
            Yii::log(var_export($_POST, true), "warning", "pro/createEvent user role ".$validate['user_role']);
            $userId = $_POST['user_id'];
            $venue_type="";
            $event = new EventDetail();

            $event->venue_id = $venueId = $_POST['venue_id'];
            $event->event_name = $eventName = $_POST['event_name'];
            $event->event_description = $eventDescription = $_POST['event_description'];
            $event->event_age_group = $eventAgeGroup = $_POST['event_age_group'];

            if (isset($_POST['event_start_time'])) {
                $eventStartTime = date("H:i", strtotime($_POST['event_start_time']));
                $event->event_start_time = $eventStartTime;
            }

            if (isset($_POST['event_end_time'])) {
                $eventEndTime = date("H:i", strtotime($_POST['event_end_time']));
                $event->event_end_time = $eventEndTime;
            }

            if (isset($_POST['event_tickets'])) {
                $event->event_seats = $eventTickets = $_POST['event_tickets'];
            }

            if (isset($_POST['event_fee'])) {
                $event->event_fee = $eventFee = $_POST['event_fee'];
            }

            if ($event->save()) {

                $eventId = Yii::app()->db->lastInsertID;

                $eventDates = explode(',', $_POST['event_dates']);
                $emailEventDate = null;
                foreach ($eventDates as $eventDate) {

                    $eventMeta = new EventMeta();

                    $eventMeta->event_id = $eventId;
                    $date = date_create($eventDate);
                    $eventMeta->event_date = date_format($date, "Y-m-d");
                    if(isset($emailEventDate)){
                        $emailEventDate = $emailEventDate.', '.$eventMeta->event_date;
                    }else{
                        $emailEventDate =$eventMeta->event_date;
                    }

                    /******************************************************************************
                    * Event End Date logic added, especially for events which end after midnight.
                     */
                    if ($eventEndTime < $eventStartTime)
                    {
                        $end_date = $date;
                        $end_date->modify('+1 day');
                        $eventMeta->event_end_date = date_format($end_date, "Y-m-d");
                    }
                    else
                    {   $eventMeta->event_end_date = date_format($date, "Y-m-d"); }

                    $eventMeta->created_by = $userId;

                    $eventMeta->save();
                }

                if (!empty($_FILES)) {
                    Application_helper::add_event_image($eventId);
                }

                $response['status'] = 'success';
                $response['message'] = 'Event Created Successfully';
                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST sending email");
                Email_helper::send_new_event_admin($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Email_helper::send_event_summary($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST email sent");
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'There is some error while creating event';
                $response['data'] = $event->getErrors();
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/createEvent RESPONSE");
        $this->renderJSON($response);
    }catch(Exception $e){
        Yii::log(var_export($e->getMessage(), true), "warning", "pro/createEvent EXCEPTION");
        Yii::log(var_export($e->getTraceAsString(), true), "warning", "pro/createEvent TRACE");
    }
    }


   public function actionCreateEvent()
    {
        try{

        Yii::log(var_export($_POST, true), "warning", "pro/createEvent POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_name', 'event_description', 'event_age_group', 'event_start_time', 'event_dates']);

        Yii::log(var_export($_POST, true), "warning", "pro/createEvent request validated");
        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {
            Yii::log(var_export($_POST, true), "warning", "pro/createEvent user role ".$validate['user_role']);
            $userId = $_POST['user_id'];
            $venue_type="";
            $event = new EventDetail();

            $event->venue_id = $venueId = $_POST['venue_id'];
            $event->event_name = $eventName = $_POST['event_name'];
            $event->event_description = $eventDescription = $_POST['event_description'];
            $event->event_age_group = $eventAgeGroup = $_POST['event_age_group'];

            if (isset($_POST['event_start_time'])) {
                $eventStartTime = date("H:i", strtotime($_POST['event_start_time']));
                $event->event_start_time = $eventStartTime;
            }

            if (isset($_POST['event_end_time'])) {
                $eventEndTime = date("H:i", strtotime($_POST['event_end_time']));
                $event->event_end_time = $eventEndTime;
            }

            if (isset($_POST['event_tickets'])) {
                $event->event_seats = $eventTickets = $_POST['event_tickets'];
            }

            if (isset($_POST['event_fee'])) {
                $event->event_fee = $eventFee = $_POST['event_fee'];
            }

			$totalSeats = 0;
            $min_event_fee = 999;
            $max_event_fee = 0;
            if(isset($_POST['ticket_type_list'])){
	            foreach($_POST['ticket_type_list'] as $value){
	        		 $totalSeats = $totalSeats + (int)$value['ticketCount'];
	        		 if(((float)$value['ticketFee']) < $min_event_fee)
	        			$min_event_fee = $value['ticketFee'];
	        		if(((float)$value['ticketFee']) > $max_event_fee)
	        			$max_event_fee = $value['ticketFee'];
	        	}
	        	$event->event_seats = $totalSeats;
	            $event->event_fee = $min_event_fee;
            }
            if ($event->save()) {

                $eventId = Yii::app()->db->lastInsertID;

                $eventDates = explode(',', $_POST['event_dates']);
                $emailEventDate = null;
                foreach ($eventDates as $eventDate) {

                    $eventMeta = new EventMeta();

                    $eventMeta->event_id = $eventId;
                    $date = date_create($eventDate);
                    $eventMeta->event_date = date_format($date, "Y-m-d");
                    if(isset($emailEventDate)){
                        $emailEventDate = $emailEventDate.', '.$eventMeta->event_date;
                    }else{
                        $emailEventDate =$eventMeta->event_date;
                    }

                    /******************************************************************************
                    * Event End Date logic added, especially for events which end after midnight.
                     */
                    if ($eventEndTime < $eventStartTime)
                    {
                        $end_date = $date;
                        $end_date->modify('+1 day');
                        $eventMeta->event_end_date = date_format($end_date, "Y-m-d");
                    }
                    else
                    {   $eventMeta->event_end_date = date_format($date, "Y-m-d"); }

                    $eventMeta->created_by = $userId;

                    $eventMeta->save();
                    $event_meta_id = Yii::app()->db->lastInsertID;
                    $minFee=999999;
                    $maxFee=0;
                    $eventSeats = 0;
                    if(isset($_POST['ticket_type_list'])){
                    	$ticketTypeList = json_decode(base64_decode($_POST['ticket_type_list']),true);

	                    foreach($ticketTypeList as $value){
	                        $ticketTypeModel = new EventTicketType();

	                        $ticketTypeModel->ticket_name = $value['ticketType'];


	                        $ticketTypeModel->ticket_fee = $value['ticketFee'];
	                        $ticketTypeModel->ticket_instructions = $value['ticketDescription'];
	                        $ticketTypeModel->ticket_count = $value['ticketCount'];
	                        $eventSeats = $eventSeats + (int)$value['ticketCount'];
	                        if($minFee > (float)$value['ticketFee']){
	                        	$minFee = (float)$value['ticketFee'];
	                        }
	                        if($maxFee < (float)$value['ticketFee']){
	                        	$maxFee = (float)$value['ticketFee'];
	                        }
	                        $ticketTypeModel->event_id = $eventId;
	                        $ticketTypeModel->event_meta_id = $event_meta_id;

	                        $eventStartDateTime = date('Y-m-d H:i:s', strtotime($eventDate .' '.  $value['ticketStartTime']));
	                        $ticketStartDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'. $value['startDays'] .' days'));


	                        $ticketTypeModel->ticket_start_date_time = $ticketStartDateTime;

	                        $eventStartDateTime = date('Y-m-d H:i:s', strtotime($eventDate .' '.  $value['ticketExpiryTime']));
	                        $ticketEndDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'.$value['expireDay'].' days'));

	                        $ticketTypeModel->ticket_end_date_time = $ticketEndDateTime;

	                        $ticketTypeModel->save(false);
	                    }

	                    if (isset($_POST['promo_code_list'])) {
	                    	$promoCodeList = json_decode(base64_decode($_POST['promo_code_list']),true);

		                    foreach($promoCodeList as $value){

		                            $promomodel = new EventPromocode();

		                            $promomodel->promo_code = $value['promoCode'];
		                            $promomodel->promo_description = $value['promoCodeDescription'];
		                            $promomodel->discount = $value['promoCodeDiscount'];
		                            $eventStartDateTime = date('Y-m-d H:i:s', strtotime($eventDate .' '.  $value['promoStartTime']));

		                            $promoStartDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'.$value['promoStartDays'] .' days'));

		                            $eventEndDateTime = date('Y-m-d H:i:s', strtotime($eventDate.' '.$value['expiryTime']));
		                            $promoEndDateTime = date('Y-m-d H:i:s', strtotime($eventEndDateTime . ' -'.$value['expiryDay'] .' days'));

		                            $promomodel->promo_start_date_time = $promoStartDateTime;
		                            $promomodel->promo_end_date_time = $promoEndDateTime;
		                            $promomodel->event_id = $eventId;
		                            $promomodel->event_meta_id = $event_meta_id;

		                            $promomodel->ticket_name = $value['ticketTypes'];
		                            if($promomodel->ticket_name == "ALL"){
		                                $promomodel->all_tickets = 1;
		                            }
		                            $promomodel->save(false);


		                        }
		                    }
                		}
                	// }

                }

                if (!empty($_FILES)) {
                    Application_helper::add_event_image($eventId);
                }


                $fileName = 'IMG' . '_' . $eventId . ".png";
            	$event->event_pic = $fileName;
                $event->update();


                $response['status'] = 'success';
                $response['message'] = 'Event Created Successfully';

                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST sending email");
                Email_helper::send_new_event_admin($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Email_helper::send_event_summary($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST email sent");
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'There is some error while creating event';
                $response['data'] = $event->getErrors();
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/createEvent RESPONSE");
        // $this->renderJSON($response);
    }catch(Exception $e){
        Yii::log(var_export($e->getMessage(), true), "warning", "pro/createEvent EXCEPTION");
        Yii::log(var_export($e->getTraceAsString(), true), "warning", "pro/createEvent TRACE");
        // $this->renderJSON($e);
        $response = $e;
    }
    $this->renderJSON($response);
    }




   public function actionGetBouncers()
    {
        Yii::log(var_export($_POST, true), "warning", "Users/GetBouncers POST");
        $validation = Application_helper::check_validation($_POST, ['user_id']);
        if ($validation['status'] && ($validation['user_role'] == 'Admin' || $validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Promoter')) {

            $venueId = $_POST['venue_id'];
            $AndVenueClause = "";
            if(isset($venueId)){
            	 $AndVenueClause = " AND `user_login`.`created_by` = $venueId";
            }

            $getBouncersSql = "SELECT `users`.`id` AS `bouncer_id`, `users`.`name` AS `bouncer_name`,  `venue_detail`.`name` AS `bouncer_venue`,
                                      `users`.`email` AS `bouncer_email`, generate_phone_no(`users`.`mobile_no`) AS `formatted_phone_no`,
                                      `venue_detail`.`id` AS `bouncer_venue_id`, `users`.`mobile_no` AS `phone_no`, get_venue_pic(`venue_detail`.`id`) AS `bouncer_venue_pic`
                               FROM `users`
                               LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                               LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `user_login`.`created_by`
                               WHERE `user_login`.`role` = 'Bouncer'";

            $getBouncers = Application_helper::getData($getBouncersSql);

            if (!empty($getBouncers)) {
                $response['status'] = 'success';
                $response['message'] = 'Bouncers Fetched successfully';
                $response['data'] = $getBouncers;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No bouncers available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), "warning", "Users/GetBouncers Response");
        $this->renderJSON($response);
    }

   public function actionGetUserTagList(){
		Yii::log(var_export($_POST, true), "warning", "pro/getUserTagList GET");
        $validate = Application_helper::check_validation($_POST, ['user_id']);
        if ($validate['status']) {
	        $userId = $_POST['user_id'];
	        $user_role = $validate['user_role'];
                if ($user_role == "Admin") {
                    $get_user_follower_data_sql = "SELECT CONCAT(`users`.`id`, '-USER'), `users`.`name` FROM `users`";

                }
                if ($user_role == "Promoter") {
                    // $get_user_follower_data_sql = "SELECT `users`.`id`, `users`.`name` FROM `promoter_fav` LEFT JOIN `users` ON `users`.`id` = `promoter_fav`.`user_id` WHERE `promoter_fav`.`promoter_id` = $user_id";
                    $get_user_follower_data_sql = "SELECT
                    (CASE WHEN `users_followers`.`venue_id` THEN CONCAT(`venue_detail`.`name`,'') ELSE CONCAT(`user_login`.`display_name`,'') END) AS `name`,
                    (CASE WHEN `users_followers`.`venue_id` THEN  CONCAT(`users_followers`.`venue_id`,'-VENUE') ELSE CONCAT(`user_login`.`user_id`, '-USER') END) AS `id`
                    FROM `users_followers`
                    LEFT JOIN `user_login` ON  `user_login`.`user_id`  = `users_followers`.`users_id`
                    LEFT JOIN `venue_detail` ON  `venue_detail`.`id`  = `users_followers`.`venue_id`
                    WHERE `users_followers`.`other_users_id` = $user_id AND `users_followers`.`status` = 1";
                }

                if ($user_role == "VenueOwner") {
                	$venue_param = $_POST['venue_id'];
                    // $get_user_follower_data_sql = "SELECT `users`.`id`, `users`.`name` FROM `users_followers` LEFT JOIN `users` ON `users`.`id` = `users_followers`.`users_id` WHERE `users_followers`.`other_users_id` = $user_id AND `users_followers`.`status` = 1";
                    // `venue_followers`.`user_id` as `id`, `users`.`name`

                    $get_user_follower_data_sql ="SELECT
                    (CASE WHEN `venue_followers`.`action_venue_id` THEN CONCAT(`venue_detail`.`name`,'') ELSE CONCAT(`user_login`.`display_name`,'') END) AS `name`,
                    (CASE WHEN `venue_followers`.`action_venue_id` THEN  CONCAT(`venue_followers`.`action_venue_id`,'-VENUE') ELSE CONCAT(`user_login`.`user_id`, '-USER') END) AS `id`

                     FROM `venue_followers`
               LEFT JOIN `users` ON `users`.`id` = `venue_followers`.`user_id`
               LEFT JOIN `user_login` ON  `user_login`.`user_id`  = `venue_followers`.`user_id`
               LEFT JOIN `venue_detail` ON  `venue_detail`.`id`  = `venue_followers`.`action_venue_id`
               WHERE `venue_followers`.`venue_id` = $venue_param";
                }

                // print_r($get_user_follower_data_sql);
                $user_list = Application_helper::getData($get_user_follower_data_sql);
                 $response['status'] = 'success';
                // $response['message'] = 'There is some error while creating event';
                $response['data'] = $user_list;
        }else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getUserTagList RESPONSE");
        $this->renderJSON($response);

	}

    /**
     * 42nite PRO API
     * Create Event
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
   public function actionSendEventSummary()
    {
        try{

        Yii::log(var_export($_POST, true), "warning", "pro/createEvent POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_name', 'event_description', 'event_age_group', 'event_start_time', 'event_dates']);
        Yii::log(var_export($_POST, true), "warning", "pro/createEvent request validated");
        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {
            Yii::log(var_export($_POST, true), "warning", "pro/sendEventSummary user role ".$validate['user_role']);
            $userId = $_POST['user_id'];
            $venue_type="";
            $event = new EventDetail();

            $event->venue_id = $venueId = $_POST['venue_id'];
            $event->event_name = $eventName = $_POST['event_name'];
            $event->event_description = $eventDescription = $_POST['event_description'];
            $event->event_age_group = $eventAgeGroup = $_POST['event_age_group'];

            if (isset($_POST['event_start_time'])) {
                $eventStartTime = date("H:i", strtotime($_POST['event_start_time']));
                $event->event_start_time = $eventStartTime;
            }

            if (isset($_POST['event_end_time'])) {
                $eventEndTime = date("H:i", strtotime($_POST['event_end_time']));
                $event->event_end_time = $eventEndTime;
            }

            if (isset($_POST['event_tickets'])) {
                $event->event_seats = $eventTickets = $_POST['event_tickets'];
            }

            if (isset($_POST['event_fee'])) {
                $event->event_fee = $eventFee = $_POST['event_fee'];
            }

            if ($event->save()) {

                $eventId = Yii::app()->db->lastInsertID;

                $eventDates = explode(',', $_POST['event_dates']);
                $emailEventDate = null;
                foreach ($eventDates as $eventDate) {

                    $eventMeta = new EventMeta();

                    $eventMeta->event_id = $eventId;
                    $date = date_create($eventDate);
                    $eventMeta->event_date = date_format($date, "Y-m-d");
                    if(isset($emailEventDate)){
                        $emailEventDate = $emailEventDate.', '.$eventMeta->event_date;
                    }else{
                        $emailEventDate =$eventMeta->event_date;
                    }

                    /******************************************************************************
                    * Event End Date logic added, especially for events which end after midnight.
                     */
                    if ($eventEndTime < $eventStartTime)
                    {
                        $end_date = $date;
                        $end_date->modify('+1 day');
                        $eventMeta->event_end_date = date_format($end_date, "Y-m-d");
                    }
                    else
                    {   $eventMeta->event_end_date = date_format($date, "Y-m-d"); }

                    $eventMeta->created_by = $userId;

                    $eventMeta->save();
                }

                if (!empty($_FILES)) {
                    Application_helper::add_event_image($eventId);
                }

                $response['status'] = 'success';
                $response['message'] = 'Event Created Successfully';
                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST sending email");
                Email_helper::send_new_event_admin($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Email_helper::send_event_summary($eventTickets,$eventAgeGroup,$eventFee,$_POST['venue_name'], $_POST['venue_address'], $eventDescription,$venue_type,$eventName, $emailEventDate, $eventStartTime." - ".$eventEndTime);
                Yii::log(var_export($_POST, true), "info", "pro/createEvent POST email sent");
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'There is some error while creating event';
                $response['data'] = $event->getErrors();
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/createEvent RESPONSE");
        $this->renderJSON($response);
    }catch(Exception $e){
        Yii::log(var_export($e->getMessage(), true), "warning", "pro/createEvent EXCEPTION");
        Yii::log(var_export($e->getTraceAsString(), true), "warning", "pro/createEvent TRACE");
    }
    }


    /**
     * 42nite PRO API
     * Update Event
     *
     * Last Update Details
     * For : Response updated (event object required)
     * By : Bhavik Rathod
     * On : 16/6/17
     */
   public function actionUpdateEvent()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/updateEvent POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {
            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
            $eventId = $_POST['parent_event_id'];
            $eventMetaId = $_POST['event_id'];

            $event = EventDetail::model()->findByAttributes(['id' => $eventId, 'venue_id' => $venueId]);

            if ($event) {

                if (isset($_POST['event_name'])) {
                    $event->event_name = $_POST['event_name'];
                }

                if (isset($_POST['event_description'])) {
                    $event->event_description = $_POST['event_description'];
                }

                if (isset($_POST['event_tickets'])) {
                    $event->event_seats = $_POST['event_tickets'];
                }

                if (isset($_POST['event_fee'])) {
                    $event->event_fee = $_POST['event_fee'];
                }

                if (isset($_POST['event_age_group'])) {
                    $event->event_age_group = $_POST['event_age_group'];
                }

                if (isset($_POST['event_start_time'])) {
                    $startTime = date("H:i", strtotime($_POST['event_start_time']));
                    $event->event_start_time = $startTime;
                }

                if (isset($_POST['event_end_time'])) {
                    $endTime = date("H:i", strtotime($_POST['event_end_time']));
                    $event->event_end_time = $endTime;
                }

                if (isset($_POST['event_dates'])) {
                    $eventMeta = EventMeta::model()->findByPk($eventMetaId);
                    $date = date_create($_POST['event_dates']);
                    $eventMeta->event_date = date_format($date, "Y-m-d");

                     /******************************************************************************
                    * Event End Date logic added, especially for events which end after midnight.
                     */
                    if ($endTime < $startTime)
                    {
                        $end_date = $date;
                        $end_date->modify('+1 day');
                        $eventMeta->event_end_date = date_format($end_date, "Y-m-d");
                    }
                    else
                    {   $eventMeta->event_end_date = date_format($date, "Y-m-d"); }

                    $eventMeta->update();

                    $minFee=999999;
                    $maxFee=0;
                    $eventSeats = 0;
                    if(isset($_POST['ticket_type_list'])){
                    	EventTicketType::deleteAll('event_meta_id = :event_meta_id', array(':event_meta_id' => $eventMetaId));
                    	$ticketTypeList = json_decode(base64_decode($_POST['ticket_type_list']),true);

	                    foreach($ticketTypeList as $value){
	                        $ticketTypeModel = new EventTicketType();

	                        $ticketTypeModel->ticket_name = $value['ticketType'];


	                        $ticketTypeModel->ticket_fee = $value['ticketFee'];
	                        $ticketTypeModel->ticket_instructions = $value['ticketDescription'];
	                        $ticketTypeModel->ticket_count = $value['ticketCount'];
	                        $eventSeats = $eventSeats + (int)$value['ticketCount'];
	                        if($minFee > (float)$value['ticketFee']){
	                        	$minFee = (float)$value['ticketFee'];
	                        }
	                        if($maxFee < (float)$value['ticketFee']){
	                        	$maxFee = (float)$value['ticketFee'];
	                        }
	                        $ticketTypeModel->event_id = $eventId;
	                        $ticketTypeModel->event_meta_id = $eventMetaId;

	                        $eventStartDateTime = date('Y-m-d H:i:s', strtotime($end_date .' '.  $value['ticketStartTime']));
	                        $ticketStartDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'. $value['startDays'] .' days'));


	                        $ticketTypeModel->ticket_start_date_time = $ticketStartDateTime;

	                        $eventStartDateTime = date('Y-m-d H:i:s', strtotime($end_date .' '.  $value['ticketExpiryTime']));
	                        $ticketEndDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'.$value['expireDay'].' days'));

	                        $ticketTypeModel->ticket_end_date_time = $ticketEndDateTime;

	                        $ticketTypeModel->save(false);
	                    }

	                    if (isset($_POST['promo_code_list'])) {
	                    	 $eventPromocodeModel = EventPromocode::deleteAll('event_meta_id = :event_meta_id', array(':event_meta_id' => $eventMetaId));
	                    	$promoCodeList = json_decode(base64_decode($_POST['promo_code_list']),true);

		                    foreach($promoCodeList as $value){

		                            $promomodel = new EventPromocode();

		                            $promomodel->promo_code = $value['promoCode'];
		                            $promomodel->promo_description = $value['promoCodeDescription'];
		                            $promomodel->discount = $value['promoCodeDiscount'];
		                            $eventStartDateTime = date('Y-m-d H:i:s', strtotime($end_date .' '.  $value['promoStartTime']));

		                            $promoStartDateTime = date('Y-m-d H:i:s', strtotime($eventStartDateTime . ' -'.$value['promoStartDays'] .' days'));

		                            $eventEndDateTime = date('Y-m-d H:i:s', strtotime($end_date.' '.$value['expiryTime']));
		                            $promoEndDateTime = date('Y-m-d H:i:s', strtotime($eventEndDateTime . ' -'.$value['expiryDay'] .' days'));

		                            $promomodel->promo_start_date_time = $promoStartDateTime;
		                            $promomodel->promo_end_date_time = $promoEndDateTime;
		                            $promomodel->event_id = $eventId;
		                            $promomodel->event_meta_id = $eventMetaId;

		                            $promomodel->ticket_name = $value['ticketTypes'];
		                            if($promomodel->ticket_name == "ALL"){
		                                $promomodel->all_tickets = 1;
		                            }
		                            $promomodel->save(false);


		                        }
		                    }
                		}
                }

                if ($event->update()) {
                    if (!empty($_FILES)) {
                        Application_helper::add_event_image($eventId);
                    }
                    if(isset($_POST['profile_pic'])){
                		$event->event_pic = $_POST['profile_pic'];
                    	$event->update();
                	}

                    $getEventDataSql = "SELECT `event_detail`.`id` AS `parent_event_id`, `event_meta`.`id` AS `event_id`, `event_detail`.`event_name`, `event_detail`.`venue_id` AS `venue_id`,
                                 `user_login`.`role` AS `organised_by`, `user_login`.`display_name` AS `organiser_name`, `user_login`.`user_id` AS `organiser_id`,
                                 `event_detail`.`event_age_group`, `event_detail`.`event_description`, `event_meta`.`event_view_count`,
                                 get_event_pic(`event_detail`.`id`) AS `event_pic`, get_event_ticket_type(`event_detail`.`id`) AS `event_ticket_type`,
                                 `event_detail`.`event_fee` AS `event_fee`, `event_detail`.`event_seats` AS `event_tickets`, DATE_FORMAT(`event_meta`.`event_date`,'%m/%d/%Y') AS `event_date`,
                                 (SELECT COUNT(`order`.`quantity`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id`) AS `event_ticket_sold`,
                                 DATEDIFF(`event_meta`.`event_date`, CURRENT_DATE) AS `event_remained_days`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%d') AS `date_short`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%b') AS `date_month`,
                                 DATE_FORMAT(`event_detail`.`event_start_time`, '%h:%i %p') AS `event_start_time`,
                                 DATE_FORMAT(`event_detail`.`event_end_time`, '%h:%i %p') AS `event_end_time`
                         FROM `event_meta`
                         LEFT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id`
                         LEFT JOIN `user_login` ON `event_meta`.`created_by` = `user_login`.`user_id`
                         WHERE `event_meta`.`id` = $eventMetaId";

                    $eventData = Application_helper::getData($getEventDataSql);

                    $response['status'] = 'success';
                    $response['message'] = 'Event Updated Successfully';
                    $response['data'] = $eventData[0];
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'There was some error while updating event';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Event data not available or Event not created for this venue';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/updateEvent RESPONSE");
        $this->renderJSON($response);
    }


    /**
     * 42nite PRO API
     * Remove Event
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
   public function actionRemoveEvent()
    {
        Yii::log(var_export($_POST, true), 'warning', 'pro/removeEvent POST');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'event_meta_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin' || $validation['user_role'] == 'Promoter')) {

            //TODO : ADD EXTRA VALIDATION FOR CHECK FROM BY WHOM EVENT IS CREATED (IF REQUIRED)

            $eventMetaId = $_POST['event_meta_id'];

            EventMeta::model()->deleteByPk($eventMetaId);

            $response['status'] = 'success';
            $response['message'] = 'Event removed Successfully';
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), 'warning', 'pro/removeEvent RESPONSE');
        $this->renderJSON($response);
    }

    /**
     * Cancel Event from LIve SYstem including refunds of all tickets sold.
     */
   public function actionCancelEventV2()
     {

         Yii::log(var_export($_POST, true), 'warning', 'pro/cancelEvent POST');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'event_meta_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin' || $validation['user_role'] == 'Promoter')) {

            //TODO : ADD EXTRA VALIDATION FOR CHECK FROM BY WHOM EVENT IS CREATED (IF REQUIRED)

            $eventMetaId = $_POST['event_meta_id'];

            $event = EventMeta::model()->findByAttributes(["id" => $eventMetaId]);
            $event_id = $event->event_id;
            Yii::log(var_export($event_id, true), 'warning', 'Event ID :');

            // check transactions and orders
            $getMasterOrderSQL = "SELECT `id`,`master_order_id` from `order` where `event_meta_id` = $eventMetaId and `status` = 1";
            $saleData = Application_helper::getData($getMasterOrderSQL);
            $total_count = sizeof($saleData);
            Yii::log(var_export($total_count, true), 'warning', 'Total Count:');
            if (sizeof($saleData) > 0)
            {
               // delete transaction data
               $count = 0;
               foreach ($saleData as $sale_order) {
                     Yii::log(var_export($sale_order, true), 'warning', 'Sale Data:');
                     $sale_order_id = $sale_order['master_order_id'];
                     $sub_order_id = $sale_order['id'];
                     // get Transaction Amount from Transaction Table.
                     if ($sale_order_id > 0) {

                        $transaction_data = Transaction::model()->findByAttributes(["order_id" => $sale_order_id]);
                        Yii::log(var_export($transaction_data, true), 'warning', 'Transaction Data:');
                        if ($transaction_data) {
                           $transaction_amount = $transaction_data->amount;
                           $transaction_id = $transaction_data->transaction_id;
                           if ($transaction_amount > 0) {
                              $amount_to_refund = $transaction_amount * 100;
                              Yii::log(var_export($transaction_id, true), 'warning', 'Refunding Transaction:');
                              Yii::log(var_export($transaction_amount, true), 'warning', 'Refund Amount:');
                              // call Refund API to refund Transaction.
                              $refund = Stripe::createRefundForCharge($transaction_id, $amount_to_refund);
                              $status = $refund['status'];

                              if ($status == 'succeeded') {
                                    $response['status'] = $status;
                                    $response['id'] = $refund['id'];
                                    // update status of order to refunded.
                                    $refund_updateSQL = "UPDATE `order` SET `status` = 2 WHERE `id`= $sub_order_id AND `event_meta_id` = $eventMetaId AND `status` = 1";
                                    $refund_status = Application_helper::updateData($refund_updateSQL);
                                    if ($refund_status) {
                                       $count++;
                                    }
                              } else {
                                    $response['status'] = "failure";
                                    $response['message'] = "Refund Failed.";
                                    $response['error'] = $refund['error'];
                              }
                           }
                        }

                     }
               }
               Yii::log(var_export($count, true), 'warning', 'Refunded Count:');
               if ($count > 0) {
                  $response['Total Transactions'] = $total_count;
                  $response['Refunded Transactions'] = $count;
                  $response['status'] = 'success';
                  $response['message'] = 'Event Cancelled Successfully';
               } else {
                  $response['Total Transactions'] = $total_count;
                  $response['Refunded Transactions'] = $count;
                  $response['status'] = 'failure';
                  $response['message'] = 'Refund Failed.';
               }
            } else {
                  $response['status'] = 'failure';
                  $response['message'] = 'No Transactions found for refund.';
            }

      } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
      }

        Yii::log(var_export($response, true), 'warning', 'pro/cancelEvent RESPONSE');
        $this->renderJSON($response);

   }

   /**
     * Cancel A Particular Transaction from LIve SYstem including refunds of tickets purchased.
     */
   public function actionCancelTransactionV2()
   {

         Yii::log(var_export($_POST, true), 'warning', 'pro/cancelTransaction POST');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'transaction_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin' || $validation['user_role'] == 'Promoter')) {

            //TODO : ADD EXTRA VALIDATION FOR CHECK FROM BY WHOM EVENT IS CREATED (IF REQUIRED)

            $transaction_id = $_POST['transaction_id'];
            $transaction_data = Transaction::model()->findByAttributes(["transaction_id" => $transaction_id]);
            Yii::log(var_export($transaction_data, true), 'warning', 'Transaction Data:');
            if ($transaction_data) {
                  $order_id = $transaction_data->order_id;
				 $transaction_amount = $transaction_data->amount;
                  $transaction_id = $transaction_data->transaction_id;
		  
				 $order_data = Order::model()->findByAttributes(["master_order_id" => $order_id]);
				 Yii::log(var_export($order_data, true), 'warning', 'Transaction Data:');
				 $order_qty = $order_data->quantity;
				 $order_amount = $order_data->amount;
				 $type = $order_data->type;
                  
                  if ($order_amount > 0) {
                        $amount_to_refund = $order_amount * 100;
                        Yii::log(var_export($transaction_id, true), 'warning', 'Refunding Transaction:');
                        Yii::log(var_export($order_amount, true), 'warning', 'Refund Amount:');

                        // call Refund API to refund Transaction.
                        $refund = Stripe::createRefundForCharge($transaction_id, $amount_to_refund);
                        $status = $refund['status'];

                        if ($status == 'succeeded') {
                           $response['status'] = $status;
                           $response['id'] = $refund['id'];

                           // update status of order to refunded.
                           $refund_updateSQL = "UPDATE `order` SET `status` = 2, `refunded_amount` = $order_amount, `refund_date` = CURRENT_TIMESTAMP, `refund_qty` = $order_qty  WHERE `master_order_id`= $order_id AND `status` = 1";
                           $refund_status = Application_helper::updateData($refund_updateSQL);
                           if ($refund_status) {
                              $response['status'] = 'success';
                              $response['message'] = 'Transaction is cancelled successfully';
                           } else {
                              $response['status'] = 'failure';
                              $response['message'] = 'Refund Failed.';
                           }  // end of refund status if block

                        } else {
                           $response['status'] = "failure";
                           $response['message'] = "Refund Failed.";
                           $response['error'] = $refund['error'];
                        }     // end of stripe refund status if block
                  } else {
						if ($type == "3") {
							// update status of order to refunded.
                           $refund_updateSQL = "UPDATE `order` SET `status` = 2, `refunded_amount` = $order_amount, `refund_date` = CURRENT_TIMESTAMP, `refund_qty` = $order_qty  WHERE `master_order_id`= $order_id AND `status` = 1";
                           $refund_status = Application_helper::updateData($refund_updateSQL);
						   
                           if ($refund_status) {
                              $response['status'] = 'success';
                              $response['message'] = 'Transaction is cancelled successfully';
                           } 
						   
						} else {
							  $response['status'] = 'failure';
							  $response['message'] = 'Refund Amount should be greater than 0';
						}
					} 
				}
				else {
					$response['status'] = 'failure';
					$response['message'] = 'Invalid Transaction ID';
				}

         } else  {
            $response['status'] = 'failure';
            $response['message'] = 'You are not authorized to cancel transaction.';
         }

         Yii::log(var_export($response, true), 'warning', 'pro/cancelEvent RESPONSE');
        $this->renderJSON($response);
   }
    /**
     * Remove Event from LIve SYstem including all orders, transactions.
     */
   public function actionRemoveEventV2()
    {
        Yii::log(var_export($_POST, true), 'warning', 'pro/removeEvent POST');

        $validation = Application_helper::check_validation($_POST, ['user_id', 'event_meta_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin' || $validation['user_role'] == 'Promoter')) {

            //TODO : ADD EXTRA VALIDATION FOR CHECK FROM BY WHOM EVENT IS CREATED (IF REQUIRED)

            $eventMetaId = $_POST['event_meta_id'];

            $event = EventMeta::model()->findByAttributes(["id" => $eventMetaId]);
            $event_id = $event->event_id;
            Yii::log(var_export($event_id, true), 'warning', 'Event ID :');

            // check transactions and remove transactions and orders
            $getMasterOrderSQL = "SELECT `master_order_id` from `order` where `event_meta_id` = $eventMetaId and `status` = 1";
            $saleData = Application_helper::getData($getMasterOrderSQL);


            $total_count = sizeof($saleData);
            Yii::log(var_export($total_count, true), 'warning', 'Total Count:');

            // Delete Transaction & Order Data, if Any
            if (sizeof($saleData) > 0)
            {
               // delete transaction data
               $count = 0;
               foreach ($saleData as $sale_order) {
                     Yii::log(var_export($sale_order, true), 'warning', 'Sale Data:');
                     $sale_order_id = $sale_order['master_order_id'];
                     Yii::log(var_export($sale_order_id, true), 'warning', 'Deleting Order:');
                     $deleteTransaction = "DELETE FROM `transaction` WHERE `order_id` = $sale_order_id";
                     $deletedT = Application_helper::updateData($deleteTransaction);
                     if ($deletedT) {

                        $deleteOrder = "DELETE FROM `order` Where `master_order_id` = $sale_order_id";
                        $deletedO = Application_helper::updateData($deleteOrder);
                        if ($deletedO) {
                           $count++;
                        }
                     }
               }
               Yii::log(var_export($count, true), 'warning', 'Deleted Count:');

            }

            // Delete Event Tickets & Event Promocodes
            $deleteTicketsSQL = "DELETE FROM `event_tickets` WHERE `event_meta_id` = $eventMetaId";
            $deletedTickets = Application_helper::updateData($deleteTicketsSQL);
            if ($deletedTickets) {
               Yii::log(var_export($eventMetaId, true), 'warning', 'Deleted Tickets for Event:');

               //get Promo codes count
               $promo_count = 0;
               $getpromocodesSQL = "SELECT count(`id`) as `count` from `event_promocode` WHERE `event_meta_id` = $eventMetaId";
               $promo_count = Application_helper::getData($getpromocodesSQL);
               Yii::log(var_export($promo_count, true), 'warning', 'Promocode Count:');
               $promoCount = $promo_count['count'];
               if ($promoCount > 0) {
                  // Delete Event Promocodes
                  $deletePromocodesSQL = "DELETE FROM `event_promocode` WHERE `event_meta_id` = $eventMetaId";
                  $deletedPromos = Application_helper::updateData($deletePromocodesSQL);
                  if ($deletedPromos) {
                     Yii::log(var_export($eventMetaId, true), 'warning', 'Deleted Promocodes for Event: ');

                     //Delete from Event Detail
                     EventDetail::model()->deleteByPk($event_id);

                     // Delete from Event Meta
                     EventMeta::model()->deleteByPk($eventMetaId);

                     $response['status'] = 'success';
                     $response['message'] = 'Event removed Successfully';
                  }
                  else {
                     $response['status'] = 'failure';
                     $response['message'] = "failed to delete promo codes";
                  }
               }
               else {

                    //Delete from Event Detail
                     EventDetail::model()->deleteByPk($event_id);

                     // Delete from Event Meta
                     EventMeta::model()->deleteByPk($eventMetaId);

                     $response['status'] = 'success';
                     $response['message'] = 'Event removed Successfully';
               }
            } else {


                     $response['status'] = 'failure';
                     $response['message'] = "failed to delete tickets";

            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), 'warning', 'pro/removeEvent RESPONSE');
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Get Past event images
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */
   public function actionGetPastEventImages()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getPastEventImages POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $eventId = $_POST['parent_event_id'];
            $eventMetaId = $_POST['event_id'];

            $images = Application_helper::get_event_images($eventId, $eventMetaId, TRUE);

            if (!empty($images)) {
                $response['status'] = 'success';
                $response['message'] = 'Past images fetched Successfully';
                $response['data'] = $images;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No past event images are available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getPastEventImages RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Add Past Event Images
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
   public function actionAddPastEventImages()
    {

    	header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');

		if(array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
		    header('Access-Control-Allow-Headers: '
		           . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
		} else {
		    header('Access-Control-Allow-Headers: origin, x-requested-with, content-type, cache-control');
		}

        Yii::log(var_export($_POST, true), "warning", "pro/addPastEventImages POST");
        Yii::log(var_export($_FILES, true), "warning", "pro/addPastEventImages FILES");

        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);

        if(!$validate['status']){
        	$validate = Application_helper::check_validation($_GET, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);
        }

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $eventId = $_POST['parent_event_id'];
            $eventMetaId = $_POST['event_id'];

            $images = [];

            if (!empty($_FILES)) {
                $counter = 0;
                foreach ($_FILES['file']['tmp_name'] as $image) {

                    $fileName = $eventMetaId . $counter . '_' . time() . '.png';

                    $folder = "images/event/$eventId/EventAlbum/";

                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }

                    if (move_uploaded_file($_FILES['file']['tmp_name'][$counter]['image'], $folder . $fileName)) {

                        $model = new EventAlbum();
                        $model->image_url = $fileName;
                        $model->event_id = $eventId;
                        $model->event_meta_id = $eventMetaId;

                        if ($model->save()) {
                            $imageId = Yii::app()->db->getLastInsertID();

                            $base_url = Yii::app()->getBaseUrl(true);

                            $uploadedImage['event_album_id'] = $imageId;
                            $uploadedImage['image_url'] = $base_url . '/' . $folder . $fileName;

                            $images[] = $uploadedImage;
                        }

                        //Resize Uploaded image
                        Application_helper::resize_image($folder . $fileName, 600, 70);

                        Yii::app()->user->setFlash('success', "Image saved Successfully!");
                    }

                    $counter++;
                }

                $response['status'] = 'success';
                $response['message'] = "$counter past event images uploaded Successfully";
                $response['data'] = $images;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Please choose image to upload';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/addPastEventImages RESPONSE");
        $this->renderJSON($response);
    }



   public function actionAddPastEventImagesV2()
    {

    	header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');

		if(array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
		    header('Access-Control-Allow-Headers: '
		           . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
		} else {
		    header('Access-Control-Allow-Headers: origin, x-requested-with, content-type, cache-control');
		}

        Yii::log(var_export($_POST, true), "warning", "pro/addPastEventImages POST");
        Yii::log(var_export($_FILES, true), "warning", "pro/addPastEventImages FILES");

        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);

        if(!$validate['status']){
        	$validate = Application_helper::check_validation($_GET, ['user_id', 'venue_id', 'event_id', 'parent_event_id']);
        }

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $eventId = $_POST['parent_event_id'];
            $eventMetaId = $_POST['event_id'];

            $images = [];

            if (!empty($_FILES)) {
                $counter = 0;
                foreach ($_FILES['file']['name'] as $image) {

                    $fileName = $eventMetaId . $counter . '_' . time() . '.png';

                    $folder = "images/event/$eventId/EventAlbum/";

                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }

                    if (move_uploaded_file($_FILES['file']['name'][$counter], $folder . $fileName)) {

                        $model = new EventAlbum();
                        $model->image_url = $fileName;
                        $model->event_id = $eventId;
                        $model->event_meta_id = $eventMetaId;

                        if ($model->save()) {
                            $imageId = Yii::app()->db->getLastInsertID();

                            $base_url = Yii::app()->getBaseUrl(true);

                            $uploadedImage['event_album_id'] = $imageId;
                            $uploadedImage['image_url'] = $base_url . '/' . $folder . $fileName;

                            $images[] = $uploadedImage;
                        }

                        //Resize Uploaded image
                        Application_helper::resize_image($folder . $fileName, 600, 70);

                        Yii::app()->user->setFlash('success', "Image saved Successfully!");
                    }

                    $counter++;
                }

                $response['status'] = 'success';
                $response['message'] = "$counter past event images uploaded Successfully";
                $response['data'] = $images;
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Please choose image to upload';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/addPastEventImages RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Remove Past event images
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
   public function actionRemovePastEventImage()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/removePastEventImage POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'event_id', 'event_album_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            //TODO : ADD EXTRA VALIDATION FOR CHECK FROM WHOME EVENT IS CREATED BY (IF REQUIRED)

            $eventAlbumId = $_POST['event_album_id'];

            EventAlbum::model()->deleteByPk($eventAlbumId);

            $response['status'] = 'success';
            $response['message'] = 'Image removed Successfully';

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/removePastEventImage RESPONSE");
        $this->renderJSON($response);
    }




   public function actionStripeAccountWebHook()
    {
    	Yii::log(var_export($_GET, true), "warning", "pro/stripeAccountWebHook GET");
        $validate = Application_helper::check_validation($_GET, ['user_id']);
        if ($validate['status']) {
	        $userId = $_GET['user_id'];
        	 if($validate['user_role'] == 'VenueOwner'){
	        	$venueId = $_GET['venue_id'];
	        	$stripeAccountStatus = Stripe::checkStripeBusinessAccountStatus($venueId);
				$userBusinessRequested = UserBusinessRequests::model()->findByAttributes(["user_id" => $userId,"venue_id" => $venueId]);
	            if($stripeAccountStatus->status == 'ACTIVE'){
    	            $userBusinessRequested->request_status = 3;
        	    }else{
            	    $userBusinessRequested->request_status = 2;
	            }
    	        $userBusinessRequested->save();

        	 }else if($validate['user_role'] == 'Promoter' ){
        	 	$stripeAccountStatus = Stripe::checkStripeBusinessAccountStatus($userId);
				$userBusinessRequested = UserBusinessRequests::model()->findByAttributes(["user_id" => $userId]);
	            if($stripeAccountStatus->status == 'ACTIVE'){
    	            $userBusinessRequested->request_status = 3;
        	    }else{
            	    $userBusinessRequested->request_status = 2;
	            }
    	        $userBusinessRequested->save();

        	 }

        }
        $response['status'] = "Success";
        $response['message'] = "Stripe web hook generated successfully.";
        $this->renderJSON($response);

    }

   public function actionRequestStripeWebLink()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/requestStripeWebLink POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' )) {

            $userId = $_POST['user_id'];
            $id = $userId;
            if($validate['user_role'] == 'VenueOwner'){
                $id =  $_POST['venue_id'];
            }
            $checkStatusSql = "SELECT * FROM `user_business_requests` WHERE `user_business_requests`.`id` = $userId";
            $checkStatus = Application_helper::getData($checkStatusSql);

            if (empty($checkStatus)) {
                $nextURL = "/development/apiv2/pro/stripeAccountWebHook?user_id=". $userId ;
                if($validate['user_role'] == 'VenueOwner'){
                	$nextURL = $nextURL .  "&venue_id=".$id;
                }

                $routingNumber = "110000000";
                $bankAccountNumber = "000123456789";
                $webLinkObj = Stripe::generateNewStripeBusinessAccountWebLink($id,$nextURL,$routingNumber,$bankAccountNumber);
                if(!empty($webLinkObj) && $webLinkObj->result == "success"){
                    $userBusinessRequests = new UserBusinessRequests();
                    $userBusinessRequests->user_id = $userId;
                    if($validate['user_role'] == 'VenueOwner'){
                        $userBusinessRequests->venue_id = $id;
                    }
                    if($userBusinessRequests->save()){
                        $response['status'] = "Success";
                        $response['message'] = "Stripe web link generated successfully.";
                        $response['stripe_web_link'] = $webLinkObj->url;
                    }else{
                        $response['status'] = "Failure";
                        $response['message'] = "Business request couldn't be set into db. ";
                    }
                }else{
                    $response['status'] = "Failure";
                    $response['message'] = "Web link couldn't be created. ".$webLinkObj->error;
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'User Already requested for W9';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/requestStripeWebLink RESPONSE");
        $this->renderJSON($response);
    }
    /**
     * 42nite PRO API
     * W9 Form request
     * //TODO  check if already requested
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 24/5/17
     */
   public function actionRequestW9()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/requestW9 POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' )) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
            // check for Role [promoter & venue owner]
            if ($validate['user_role'] == 'Promoter')
            { $checkStatusSql = "SELECT * FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = $userId"; }
            else
            { $checkStatusSql = "SELECT * FROM `user_business_requests` WHERE `user_business_requests`.`user_id` = $userId AND `user_business_requests`.`venue_id` = $venueId";}

            $checkStatus = Application_helper::getData($checkStatusSql);

            if (empty($checkStatus)) {
                $W9Request = new UserBusinessRequests();

                $W9Request->user_id = $userId;
                $W9Request->venue_id = $venueId;

                if ($W9Request->save()) {

                    // send mail to admin for w9 request
                  //  Email_helper::send_mail_to_admin_on_user_w9_request($userId);

                    $response['status'] = 'success';
                    $response['message'] = 'User requested W9 Successfully';

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'There is some error while requesting W9';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'User Already requested for W9';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/requestW9 RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     *
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */

   public function actionCheckW9Status()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/checkW9Status POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Admin' || $validate['user_role'] == 'Promoter')) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];

            if ($validate['user_role'] == 'Promoter')
            {
               $getW9StatusSql = "SELECT `request_status` FROM `user_business_requests`
                              WHERE `user_id` = $userId";
            }
            else
            {
               $getW9StatusSql = "SELECT `request_status` FROM `user_business_requests`
                              WHERE `venue_id` = $venueId AND `user_id` = (SELECT `venue_detail`.`created_by` FROM `venue_detail`
                                                 WHERE `venue_detail`.`id` = $venueId)";
            }

            $getW9Status = Application_helper::getData($getW9StatusSql);

            $response['status'] = 'success';

            if (!empty($getW9Status)) {
                $response['message'] = "User W9 request fetched Successfully";
                $response['data'] = $getW9Status[0];
            } else {
                $response['message'] = "User haven't requested for W9";
                $response['data']['request_status'] = '0';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/checkW9Status RESPONSE");
        $this->renderJSON($response);
    }



   public function actionCreateBouncer()
    {
        Yii::log(var_export($_POST, true), "warning", "bouncer/createBouncer POST");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'email', 'password', 'name', 'contact_number']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];

            $bouncerEmail = $_POST['email'];
            $bouncerName = $_POST['name'];
            $bouncerContact = $_POST['contact_number'];
            $bouncerPassword = $_POST['password'];
            $bouncerCreatedBy = $_POST['venue_id'];

            $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $length = 6;

            $user_model = new Users();
            $user_model->name = $bouncerName;
            $user_model->email = $bouncerEmail;
            $user_model->mobile_no = $bouncerContact;
            $user_model->notification = 0;
            $user_model->status = 0;

            if ($user_model->save()) {

                // last insert id in the users table
                $bouncerId = Yii::app()->db->lastInsertID;

                // create new model
                $user_login = new UserLogin;
                $user_login->user_id = $bouncerId;
                $user_login->display_name = $bouncerName;
                $user_login->username = $bouncerEmail;
                $user_login->salt = substr(str_shuffle($string), 0, $length);
                $user_login->password = md5($user_login->salt . $bouncerPassword);
                $user_login->role = 'Bouncer';
                $user_login->created_by = $bouncerCreatedBy;
                $user_login->created_date = time();

                if ($user_login->save()) {

                    //TODO : SEND CONFIRMATION MAIL TO BOUNCER

                    $getDataSql = "SELECT `venue_detail`.`name`, generate_phone_no($bouncerContact) AS `contact_no`, get_venue_pic(`venue_detail`.`id`) AS `venue_pic`
                                   FROM `user_login`
                                   LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `user_login`.`created_by`
                                   WHERE `user_login`.`user_id` = $bouncerId ";

                    $getData = Application_helper::getData($getDataSql);

                    $response = [
                        'status' => 'success',
                        'message' => 'Bouncer created successfully',
                        'data' => [
                            'bouncer_id' => $bouncerId,
                            'bouncer_name' => $bouncerName,
                            'bouncer_venue' => $getData[0]['name'],
                            'bouncer_email' => $bouncerEmail,
                            'formatted_phone_no' => $getData[0]['contact_no'],
                            'phone_no' => $bouncerContact,
                            'bouncer_venue_id' => $bouncerCreatedBy,
                            'bouncer_venue_pic' => $getData[0]['venue_pic']
                        ]
                    ];
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Bouncer Not created internal';

                    //DELETE
                    Users::model()->deleteByPk($bouncerId);
                }

            } else {
                $response['status'] = 'failure';
                $response['message'] = 'Bouncer Not created';
                $response['data'] = $user_model->getErrors();
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), "warning", "bouncer/createBouncer RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Update Bouncer
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 25/5/17
     */
   public function actionUpdateBouncer()
    {
        Yii::log(var_export($_POST, true), "warning", "bouncer/updateBouncer POST");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'bouncer_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin')) {

            $venueId = $_POST['venue_id'];
            $bouncerId = $_POST['bouncer_id'];

            $bouncer = Users::model()->findByPk($bouncerId);
            $bouncerUser = UserLogin::model()->findByAttributes(['user_id' => $bouncerId]);

            if (!empty($bouncerUser)) {

                $checkEmail = 1;
                if (isset($_POST['email'])) {

                    $email = $_POST['email'];

                    $bouncer->email = $_POST['email'];
                    $bouncerUser->username = $_POST['email'];

                    $check_is_already_email = "SELECT 1 FROM `users` WHERE `users`.`email` = '$email' AND `id` NOT IN ($bouncerId)";
                    $check_email = Application_helper::getData($check_is_already_email);
                    if (!empty($check_email)) {
                        $checkEmail = 0;
                    }
                }

                if (isset($_POST['name'])) {
                    $bouncer->name = $_POST['name'];
                    $bouncerUser->display_name = $_POST['name'];
                }

                if (isset($_POST['contact_number'])) {
                    $bouncer->mobile_no = $_POST['contact_number'];
                }

                $bouncerUser->created_by = $venueId;

                if ($checkEmail) {

                    $bouncer->update();
                    $bouncerUser->update();

                    $getBouncerData = "SELECT `users`.`id` AS `bouncer_id`, `users`.`name` AS `bouncer_name`, `venue_detail`.`name` AS `bouncer_venue`,
                                        `users`.`email` AS `bouncer_email`, generate_phone_no(`users`.`mobile_no`) AS `formatted_phone_no`, `user_login`.`created_by` As `bouncer_venue_id`,
                                        `users`.`mobile_no` AS `phone_no`, get_venue_pic(`venue_detail`.`id`) AS `bouncer_venue_pic`
	                                    FROM `users` LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
	                                    LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `user_login`.`created_by`
	                                    WHERE `users`.`id` = $bouncerId";

                    $bouncerData = Application_helper::getData($getBouncerData);

                    $response['status'] = 'success';
                    $response['message'] = 'Bouncer updated successfully';
                    $response['data'] = $bouncerData[0];

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Bouncer Not Updated';
                    $response['data']['email'][] = 'This Email is already in use';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'There is some error while updating bouncer';
            }

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), "warning", "bouncer/updateBouncer RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Remove Bouncer
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 23/5/17
     */
   public function actionRemoveBouncer()
    {
        Yii::log(var_export($_POST, true), "warning", "bouncer/removeBouncer POST");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'bouncer_id']);

        if ($validation['status'] && ($validation['user_role'] == 'VenueOwner' || $validation['user_role'] == 'Admin')) {

            $bouncer_id = $_POST['bouncer_id'];

            Users::model()->deleteByPk($bouncer_id);

            $response['status'] = 'success';
            $response['message'] = 'Bouncer removed successfully';

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), "warning", "bouncer/removeBouncer RESPONSE");
        $this->renderJSON($response);
    }
    /**
     * 42nite PRO API
     * Get Following users
     *
     * Last Update Details
     * For : Initialised
     * By : Bhavik Rathod
     * On : 16/6/17
     */
   public function actionGetFollowersList()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getFollowersList POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status']) {

            $userId = $_POST['user_id'];

            //Default limit 20
            $limit = "LIMIT 20";
            if (!empty($_POST['limit'])) {
                $limitIs = $_POST['limit'];
                $limit = "LIMIT $limitIs";
            }

            $offset = "";
            if (!empty($_POST['offset'])) {
                $offsetIs = $_POST['offset'];
                $offset = "OFFSET $offsetIs ";
            }

            if (!empty($userId) && !empty($_POST['venue_id'])) {

                $venueId = $_POST['venue_id'];

                  // $getFollowersSql = "SELECT `user_id`, `action_venue_id`, `created_at` FROM `venue_followers` WHERE `venue_id` = $venueId";// $limit $offset";
                $getFollowersSql = "SELECT `other_users_id` as user_id, NULL as `action_venue_id`, `created_at` FROM `users_followers` WHERE `venue_id` = $venueId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "Venue followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'App' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        } else if (!empty($follower['action_venue_id'])) {

                            $action_venue_id = $follower['action_venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $action_venue_id $where";
                        }

                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }

                    $response['data'] = $data;
                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }
                   // $pagination_data['offset'] = "" . count($data) + $offset . "";
                    $response['pagination'] = $pagination_data;

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }

            if (empty($_POST['venue_id']) && !empty($userId)) {

                // $getFollowersSql = "SELECT `users_id` as user_id, `venue_id`, `created_at` FROM `users_followers` WHERE `other_users_id` = $userId AND `status` = 1 $limit $offset";
                $getFollowersSql = "SELECT `other_users_id` as user_id, `venue_id`, `created_at` FROM `users_followers` WHERE `action_user_id` = $userId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "user followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'app' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        }

                        if (!empty($follower['venue_id'])) {

                            $venue_id = $follower['venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $venue_id $where";

                        }
                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }
                    $response['data'] = $data;
                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }
                   // $pagination_data['offset'] = "" . count($data) + $offset . "";
                    $response['pagination'] = $pagination_data;
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getFollowersList RESPONSE");
        $this->renderJSON($response);
    }




   public function actionGetVenueFollowersList()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getFollowersList POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status']) {

            $userId = $_POST['user_id'];

            //Default limit 20
            $limit = "LIMIT 20";
            if (!empty($_POST['limit'])) {
                $limitIs = $_POST['limit'];
                $limit = "LIMIT $limitIs";
            }

            $offset = "";
            if (!empty($_POST['offset'])) {
                $offsetIs = $_POST['offset'];
                $offset = "OFFSET $offsetIs ";
            }

            if (!empty($userId) && !empty($_POST['venue_id'])) {

                $venueId = $_POST['venue_id'];

                   $getFollowersSql = "SELECT `user_id`, `action_venue_id`, `created_at` FROM `venue_followers` WHERE `venue_id` = $venueId";// $limit $offset";
                //$getFollowersSql = "SELECT `other_users_id` as user_id, NULL as `action_venue_id`, `created_at` FROM `users_followers` WHERE `venue_id` = $venueId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "Venue followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'App' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        } else if (!empty($follower['action_venue_id'])) {

                            $action_venue_id = $follower['action_venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $action_venue_id $where";
                        }

                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }

                    $response['data'] = $data;
                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }

                   // $pagination_data['offset'] = "" . count($data) + $offset . "";
                    $response['pagination'] = $pagination_data;

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }

            if (empty($_POST['venue_id']) && !empty($userId)) {

                // $getFollowersSql = "SELECT `users_id`, `venue_id`, `created_at` FROM `users_followers` WHERE `other_users_id` = $userId AND `status` = 1 $limit $offset";
                $getFollowersSql = "SELECT `other_users_id` as user_id, `venue_id`, `created_at` FROM `users_followers` WHERE `action_user_id` = $userId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "user followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'app' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        } else if (!empty($follower['venue_id'])) {

                            $venue_id = $follower['venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $venue_id $where";

                        }
                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }
                    $response['data'] = $data;
                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }

                   // $pagination_data['offset'] = "" . count($data) + $offset . "";
                    $response['pagination'] = $pagination_data;
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getFollowersList RESPONSE");
        $this->renderJSON($response);
    }



   public function actionGetMyFollowers()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getFollowersList POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status']) {

            $userId = $_POST['user_id'];

            //Default limit 20
            $limit = "LIMIT 20";
            if (!empty($_POST['limit'])) {
                $limitIs = $_POST['limit'];
                $limit = "LIMIT $limitIs";
            }

            $offset = "";
            if (!empty($_POST['offset'])) {
                $offsetIs = $_POST['offset'];
                $offset = "OFFSET $offsetIs ";
            }

            Yii::log(var_export($offset, true), "warning", "pro/getMyFollowers Offset");
            if (!empty($userId) && !empty($_POST['venue_id'])) {

                $venueId = $_POST['venue_id'];

                // $getFollowersSql = "SELECT `user_id`, `action_venue_id`, `created_at` FROM `venue_followers` WHERE `venue_id` = $venueId $limit $offset";
                $getFollowersSql = "SELECT `users_id` as user_id, NULL as `action_venue_id`, `created_at` FROM `users_followers` WHERE `venue_id` = $venueId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "Venue followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'App' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        } else if (!empty($follower['action_venue_id'])) {

                            $action_venue_id = $follower['action_venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $action_venue_id $where";
                        }

                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }

                    $response['data'] = $data;

                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }

                    $response['pagination'] = $pagination_data;

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }

            if (empty($_POST['venue_id']) && !empty($userId)) {

                // $getFollowersSql = "SELECT `users_id`, `venue_id`, `created_at` FROM `users_followers` WHERE `other_users_id` = $userId AND `status` = 1 $limit $offset";
                $getFollowersSql = "SELECT `users_id` as user_id, `venue_id`, `created_at` FROM `users_followers` WHERE `other_users_id` = $userId AND `status` = 1 $limit $offset";
                $followersData = Application_helper::getData($getFollowersSql);

                if (!empty($followersData)) {
                    $response['status'] = 'success';
                    $response['message'] = "user followers fetched Successfully";

                    $data = array();
                    $count = 0;
                    foreach ($followersData as $follower) {

                        $created_at = Application_helper::timeAgo($follower['created_at']);

                        $searchBy = null;
                        $where = '';
                        if (isset($_POST['keyword'])) {
                            if (!empty($_POST['keyword'])) {
                                $searchBy = $_POST['keyword'];
                                $where = " HAVING `user_name` LIKE '%$searchBy%' ";
                            }
                        }

                        if (!empty($follower['user_id'])) {

                            $follow_user_id = $follower['user_id'];
                            $FollowerSql = "SELECT `users`.`id` AS `user_id`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic`,
                                       `users`.`name` AS `user_name`, `users`.`city` AS `user_location`,
                                        CASE `user_login`.`role`
                                        WHEN 'App' THEN 'app' WHEN 'Promoter' THEN 'promoter' ELSE 'App' END AS `user_role`,
                                        '$created_at' AS `time`,
                                        IFNULL(TIMESTAMPDIFF(YEAR,`users`.`dob`,CURDATE()),'') AS `age`,  `users`.`sex` AS `gender`
                                FROM `users`
                                LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id`
                                WHERE `users`.`id` = $follow_user_id $where";

                        } else if (!empty($follower['venue_id'])) {

                            $venue_id = $follower['venue_id'];
                            $FollowerSql = "SELECT `venue_detail`.`id` AS `user_id`, IFNULL(get_venue_pic(`venue_detail`.`id`), '') AS `user_pic`,
                                       `venue_detail`.`name` AS `user_name`, concat(`venue_detail`.`city`, ', ',`venue_detail`.`state`) AS `user_location`,
                                        CASE `venue_detail`.`venue_type`
                                        WHEN 1 THEN 'venue' WHEN 2 THEN 'venue'
                                        ELSE 'venue'
                                        END AS `user_role`, '$created_at' AS `time`,
                                        '' AS `age`,  '' AS `gender`
                                FROM `venue_detail`
                                WHERE `venue_detail`.`id` = $venue_id $where";

                        }
                        $followers = Application_helper::getData($FollowerSql);
                        if (!empty($followers)) {
                            $data[] = $followers[0];
                        }
                        $count++;
                    }
                    $response['data'] = $data;
                    Yii::log(var_export($data, true), "warning", "pro/getFollowersList Data");
                    if (!empty($offset)) {
                        $pagination_data['offset'] = "" . count($data) + $offset . ""; }
                    else
                    { $pagination_data['offset'] = "" . count($data) . ""; }

                    $response['pagination'] = $pagination_data;
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "no followers available";
                }
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/getFollowersList RESPONSE");
        $this->renderJSON($response);
    }





   public function actionGetSocialReportsData()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getSocialReportsData POST");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'chart_type', 'time_range']);

        if ($validation['status']) {

            $userId = $_POST['user_id'];
            $venueId = $_POST['venue_id'];
            $chartType = $_POST['chart_type'];
            $timeRange = $_POST['time_range'];

            if ($chartType == 1) {
                $yData = ['0.0', '3.0', '3.4', '3.8', '4.0', '3.0', '4.3', '4.5', '4.0', '4.2'];
            } else {
                $yData = ['0', '2', '5', '10', '12', '22', '22', '24', '26', '29'];
            }

            shuffle($yData);


            if ($timeRange == 'hourly') {
                $data['xAxisData'] = ['08:00 PM', '09:00 PM', '10:00 PM', '11:00 PM', '12:00 AM', '01:00 AM', '02:00 AM', '03:00 AM', '04:00 AM', '05:00 AM'];
            } else if ($timeRange == 'daily') {
                $data['xAxisData'] = ['12/31', '01/01', '01/02', '01/03', '01/04', '01/05', '01/06', '01/07', '01/08', '01/09'];
            } else if ($timeRange == 'weekly') {
                $data['xAxisData'] = ['03/05 - 03/11', '03/12 - 03/18', '03/19 - 03/25', '03/26 - 04/01', '04/02 - 04/08', '04/09 - 04/15', '04/16 - 04/22', '04/23 - 04/29', '04/30 - 05/06', '05/07 - 05/13'];
            } else if ($timeRange == 'monthly') {
                $data['xAxisData'] = ['Jan \'17', 'Feb \'17', 'Mar \'17', 'Apr \'17', 'May \'17', 'Jun \'17', 'Jul \'17', 'Aug \'17', 'Sep \'17', 'Oct \'17'];
            } else if ($timeRange == 'yearly') {
                $data['xAxisData'] = ['2007', '2008', '2009', '2010', '2011', '2012', '2013', '2014', '2015', '2016'];
            }

            $data['yAxisData'] = $yData;

            $response['status'] = 'success';
            $response['message'] = 'Data fetched Successfully';
            $response['data'] = $data;
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
            $response['data'] = $_POST;
        }

        Yii::log(var_export($response, true), "warning", "pro/getSocialReportsData RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite API
     * venue Rate Promoter
     *
     * Last Update Details
     * For : Initialised
     * By : Jenish
     * On : 24/08/17
     */
   public function actionVenue_Rate_Promoter()
    {
        Yii::log(var_export($_POST, true), "warning", "Venue Rate Promoter POST");
        $validation = Application_helper::check_validation($_POST, ['promoter_id', 'venue_id', 'rating']);

        if ($validation['status']) {

            $venue_id = $_POST['venue_id'];
            $promoter_id = $_POST['promoter_id'];
            $rating = $_POST['rating'];

            // check is already venue rated promoter
            $check_criteria = new CDbCriteria;
            $check_criteria->select = 'id';
            $check_criteria->condition = "venue_id = $venue_id AND promoter_id = $promoter_id";
            $rating_data = PromoterRatings::model()->findAll($check_criteria);

            if (empty($rating_data)) {
                //INSERT
                $model = new PromoterRatings;
                $model->venue_id = $venue_id;
                $model->promoter_id = $promoter_id;
                $model->rating = $rating;

                if ($model->save()) {

                    $promoter_rating_data = Application_helper::get_venue_rating($promoter_id, null);
                    $promoter_rating['rating'] = (string)round($promoter_rating_data[0]['rating'], 1, PHP_ROUND_HALF_UP);

                    $response['status'] = "Success";
                    $response['message'] = "Promoter Successfully Rated";
                    $response['data'] = $promoter_rating;
                } else {
                    $response['status'] = "Failure";
                    $response['message'] = "Venue failed to Rate Promoter";
                }
            } else {
                //UPDATE
                $update_rating_sql = "UPDATE `promoter_ratings` SET `rating` = $rating WHERE `venue_id` = $venue_id AND `promoter_id` = $promoter_id";
                $update_rating = Application_helper::updateData($update_rating_sql);

                $promoter_rating_data = Application_helper::get_venue_rating($promoter_id, null);
                $promoter_rating['rating'] = (string)round($promoter_rating_data[0]['rating'], 1, PHP_ROUND_HALF_UP);

                $response['status'] = "Success";
                $response['message'] = "Promoter Rate Successfully Updated";
                $response['data'] = $update_rating;
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
        }
        Yii::log(var_export($response, true), "warning", "Venue Rate Promoter Response");
        $this->renderJSON($response);
    }


    /**
     * 42nite API
     * venue Rate Specific venue
     *
     * Last Update Details
     * For : Initialised
     * By : jenish
     * On : 24/8/17
     */
   public function actionRate_Venue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/rate_venue IN");

        $validation = Application_helper::check_validation($_POST, ['venue_id', 'rating']);

        if ($validation['status']) {

            $venue_id = $_POST['venue_id'];
            $rating = $_POST['rating'];

            if (!empty($_POST['action_venue_id'])) {
                $action_venue_id = $_POST['action_venue_id'];

                $check_criteria = new CDbCriteria;
                $check_criteria->select = 'id';
                $check_criteria->condition = "action_venue_id = $action_venue_id AND venue_id = $venue_id";
                $rating_data = VenueRatings::model()->findAll($check_criteria);
            }

            if (!empty($_POST['user_id'])) {
                $user_id = $_POST['user_id'];

                $check_criteria = new CDbCriteria;
                $check_criteria->select = 'id';
                $check_criteria->condition = "user_id = $user_id AND venue_id = $venue_id";
                $rating_data = VenueRatings::model()->findAll($check_criteria);
            }

            if (empty($rating_data)) {
                //INSERT
                $model = new VenueRatings();

                if (!empty($_POST['user_id'])) {
                    $model->user_id = $_POST['user_id'];
                }

                if (!empty($_POST['action_venue_id'])) {
                    $model->action_venue_id = $_POST['action_venue_id'];
                }

                $model->venue_id = $venue_id;
                $model->rating = $rating;

                if ($model->save()) {
                    $venue_rating_data = Application_helper::get_venue_rating($venue_id, TRUE);
                    $venue_rating['rating'] = $venue_rating_data[0]['rating'];

                    $response['status'] = "Success";
                    $response['message'] = "Venue Successfully Rated";
                    $response['data'] = $venue_rating;
                } else {
                    $response['status'] = "Failure";
                    $response['message'] = "Venue failed to Rate";
                    $response['error'] = $model->getErrors();
                }
            } else {
                //UPDATE
                if (!empty($_POST['action_venue_id'])) {
                    $action_venue_id = $_POST['action_venue_id'];
                    $where = " AND `action_venue_id` = $action_venue_id ";
                }

                if (!empty($_POST['user_id'])) {
                    $user_id = $_POST['user_id'];
                    $where = " AND `user_id` = $user_id ";
                }

                $update_rating_sql = "UPDATE `venue_ratings` SET `rating` = $rating WHERE `venue_id` = $venue_id $where";
                $update_rating = Application_helper::updateData($update_rating_sql);

                $venue_rating_data = Application_helper::get_venue_rating($venue_id, TRUE);
                $venue_rating['rating'] = $venue_rating_data[0]['rating'];

                $response['status'] = "Success";
                $response['message'] = "Venue Rate Successfully Updated";
                $response['data'] = $venue_rating;
            }
        } else {
            $response['status'] = "Failure";
            $response['message'] = $validation['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/rate_venue OUT");
        $this->renderJSON($response);
    }

    /**
     * 42nite API
     * Follow / UnFollow Specific venue By Promoter
     *
     * Last Update Details
     * For : Initialised
     * By : jenish
     * On : 24/08/17
     */
   public function actionFollowVenue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/followVenue IN");

        $validation = Application_helper::check_validation($_POST, ['venue_id']);

        if ($validation['status']) {

            //Initialise user_id, venue_id
            $user_id = $_POST['user_id'];

            //check is user role is promoter or not
            $checkUserSql = "SELECT `role` FROM `user_login` WHERE `user_id` = $user_id";
            $checkUser = Application_helper::getData($checkUserSql);

            if ($checkUser[0]['role'] == "Promoter") {

                $venue_id = $_POST['venue_id'];

                //Check if user has already added venue to fav list
                $criteria = new CDbCriteria;
                $criteria->select = 'id';
                $criteria->condition = "user_id = $user_id AND venue_id = $venue_id";
                $check_data = VenueFollowers::model()->findAll($criteria);

                //If already not added to venueFollowers
                if (empty($check_data)) {
                    $model = new VenueFollowers();
                    $model->user_id = $user_id;
                    $model->venue_id = $venue_id;

                    //Successfully saved into table
                    if ($model->save(false)) {
                        //get followers_count
                        $followers_count = Application_helper::get_followers_count($venue_id, TRUE);

                        $response['status'] = "Success";
                        $response['message'] = "Venue has been Successfully added to your favorite list";
                        $response['data'] = $followers_count[0];
                    } else {
                        $response['status'] = "Failure";
                        $response['message'] = "There is some error while adding Venue to favorites";
                        $response['error'] = $model->getErrors();
                    }
                } else {
                    $criteria = new CDbCriteria;
                    $criteria->condition = 'user_id = ' . $user_id . ' AND venue_id = ' . $venue_id;

                    //Successfully removed
                    if (VenueFollowers::model()->deleteAll($criteria)) {
                        //get followers_count
                        $followers_count = Application_helper::get_followers_count($venue_id, TRUE);

                        $response['status'] = "Success";
                        $response['message'] = "Venue has been Successfully removed from your favorite list";
                        $response['data'] = $followers_count[0];
                    } else {
                        $response['status'] = "Failure";
                        $response['message'] = "There is some error while removing venue from favorites";
                    }
                }
            } else {
                $response['status'] = "Failure";
                $response['message'] = 'User is not a promoter';
            }
        } else {
            $response['status'] = "Failure";
            $response['message'] = $validation['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/followVenue OUT");
        $this->renderJSON($response);
    }


    /**
     * 42nite API
     * Follow user By Promoter
     *
     * Last Update Details
     * For : Initialised
     * By : jenish
     * On : 24/08/2017
     */
   public function actionFollow_User()
    {
        header('Connection : nokeepalive');
        Yii::log(var_export($_POST, true), "warning", "Follow User");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'promoter_id']);

        if ($validation['status']) {

            $promoter_id = $_POST['promoter_id'];
            $user_id = $_POST['user_id'];

            //check is user role is promoter or not
            $checkUserSql = "SELECT `role` FROM `user_login` WHERE `user_id` = $promoter_id";
            $checkUser = Application_helper::getData($checkUserSql);

            if ($checkUser[0]['role'] == "Promoter") {

                $check_already_followed_sql = "SELECT `users_followers`.`status` FROM `users_followers` WHERE `users_followers`.`users_id` = $promoter_id AND `users_followers`.`other_users_id` = $user_id";
                $check_already_followed = Application_helper::getData($check_already_followed_sql);

                if (empty($check_already_followed)) {

                    $check_is_profile_private_sql = "SELECT `users`.`is_private_profile` FROM `users` WHERE `users`.`id` = $user_id AND `users`.`is_private_profile` = 0";
                    $check_is_profile_private = Application_helper::getData($check_is_profile_private_sql);

                    if (!empty($check_is_profile_private)) {

                        $model = new UsersFollowers();
                        $model->users_id = $promoter_id;
                        $model->other_users_id = $user_id;
                        $model->status = 1;
                        $model->action_user_id = $promoter_id;
                        $model->unix_timestamp = time();

                        if ($model->save(false)) {

                            $count_follow_sql = "SELECT COUNT(*) AS `followers_count` FROM `users_followers` WHERE `users_followers`.`other_users_id` = $user_id AND `users_followers`.`status` = 1";
                            $count_follow = Application_helper::getData($count_follow_sql);

                            $response = [
                                'status' => 'success',
                                'message' => 'user follow Successfully.',
                                'data' => [
                                    'followers_count' => $count_follow[0]['followers_count'],
                                ],
                            ];
                        } else {
                            //error msg when model is not save in UsersFollowers
                            $response = [
                                'status' => 'failure',
                                'message' => 'user fails to follow the user.'
                            ];
                        }
                    } else {

                        $model = new UsersFollowers();
                        $model->users_id = $promoter_id;
                        $model->other_users_id = $user_id;
                        $model->status = 2;
                        $model->action_user_id = $promoter_id;
                        $model->unix_timestamp = time();

                        if ($model->save(false)) {

                            $count_follow_sql = "SELECT COUNT(*) AS `followers_count` FROM `users_followers` WHERE `users_followers`.`other_users_id` = $user_id AND `users_followers`.`status` = 1";
                            $count_follow = Application_helper::getData($count_follow_sql);

                            $response = [
                                'status' => 'success',
                                'message' => 'user follow Successfully.',
                                'data' => [
                                    'followers_count' => $count_follow[0]['followers_count'],
                                ],
                            ];
                        } else {
                            //error msg when model is not save in UsersFollowers
                            $response = [
                                'status' => 'failure',
                                'message' => 'Promoter fails to follow the user.'
                            ];
                        }
                    }
                } else {

                    if ($check_already_followed[0]['status'] == 3) {

                        $response = [
                            'status' => 'failure',
                            'message' => 'Promoter is Block by user.',
                        ];
                    } else if ($check_already_followed[0]['status'] == 2) {

                        $response = [
                            'status' => 'failure',
                            'message' => 'Promoter already request for follow.',
                        ];
                    } else if ($check_already_followed[0]['status'] == 1) {

                        $response = [
                            'status' => 'failure',
                            'message' => 'Promote is already request for follow.',
                        ];
                    }
                }
            } else {
                $response['status'] = "Failure";
                $response['message'] = 'User is not a promoter';
            }
        } else {
            //error msg when filed is not set properly
            $response = [
                'status' => 'failure',
                'message' => $validation['message']
            ];
        }
        header('Content-type: application/json');
        echo json_encode($response);
        Yii::log(var_export($response, true), "warning", "Follow User");
        exit;
    }


   public function actionGet_newsfeed_data()
    {
    	Yii::log(var_export($_POST, true), "warning", "pro/get_newsfeed_data POST");

        $validation = Application_helper::check_validation($_POST, ['user_id']);

        if ($validation['status']) {
        	$user_role = $validation['user_role'];
        	$venue_id = "All";
        	if(isset($_POST['venue_id'])){
        		$venue_id = $_POST['venue_id'];
        	}

        	$venue_id = $_POST['venue_id'];

        if ($venue_id == "42niteTeam" || $venue_id == "All") {

            $where = "";
            $total_post_join = "";
            $total_post = "";
            $condition = " WHERE ";
            if ($user_role == 'VenueOwner') {
                $total_post_join = " LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `posts`.`venue_id`";
                $total_post = " WHERE `venue_detail`.`created_by` = $user_id";
                //  OR `posts`.`users_id` = $user_id ";
                $condition = " AND ";
                // $where = " WHERE `venue_detail`.`created_by` = $user_id";
            }

            if ($user_role == "Promoter") {
                // $total_post_join = " LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `posts`.`venue_id`";
                $total_post_join = "";
                // $total_post = " WHERE `venue_detail`.`id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = '$user_id')";
                $total_post = " WHERE `posts`.`users_id` =  $user_id";

                // OR `posts`.`users_id` = $user_id";
                $condition = " AND ";
                // $where = " WHERE `venue_detail`.`id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = '$user_id')";
            }

            $post_count_sql = "SELECT IFNULL(COUNT(`posts`.`id`), 0) AS `post_count` FROM `posts` $total_post_join $total_post";
            $post_counts = Application_helper::getData($post_count_sql);

            $post_like_count_sql = "SELECT IFNULL(COUNT(`posts_likes`.`id`), 0) AS `posts_likes` FROM `posts_likes`
                                LEFT JOIN `posts` ON `posts`.`id` = `posts_likes`.`posts_id` $total_post_join $total_post $condition `posts_likes`.`is_liked` = 1";
            $posts_like_count = Application_helper::getData($post_like_count_sql);

            $post_reach_count_sql = "SELECT IFNULL(COUNT(`posts_views`.`id`), 0) AS `posts_views` FROM `posts_views`
                                 LEFT JOIN `posts` ON `posts`.`id` = `posts_views`.`posts_id` $total_post_join $total_post";
            $posts_reach_count = Application_helper::getData($post_reach_count_sql);

            $post_share_count_sql = "SELECT IFNULL(COUNT(`posts_shares`.`id`), 0) AS `posts_shares` FROM `posts_shares`
                                 LEFT JOIN `posts` ON `posts`.`id` = `posts_shares`.`posts_id` $total_post_join $total_post";
            $posts_share_count = Application_helper::getData($post_share_count_sql);

            $post_comment_count_sql = "SELECT IFNULL(COUNT(`posts_comments`.`id`), 0) AS `posts_comments` FROM `posts_comments`
                                  LEFT JOIN `posts` ON `posts`.`id` = `posts_comments`.`posts_id` $total_post_join $total_post";
            $posts_comment_count = Application_helper::getData($post_comment_count_sql);

	        $post_counts = $post_counts[0]['post_count'];
            $posts_like_count = $posts_like_count[0]['posts_likes'];
            $posts_reach_count = $posts_reach_count[0]['posts_views'];
            $posts_share_count = $posts_share_count[0]['posts_shares'];
            $posts_comment_count = $posts_comment_count[0]['posts_comments'];

        } else {

            if ($user_role == 'VenueOwner') {
                $post_counts = Application_helper::_get_post_count($venue_id);
                $posts_like_count = Application_helper::_get_post_like_count($venue_id);
                $posts_reach_count = Application_helper::_get_post_reach_count($venue_id);
                $posts_share_count = Application_helper::_get_post_share_count($venue_id);
                $posts_comment_count = Application_helper::_get_post_comment_count($venue_id);
            } if ($user_role == "Promoter") {


                    // $total_post_join = " LEFT JOIN `venue_detail` ON `venue_detail`.`id` = `posts`.`venue_id`";
                    $total_post_join = "";
                    // $total_post = " WHERE `venue_detail`.`id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = '$user_id')";
                    $total_post = " WHERE `posts`.`users_id` =  $user_id";

                    // OR `posts`.`users_id` = $user_id";
                    $condition = " AND ";
                    // $where = " WHERE `venue_detail`.`id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = '$user_id')";

                $post_count_sql = "SELECT IFNULL(COUNT(`posts`.`id`), 0) AS `post_count` FROM `posts` $total_post_join $total_post";
                $post_counts = Application_helper::getData($post_count_sql);

                $post_like_count_sql = "SELECT IFNULL(COUNT(`posts_likes`.`id`), 0) AS `posts_likes` FROM `posts_likes`
                                    LEFT JOIN `posts` ON `posts`.`id` = `posts_likes`.`posts_id` $total_post_join $total_post $condition `posts_likes`.`is_liked` = 1";
                $posts_like_count = Application_helper::getData($post_like_count_sql);

                $post_reach_count_sql = "SELECT IFNULL(COUNT(`posts_views`.`id`), 0) AS `posts_views` FROM `posts_views`
                                     LEFT JOIN `posts` ON `posts`.`id` = `posts_views`.`posts_id` $total_post_join $total_post";
                $posts_reach_count = Application_helper::getData($post_reach_count_sql);

                $post_share_count_sql = "SELECT IFNULL(COUNT(`posts_shares`.`id`), 0) AS `posts_shares` FROM `posts_shares`
                                     LEFT JOIN `posts` ON `posts`.`id` = `posts_shares`.`posts_id` $total_post_join $total_post";
                $posts_share_count = Application_helper::getData($post_share_count_sql);

                $post_comment_count_sql = "SELECT IFNULL(COUNT(`posts_comments`.`id`), 0) AS `posts_comments` FROM `posts_comments`
                                      LEFT JOIN `posts` ON `posts`.`id` = `posts_comments`.`posts_id` $total_post_join $total_post";
                $posts_comment_count = Application_helper::getData($post_comment_count_sql);

                $post_counts = $post_counts[0]['post_count'];
                $posts_like_count = $posts_like_count[0]['posts_likes'];
                $posts_reach_count = $posts_reach_count[0]['posts_views'];
                $posts_share_count = $posts_share_count[0]['posts_shares'];
                $posts_comment_count = $posts_comment_count[0]['posts_comments'];
            }

        }

        $data["post_count"] = $post_counts;
        $data['post_like'] = $posts_like_count;
        $data['post_reach'] = $posts_reach_count;
        $data['post_share'] = $posts_share_count;
        $data['post_comment'] = $posts_comment_count;

    	$response['status'] = 'success';
        $response['message'] = 'post data fetched Successfully.';
        $response['data'] = $data;
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
            $response['data'] = $_POST;
        }

        Yii::log(var_export($response, true), "warning", "pro/get_newsfeed_data RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * get all the counter of venues
     *
     * Last Update Details
     * For : Initialised
     * By : Jenish
     * On : 24/08/17
     */
   public function actionVenue_counter()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/venue_counter POST");

        $validation = Application_helper::check_validation($_POST, ['user_id']);

        if ($validation['status']) {

            $user_id = $_POST['user_id'];
            $user_role = $validation['user_role'];
            $venue_id = "";
            $venueRating = "";
            $venueFollower = "0";
            $venueOutreach = "";

            $where_event_count = "";
            $post_where = "";
            $ticket_data_where = "";
            if ($user_role == "Promoter") {
                $where_event_count = " WHERE `event_meta`.`created_by` = $user_id";

                $post_where = " WHERE `posts`.`users_id` = $user_id";
                $ticket_data_where = "LEFT JOIN `event_meta` ON `order`.`event_meta_id` = `event_meta`.`id`
                                      WHERE `event_meta`.`created_by` = $user_id";
            } else if ($user_role == "VenueOwner") {
                $where_event_count = " LEFT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id` WHERE `event_detail`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $user_id) ";

                $post_where = " WHERE `posts`.`users_id` = $user_id OR `posts`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $user_id)";
                $ticket_data_where = " LEFT JOIN `event_meta` ON `order`.`event_meta_id` = `event_meta`.`id` LEFT JOIN `event_detail` ON `event_meta`.`event_id` = `event_detail`.`id` WHERE `event_detail`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $user_id)";
            }

            // get venue followers
            $user_follower_sql = "SELECT IFNULL(COUNT(*), 0) AS `followers` FROM `venue_followers` WHERE `venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $user_id)";
            $user_follower = Application_helper::getData($user_follower_sql);
            $venueFollower = $user_follower[0]['followers'];

            if ($user_role == "Promoter") {

                $user_follower_sql = "SELECT IFNULL(COUNT(*), 0) AS `followers` FROM `users_followers` WHERE `other_users_id` = $user_id AND `status` = 1";
                $user_follower = Application_helper::getData($user_follower_sql);

                $venue_follower_sql = "SELECT IFNULL(COUNT(*), 0) AS `followers` FROM `venue_followers` WHERE `venue_id` IN (SELECT `venue_id` FROM `affiliation_requests` WHERE `promoter_id` = $user_id AND `request_status` = 2)";
                $venue_follower = Application_helper::getData($venue_follower_sql);

                $promoter_rating_sql = "SELECT `users`.`name`, get_promoter_rating($user_id) AS `rating` FROM `users` WHERE `users`.`id` = $user_id";
                $promoter_rating = Application_helper::getData($promoter_rating_sql);

                  $venueFollower = $user_follower[0]['followers'];
                //$venueFollower = $user_follower[0]['followers'] + $venue_follower[0]['followers'];
                  $venueRating = $promoter_rating[0]['rating'];
            }


            if (!empty($_POST['venue_id'])) {
                $venue_id = $_POST['venue_id'];

                // get venue rating
                $get_venues_rating_counter_sql = "SELECT IFNULL(ROUND(AVG(`venue_ratings`.`rating`),1), 0) AS `venue_ratings`
                                                  FROM `venue_ratings` WHERE `venue_ratings`.`venue_id` = $venue_id ";
                $venues_rating_counter = Application_helper::getData($get_venues_rating_counter_sql);
                $venueRating = $venues_rating_counter[0]['venue_ratings'];

                // get venue followers
                $get_venues_followers_counter_sql = "SELECT IFNULL(COUNT(*), 0) AS `followers`
                                                     FROM `venue_followers` WHERE `venue_followers`.`venue_id` = $venue_id";
                $venues_followers_counter = Application_helper::getData($get_venues_followers_counter_sql);
                $venueFollower = $venues_followers_counter[0]['followers'];

                // get venue outreach
                $get_venues_outreach_counter_sql = "SELECT COUNT(*) AS `venue_outreach`
                                                  FROM `venue_outreach` WHERE `venue_outreach`.`venue_id` = $venue_id ";
                $get_venues_outreach_counter = Application_helper::getData($get_venues_outreach_counter_sql);
                $venueOutreach = $get_venues_outreach_counter[0]['venue_outreach'];

                $where_event_count = " LEFT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id` WHERE `event_detail`.`venue_id` = $venue_id";
//                $post_where = " WHERE `posts`.`users_id` = $user_id OR `posts`.`venue_id` = $venue_id";
                $post_where = " WHERE `posts`.`venue_id` = $venue_id";
                $ticket_data_where = " LEFT JOIN `event_meta` ON `order`.`event_meta_id` = `event_meta`.`id`
                                      LEFT JOIN `event_detail` ON `event_meta`.`event_id` = `event_detail`.`id`
                                      WHERE `event_detail`.`venue_id` = $venue_id";
            }

            if ($user_role == "Promoter") {

                //total ticket and amount
                $ticket_data_sql = "SELECT IFNULL(TRUNCATE(SUM(`order`.`amount`)*0.9, 1),0) AS `amount`
                                       FROM `transaction`
                                       LEFT JOIN `order` ON `transaction`.`order_id` = `order`.`id`
                                       LEFT JOIN `event_meta` ON `event_meta`.`id` = `order`.`event_meta_id`
                                       WHERE `event_meta`.`created_by` = $user_id";
                $total_amount_data = Application_helper::getData($ticket_data_sql);
            }

            //total ticket and amount
            $ticket_data_sql = "SELECT IFNULL(TRUNCATE(SUM(`order`.`amount`)*0.9, 1),0) AS `amount`, IFNULL(SUM(`order`.`quantity`),0) AS `ticket`
                              FROM `transaction` LEFT JOIN `order` ON `transaction`.`order_id` = `order`.`id` $ticket_data_where";
            $total_ticket_data = Application_helper::getData($ticket_data_sql);

            //total event
            $total_event_sql = "SELECT IFNULL(COUNT(`event_meta`.`id`),0) AS `total_event` FROM `event_meta` $where_event_count";
            $total_event = Application_helper::getData($total_event_sql);

            // total posts
            $total_post_sql = "SELECT COUNT(`posts`.`id`) AS `post_count`, SUM(`posts`.`view_count`) AS `post_view` FROM `posts` $post_where";
            $total_post = Application_helper::getData($total_post_sql);

            $response['status'] = 'success';
            $response['message'] = 'venues data fetch Successfully';

            Yii::log(var_export($venueOutreach, true), "warning", "pro/venueOut Reach");
            Yii::log(var_export($total_post[0]['post_view'], true), "warning", "pro/Post View");

            if ($venueOutReach) {
            $total_out_reach = $venueOutreach + $total_post[0]['post_view'];
            }
            else {
            $total_out_reach = $total_post[0]['post_view'];
            }

            $venue_total_sell = Application_helper::getFixedNumner($total_ticket_data[0]['amount']);

            if ($user_role == "Promoter") {
                $venue_total_sell = Application_helper::getFixedNumner($total_amount_data[0]['amount']);
            }

            $response['data'] = [
                'venue_rating' => $venueRating,
                'venue_followers' => Application_helper::getFixedNumner($venueFollower),
                'venue_ticket_sold' => Application_helper::getFixedNumner($total_ticket_data[0]['ticket']),
                'venue_event_created' => Application_helper::getFixedNumner($total_event[0]['total_event']),
                'venue_total_sell' => $venue_total_sell,
                'venue_total_posts' => Application_helper::getFixedNumner($total_post[0]['post_count']),
                'venue_total_outreach' => Application_helper::getFixedNumner($total_out_reach),
                'venue_post_likes' => Application_helper::getFixedNumner($total_out_reach),
            ];
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
            $response['data'] = $_POST;
        }

        Yii::log(var_export($response, true), "warning", "pro/venue_counter RESPONSE");
        $this->renderJSON($response);
    }


    /**
     * 42nite PRO API
     * Count Profile view of venue
     * TODO : DEPRECATED, IMPLEMENT IN venue/viewVenueProfile AND pro/getVenueDetail USING FUNCTION
     *
     * Last Update Details
     * For : Initialised
     * By : Jenish
     * On : 28/08/17
     */
   public function actionView_venue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/view_venue POST");
        $validation = Application_helper::check_validation($_POST, ['venue_id']);

        if ($validation['status']) {

            $validation2 = Application_helper::check_validation($_POST, ['user_id']);

            if ($validation2['status']) {

                $venue_id = $_POST['venue_id'];
                $user_id = $_POST['user_id'];

                $model = new VenueOutreach();
                $model->users_id = $user_id;
                $model->venue_id = $venue_id;

                $view_venue_id = '';
                $where = "";
                if (!empty($_POST['view_venue_id'])) {
                    $view_venue_id = $_POST['view_venue_id'];
                    $model->view_venue_id = $view_venue_id;
                    $where = " AND `venue_outreach`.`view_venue_id` = $view_venue_id ";
                }

                // check is venue already view or not
                $check_sql = "SELECT * FROM `venue_outreach` WHERE `venue_outreach`.`users_id` = $user_id AND `venue_outreach`.`venue_id` = $venue_id $where";
                $Check = Application_helper::getData($check_sql);

                if (empty($Check)) {
                    if ($model->save()) {
                        //Inserted view view venue id
                        $Id = Yii::app()->db->getLastInsertID();

                        $response['status'] = 'success';
                        $response['message'] = 'venue view Successfully';

                    } else {
                        $response['status'] = 'failure';
                        $response['message'] = "There is some error while saving data";
                        $response['error'] = $model->getErrors();
                    }
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = "Already view this venue";
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = $validation2['message'];
                $response['data'] = $_POST;
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validation['message'];
            $response['data'] = $_POST;
        }

        Yii::log(var_export($response, true), "warning", "pro/view_venue RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * 42nite PRO API
     * Get Event Detail
     *
     * Last Update Details
     * For : Initialised
     * By : jenish paghadar
     * On : 31/08/17
     */
   public function actionGetEventDetail()
    {
        // echo var_export($_POST, true);
        // die();
        Yii::log(var_export($_POST, true), "warning", "pro/getEventDetail POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'event_id']);

        if ($validate['status'] && ($validate['user_role'] == 'VenueOwner' || $validate['user_role'] == 'Promoter' || $validate['user_role'] == 'Admin')) {

            $userId = $_POST['user_id'];
            $event_id = $_POST['event_id'];

            $whereEvent = " `event_meta`.`id` = $event_id ";

//            if ($validate['user_role'] == 'Promoter') {
//                $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `venue_id` FROM `promoter_connects` WHERE `promoter_connects`.`promoters_id` = $userId) ";
//            } else if ($validate['user_role'] == 'VenueOwner') {
//                $whereEvent .= " AND `event_detail`.`venue_id` IN (SELECT `id` FROM `venue_detail` WHERE `venue_detail`.`created_by` = $userId)";
//            }

            // get Event Detail
            $eventSql = "SELECT `event_detail`.`id` AS `parent_event_id`, `event_meta`.`id` AS `event_id`, `event_detail`.`event_name`, `event_detail`.`venue_id` AS `venue_id`,
                                 `user_login`.`role` AS `organised_by`, `user_login`.`display_name` AS `organiser_name`, `user_login`.`user_id` AS `organiser_id`,
                                 `event_detail`.`event_age_group`, `event_detail`.`event_description`, `event_meta`.`event_view_count`,
                                 (SELECT COUNT(`id`) FROM `event_album` WHERE `event_album`.`event_id` = `event_detail`.`id`) AS `total_upload_images`,
                                 get_event_pic(`event_detail`.`id`) AS `event_pic`, get_event_ticket_type(`event_detail`.`id`) AS `event_ticket_type`,
                                 `event_detail`.`event_fee` AS `event_fee`, `event_detail`.`event_seats` AS `event_tickets`, DATE_FORMAT(`event_meta`.`event_date`,'%m/%d/%Y') AS `event_date`,
                                 (SELECT COUNT(`order`.`quantity`) FROM `order` WHERE `event_meta`.`id` = `order`.`event_meta_id`) AS `event_ticket_sold`,
                                 DATEDIFF(`event_meta`.`event_date`, CURRENT_DATE) AS `event_remained_days`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%d') AS `date_short`,
                                 DATE_FORMAT(`event_meta`.`event_date`, '%b') AS `date_month`,
                                 DATE_FORMAT(`event_detail`.`event_start_time`, '%h:%i %p') AS `event_start_time`,
                                 DATE_FORMAT(`event_detail`.`event_end_time`, '%h:%i %p') AS `event_end_time`
                         FROM `event_meta`
                         LEFT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id`
                         LEFT JOIN `user_login` ON `event_meta`.`created_by` = `user_login`.`user_id`
                         WHERE $whereEvent ";
            $event = Application_helper::getData($eventSql);


		$get_event_dates = "SELECT DATE_FORMAT(`event_date`,'%m/%d/%Y') AS `event_date` FROM `event_meta` WHERE `event_meta`.`id` = $event_id";
        $event_dates = Application_helper::getData($get_event_dates);
        $eventEndDateTimeforDB = date('y-m-d',strtotime( $event_dates[0]['event_date']));
        $promocodes_query = "SELECT `promo_code` AS `promocode`, `promo_description` AS `desc`, `discount`, `ticket_name`, DATEDIFF('" . $eventEndDateTimeforDB . "', DATE(`promo_start_date_time`))  AS `start_date`, DATE_FORMAT(`promo_start_date_time`,'%h:%i %p') AS start_time,     DATEDIFF('" . $eventEndDateTimeforDB . "', DATE(`promo_end_date_time`)) AS `end_days`, DATE_FORMAT(`promo_end_date_time`,'%h:%i %p') AS end_time FROM `event_promocode` WHERE `event_meta_id` = $event_id ";
        $promocodes = Application_helper::getData($promocodes_query);

        $event[0]['promocodes']=$promocodes;

         $ticketTypes_query = "SELECT `ticket_name` AS `ticketType`, `ticket_count` as `ticketCount`, `ticket_fee` as `ticketFee`, `ticket_instructions` as `ticketDescription`,
         DATEDIFF('" . $eventEndDateTimeforDB . "', DATE(`ticket_start_date_time`))  AS `startDays`, DATE_FORMAT(`ticket_start_date_time`,'%h:%i %p') AS ticketStartTime,     DATEDIFF('" . $eventEndDateTimeforDB . "', DATE(`ticket_end_date_time`)) AS `expireDay`, DATE_FORMAT(`ticket_end_date_time`,'%h:%i %p') AS ticketExpiryTime FROM `event_tickets` WHERE `event_meta_id` = $event_id ";
         $ticketTypes = Application_helper::getData($ticketTypes_query);
		$event[0]['$ticketTypes']=$ticketTypes;

            if (!empty($event)) {
                $response['status'] = 'success';
                $response['message'] = 'Event Detail fetched Successfully';
                $response['data'] = $event;

            } else {
                $response['status'] = 'failure';
                $response['message'] = 'No event available';
            }
        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/getEventDetail RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * Get user detail of All the VenueOwner
     *
     * Last Update Details
     * For : Initialised
     * By : jenish paghadar
     * On : 20/09/17
     */
   public function actionGetVenueOwners()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/getVenueOwners POST");
        $validate = Application_helper::check_validation($_POST, ['user_id']);

        if ($validate['status']) {

            if ($validate['user_role'] == "Admin") {

                // get detail of all the VenueOwner User
                $venueOwner_user_data_sql = "SELECT `users`.`name` AS `user_name`, `users`.`id` AS `user_id`, (SELECT COUNT(*) FROM `venue_detail` WHERE `venue_detail`.`created_by` = `users`.`id`) AS `venue_count`, IFNULL(get_profile_pic(`users`.`id`),'') AS `user_pic` FROM `users` LEFT JOIN `user_login` ON `user_login`.`user_id` = `users`.`id` WHERE `user_login`.`role` LIKE '%venueowner%'";
                $venueOwner_user = Application_helper::getData($venueOwner_user_data_sql);

                if (!empty($venueOwner_user)) {
                    $response['status'] = 'success';
                    $response['message'] = 'User detail of VenueOwner fetch Successfully';
                    $response['data'] = $venueOwner_user;

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'not VenueOwner found';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'User is not allow to access';
            }

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/getVenueOwners RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * Create VenueOwner By Admin
     *
     * Last Update Details
     * For : Initialised
     * By : jenish paghadar
     * On : 20/09/17
     */
   public function actionCreateVenueOwner()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/createVenueOwner POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'email', 'name', 'password']);

        if ($validate['status']) {

            if ($validate['user_role'] == "Admin") {

                $name = $_POST['name'];
                $password = $_POST['password'];
                $email = $_POST['email'];

                $model = new Users();
                $model->name = $name;
                $model->email = $email;
                $model->notification = 1;
                $model->status = 1;

                // save in user data
                if ($model->save()) {

                    $EncryptString = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
                    $EncryptLength = 6;

                    $userId = Yii::app()->db->lastInsertID;

                    $user_login = new UserLogin;

                    $user_login->user_id = $userId;
                    $user_login->username = $email;
                    $user_login->display_name = $name;

                    $user_login->salt = substr(str_shuffle($EncryptString), 0, $EncryptLength);
                    $user_login->password = md5($user_login->salt . $password);

                    $user_login->role = 'VenueOwner';
                    $user_login->created_by = 0;    //Self
                    $user_login->created_date = strtotime(date('Y-m-d H:i:s'));

                    if (isset($_POST['device_id'])) {
                        $user_login->device_id = $device_id = $_POST['device_id'];
                    }

                    if ($user_login->save()) {

                        $userData = [
                            'user_id' => $userId,
                            'full_name' => $name,
                            'user_name' => $name,
                            'user_role' => $user_login->role,
                        ];

                        $response['status'] = 'success';
                        $response['message'] = 'Registration Successful';
                        $response['data'] = $userData;

                    } else {
                        //DELETE
                        Users::model()->deleteByPk($userId);

                        $response['status'] = 'failure';
                        $response['message'] = 'Registration Failed After Save';
                        $response['data'] = $model->getErrors();
                    }
                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'Registration Failed';
                    $response['data'] = $model->getErrors();
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'User is not allow to access';
            }

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/createVenueOwner RESPONSE");
        $this->renderJSON($response);
    }

    /**
     * Assing Admin created Venue To other user
     *
     * Last Update Details
     * For : Initialised
     * By : jenish paghadar
     * On : 20/09/17
     */
   public function actionAssignVenue()
    {
        Yii::log(var_export($_POST, true), "warning", "pro/AssignVenue POST");
        $validate = Application_helper::check_validation($_POST, ['user_id', 'venue_tag', 'other_user_id']);

        if ($validate['status']) {

            if ($validate['user_role'] == "Admin") {

                $other_user_id = $_POST['other_user_id'];
                $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
                $length = 6;

                // check other user role
                $checkOtherUserRoleSql = "SELECT * FROM `user_login` WHERE `role` = 'VenueOwner' AND `user_id` = $other_user_id";
                $checkOtherUserRole = Application_helper::getData($checkOtherUserRoleSql);

                if (!empty($checkOtherUserRole)) {

                    if (isset($_POST['venue_tag'])) {
                        $venues_id = explode(',', $_POST['venue_tag']);
                        foreach ($venues_id as $venueId) {
                            $update_venue_detail_sql = "UPDATE `venue_detail` SET `created_by` = $other_user_id WHERE `id` = $venueId";
                            Application_helper::updateData($update_venue_detail_sql);
                        }
                    }

                    $record = UserLogin::findOne(array('user_id' => $other_user_id));

                    $reset_token = substr(str_shuffle($string), 0, $length);
                    $record->reset_password = 1;
                    $record->reset_token = $reset_token;
                    $record->update(false);

                    $reset_url = 'http://whats42nite.com/admin/userLogin/reset_password?token=' . $reset_token . '&id=' . $other_user_id;
                    Email_helper::send_password_request_mail($checkOtherUserRoleSql[0]['username'], $checkOtherUserRoleSql[0]['display_name'], $reset_url);

                    $response['status'] = 'success';
                    $response['message'] = 'venue assign Successfully';

                } else {
                    $response['status'] = 'failure';
                    $response['message'] = 'selected user is not VenueOwner';
                }
            } else {
                $response['status'] = 'failure';
                $response['message'] = 'User is not allow to access';
            }

        } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }
        Yii::log(var_export($response, true), "warning", "pro/AssignVenue RESPONSE");
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

    /**
     * testing mail
     */
   public function actionEmail_testing()
    {
        $user_id = $_POST['user_id'];
        $event_id = $_POST['event_id'];
        $event_date = $_POST['event_date'];
        $email = $_POST['email'];

        $venue_data['venue_type'] = 1;
        $venue_data['name'] = "DR World Ahmedabad";
        $venue_data['email'] = "DR_world@whats42nite.com";
        $venue_data['contact_no'] = "(983) 546-4563";
        $venue_data['address'] = "C.G. Road";
        $venue_data['city'] = "Ahmedabad";
        $venue_data['country'] = "India";
        $time_date_registration = "4 December 2017";


        $event_data['venue_type'] = "Club";
        $event_data['venue_name'] = "DR World Ahmedabad";
        $event_data['event_name'] = "Rain In Winter";
        $event_data['event_description'] = "This event in ahmedabad anyone can welcome";
        $event_dates = "12-5-2017,12-6-2017,12-7-2017,12-8-2017";
//        Email_helper::send_welcome_user_mail($email, "Deep Rathod", "12345678");
//        Email_helper::send_password_request_mail($email, "Deep Rathod", "12345678");
//        Email_helper::send_forgot_password_mail($email, "Deep Rathod", "12345678");
//        Email_helper::send_reminder_mail($email, "DR Venue Ahmedabad");
//        Email_helper::send_invite_venue_mail($email, "DR Venue Ahmedabad");
//        Email_helper::send_welcome_venue_mail($email, "new venue", "12345678");
//        Email_helper::send_new_venue_mail($venue_data, $time_date_registration);
//        Email_helper::send_new_event_mail($event_data, $event_dates);
//        Email_helper::send_new_event_register_user("Jenish Paghadal", "Rain In Winter", "2017-12-8", "Qurious Click", "11:00 PM To 02:00 AM", $email);
//        Email_helper::send_mail_to_admin_on_user_w9_request($user_id);
//        Email_helper::send_feedback_mail("Jenish Paghadal", "iOS", "Whats42nite to night..");
//        Email_helper::send_follow_request_mail($email, "Jenish Paghadal", "Deep Rathod");
        Email_helper::send_transaction_confirmation_mail($user_id, $event_id, $event_date);

    }


   // add debit card in stripe account
   public function actionAddCard4InstantPayout()
   {

         Yii::log(var_export($_POST, true), "warning", "pro/AddCardStripe POST");
         $validate = Application_helper::check_validation($_POST, ['user_id']);
         if ($validate['status']) {

            $userId = $_POST['user_id'];
            $cardNumber = $_POST['card_number'];
            $card_exMonth = $_POST['expiry_month'];
            $card_exYear = $_POST['expiry_year'];

            $stripeAccount = StripeAccounts::model()->findByAttributes(['users_id' => $userId]);
            if (!empty($stripeAccount))
            {
               $accountObject = json_decode($stripeAccount->account_object_json);
               if (!empty($accountObject))
               {
                  $customAccountID = $accountObject->id;
                  if (!empty($customAccountID))
                  {
                     $cardObject = Stripe::createDebitCard($customAccountID, $cardNumber, $card_exMonth, $card_exYear);
                     Yii::log(var_export($cardObject, true), "warning", "Card Object");
                     if (!empty($cardObject)) {
                        $card_token = $cardObject['token'];
                        Yii::log(var_export($card_token, true), "warning", "Card Token:");
                        if (!empty($card_token)){
			
							try {
										$cardStatus = \Stripe\account::createExternalAccount($customAccountID,
                                                                                    ['external_account' => $card_token]);
										Yii::log(var_export($cardStatus, true), "warning", "Card Object");
								} 
								catch (Exception $e) {
									$error = $e->getMessage();
									$response['status'] = 'failure';
									$response['message'] = $error;
								}
                        }
                        if (!empty($cardStatus)) {

                           $response['status'] = 'Success';
                           $response['data'] = $cardStatus;
						  
							$stripeAccount->instant_pay = 1;
							$stripeAccount->save();
						   
						   //$updateflagSql = "UPDATE `stripe_accounts` SET `instant_pay`=1 WHERE `users_id` = $userId ";
						   

                        } else {

                           $response['status'] = 'failure';
                           $response['message'] = 'Failure in adding debit card';

                        }

                     } else {

                        $response['status'] = 'failure';
                        $response['message'] = 'Failure in adding debit card';
                     } // end of if block for $card Object
                  } else {

                        $response['status'] = 'failure';
                        $response['message'] = 'Stripe account not found for $userId';
                  } // end of if block for $customAccountID Object
               } else {

                     $response['status'] = 'failure';
                     $response['message'] = 'Account not found for $userId';
               }  // endif block for $ accountObject

            }else {

                  $response['status'] = 'failure';
                  $response['message'] = 'Account not found for $userId';
            }  // endif block for $ accountObject
         } else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/AddCardStripe RESPONSE");
        $this->renderJSON($response);





   }

    /**
     * Private Function
     * Return create Custom Account.
     * @param array $data
     */

   public function actionLinkCustomAccount()
     {

         Yii::log(var_export($_POST, true), "warning", "pro/linkCustomAccount POST");
         $validate = Application_helper::check_validation($_POST, ['venue_id']);

        if ($validate['status']) {
            $userId = $_POST['venue_id'];
            $business_type = $_POST['business_type'];
            $business_name = $_POST['business_name'];
            $business_address = $_POST['business_address'];
            $business_city = $_POST['business_city'];
            $business_state = $_POST['business_state'];
            $business_zip = $_POST['business_zip'];
            $business_phone = $_POST['business_phone'];
            $business_email = $_POST['business_email'];
            $business_url = $_POST['business_url'];
            $business_eid = $_POST['business_ein'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $address = $_POST['address'];
            $city = $_POST['city'];
            $state = $_POST['state'];
            $zip = $_POST['zip'];
            $phone_number = $_POST['phone_number'];
            $dob = $_POST['dob'];
            $ssn_last4 = $_POST['last_ssn4'];
            $routing_number = $_POST['routing_number'];
            $bankAccount = $_POST['external_account'];

            $stripeAccount = StripeAccounts::model()->findByAttributes(['users_id' => $userId]);
            if (!empty($stripeAccount))
            {
               $accountObject = json_decode($stripeAccount->account_object_json);
            }
            else
            {
               if (($businessType == 'Individual'))
               {
                  $log_message = 'Inside Invidual Stripe Call';Yii::log(var_export($log_message, true), "warning", "pro/linkCustomAccount INTERMEDIATE");
                  $accountObject = Stripe::createStripeAccount($userId, $business_type,$business_name, $business_eid, $business_url, $business_address, $business_city, $business_state, $business_zip, $business_phone, $business_email,
                                              $first_name, $last_name,$address,$city,$state,$zip,$phone_number, $dob, $ssn_last4,$routing_number, $bank_account);
               }
               else
               {  $log_message = 'Inside Business Stripe Call';Yii::log(var_export($log_message, true), "warning", "pro/linkCustomAccount INTERMEDIATE");
                  $accountObject = Stripe::createStripeAccount($userId, $business_type,$business_name, $business_eid, $business_url, $business_address, $business_city, $business_state, $business_zip, $business_phone, $business_email,
                                              $first_name, $last_name,$address,$city,$state,$zip,$phone_number, $dob, $ssn_last4,$routing_number, $bank_account);
               }


            }

            if (!empty($accountObject))
            {
               $errorStatus = $accountObject->result;
               if ($errorStatus == 'error')
               {
                  $response['status'] = 'failure';
                  $response['message'] = $accountObject->error;
               }
               else {
                     // checking pending verification status in Account
                     if(sizeof($accountObject->verification->fields_needed) > 0){
                           $latestAccountStatus = 'moreinfo';
                           $AccountStatusMsg = 'more information is required. Please contact system administrator.';
                     }else{
                           $latestAccountStatus = 'active';
                           $AccountStatusMsg = 'custom account created successfully.';
                     }

                     $accountId = $accountObject->id;
                     $response['status'] = 'success';
                     $response['account_status'] = $latestAccountStatus;
                     $response['account_id'] = $accountId;
                     $response['message'] = $AccountStatusMsg;
                     $response['user_id'] = $userId;
                     $response['business_type'] = $businessType;
                     $response['result'] = $errorStatus;
                     $response['error'] = $AccountStatusMsg;
                     $response['data'] = $accountObject;
               }
            } else {
               $response['status'] = 'failure';
               $response['message'] = 'something went wrong. custom account creation FAILED.';
               $response['user_id'] = $userId;
               $response['business_type'] = $businessType;
            }
         }else {
            $response['status'] = 'failure';
            $response['message'] = $validate['message'];
        }

        Yii::log(var_export($response, true), "warning", "pro/linkCustomAccount RESPONSE");
        $this->renderJSON($response);

      }


   public function actionTestAvailabeBalance(){
         $balance = Stripe::getBalance();
         print_r($balance);
      }

/************************************************************ Payout Module APIs *********************************************************************
* 1. CreateAdvancePayout API : API to create advance for a particular event.
* 2. GetPayoutStatus API : API to get status whether  final payout is released for a particular event or not.
* 3. _getPayoutAdvance API :  API to get total advance amount released for a particular event.
* 4. CreatePayout API : API to generate final (standard) payout for a particular event.
* 5. CreateInstantPayout API : API to generate final (Instant) payout for a particular event.
* 6. GeneratePayout API: Single API to generate either Advance or Final Payout, either by  standard mode or Instant mode. 
**/
	   // Advance Payout API
   public function actionCreateAdvancePayout()
     {

            Yii::log(var_export($_POST, true), "warning", "pro/createPayout POST");
            $date1 = new DateTime();
            $venue_id = $_POST['venue_id'];
            $userId = $_POST['user_id'];


            Yii::log(var_export($date1, true), "warning", "pro/createPayout DATETIME");

            if ($venue_id) {
               $validate = Application_helper::check_validation($_POST, ['venue_id']);
               $userId = $_POST['venue_id'];
            }
            else
            {
               $validate = Application_helper::check_validation($_POST, ['user_id']);
               $userId = $_POST['user_id'];
            }

            if ($validate['status']) {
               $amountToPay = $_POST['amount'];       // Venue Transfer Amount

               if (isset($_POST['event_id'])){
                  $event_id = $_POST['event_id'];
               }
               Yii::log(var_export($event_id, true), "warning", "pro/createPayout EVENT ID");
               Yii::log(var_export($userId, true), "warning", "pro/createPayout USER ID");
               $transfer = Stripe::transferPayment($userId,$amountToPay);
               Yii::log(var_export($transfer, true), "warning", "pro/Payout Response");
               if (!empty($transfer))
               {

                  $errorStatus = $transfer['status'];
               //   Yii::log(var_export($errorStatus, true), "warning", "pro/error status");
                  if ($errorStatus == 'failure')
                  {
                     $response['status'] = 'failure';
                     $response['message'] = $transfer['message'];
                  }
                  else {
                     $transaction_id = $transfer['id'];
                     Yii::log(var_export($transaction_id, true), "warning", "pro/Payout Transcation ID");

                     $str_date = $date1->format('Y-m-d-H-i-s');
                     $payout = new Payouts();
                     $payout->event_id = $event_id;
                     $payout->venue_id = $userId;
                     $payout->payout_type = 'A';
                     $payout->transaction_id = $transaction_id;
                     $payout->amount = $amountToPay;
                     $payout->payment_date = $str_date;
                     $payout->response_code = '100';
                     $payout->created_date = $str_date;

                     if ($payout->save(false)) {
                        //Inserted payout id
                        $payoutId = Yii::app()->db->getLastInsertID();
                        $feePayoutDescription = "Event Fee for Event ID:".$event_id;

                        $response['status'] = 'success';
                        $response['transaction_id'] = $transaction_id;
                        $response['payout_id'] = $payoutId;
                        $response['message'] = 'Payout created successfully.';
                        $response['user_id'] = $userId;
                        $response['amount'] = $amountToPay;
                        $response['data'] = $transfer;
                     } else {
                           $response['status'] = 'failure';
                           $response['message'] = "There is some error while saving payout data";
                           $response['error'] = $payout->getErrors();
                     }
                  }
               }
               else
               {
                  $response['status'] = 'failure';
                  $response['message'] = 'something went wrong. payout FAILED.';
                  $response['user_id'] = $userId;
                  $response['amount'] = $amountToPay;
               }
            } else {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
            }
            Yii::log(var_export($response, true), "warning", "pro/createPayout RESPONSE");
            $this->renderJSON($response);

     }

  

	// get final payout status 
  public function actionGetPayoutStatus()
   {
         Yii::log(var_export($_POST, true), "warning", "pro/getPayoutStatus POST");
         $validate = Application_helper::check_validation($_POST, ['event_id']);

         if ($validate['status'])
         {
            $eventId = $_POST['event_id'];         // Event Id

            $checkStatusSql = "SELECT `payouts`.`transaction_id` as transaction_id,`payouts`.`payment_date` as payment_date FROM `payouts` WHERE `payouts`.`event_id` = $eventId AND `payouts`.`payout_type` = 'F'";
            $checkStatus = Application_helper::getData($checkStatusSql);
            Yii::log(var_export($checkStatusSql, true), "warning", "pro/getPayoutStatus SQL");
             Yii::log(var_export($checkStatus, true), "warning", "pro/getPayoutStatus RESPONSE");
            if (empty($checkStatus)) {
               $response['status'] = "Failure";
               $response['message'] = "No payout exist for ["+$eventId+"]";
               $response['payout_status'] = 'unpaid';
            }
            else
            {
                $tr_id = $checkStatus[0]['transaction_id'];
                $payment_date = $checkStatus[0]['payment_date'];
                Yii::log(var_export($tr_id, true), "warning", "pro/getPayoutStatus Transaction ID");
                Yii::log(var_export($payment_date, true), "warning", "pro/getPayoutStatus Payment Date");
                $response['status'] = "Success";
                $response['message'] = "Payout is already completed.";
                $response['payout_status'] = 'paid';
                $response['transaction_id'] = $checkStatus[0]['transaction_id'];
                $response['payment_date'] = $payment_date;
            }
         }
         else
         {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
         }
         Yii::log(var_export($response, true), "warning", "pro/getPyoutStatus RESPONSE");
        $this->renderJSON($response);
    }



   // get advance amount already apid for the event, if advance released.
   public function _getPayoutAdvance($event_meta_id)
    {

            $eventId = $event_meta_id;         // Event Meta Id
            $paid_amount = 0;
            $payout_type = "A";

            $getAdvanceSql = "SELECT IFNULL(sum(`payouts`.`amount`),0) as advance_amount FROM `payouts` WHERE `payouts`.`event_id` = $eventId and `payouts`.`payout_type` = '$payout_type'";
            $getAdvance = Application_helper::getData($getAdvanceSql);

            $paid_amount = $getAdvance[0]['advance_amount'];
            Yii::log(var_export($paid_amount, true), "warning", "pro/getAdvance Amount Paid:");

            return $paid_amount;
    }



      // Final Payout API
   public function actionCreatePayout()
     {

            Yii::log(var_export($_POST, true), "warning", "pro/createPayout POST");
            $date1 = new DateTime();
            $venue_id = $_POST['venue_id'];
            $userId = $_POST['user_id'];
            if (isset($_POST['event_id'])){
                  $event_id = $_POST['event_id'];
            }

            Yii::log(var_export($date1, true), "warning", "pro/createPayout DATETIME");

            if ($venue_id) {
               $validate = Application_helper::check_validation($_POST, ['venue_id']);
               $userId = $_POST['venue_id'];
            }
            else
            {
               $validate = Application_helper::check_validation($_POST, ['user_id']);
               $userId = $_POST['user_id'];
            }

            if ($validate['status']) {

               $advance_amount = self::_getPayoutAdvance($event_id);
               Yii::log(var_export($advance_amount, true), "warning", "pro/createPayout Advance Paid");

               $gross_amount = $_POST['amount'];       // Venue Transfer Amount

               $amountToPay = $gross_amount - $advance_amount;       // net_amount after adjusting advance payment.
               $feeAmount = $_POST['event_fee'];      // 42nite Event Fee after adjusting Stripe Fee


               Yii::log(var_export($event_id, true), "warning", "pro/createPayout EVENT ID");
               Yii::log(var_export($userId, true), "warning", "pro/createPayout USER ID");
               $transfer = Stripe::transferPayment($userId,$amountToPay);
               Yii::log(var_export($transfer, true), "warning", "pro/Payout Response");
               if (!empty($transfer))
               {

                  $errorStatus = $transfer['status'];
               //   Yii::log(var_export($errorStatus, true), "warning", "pro/error status");
                  if ($errorStatus == 'failure')
                  {
                     $response['status'] = 'failure';
                     $response['message'] = $transfer['message'];
                  }
                  else {
                     $transaction_id = $transfer['id'];
                     Yii::log(var_export($transaction_id, true), "warning", "pro/Payout Transcation ID");

                     $str_date = $date1->format('Y-m-d-H-i-s');
                     $payout = new Payouts();
                     $payout->event_id = $event_id;
                     $payout->venue_id = $userId;
                     $payout->payout_type = 'F';
                     $payout->transaction_id = $transaction_id;
                     $payout->amount = $amountToPay;
                     $payout->payment_date = $str_date;
                     $payout->response_code = '100';
                     $payout->created_date = $str_date;

                     if ($payout->save(false)) {
                        //Inserted payout id
                        $payoutId = Yii::app()->db->getLastInsertID();
                        $feePayoutDescription = "Event Fee for Event ID:".$event_id;
                        if ($feeAmount > 0) {
                           $feePayout = Stripe::createPayout($feeAmount,$feePayoutDescription);
                           if (!empty($feePayment))
                           {
                              Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite was successful.");
                           }
                           else
                           {
                              Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite failed.");
                           }
                        }
                        $response['status'] = 'success';
                        $response['transaction_id'] = $transaction_id;
                        $response['payout_id'] = $payoutId;
                        $response['message'] = 'Payout created successfully.';
                        $response['user_id'] = $userId;
                        $response['amount'] = $amountToPay;
                        $response['data'] = $transfer;
                     } else {
                           $response['status'] = 'failure';
                           $response['message'] = "There is some error while saving payout data";
                           $response['error'] = $payout->getErrors();
                     }
                  }
               }
               else
               {
                  $response['status'] = 'failure';
                  $response['message'] = 'something went wrong. payout FAILED.';
                  $response['user_id'] = $userId;
                  $response['amount'] = $amountToPay;
               }
            } else {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
            }
            Yii::log(var_export($response, true), "warning", "pro/createPayout RESPONSE");
            $this->renderJSON($response);

     }

       // Final Instant Payout API
   public function actionCreateInstantPayout()
     {
            Yii::log(var_export($_POST, true), "warning", "pro/createPayout POST");
            $date1 = new DateTime();
            $venue_id = $_POST['venue_id'];
            $userId = $_POST['user_id'];
            if (isset($_POST['event_id'])){
                  $event_id = $_POST['event_id'];			//event meta id
				  
				  //get organizer of the event.
				  $hosted_by = Application_helper::get_organizer_id($event_id);
				  Yii::log(var_export($hosted_by, true), "warning", "Organiser:");
			
				  //get fee structure for the event
				  $transfer_fee = Application_helper::getCustomFee('venue_fee', $hosted_by);
				  Yii::log(var_export($transfer_fee, true), "warning", "Transfer Fee %:");
				  $payout_fee = Application_helper::getCustomFee('payout_fee', $hosted_by);
				  Yii::log(var_export($payout_fee, true), "warning", "Payout Fee %:");
				  $topup_fee = Application_helper::getCustomFee('stripe_topup', $hosted_by);
				  Yii::log(var_export($topup_fee, true), "warning", "Topup Fee %:");
				  
				  //get Event Reveue
				  $event_revenue = 0;
				  $eventRevenueSQL = "SELECT sum(`order`.`amount`) as `total_revenue` FROM `order` WHERE `order`.`event_meta_id` = $event_id AND `order`.`status` = 1";
				   Yii::log(var_export($eventRevenueSQL, true), "warning", "Revenue SQL:");
				  $eventRevenueData = Application_helper::getData($eventRevenueSQL);
				  
				  Yii::log(var_export($eventRevenueData, true), "warning", "Revenue Data:");
				  
				  if ($eventRevenueData) {
					$event_revenue = $eventRevenueData[0]['total_revenue'];
				  }
				  Yii::log(var_export($event_revenue, true), "warning", "Revenue $:");
				  // calculate fee amounts
					$transfer_amount = round(($event_revenue * $transfer_fee)/100,2);
					$payout_amount =  round(($event_revenue * $payout_fee)/100,2);
					$topup_amount =  round(($event_revenue * $topup_fee)/100,2);
				  
            }

            Yii::log(var_export($date1, true), "warning", "pro/createPayout DATETIME");

            if ($venue_id) {
               $validate = Application_helper::check_validation($_POST, ['venue_id']);
               $userId = $_POST['venue_id'];
            }
            else
            {
					if (isset($_POST['user_id'])){
						$validate = Application_helper::check_validation($_POST, ['user_id']);
						$userId = $_POST['user_id'];
					} else {
						$userId = $hosted_by;
					}
			   
            }

            if ($validate['status']) {

				//get Advance Payment paid for the event
               $advance_amount = self::_getPayoutAdvance($event_id);
               Yii::log(var_export($advance_amount, true), "warning", "pro/createPayout Advance Paid");

               $gross_amount = $_POST['amount'];       // Venue Transfer Amount

               $amountToPay = $gross_amount - $advance_amount;       // net_amount after adjusting advance payment.
               $feeAmount = $_POST['event_fee'];      // 42nite Event Fee after adjusting Stripe Fee


               Yii::log(var_export($event_id, true), "warning", "pro/createPayout EVENT ID");
               Yii::log(var_export($userId, true), "warning", "pro/createPayout USER ID");
               $transfer = Stripe::transferPaymentWithInstantPayout($userId,$amountToPay);
               Yii::log(var_export($transfer, true), "warning", "pro/Payout Response");
               if (!empty($transfer))
               {

                  $errorStatus = $transfer['status'];
               //   Yii::log(var_export($errorStatus, true), "warning", "pro/error status");
                  if ($errorStatus == 'failure')
                  {
                     $response['status'] = 'failure';
                     $response['message'] = $transfer['message'];
                  }
                  else {
                     $transaction_id = $transfer['id'];
                     Yii::log(var_export($transaction_id, true), "warning", "pro/Payout Transcation ID");

                     $str_date = $date1->format('Y-m-d-H-i-s');
                     $payout = new Payouts();
                     $payout->event_id = $event_id;
                     $payout->venue_id = $userId;
                     $payout->payout_type = 'F';
                     $payout->transaction_id = $transaction_id;
                     $payout->amount = $amountToPay;
					 $payout->payout_speed = 'I';
					 $payout->transfer_fee = $transfer_fee;
					 $payout->transfer_amount = $transfer_amount;
					 $payout->payout_fee = $payout_fee;
					 $payout->payout_fee_amount = $payout_amount;
					 $payout->topup_fee = $topup_fee;
					 $payout->topup_amount = $topup_amount;
                     $payout->payment_date = $str_date;
                     $payout->response_code = '100';
                     $payout->created_date = $str_date;

                     if ($payout->save(false)) {
                        //Inserted payout id
                        $payoutId = Yii::app()->db->getLastInsertID();
                        $feePayoutDescription = "Event Fee for Event ID:".$event_id;
                        $feePayout = Stripe::createPayout($feeAmount,$feePayoutDescription);
                        if (!empty($feePayment))
                        {
                           Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite was successful.");
                        }
                        else
                        {
                           Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite failed.");
                        }

                        $response['status'] = 'success';
                        $response['transaction_id'] = $transaction_id;
                        $response['payout_id'] = $payoutId;
                        $response['message'] = 'Payout created successfully.';
                        $response['user_id'] = $userId;
                        $response['amount'] = $amountToPay;
                        $response['data'] = $transfer;
                     } else {
                           $response['status'] = 'failure';
                           $response['message'] = "There is some error while saving payout data";
                           $response['error'] = $payout->getErrors();
                     }
                  }
               }
               else
               {
                  $response['status'] = 'failure';
                  $response['message'] = 'something went wrong. payout FAILED.';
                  $response['user_id'] = $userId;
                  $response['amount'] = $amountToPay;
               }
            } else {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
            }
            Yii::log(var_export($response, true), "warning", "pro/createPayout RESPONSE");
            $this->renderJSON($response);

     }

   
  
	// New API to create Advance or Final Payout: by Standard or Instant Speed.
	public function actionGeneratePayout()
	{
           
			/*
				 * @ API to manage standard as well instant payout for both Advance & Final Payout.
				 *@ input parameters:
				 *@   event_id: mandatory;  payout_speed: mandatory; payout_type:mandatory;amount:mandatory;
				 *@   event_fee:optional; venue_id: mandatory, if orgainzer is venue owner; userId; mandatory if organizer = promoter;
				 *@
				 
			*/
	 
			Yii::log(var_export($_POST, true), "warning", "pro/generatePayout POST");
            $date1 = new DateTime();
			//get Input Parameters
			
			$payout_speed = $_POST['payout_speed'];	// payout speed: S (Standard), I (Instant)
			$payout_type = $_POST['payout_type'];	// payout type: A (Advance), F (Final)
			$event_id = $_POST['event_id'];				// event meta id for which payout is required to be released
			$gross_amount = $_POST['amount'];       // Venue Payout Amount
			$feeAmount = $_POST['event_fee'];      // 42nite Event Fee after adjusting Stripe Fee
			
			// check if venue id is set, if event organizer is venue
			if (isset($_POST['venue_id'])){
				$venue_id = $_POST['venue_id'];			// venue ID
			}
			// check if user id is set, if event organizer is promoter
			if (isset($_POST['user_id'])){
				$userId = $_POST['user_id'];				// poromoter ID
			}
			
			Yii::log(var_export($date1, true), "warning", "pro/generatePayout DATETIME:");
			
			// validate userID / venue_id to check if provided data is correct or not;
			if ($venue_id) {
               $validate = Application_helper::check_validation($_POST, ['venue_id']);
               $userId = $_POST['venue_id'];
            }
            else
            {
					if (isset($_POST['user_id'])){
						$validate = Application_helper::check_validation($_POST, ['user_id']);
						$userId = $_POST['user_id'];
					} else {
						$userId = $hosted_by;
					}
			   
            }
			
			if ($validate['status']) {
			 
				//  *********** get various Fee & their respective Amount along with Event Revenue *****************
					if (isset($_POST['event_id'])){
					  
						  //get organizer of the event.
						  $hosted_by = Application_helper::get_organizer_id($event_id);
						  Yii::log(var_export($hosted_by, true), "warning", "Organiser:");
					
						  //get fee structure for the event
						  $transfer_fee = Application_helper::getCustomFee('venue_fee', $hosted_by);
						  Yii::log(var_export($transfer_fee, true), "warning", "Transfer Fee %:");
						  $payout_fee = Application_helper::getCustomFee('payout_fee', $hosted_by);
						  Yii::log(var_export($payout_fee, true), "warning", "Payout Fee %:");
						  $topup_fee = Application_helper::getCustomFee('stripe_topup', $hosted_by);
						  Yii::log(var_export($topup_fee, true), "warning", "Topup Fee %:");
						  
						  //get Event Reveue
						  $event_revenue = 0;
						  $eventRevenueSQL = "SELECT sum(`order`.`amount`) as `total_revenue` FROM `order` WHERE `order`.`event_meta_id` = $event_id AND `order`.`status` = 1";
						   Yii::log(var_export($eventRevenueSQL, true), "warning", "Revenue SQL:");
						  $eventRevenueData = Application_helper::getData($eventRevenueSQL);
						  
						  Yii::log(var_export($eventRevenueData, true), "warning", "Revenue Data:");
						  
						  if ($eventRevenueData) {
							$event_revenue = $eventRevenueData[0]['total_revenue'];
						  }
						Yii::log(var_export($event_revenue, true), "warning", "Revenue $:");
					}
				//**********************************************************************************************
			 
				// check payout type and process accordingly.
					if ($payout_type == 'F') {
			
						// calculate fee amounts
						$transfer_amount = round(($event_revenue * $transfer_fee)/100,2);
						$payout_amount =  round(($event_revenue * $payout_fee)/100,2);
						$topup_amount =  round(($event_revenue * $topup_fee)/100,2);
						
						//get Advance Payment paid for the event
						$advance_amount = self::_getPayoutAdvance($event_id);
						Yii::log(var_export($advance_amount, true), "warning", "pro/createPayout Advance Paid");

						$amountToPay = $gross_amount - $advance_amount;       // net_amount after adjusting advance payment.
						Yii::log(var_export($event_id, true), "warning", "pro/createPayout EVENT ID");
						Yii::log(var_export($userId, true), "warning", "pro/createPayout USER ID");
					} 
					else if ($payout_type == 'A') 
					{
							$transfer_fee = 0; $transfer_amount = 0;
							$payout_amount =  round(($gross_amount * $payout_fee)/100,2);
							$topup_fee = 0;$topup_amount = 0;
							
							$amountToPay = $gross_amount;       // amount to pay as advance.
							Yii::log(var_export($event_id, true), "warning", "pro/createPayout EVENT ID");
							Yii::log(var_export($userId, true), "warning", "pro/createPayout USER ID");
							
					}
					
					// check payout speed and transfer funds accordingly.
					if ($payout_speed == 'I') {
							$transfer = Stripe::transferPaymentWithInstantPayout($userId,$amountToPay);
					} else if ($payout_speed == 'A') {
							$transfer = Stripe::transferPayment($userId,$amountToPay);
					}
						
					Yii::log(var_export($transfer, true), "warning", "pro/Payout Response");
					
					if (!empty($transfer))
					{

									$errorStatus = $transfer['status'];
							   //   Yii::log(var_export($errorStatus, true), "warning", "pro/error status");
									if ($errorStatus == 'failure')
									{
										$response['status'] = 'failure';
										$response['message'] = $transfer['message'];
									} 
									else 
									{
											$transaction_id = $transfer['id'];
											Yii::log(var_export($transaction_id, true), "warning", "pro/Payout Transcation ID");

											$str_date = $date1->format('Y-m-d-H-i-s');
											
											$payout = new Payouts();
											$payout->event_id = $event_id;
											$payout->venue_id = $userId;
											$payout->payout_type = $payout_type;
											$payout->transaction_id = $transaction_id;
											$payout->amount = $amountToPay;
											$payout->payout_speed = $payout_speed;
											$payout->transfer_fee = $transfer_fee;
											$payout->transfer_amount = $transfer_amount;
											$payout->payout_fee = $payout_fee;
											$payout->payout_fee_amount = $payout_amount;
											$payout->topup_fee = $topup_fee;
											$payout->topup_amount = $topup_amount;
											$payout->payment_date = $str_date;
											$payout->response_code = '100';
											$payout->created_date = $str_date;

											if ($payout->save(false)) {
													//Inserted payout id
													$payoutId = Yii::app()->db->getLastInsertID();
													$feePayoutDescription = "Event Fee for Event ID:".$event_id;
													$feePayout = Stripe::createPayout($feeAmount,$feePayoutDescription);
													if (!empty($feePayment))
													{
													   Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite was successful.");
													}
													else
													{
													   Yii::log(var_export($feePayout, true), "warning", "pro/Payout to 42nite failed.");
													}

													$response['status'] = 'success';
													$response['transaction_id'] = $transaction_id;
													$response['payout_id'] = $payoutId;
													$response['message'] = 'Payout created successfully.';
													$response['user_id'] = $userId;
													$response['amount'] = $amountToPay;
													$response['data'] = $transfer;
											} else {
												$response['status'] = 'failure';
												$response['message'] = "There is some error while saving payout data";
												$response['error'] = $payout->getErrors();
											}
									}
					}   
					else
					{
							  $response['status'] = 'failure';
							  $response['message'] = 'something went wrong. payout FAILED.';
							  $response['user_id'] = $userId;
							  $response['amount'] = $amountToPay;
					}		
					
			} // if validate status ends here
			else {
               $response['status'] = 'failure';
               $response['message'] = $validate['message'];
            }
            Yii::log(var_export($response, true), "warning", "pro/createPayout RESPONSE");
            $this->renderJSON($response);
     }


/*****************************************************Dispute Management APIs************************************************************************************
* 1. GetAllDisputes API: API to download, create new or update existing Disputes from Stripe to our system.
* 2. getDispute API: API to get detail of a particular dispute from Stripe.
*/

// Fetch All Disputes, Create New Disputes, Update Existing Disputes
   public function actionGetAllDisputes()
   {

         Yii::log(var_export($_POST, true), "warning", "pro/get All dispute");
         $date1 = new DateTime();
         // getting dispute list
         $disputeList = Stripe::getAllDisputes();

         if ((!empty($disputeList)))
         {

            $disputes = $disputeList['data'];
         //   Yii::log(var_export($disputes, true), "warning", "pro/dispute List");
            if (sizeof($disputes)>0) {

               $dispute_count = 0;
               foreach ($disputes as $dispute) {


                     $dispute_id = $dispute['id'];
                     $transaction_id = $dispute['charge'];
                     $current_status = $dispute['status'];

                     Yii::log(var_export($dispute_id, true), "warning", "pro/dispute ID");
                     Yii::log(var_export($transaction_id, true), "warning", "pro/charge ID");
                     Yii::log(var_export($current_status, true), "warning", "pro/dispute Status");

                     // check if dispute already exists
                     $checkDisputeSQL = "SELECT `transaction_id`,`order_id`,`status` from `disputes` where `dispute_id` = '$dispute_id'";
                     $checkDispute = Application_helper::getData($checkDisputeSQL);
                     if ($checkDispute != null){
                        $dispute_status = $checkDispute[0]['status'];
			$dispute_order_id = $checkDispute[0]['order_id'];
			Yii::log(var_export($dispute_order_id, true), "warning", " Order ID of dispute:");
                        if ($current_status != $dispute_status) {
                              // update dispute
                              $dispute_evidenceDetails = $dispute['evidence_details'];

                              // getting evidence due date & evidence status
                              if (($dispute_evidenceDetails != null) && (sizeof($dispute_evidenceDetails) > 0)) {
                                 $evidence_due_tmp = $dispute_evidenceDetails['due_by'];
                                 $evidence_due = new DateTime("@$evidence_due_tmp");
                                 $evidence_due_date = $evidence_due->format('Y-m-d H:i:s');
                                 $evidence_status = $dispute_evidenceDetails['has_evidence'];
                                 Yii::log(var_export($evidence_due_date, true), "warning", "Evidence Due Date:");
                                 Yii::log(var_export($evidence_status, true), "warning", "Evidence Status:");

                                 // update dispute evidence due date, status & dispute status
                                 $dispute = Dispute::model()->findByAttributes(['dispute_id' => $dispute_id]);
                               //  $dispute = Dispute::model()->findByPk($dispute_id);
                                 if ($dispute != null)
                                 {
				    $dispute_order_id = $dispute->order_id;
				     Yii::log(var_export($dispute_order_id, true), "warning", " Order ID of dispute:");
                                    $dispute->due_date = $evidence_due_date;
                                    $dispute->evidence_status = $evidence_status;
                                    $dispute->status = $current_status;

                                    if ($dispute->update())
                                    {
					Yii::log(var_export($dispute_id, true), "warning", " Dispute Updated Successfully:");
                                    } else {
                                       Yii::log(var_export($dispute_id, true), "warning", "Failed to update dispute:");
                                    }
                                 } else {
                                       Yii::log(var_export($dispute_id, true), "warning", "Dispute not found: .");
                                 }
                              }
                        }        // dispute update ends here
                        else {
				Yii::log(var_export($dispute_id, true), "warning", " No change in dispute status for Dispute ID:");
                        }
                     }
                     else {
                        // dispute is a new dispute

                        $order_detail_sql = "SELECT `transaction`.`order_id`, `order`.`event_meta_id` FROM `transaction` LEFT JOIN `order` on `order`.`master_order_id` = `transaction`.`order_id`
                                             WHERE `transaction`.`transaction_id` = '$transaction_id'";
                        Yii::log(var_export($order_detail_sql, true), "warning", " SQL:");
                        $checkOrder = Application_helper::getData($order_detail_sql);
                        Yii::log(var_export($checkOrder, true), "warning", " Order Data:");
                        if ($checkOrder != null){
                           $order_id = $checkOrder[0]['order_id'];
                           $event_meta_id = $checkOrder[0]['event_meta_id'];


                        } else {
                           $order_id = 0;
                           $event_meta_id = 0;
                        }
                         Yii::log(var_export($order_id, true), "warning", "Order ID");
                         Yii::log(var_export($event_meta_id, true), "warning", "event meta");

                        //getting balance Tarnsactions and evidence detils
                        $dispute_balanceTransactions = $dispute['balance_transactions'];
                        $dispute_evidenceDetails = $dispute['evidence_details'];

                        //   Yii::log(var_export($dispute_balanceTransactions, true), "warning", "Balance Transactions");
                        //   Yii::log(var_export($dispute_evidenceDetails, true), "warning", "evidence_details");

                        $dispute_amount = $dispute['amount'];  // in cents
                        $dispute_amount = $dispute_amount / 100;  // converted in Dollars

                        $balance_id = $dispute['balance_transaction'];
                        $dispute_date_tmp = $dispute['created'];
                        $dispute_date = new DateTime("@$dispute_date_tmp");
                        $dispute_created_on = $dispute_date->format('Y-m-d H:i:s');
                        $dispute_reason = $dispute['reason'];
                        Yii::log(var_export($dispute_amount, true), "warning", "Dispute Amount");
                        Yii::log(var_export($balance_id, true), "warning", "Balance ID");
                        Yii::log(var_export($dispute_created_on, true), "warning", "Dispute Date");
                        Yii::log(var_export($dispute_reason, true), "warning", "Dispute Reason");
                        Yii::log(var_export($current_status, true), "warning", "Dispute Status");


                        //getting dispute fee and total dispute amount
                        if (($dispute_balanceTransactions != null) & (sizeof($dispute_balanceTransactions) > 0)) {

                           $dispute_fee = $dispute_balanceTransactions[0]['fee'];   // in cents
                           $dispute_fee = $dispute_fee / 100;
                           $dispute_total = $dispute_balanceTransactions[0]['net']; // in cents

                           $dispute_total = ($dispute_total * -1)/100;
                           Yii::log(var_export($dispute_fee, true), "warning", "Dispute Fee");
                           Yii::log(var_export($dispute_total, true), "warning", "Dispute Total");


                        }
                        // getting evidence due date & evidence status
                        if (($dispute_evidenceDetails != null) && (sizeof($dispute_evidenceDetails) > 0)) {
                           $evidence_due_tmp = $dispute_evidenceDetails['due_by'];
                           $evidence_due = new DateTime("@$evidence_due_tmp");
                           $evidence_due_date = $evidence_due->format('Y-m-d H:i:s');
                           $evidence_status = $dispute_evidenceDetails['has_evidence'];
                           Yii::log(var_export($evidence_due_date, true), "warning", "Evidence Due");
                           Yii::log(var_export($evidence_status, true), "warning", "Evidence Status");
                        }

                        // adding dispute in table
						
						if ($dispute_amount > 1) {

                        $DisputeModel = new Dispute();
                        $DisputeModel->dispute_id = $dispute_id;
                        $DisputeModel->dispute_date =  $dispute_created_on;
                        $DisputeModel->transaction_id = $transaction_id;
                        $DisputeModel->order_id = $order_id;
                        $DisputeModel->event_meta_id = $event_meta_id;
                        $DisputeModel->disputed_amount = $dispute_amount;
                        $DisputeModel->balance_id = $balance_id;
                        $DisputeModel->reason = $dispute_reason;
                        $DisputeModel->dispute_fee = $dispute_fee;
                        $DisputeModel->total_amount = $dispute_total;
                        $DisputeModel->created_date = $date1->format('Y-m-d H:i:s');
                        $DisputeModel->due_date = $evidence_due_date;
                        $DisputeModel->evidence_status = $evidence_status;
                        $DisputeModel->status = $current_status;


                        if ($DisputeModel->save()) {

                           $Id = Yii::app()->db->getLastInsertID();
			   
						 $update_status_sql = "UPDATE `order` SET `disputed` = 1 Where `order`.`master_order_id` = $order_id";
						 $update_status = Application_helper::updateData($update_status_sql);
                           
						if ($current_status == "needs_response") {
                              // send email to event organiser
                           //   Email_helper::send_new_dispute_email_to_organizer($order_id, $event_meta_id, $transaction_id);
                              // send acknowledgement email to buyer
                              Email_helper::send_new_dispute_email_to_buyer($order_id, $event_meta_id, $transaction_id);
                           }
                           $dispute_count++;
                        }
                     }
					}
               }

               $response['status'] = 'success';
               $response['dispute_count'] = $dispute_count;
               $response['data'] = $disputes;

            } else {

               $response['status'] = 'failure';
               $response['message'] = 'no disputes found';
            }

         } else {
            $response['status'] = 'failure';
            $response['message'] = 'no disputes found';
         }

         Yii::log(var_export($response, true), "warning", "pro/getAllDisputes RESPONSE");
         $this->renderJSON($response);

   }


   // get detail of particular dispute
   public function actionGetDispute()
   {

         Yii::log(var_export($_POST, true),"warning", "pro/getDispute POST");
         $date1 = new DateTime();
         $dispute_id = $_POST['dispute_id'];
         if ($dispute_id != null) {

            $dispute = Stripe::getDispute($dispute_id);
         }
         Yii::log(var_export($dispute, true), "warning", "pro/dispute RESPONSE");
         if (!empty($dispute))
         {
            $response['status'] = 'success';
            $response['data'] = $dispute;

         } else {
            $response['status'] = 'failure';
            $response['message'] = 'no disputes found';
         }

         Yii::log(var_export($response, true), "warning", "pro/getDispute RESPONSE");
         $this->renderJSON($response);

   }

/*****************************************************Get Fee Struture API******************************************************************

*/
   // get Free Structure for Promoters/VOs
   public function actionGetFeeStructure()
   {
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');

      Yii::log(var_export($_POST, true), "warning", "pro/Instant_payout");

        //$validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'status']);
      $user_id= $_POST['user_id'];

      if (!empty($user_id))
      {
        	$user = UserLogin::model()->findByAttributes(['user_id' => $user_id]);
        	if($user->role =='Admin'){

            // getting standard fee structure
        		$standard_fee_sql ="SELECT `setting_enum`,`data` FROM `admin_configuration` WHERE `account_id`=0";
        		$standard_fee = Application_helper::getData($standard_fee_sql);

            if ($standard_fee != null) {
               $response['status'] = 'Success';
               $response['standard_fee'] = $standard_fee;

               $custom = array();
               // getting custom fee structure
               $get_custom_accounts = "SELECT DISTINCT `admin_configuration`.`account_id`,
								   `user_login`.`display_name` as `Name`,
								   `user_login`.`role` as `Role`,
                           `admin_configuration`.`payout_type`
								   FROM `admin_configuration`
								   LEFT JOIN `user_login` ON `user_login`.`user_id`=`admin_configuration`.`account_id`
                           WHERE `admin_configuration`.`account_id` != 0";
               $custom_accounts = Application_helper::getData($get_custom_accounts);
               if ($custom_accounts != null) {

                  $count = 0;
                  foreach($custom_accounts as $account) {

                        Yii::log(var_export($account, true), "warning", "pro/Custom Account:");

                        $account_id = $account['account_id'];
                        $account_name = $account['Name'];
                        $role = $account['Role'];
                        $payout_type = $account['payout_type'];

                        $custom[$count]['account_id'] = $account_id;
                        $custom[$count]['account_name'] = $account_name;
                        $custom[$count]['role'] = $role;
                        $custom[$count]['payout_type'] = $payout_type;

                        // get fee structure for specific account
                        $account_fee_sql ="SELECT `admin_configuration`.`setting_enum`,`admin_configuration`.`data`
                                 FROM `admin_configuration`
                                 WHERE `admin_configuration`.`account_id` = $account_id";
                        $account_fee = Application_helper::getData($account_fee_sql);

                        if ($account_fee != null) {
                           $custom[$count]['fee_structure'] = $account_fee;
                        } else {
                           $custom[$count]['fee_structure'] = [];
                        }  // end of account_fee end block
                        $count++;
                     } // end of foreach loop
                     if (sizeof($custom)>0) {
                        $response['custom_fee'] = $custom;
                     } else {
                        $response['custom_fee'] = [];
                     }
               }  // end of custom account if block
            }  // end of standard fee if block
         }else{
        		$response =[
        			'status'=>'Failure',
        			'message'=>'You are not authrised user'
        			];
         } // end of role if block
      }else{
        	$response =[
        		'status'=>'failure',
        		'message'=>'Please enter a user_id'
        		];
      }

      Yii::log(var_export($response, true), "warning", "pro/Instant_payout");
      $this->renderJSON($response);
   }

   
   
   public function actionUpdate_w9_request()
     {
        Yii::log(var_export($_POST, true), "warning", "pro/update_w9_request");

        $validation = Application_helper::check_validation($_POST, ['user_id', 'venue_id', 'status']);

        if ($validation['status'])
        {

            $userId = $_POST['user_id'];
            $status = $_POST['status'];

            // SQL for Promoters
            $check_user_request_sql = "SELECT * FROM `user_business_requests` WHERE `user_id` = $userId";

            // check if venue_id is set, if yes, then add venue_id logic
            if (isset($_POST['venue_id']))
            {
               $venueId = $_POST['venue_id'];
               $check_user_request_sql = $check_user_request_sql. " AND `venue_id` = $venueId";
            }

            $check_user_request = Application_helper::getData($check_user_request_sql);

               if (!empty($check_user_request))
               {

                    $updateSql = "UPDATE `user_business_requests` SET `request_status` = $status WHERE `user_id` = $userId AND `venue_id` = $venueId";
                    $updateBussinessRequest = Application_helper::updateData($updateSql);

                    $response = [
                        'status' => 'success',
                        'message' => 'user request updated successfully',
                    ];
                } else {
                    $response = [
                        'status' => 'failure',
                        'message' => 'Business Request not found.'
                    ];
                }
        } else {
            //error msg when filed is not set properly
            $response = [
                'status' => 'failure',
                'message' => $validation['message']
            ];
        }
        Yii::log(var_export($response, true), "warning", "pro/update_w9_request");
        $this->renderJSON($response);
    }
    
    public function actionConfirmationmailsend()
    {
      /**
      * Changes Implemented for Multi Ticket Buy and generating multiple barcodes. barcode id is created by joining $transaction_id & $sub_order_id with an underscore(_).
      * Implementations by Sandy
      */

      header('Access-Control-Allow-Origin: *');
         header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');

        if (array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
            header('Access-Control-Allow-Headers: '
                . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
        } else {
            header('Access-Control-Allow-Headers: origin, x-requested-with, content-type, cache-control');
        }

        Yii::log(var_export($_POST, true), "warning", "Event - Stripe Web Purchase IN");

        $validation = Application_helper::check_validation($_POST, [ 'user_id', 'event_id', 'order_id']);

        if ($validation['status']) {
           // $token = $_POST['token'];
            $user_id = $_POST['user_id'];
           // $amount = $_POST['amount'];
           // $payment_description = "Payment Through Web Purchase"; //$_POST['payment_description'];
            $order_id = $_POST['order_id'];
            $event_id = $_POST['event_id'];
          //  $platform_fee = $_POST['fee_amount'];

         //   $stripeUser = StripeUsers::model()->findByAttributes(['users_id' => $user_id]);
            $transaction = Transaction::model()->findByAttributes(['order_id' => $order_id]);
          // echo"Ram"; echo $transaction->order_id;die;
            $amount_with_fee = $transaction->amount;
            $amount = $amount_with_fee - $transaction->platform_fee;
            $platform_fee = $transaction->platform_fee;
            $transaction_id = $transaction->transaction_id;
            $stripe_fee = $transaction->stripe_fee;
            $balance_id = $transaction->balance_id;
            $customerObject = json_decode($stripeUser->customer_object_json);
            $customerId = $customerObject->id;
            $orderDataSql = "SELECT `order`.`id` FROM `order` where `order`.`master_order_id` = $order_id";
           $orderData = Application_helper::getData($orderDataSql);
           $emailTicketArrayData = [];
           $qrList = [];
           $total_tickets_booked = 0;
           if (!empty($orderData))
           {


              foreach ($orderData as $order)
              {
              	$emailTicketData = [];
                 $qr_data = [];
                 $venue_address = "";
                 $sub_order_id = $order['id'];


                // Yii::log(var_export($sub_order_id, true), "warning", "sub order :");
                // $updateSql = "UPDATE `order` SET `status` = '1' WHERE `order`.`id` = $sub_order_id";
                // Yii::log(var_export($updateSql, true), "warning", "update SQL :");
                // $updateData = Yii::app()->db->createCommand($updateSql)->execute();

                 // get order & event data, generate barcode & send email.
                // $get_order_data_sql = "SELECT `order`.`user_id`, `event_meta`.`event_date`, `order`.`event_id`, `order`.`event_meta_id`, `order`.`ticket_name`, `order`.`quantity`, `order`.`amount` FROM `order` LEFT JOIN `event_meta` ON `event_meta`.`id`= `order`.`event_meta_id` WHERE `order`.`id` = $sub_order_id";
                 $get_order_data_sql = "SELECT `order`.`user_id`, `event_detail`.`event_name`,`event_detail`.`venue_id`,`event_meta`.`event_date`,`event_detail`.`event_start_time`,`event_detail`.`event_end_time`, `order`.`event_id`,`order`.`event_meta_id`,`order`.`ticket_name`, `order`.`quantity`, `order`.`amount` FROM `order` LEFT JOIN `event_meta` ON `event_meta`.`id`= `order`.`event_meta_id` LEFT JOIN `event_detail` ON `event_detail`.`id` = `event_meta`.`event_id` WHERE `order`.`master_order_id` = $order_id";
                 $get_order_data = Yii::app()->db->createCommand($get_order_data_sql)->queryAll();

                 $user_id = $get_order_data[0]['user_id'];
                 $event_id = $get_order_data[0]['event_id'];
                 $venue_id = $get_order_data[0]['venue_id'];
                 $meta_id = $get_order_data[0]['event_meta_id'];
                 $event_name = $get_order_data[0]['event_name'];
                 $event_date = $get_order_data[0]['event_date'];
                 $event_start_time = $get_order_data[0]['event_start_time'];
                 $event_end_time = $get_order_data[0]['event_end_time'];
                 $ticket_name = $get_order_data[0]['ticket_name'];
                 $ticket_qty = $get_order_data[0]['quantity'];
                 $ticket_amount = $get_order_data[0]['amount'];

                 $time_zone = Application_helper::getTimeZoneForEvent($meta_id);

                 if ($venue_id != null)
                 {
                       $getVenueDetailSql = "Select `venue_detail`.`name`,`venue_detail`.`address`,`venue_detail`.`city` From `venue_detail` WHERE `venue_detail`.`id` = $venue_id";
                       $venueDetail = Application_helper::getData($getVenueDetailSql);
                       if (!empty($venueDetail))
                       {
                          $venue_address = $venueDetail[0]['address'];
                       }
                 }


                 $emailTicketData['ticket_type'] = $ticket_name;
                 $emailTicketData['ticket_qty'] = $ticket_qty;
                 $emailTicketData['amount'] = $get_order_data[0]['amount'];
                 $emailTicketData['venue_address'] = $venue_address;
                 $emailTicketData['ticket_amount'] = $ticket_amount;

                 $total_tickets_booked = $total_tickets_booked + $ticket_qty;


                 
             

                 // get ticket_booked from event_tickets & update booked tickets in event_tickets.
                 $get_booked_sql = "SELECT SUM(`quantity`) `total` FROM `order` WHERE `order`.`event_meta_id` = $meta_id AND `order`.`ticket_name` = '$ticket_name' AND `order`.`status` = 1";
                 $get_booked_data = Yii::app()->db->createCommand($get_booked_sql)->queryAll();

                 $booked_seats = $get_booked_data[0]['total'];
                 Yii::log(var_export($booked_seats, true), "warning", "Event - Booked Tickets");

                 // get event_tickets blocked_seats for the ticket.
                 $get_blocked_seats_for_ticket = "SELECT IFNULL(SUM(`quantity`),0) AS `blocked_seats` FROM `order` WHERE `status`= 0 and `event_meta_id`=$meta_id and `ticket_name`= '$ticket_name'";
                // $get_blocked_seats_for_ticket = "SELECT `blocked_seats` FROM `event_tickets` WHERE `event_meta_id` = $meta_id AND `ticket_name` = '$ticket_name'";
                 $blocked_ticketData = Yii::app()->db->createCommand($get_blocked_seats_for_ticket)->queryAll();
                 $blocked_tickets = $blocked_ticketData[0]['blocked_seats'];

                 $new_blocked_count = $blocked_tickets; 

                 if ($new_blocked_count < 0) {
                    $new_blocked_count = 0;
                 } 


                 $update_booked_sql = "UPDATE `event_tickets` SET `event_tickets`.`ticket_booked` = $booked_seats , `event_tickets`.`blocked_seats` = $new_blocked_count WHERE `event_tickets`.`ticket_name` = '$ticket_name' AND `event_tickets`.`event_meta_id` = $meta_id";
                 $updateBooked = Yii::app()->db->createCommand($update_booked_sql)->execute(); 

                 // getting booked ticket detail
                 $get_ticket_detail_sql = "SELECT `event_tickets`.`ticket_count` as `total_tickets`, `event_tickets`.`ticket_booked` as `booked_tickets`,`event_tickets`.`id` as `ticket_id` FROM `event_tickets` WHERE `event_tickets`.`ticket_name` = '$ticket_name' AND `event_tickets`.`event_meta_id` = $meta_id";
                 $ticket_detail_data = Yii::app()->db->createCommand($get_ticket_detail_sql)->queryAll();
                 if (!empty($ticket_detail_data))
                 {
                    $tick_id = $ticket_detail_data[0]['ticket_id'];
                    $total_ticks = $ticket_detail_data[0]['total_tickets'];
                    $booked_ticks = $ticket_detail_data[0]['booked_tickets'];

                    Yii::log(var_export($tick_id.':'.$total_ticks.'-'.$booked_ticks, true), "warning", "Event - Booked Ticket Detail");

                    // checking if all tickets are sold
                    if ($total_ticks <= $booked_ticks) {
                       $current_date_time = date('Y-m-d H:i:s');

                       //check if any ticket is linked with this ticket.
                       $get_next_ticket_sql = "SELECT `event_tickets`.`id` as `next_tick_id` FROM `event_tickets` WHERE `event_tickets`.`sold_out_id`=$tick_id";
                       $get_next_ticket = Yii::app()->db->createCommand($get_next_ticket_sql)->queryAll();
                       // check if next ticket id has been defined.
                       if (!empty($get_next_ticket)) {
                          // activate next ticket immediately.
                          $next_tick = $get_next_ticket[0]['next_tick_id'];
                          $activate_next_tick_sql = "UPDATE `event_tickets` SET `event_tickets`.`ticket_start_date_time` = '$current_date_time' WHERE `event_tickets`.`id` = $next_tick AND `event_tickets`.`event_meta_id` = $meta_id";
                          $activate_next_tick = Yii::app()->db->createCommand($activate_next_tick_sql)->execute();
                       }
                    }

                    //*********** check if combo ticket is linked to this ticket **************************
                    $combo_event_id = 0;
                    $get_combo_ticket_sql = "SELECT `event_tickets`.`combo_event_id` as `combo_id` FROM `event_tickets` WHERE `event_tickets`.`id`=$tick_id";
                    $get_combo_ticket = Yii::app()->db->createCommand($get_combo_ticket_sql)->queryAll();
                     Yii::log(var_export($get_combo_ticket, true), "warning", "Event - Combo Ticket:");
                    if (!empty($get_combo_ticket)) {
                       $combo_event_id = $get_combo_ticket[0]['combo_id'];
                        Yii::log(var_export($combo_event_id, true), "warning", "Event - Combo Event:");
                    }
                    //********************************
                 }

                 //generate QR Code
                 $dataID = $transaction_id."_".$sub_order_id;
                 $data_id = $transaction_id."_".$sub_order_id.".png";
                 $qr = json_encode($qr_data);


                 $this->widget('application.extensions.qrcode.QRCodeGenerator', array(
                    'data' => $qr,                //$qr,dataID,
                    'filename' => $data_id,
                    'subfolderVar' => false,
                    'matrixPointSize' => 5,
                    'displayImage' => false, // default to true, if set to false display a URL path
                    'errorCorrectionLevel' => 'M', // available parameter is L,M,Q,H
                  'matrixPointSize' => 5, // 1 to 10 only

                 ));

                 //$qr_code_url = Yii::app()->getBaseUrl(true) . "/uploads/" . $data_id;
                // Yii::log(var_export($qr_code_url, true), "warning", "QR Code URL");

                $emailTicketData['qr_code_url'] = Yii::app()->getBaseUrl(true) . "/uploads/" . $data_id;
                $emailTicketData['transaction_fee'] = $platform_fee;

               //  $qr_data['qr_code_url'] = Yii::app()->getBaseUrl(true) . "/uploads/" . $data_id;
                 //$response['combo_ticket_id'] = $combo_event_id;
                // $response['ticket_qty'] = $ticket_qty;

                array_push($emailTicketArrayData	,$emailTicketData);
               //--  array_push($qrList,$qr_data);
           }
           }
            	Email_helper::send_transaction_confirmation_mail_multi_ticket($user_id, $event_id, $event_date, $total_tickets_booked,$order_id,$transaction_id,$emailTicketArrayData);
                $response['status'] = "Success";   
              
           
        } else {
            $response['status'] = "Failure";
            $response['message'] = $validation['message'];
        }

        Yii::log(var_export($response, true), "warning", "Event - Web Purchase OUT");
        $this->renderJSON($response);
    }
    
    
    
    
}
