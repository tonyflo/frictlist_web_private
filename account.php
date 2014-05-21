<?php
/* @file account.php
 * @date 2014-03-22
 * @author Tony Florida
 * @brief Functions that allow a user to interact with their data
 */

include 'apns.php';
 
$SUCCESS = 0;

/*************************************************************************
 * Private Helper Functions
 ************************************************************************/

function getStatusAsString($status)
{
   if($status == 1)
   {
      return "accepted";
   }
   else
   {
      return "rejected";
   }
}

/*
 * @brief Converts a string into a hash
 * @source http://alias.io/2010/01/store-passwords-safely-with-php-and-mysql/
 * @param password a password between 6 and 255 characters
 * @retval the hashed value of the password
 */
function pw_hash($password)
{
    // A higher "cost" is more secure but consumes more processing power
    $cost = 10;

    // Create a random salt
    $salt = strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), '+', '.');

    // Prefix information about the hash so PHP knows how to verify it later.
    // "$2a$" Means we're using the Blowfish algorithm. The following two digits are the cost parameter.
    $salt = sprintf("$2a$%02d$", $cost) . $salt;

    // Hash the password with the salt
    $hashed = crypt($password, $salt);
    
    return $hashed;
} //end pw_hash()

/*************************************************************************
 * Private MySQL Helper Functions
 ************************************************************************/

/*
 * @brief get device token given uid
 * @param uid the user's unique id
 * @retval the user's device token
 */
function getDeviceToken($uid, $db)
{
   $query="SELECT token from user where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($token);
   $sql->fetch();
   $sql->free_result();
   return $token;
}

/*
 * @brief get date of frict given frict id
 * @param frict_id the frict's unique id
 * @retval the user's device token
 */
function getFrictDate($frict_id, $db)
{
   $query="SELECT frict_from_date FROM frict WHERE frict_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $frict_id);
   $sql->execute();
   $sql->bind_result($date);
   $sql->fetch();
   $sql->free_result();
   return $date;
}

/*
 * @brief get first and last name given user id
 * @param uid the user's unique id
 * @retval the user's first and last name as an array
 */
function getFirstLastNameGivenUid($uid, $db)
{
   $query="SELECT first_name, last_name FROM user WHERE uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($first, $last);
   $sql->fetch();
   $sql->free_result();
   return array($first, $last);
}

/*
 * @brief get first and last name of mate given mate id
 * @param mid the mate's unique id
 * @retval the mate's first and last name as an array
 */
function getFirstLastNameOfMateGivenMid($mid, $db)
{
   $query="SELECT mate_first_name, mate_last_name FROM mate WHERE mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $mid);
   $sql->execute();
   $sql->bind_result($first, $last);
   $sql->fetch();
   $sql->free_result();
   return array($first, $last);
}

/*
 * @brief get first and last name of frictlist creator given mate id
 * @param mid the mate's unique id
 * @retval the creator of the frictlist's first and last name as an array
 */
function getFirstLastNameOfCreatorGivenMid($mid, $db)
{
   $query="SELECT first_name, last_name FROM user LEFT JOIN mate ON mate.uid=user.uid WHERE mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $mid);
   $sql->execute();
   $sql->bind_result($first, $last);
   $sql->fetch();
   $sql->free_result();
   return array($first, $last);
}

/*
 * @brief get user's device token given a request id for sending a request
 * @param rid the unique id associated with the request
 * @retval the user's device token who made the requst and will receive
 * the push notification
 */
function getDeviceTokenFromRequestIdForSendingRequest($rid, $db)
{
   $query="SELECT token from request left join user on user.uid=request.uid where request_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $rid);
   $sql->execute();
   $sql->bind_result($token);
   $sql->fetch();
   $sql->free_result();
   return $token;
}

/*
 * @brief get user's device token given a request id for responding to a request
 * @param rid the unique id associated with the request
 * @retval the user's device token who made the requst and will receive
 * the push notification
 */
function getDeviceTokenFromRequestIdForRespondingRequest($rid, $db)
{
   $query="SELECT token from request left join mate on mate.mate_id=request.mate_id left join user on mate.uid=user.uid where request_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $rid);
   $sql->execute();
   $sql->bind_result($token);
   $sql->fetch();
   $sql->free_result();
   return $token;
}

/*
 * @brief get mate's device token given a mate id
 * @param mid the unique id associated with the mate
 * @retval the mate's device token
 */
function getDeviceTokenOfMateGivenMid($mid, $db)
{
   $query="SELECT token FROM user LEFT JOIN request ON request.uid=user.uid WHERE mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $mid);
   $sql->execute();
   $sql->bind_result($token);
   $sql->fetch();
   $sql->free_result();
   return $token;
}

/*
 * @brief get device token of frictlist creator given mate id
 * @param mid the mate's unique id
 * @retval the creator of the frictlist's device token
 */
function getTokenOfCreatorGivenMid($mid, $db)
{
   $query="SELECT token FROM user LEFT JOIN mate ON mate.uid=user.uid WHERE mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $mid);
   $sql->execute();
   $sql->bind_result($token);
   $sql->fetch();
   $sql->free_result();
   return $token;
}

/*
 * @brief Validates an id by checking if it exists in the table and is unique
 * @param table The name of the table to g
 * @param id_str The string representation of the column name
 * @param id The id to check if it exists and is unique
 * @retval -100 if the id passed in was null or not positive
 * @retval -101 if the provided id doesn't exist or is not unique
 * @retval SUCCESS if not failure
 *
 */
function validateId($table, $id_str, $id, $db)
{
   if($id == null || $id < 0)
   {
      return -100;
   }

   //check that the uid exists in the db
   $query="select * from ".$table." where ".$id_str."=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $id);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->num_rows;
   $sql->free_result();
   
   if($numrows != 1)
   {
      return -101;
   }
   
   return $SUCCESS;
}

/*
 * @brief Get user metadata
 * @param uid the user id of the user
 * @param db the database object
 * @retval on success, a table of the user's data in which columns are separated by tabs and rows by new lines
 */
function get_user_data($uid, $db)
{
   //query database for user data
   $query="SELECT first_name, last_name, birthdate, gender from user where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($first, $last, $bday, $gender);
   while($sql->fetch())
   {
      echo $first."\t".$last."\t".$bday."\t".$gender."\n";
   }
   $sql->free_result();
}

/*
 * @brief update device token for APNS
 * @param uid user id of the user of the app
 * @param token the device token
 * @param db the database object
 * @retval NONE
 */
function update_apns_token($uid, $token, $db)
{
   if($uid > 0 && isset($token) && $token != "" && $token != "(null)")
   {
      $query="update user set token=? where uid=?";
      $sql=$db->prepare($query);
      $sql->bind_param('si', $token, $uid);
      $sql->execute();
      $sql->store_result();
      $numrows=$sql->affected_rows;
      $sql->free_result();
   }
}

/*
 * @brief determine if the frictlist is shared
 * @param mate_id the mate_id in question
 * @retval true if the frictlist is shared, false otherwise
 */
function isShared($mate_id, $db)
{
   $query="SELECT accepted FROM mate WHERE mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $mate_id);
   $sql->execute();
   $sql->bind_result($accepted);
   $sql->fetch();
   $sql->free_result();
   
   if($accepted > 0)
   {
      return true;
   }
   else
   {
      return false;   
   }
}
 
/*************************************************************************
 * Public MySQL Functions
 ************************************************************************/

/*
 * @brief Allows a user to sign into their account
 * @param username a username
 * @param password a password between 6 and 255 characters
 * @param token the user's device token for apns
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with a valid email and password
 * @retval -1 if the username was not found in the database
 * @retval -2 if the password is wrong
 */
function sign_in($username, $password, $token, $db, $table)
{
   //query database for provided email
   $query="select uid, password from ".$table." where username=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $username);
   $sql->execute();
   $sql->bind_result($uid, $hash);
   $sql->fetch();
   $sql->free_result();
   
   //try to convert uid to an int; if fails, value will be 0; assume credentials are wrong
   $uid=intval($uid);

   //check valid uid
   if($uid > 0)
   {
      //compare provided password to hash
      $valid = strcmp(crypt($password, $hash), $hash);

      if ($valid != 0) 
      {
        //the provided password was wrong
        return -2;
      }
      
      //register the device for push notifications
      update_apns_token($uid, $token, $db);
      
      //return the valid uid
      return $uid;
   }
   else
   {
      //return -1 because the username wasn't found
      return -1;
   }
} //end sign_in()

/*
 * @brief Send all (not deleted) frict data to user
 * @param uid the user id of the user
 * @param db the database object
 * @retval on success, a table of the user's fricts in which columns are separated by tabs and rows by new lines
 */
function get_frictlist($uid, $db)
{
   //validate ids
   $rc = validateId("user", "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   //echo frictlist flag
   echo "frictlist\n";
   
   //echo user data array
   get_user_data($uid, $db);
   
   //generate frictlist table
   $query="select A.mate_id, A.accepted, A.uid as mates_uid, B.mate_first_name, B.mate_last_name, B.mate_gender, B.frict_id, B.frict_from_date, B.frict_rating, B.frict_base, B.notes, B.deleted, B.mate_rating, B.mate_notes, B.mate_deleted, B.creator, B.lat, B.lon from (select m.mate_id, m.accepted, r.uid from mate m left outer join request r on m.mate_id = r.mate_id where m.uid=? AND (m.deleted=0 OR m.deleted IS NULL) ORDER BY mate_id ASC) as A left join (SELECT mate.mate_id, mate_first_name, mate_last_name, mate_gender, frict_id, frict_from_date, frict_rating, frict_base, notes, frict.deleted, mate_rating, mate_notes, mate_deleted, creator, lat, lon FROM mate LEFT JOIN frict ON mate.mate_id=frict.mate_id WHERE uid=? AND (mate.deleted=0 OR mate.deleted IS NULL) ORDER BY mate_id ASC) as B on A.mate_id=B.mate_id ORDER BY mate_first_name ASC";
   $sql=$db->prepare($query);
   $sql->bind_param('ii', $uid, $uid);
   $sql->execute();
   $sql->bind_result($mate_id, $accepted, $mates_uid, $mate_first_name, $mate_last_name, $mate_gender, $frict_id, $frict_from_date, $frict_rating, $frict_base, $notes, $deleted, $mate_rating, $mate_notes, $mate_deleted, $creator, $lat, $lon);
   while($sql->fetch())
   {
      //echo frictlist row
      echo $mate_id."\t".$accepted."\t".$mates_uid."\t".$mate_first_name."\t".$mate_last_name."\t".$mate_gender."\t".$frict_id."\t".$frict_from_date."\t".$frict_rating."\t".$frict_base."\t".$notes."\t".$deleted."\t".$mate_rating."\t".$mate_notes."\t".$mate_deleted."\t".$creator."\t".$lat."\t".$lon."\n";
   }
   $sql->free_result();
}

/*
 * @brief Get a table of requests made to the user (accepted, pending, and rejected; but not deleted)
 * @param uid the user id of the user
 * @param db the database object
 * @retval on success, a table of the user's fricts in which columns are separated by tabs and rows by new lines
 */
function get_notifications($uid, $db)
{
   //validate ids
   $rc = validateId("user", "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   //echo notifications flag
   echo "notifications\n";
   
   //generate notifications table
   $query="SELECT A.request_id, A.mate_id, A.request_status as status, A.first_name, A.last_name, A.username, A.gender as sex, A.birthdate, B.frict_id, B.frict_from_date, B.frict_rating as f_rate, B.frict_base, B.notes, B.deleted as del, B.mate_rating, B.mate_notes, B.mate_deleted, B.creator, B.lat, B.lon, A.deleted as fl_creator_deleted_mate FROM (select r.request_id, m.mate_id, m.last_update, r.request_status, s.first_name, s.last_name, s.username, s.gender, s.birthdate, r.accept_datetime, m.deleted from request r join mate m on r.mate_id = m.mate_id join user s on s.uid = m.uid where r.uid=? AND (accepted != -2) AND (deleted = 0 OR (deleted = 1 AND r.accept_datetime < m.last_update)) ORDER BY s.first_name ASC) AS A LEFT JOIN (SELECT mate.mate_id, mate_first_name, mate_last_name, mate_gender, frict_id, frict_from_date, frict_rating, frict_base, notes, frict.deleted, frict.last_update, mate_rating, mate_notes, mate_deleted, mate_last_update, creation_datetime, delete_datetime, creator, lat, lon FROM mate LEFT JOIN frict ON mate.mate_id=frict.mate_id ORDER BY mate_id ASC) AS B ON A.mate_id=B.mate_id WHERE (B.delete_datetime > A.accept_datetime) OR (B.deleted = 0 OR B.deleted IS NULL) OR (B.creation_datetime > A.accept_datetime) ORDER BY mate_first_name ASC";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($request_id, $mate_id, $request_status, $first_name, $last_name, $username, $gender, $birthdate, $frict_id, $frict_from_date, $frict_rating, $frict_base, $notes, $deleted, $mate_rating, $mate_notes, $mate_deleted, $creator, $lat, $lon, $fl_creator_deleted_mate);
   
   while($sql->fetch())
   {
      //echo notifications row
      echo $request_id."\t".$mate_id."\t".$request_status."\t".$first_name."\t".$last_name."\t".$username."\t".$gender."\t".$birthdate."\t".$frict_id."\t".$frict_from_date."\t".$frict_rating."\t".$frict_base."\t".$notes."\t".$deleted."\t".$mate_rating."\t".$mate_notes."\t".$mate_deleted."\t".$creator."\t".$lat."\t".$lon."\t".$fl_creator_deleted_mate."\n";
   }
   $sql->free_result();
}

/*
 * @brief Allows a user to sign up for an account
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param email a valid, unique email address 
 * @param email a unique username
 * @param password a password between 6 and 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @param birthdate the birthdate of the user
 * @param platform 1 for iOS, 2 for Android, etc
 * @param token the user's device token for apns
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with the new account
 * @retval -4 if the email address is in use
 * @retval -5 if the username is in use
 * @retval -7 if signing up fails
 * @retval -10 if the username or email is null
 */
function sign_up($firstname, $lastname, $username, $email, $password, $gender, $birthdate, $platform, $token, $db, $table)
{
   if($email == null || $username == null || $password == null)
   {
      return -10;
   }
   
   //check that the email doesn't exist in the db
   $queryA="select * from ".$table." where email=?";
   $sqlA=$db->prepare($queryA);
   $sqlA->bind_param('s', $email);
   $sqlA->execute();
   $sqlA->store_result();
   $numrowsA=$sqlA->num_rows;
   $sqlA->free_result();
   
   //check that the username doesn't exist in the db
   $queryB="select * from ".$table." where username=?";
   $sqlB=$db->prepare($queryB);
   $sqlB->bind_param('s', $username);
   $sqlB->execute();
   $sqlB->store_result();
   $numrowsB=$sqlB->num_rows;
   $sqlB->free_result();
 
   //the email address is available if the query returns 0 matching rows
   if($numrowsA != 0)
   {
      //the email address is taken so return error code
      return -4;
   }
   
   if($numrowsB != 0)
   {
      //the username is taken so return error code
      return -5;
   }
   
   //hash the password
   $hash=pw_hash($password);

   //the email address is available so proceed with creating account
   $query2="insert into ".$table."(email, username, password, first_name, last_name, birthdate, gender, creation_datetime, platform, token) values(?, ?, ?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."', ?, ?)";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('ssssssiis', $email, $username, $hash, $firstname, $lastname, $birthdate, $gender, $platform, $token);
   $sql2->execute();
   $sql2->free_result();
   
   //check result is TRUE meaning the insert was successful
   if($sql2 == TRUE)
   {
      //sign in as normal to get the uid
      return sign_in($username, $password, $token, $db, $table);
   }
   else
   {
      //something went wrong when signing up
      return -7;
   }
} //end sign_up()

/*
 * @brief Allows a user to add a frict to their list
 * @param mate_id the mate
 * @parma base the base of the frict
 * @param from the first occurrence of the frict
 * @param rating the rating of the frict
 * @param notes notes about the frict
 * @param creator creator of the frictlist (1 if user is creator of fl)
 * @param lat latitude
 * @param lon longitude
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @param creator 1 if the creator is the user who owns the frictlist, 0 if the creator is the user who the frictlist is shared with
 * @retval -80 insert was unsuccessful
 * @retval -81 creator parameter was not 0 or 1
 * @retval frict_id on success
 */
function add_frict($mate_id, $base, $from, $rating, $notes, $creator, $lat, $lon, $db, $table_user, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   $datetime = date("Y-m-d H:i:s");
   
   //declare variables needed for push notification
   $name="Your mate";
   $token="";
   
   //insert into frict table
   $query="";
   if($creator == 1)
   {
      $query="insert into ".$table_frict."(mate_id, frict_from_date, frict_rating, frict_base, notes, creation_datetime, last_update, creator, lat, lon) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $name=getFirstLastNameOfCreatorGivenMid($mate_id, $db);
      $token=getDeviceTokenOfMateGivenMid($mate_id, $db);
   }
   else if($creator == 0)
   {
      $query="insert into ".$table_frict."(mate_id, frict_from_date, mate_rating, frict_base, mate_notes, creation_datetime, mate_last_update, creator, lat, lon) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $name=getFirstLastNameOfMateGivenMid($mate_id, $db);
      $token=getTokenOfCreatorGivenMid($mate_id, $db);
   }
   else
   {
      return -81;
   }
   
   $sql=$db->prepare($query);
   $sql->bind_param('isiisssidd', $mate_id, $from, $rating, $base, $notes, $datetime, $datetime, $creator, $lat, $lon);
   $sql->execute();
   //get id generated from the auto increment by the previous query
   $frict_id = $sql->insert_id;
   $sql->free_result();
   
   //check that the insert was successful
   if($sql != TRUE || $frict_id <= 0)
   {
      return -80;
   }
   
   //check if frictlist is shared
   if(isShared($mate_id, $db))
   {
      //build the message
      $message=$name[0]." ".$name[1]." added a frict to your frictlist";
   
      if(isset($token) && $token != "" && $token != "(null)")
      {
         //send the push notification
         apns_send($token, $message);
      } 
   }
   
   return $frict_id;
}

/*
 * @brief Allows a user to add a mate
 * @param uid the user id of the user
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the mate table
 * @retval -60 if the insert was unsuccessful
 * @retval the mate_id of the mate if success
 */
function add_mate($uid, $firstname, $lastname, $gender, $db, $table_user, $table_mate)
{
   //validate ids
   $rc = validateId($table_user, "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
 
   //insert into mate table
   $query="insert into ".$table_mate."(uid, mate_first_name, mate_last_name, mate_gender) values(?, ?, ?, ?)";
   $sql=$db->prepare($query);
   $sql->bind_param('issi', $uid, $firstname, $lastname, $gender);
   $sql->execute();
   //get id generated from the auto increment by the previous query
   $mate_id = $sql->insert_id;
   $sql->free_result();

   //check that the insert was successful
   if($sql != TRUE || $mate_id <= 0)
   {
      return -60;
   }

   return $mate_id;
}

/*
 * @brief Allows a user to update a frict in their list
 * @param frict_id the id of the frict
 * @param mate_id the id of the mate
 * @parma base the base of the frict
 * @param from the first occurrence of the frict
 * @param rating the rating of the frict
 * @param notes notes about the frict
 * @param creator 1 if the user created the frictlist, 0 otherwise
 * @param lat latitude
 * @param lon longitude
 * @param db the database object
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -90 if the update was unsuccessful
 * @retval -91 if the creator flag was not 0 or 1
 * @retval frict_id if the update of the hookup and frict table was successful
 */
function update_frict($frict_id, $mate_id, $base, $from, $rating, $notes, $creator, $lat, $lon, $db, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_frict, "frict_id", $frict_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //update the frict table
   $query="";
   if($creator == 1)
   {
      $query="update ".$table_frict." set frict_from_date=?, frict_rating=?, frict_base=?, notes=?, lat=?, lon=?, last_update=? where frict_id='".$frict_id."'";
      $name=getFirstLastNameOfCreatorGivenMid($mate_id, $db);
      $token=getTokenOfCreatorGivenMid($mate_id, $db);
   }
   else if($creator == 0)
   {
      $query="update ".$table_frict." set frict_from_date=?, mate_rating=?, frict_base=?, mate_notes=?, lat=?, lon=?, mate_last_update=? where frict_id='".$frict_id."'";
      $name=getFirstLastNameOfMateGivenMid($mate_id, $db);
      $token=getDeviceTokenOfMateGivenMid($mate_id, $db);
   }
   else
   {
      return -91;
   }
   
   $sql=$db->prepare($query);
   $sql->bind_param('siisdds', $from, $rating, $base, $notes, $lat, $lon, $datetime);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
   //check if update was successful
   if($numrows != 1)
   {
      //something went wrong when updating hookup table
      return -90;
   }
   
   //check if frictlist is shared
   if(isShared($mate_id, $db))
   {
      //format date of frict
      $formatted_date=date('F j, Y', strtotime(getFrictDate($frict_id, $db)));
      
      //build the message
      $message=$name[0]." ".$name[1]." updated your ".$formatted_date." frict";
   
      if(isset($token) && $token != "" && $token != "(null)")
      {
         //send the push notification
         apns_send($token, $message);
      } 
   }
   
   return $frict_id;
}

/*
 * @brief Allows a user to update a mate
 * @param uid the user id of the user 
  * @param mate_id the mate id of the mate
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the mate table
 * @retval -70 if the update was unsuccessful
 * @retval the id of the mate if success
 */
function update_mate($uid, $mate_id, $firstname, $lastname, $gender, $db, $table_user, $table_mate)
{
   //validate ids
   $rc = validateId($table_user, "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //update hookup table
   $query="update ".$table_mate." set mate_first_name=?, mate_last_name=?, mate_gender=?, last_update=? where mate_id='".$mate_id."'";
   $sql=$db->prepare($query);
   $sql->bind_param('ssis', $firstname, $lastname, $gender, $datetime);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
   //check if update was successful
   if($numrows != 1)
   {
      //something went wrong when updating hookup table
      return -70;
   } 
   
   return $mate_id;
}

/*
 * @brief Allows a user to remove a frict from their list
 * @param frict_id the id of the frict
 * @param db the database object
 * @param table_frict the table name of the frict table
 * @param creator the creator of the frict is 1, 0 otherwise
 * @retval -50 if the deletion was unsuccessful
 * @retval -51 if the creator flag is not 0 or 1
 * @retval frict_id if the deletion was successful
 */
function remove_frict($frict_id, $creator, $db, $table_frict)
{
   //validate ids
   $rc = validateId($table_frict, "frict_id", $frict_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");

   //"remove" frict by updating frict table and setting deleted to true
   $query="";
   if($creator == 1)
   {
      $query="update ".$table_frict." set deleted=1, delete_datetime=? where frict_id='".$frict_id."'";
   }
   else if($creator == 0)
   {
      $query="update ".$table_frict." set mate_deleted=1, mate_last_update=? where frict_id='".$frict_id."'";
   }
   else
   {
      return -51;
   }
   
   $sql=$db->prepare($query);
   $sql->bind_param('s', $datetime);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows != 1)
   {
      //something went wrong when updating frict table
      return -50;
   }
   
   return $frict_id;
}

/*
 * @brief Allows a user to remove a mate from their list
 * @param mate_id the id of the mate
 * @param db the database object
 * @param table_mate the table name of the mate table
 * @param table_frict the table name of the frict table
 * @param creator the creator of the frict is 1, 0 otherwise
 * @retval -40 if the deletion was unsuccessful
 * @retval -41 if the creator variable wan't 1 or 0
 * @retval mate_id if the deletion was successful
 */
function remove_mate($mate_id, $creator, $db, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //"remove" all fricts associated with the mate by updating frict table and setting deleted to true
   $query="";
   if($creator == 1)
   {
      $query="update ".$table_frict." set deleted=1, delete_datetime=? WHERE mate_id='".$mate_id."' AND (deleted IS NULL OR deleted=0)";
   }
   else if($creator == 0)
   {
      $query="update ".$table_frict." set mate_deleted=1, mate_last_update=? WHERE mate_id='".$mate_id."' AND (mate_deleted IS NULL OR mate_deleted=0)";
   }
   else
   {
      return -41;
   }
   
   $sql=$db->prepare($query);
   $sql->bind_param('s', $datetime);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();

   $numrows=0;
   //remove mate
   if($creator == 1)
   {
      //"remove" mate by updating mate table and setting deleted to true
      $query="update ".$table_mate." set deleted=1, last_update=? where mate_id='".$mate_id."'";
      $sql=$db->prepare($query);
      $sql->bind_param('s', $datetime);
      $sql->execute();
      $sql->store_result();
      $numrows=$sql->affected_rows;
      $sql->free_result();
   }
   else if($creator == 0)
   {
      //A mate who has accepted a request and is now removing the user who sent the initial request. This mate cannot perform this action like above. The Accepted list, which is where this delete originated from, is generated based off of the notification table.  We will set the status of the accepted column in the mate table to -2 which will represent a deletion.  The -2 will be used by the MySQL server to not return this mate to the recipient user.
      $remove_an_accepted_request = -2;
      $query="update mate set accepted=? where mate_id=?";
      $sql=$db->prepare($query);
      $sql->bind_param('ii', $remove_an_accepted_request, $mate_id);
      $sql->execute();
      $sql->store_result();
      $numrows=$sql->affected_rows;
      $sql->free_result();
   }
   else
   {
      return -41;
   }
   
   //check result is TRUE meaning the update was successful
   if($numrows != 1)
   {
      //something went wrong when updating mate table
      return -40;
   }
   return $mate_id;
}

/*
 * @brief Search for another user by name and gender
 * @param firstname the first name of the user to search for
 * @param lastname the last name of the user to search for
 * @param gender the gender of the user to search for
 * @param db the database object
 * @retval if success, a table of uid, username, birthdays, request id of matching users.
 *         if request hasn't been made, this field will be NULL
 */
function search_mate($uid, $firstname, $lastname, $gender, $db)
{
   //query database for user data
   $query="SELECT b.uid, b.username, b.birthdate, if(a.request_id IS NULL, (select r.request_id  from mate m left join request r on m.mate_id = r.mate_id left join user u on m.uid = u.uid where r.uid=? AND u.username=b.username), a.request_id) as request_id from user b left join (select r.uid, r.request_id  from mate m left join request r on m.mate_id = r.mate_id where m.uid = ?) as a on a.uid = b.uid where first_name=? AND last_name=? AND gender=? AND b.uid != ? ORDER BY username ASC;";
   $sql=$db->prepare($query);
   $sql->bind_param('iissii', $uid, $uid, $firstname, $lastname, $gender, $uid);
   $sql->execute();
   $sql->bind_result($uid, $username, $bday, $requeset_id);
   echo "user_search\n";
   while($sql->fetch())
   {
      echo $uid."\t".$username."\t".$bday."\t".$requeset_id."\n";
   }
   $sql->free_result();
}

/*
 * @brief Send a request to another user
 * @param uid user id of the user of the app
 * @param users_mate_id mate_id from the personal matelist of the user of the app
 * @param mates_uid user id to send the request to
 * @param db the database object
 * @retval -110 if the insert was unsuccessful
 * @retval request_id if successful
 */
function send_mate_request($uid, $users_mate_id, $mates_uid, $db)
{
   //validate ids
   $rc = validateId("user", "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   $rc = validateId("user", "uid", $mates_uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   $rc = validateId("mate", "mate_id", $users_mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   $datetime = date("Y-m-d H:i:s");
   
   //insert into request table
   $query="insert into request(mate_id, uid, request_datetime, request_status) values(?, ?, ?, 0)";
   $sql=$db->prepare($query);
   $sql->bind_param('iis', $users_mate_id, $mates_uid, $datetime);
   $sql->execute();
   //get id generated from the auto increment by the previous query
   $request_id = $sql->insert_id;
   $sql->free_result();
   
   //check that the insert was successful
   if($sql != TRUE || $request_id <= 0)
   {
      return -110;
   }
   
   //apns
   //get first and last name of the user
   $name=getFirstLastNameGivenUid($uid, $db);

   //get the device token of the recipient of the push notification
   $token=getDeviceTokenFromRequestIdForSendingRequest($request_id, $db);

   //build the message
   $message=$name[0]." ".$name[1]." sent you a request";
   
   if(isset($token) && $token != "" && $token != "(null)")
   {
      //send the push notification
      apns_send($token, $message);
   } 
   
   return $request_id;
}

/*
 * @brief Respond to a request to another user
 * @param uid user id of the user of the app
 * @param request_id the id of the request
 * @param mate_id the id of the personal mate of the user who sent the request
 * @param status Accept (1) or Reject (-1)
 * @param db the database object
 * @retval -120 if the update was unsuccessful
 * @retval -122 if the value of the status was invalid
 * @retval status if successful
 */
function respond_mate_request($uid, $request_id, $mate_id, $status, $db)
{
   //validate ids
   $rc = validateId("user", "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   $rc = validateId("mate", "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   $accept = 1;
   $reject = -1;
   $status_as_int = intval($status);
   
   //ensure status is valid
   if($status_as_int != $accept && $status_as_int != $reject)
   {
      return -121;
   }
   
   $datetime = date("Y-m-d H:i:s");
   
   //todo: look into transactions when updating multiple tables
   //update mate table with status
   $query="update mate set accepted=? where mate_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('ii', $status_as_int, $mate_id);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
   //update request table with status
   $query="update request set request_status=?, accept_datetime=? where request_id=?";
   $sql=$db->prepare($query);
   $sql->bind_param('isi', $status_as_int, $datetime, $request_id);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows != 1)
   {
      //something went wrong when updating mate table
      return -120;
   }

   //apns
   //get first and last name of the user
   $name=getFirstLastNameGivenUid($uid, $db);

   //get the device token of the recipient of the push notification
   $token=getDeviceTokenFromRequestIdForRespondingRequest($request_id, $db);

   //build the message
   $message=$name[0]." ".$name[1]." ".getStatusAsString($status_as_int)." your request";
   
   if(isset($token) && $token != "" && $token != "(null)")
   {
      //send the push notification
      apns_send($token, $message);
   } 

   return $status_as_int;
}

/*
 * @brief Insert row into share table when user attempts to share app via sms or email 
 * @param uid the user id of the user
 * @param share_type 0 sms, 1 email
 * @param share_status 0 sent, 1 cancelled, 2 failed, 3 saved
 * @param mate_id the unique id of the mate who the user is trying to share the app with
 * @param db the database object
 * @return This function does not actually return. The application doesn't care if the insert was successful.
 * @retval -130 if the insert was unsuccessful
 * @retval -131 if share_type is not 0 or 1
 * @retval -132 if share_status is not 0, 1, 2, or 3
 * @retval the mate_id of the mate if success
 */
function add_share($uid, $share_type, $share_status, $mate_id, $db)
{
   //validate ids
   $rc = validateId("user", "uid", $uid, $db);
   if($rc != $SUCCESS)
   {
      //return $rc;
	  break;
   }
   
   //validate inputs
   if(0 > $share_type || 1 < $share_type)
   {
      //return -131;
	  break;
   }
   if(0 > $share_status || 3 < $share_status)
   {
      //return -132;
	  break;
   }

   $datetime = date("Y-m-d H:i:s");
 
   //insert into share table
   $query="INSERT INTO share(uid, share_type, share_status, mate_id, share_datetime) VALUES(?, ?, ?, ?, '".$datetime."')";
   $sql=$db->prepare($query);
   $sql->bind_param('iiii', $uid, $share_type, $share_status, $mate_id);
   $sql->execute();
   //get id generated from the auto increment by the previous query
   $share_id = $sql->insert_id;
   $sql->free_result();

   //check that the insert was successful
   if($sql != TRUE || $share_id <= 0)
   {
      //return -130;
	  break;
   }

   //return $share_id;
}
 
?>
