<?php
/* @file account.php
 * @date 2014-03-22
 * @author Tony Florida
 * @brief Functions that allow a user to interact with their data
 */
 
 $SUCCESS = 0;
 
/*
 * @brief Validates an id by checking if it exists in the table and is unique
 * @param table The name of the table to query
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
 * @brief Allows a user to sign into their account
 * @param username a username
 * @param password a password between 6 and 255 characters
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with a valid email and password
 * @retval -1 if the username was not found in the database
 * @retval -2 if the password is wrong
 */
function sign_in($username, $password, $db, $table)
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
   
   //genderate frictlist table
   $query="SELECT mate.mate_id, mate_first_name, mate_last_name, mate_gender, frict_id, frict_from_date, frict_rating, frict_base, notes FROM mate LEFT JOIN frict ON mate.mate_id=frict.mate_id WHERE uid=? AND mate.deleted=0 AND (frict.deleted IS NULL OR frict.deleted=0) ORDER BY mate_id ASC;";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($mate_id, $mate_first_name, $mate_last_name, $mate_gender, $frict_id, $frict_from_date, $frict_to_date, $frict_base, $notes);
   while($sql->fetch())
   {
      //echo frictlist row
      echo $mate_id."\t".$mate_first_name."\t".$mate_last_name."\t".$mate_gender."\t".$frict_id."\t".$frict_from_date."\t".$frict_to_date."\t".$frict_base."\t".$notes."\n";
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
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with the new account
 * @retval -4 if the email address is in use
 * @retval -5 if the username is in use
 * @retval -7 if signing up fails
 * @retval -10 if the username or email is null
 */
function sign_up($firstname, $lastname, $username, $email, $password, $gender, $birthdate, $db, $table)
{
   if($email == null || $username == null)
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
   $query2="insert into ".$table."(email, username, password, first_name, last_name, birthdate, gender, creation_datetime) values(?, ?, ?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."')";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('ssssssi', $email, $username, $hash, $firstname, $lastname, $birthdate, $gender);
   $sql2->execute();
   $sql2->free_result();
   
   //check result is TRUE meaning the insert was successful
   if($sql2 == TRUE)
   {
      //sign in as normal to get the uid
      return sign_in($username, $password, $db, $table);
   }
   else
   {
      //something went wrong when signing up
      return -7;
   }
} //end sign_up()

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

/*
 * @brief Allows a user to add a frict to their list
 * @param mate_id the mate
 * @parma base the base of the frict
 * @param from the first occurrence of the frict
 * @param rating the rating of the frict
 * @param notes notes about the frict
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -80 insert was unsuccessful
 * @retval frict_id on success
 */
function add_frict($mate_id, $base, $from, $rating, $notes, $db, $table_user, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   //insert into frict table
   $query="insert into ".$table_frict."(mate_id, frict_from_date, frict_rating, frict_base, notes, creation_datetime) values(?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."')";
   $sql=$db->prepare($query);
   $sql->bind_param('isiis', $mate_id, $from, $rating, $base, $notes);
   $sql->execute();
   //get id generated from the auto increment by the previous query
   $frict_id = $sql->insert_id;
   $sql->free_result();
   
   //check that the insert was successful
   if($sql != TRUE || $frict_id <= 0)
   {
      return -80;
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
 * @param db the database object
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -90 if the update was unsuccessful
 * @retval frict_id if the update of the hookup and frict table was successful
 */
function update_frict($frict_id, $mate_id, $base, $from, $rating, $notes, $db, $table_mate, $table_frict)
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
   
   //update hookup table
   $query="update ".$table_frict." set frict_from_date=?, frict_rating=?, frict_base=?, notes=?, last_update=? where frict_id='".$frict_id."'";
   $sql=$db->prepare($query);
   $sql->bind_param('siiss', $from, $rating, $base, $notes, $datetime);
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
 * @retval -50 if the deletion was unsuccessful
 * @retval frict_id if the deletion was successful
 */
function remove_frict($frict_id, $db, $table_frict)
{
   //validate ids
   $rc = validateId($table_frict, "frict_id", $frict_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");

   //"remove" frict by updating frict table and setting deleted to true
   $query="update ".$table_frict." set deleted=1, last_update=? where frict_id='".$frict_id."'";
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
 * @retval -40 if the deletion was unsuccessful
 * @retval mate_id if the deletion was successful
 */
function remove_mate($mate_id, $db, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //"remove" all fricts associated with the mate by updating frict table and setting deleted to true
   $query="update ".$table_frict." set deleted=1, last_update=? where mate_id='".$mate_id."'";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $datetime);
   $sql->execute();
   $sql->store_result();
   $sql->free_result();

   //"remove" mate by updating mate table and setting deleted to true
   $query="update ".$table_mate." set deleted=1, last_update=? where mate_id='".$mate_id."'";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $datetime);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->affected_rows;
   $sql->free_result();
   
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
 * @retval if success, a table of uid, username, and birthdays of matching users
 */
function search_mate($firstname, $lastname, $gender, $db)
{
   //query database for user data
   $query="SELECT uid, username, birthdate from user where first_name=? AND last_name=? AND gender=?";
   $sql=$db->prepare($query);
   $sql->bind_param('ssi', $firstname, $lastname, $gender);
   $sql->execute();
   $sql->bind_result($uid, $username, $bday);
   echo "user_search\n";
   while($sql->fetch())
   {
      echo $uid."\t".$username."\t".$bday."\n";
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
   
   return $request_id;
}
 
?>