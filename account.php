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
   $sql->bind_param('i', $id);
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
 * @brief Allows a user to sign into their account
 * @param email a valid email address up to 35 characters
 * @param password a password between 6 and 255 characters
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with a valid email and password
 * @retval -1 if the email address was not found in the database
 * @retval -2 if the password is wrong
 */
function sign_in($email, $password, $db, $table)
{
   //query database for provided email
   $query="select uid, password from ".$table." where email=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $email);
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
      //return -1 because the email wasn't found
      return -1;
   }
} //end sign_in()

/*
 * @brief Send all (not deleted) frict data to user
 * @param uid the user id of the user adding the frict
 * @param db the database object
 * @retval -50 if invalid uid is discovered
 * @retval on success, a table of the uer's fricts in which columns are separated by tabs and rows by new lines
 */
function get_frictlist($uid, $db)
{
   if($uid == null || $uid < 0)
   {
      return -50;
   }
   
   //query database for provided email
   $query="SELECT mate.mate_id, mate_first_name, mate_last_name, mate_gender, frict_id, frict_from_date, frict_to_date, frict_base, notes FROM mate LEFT JOIN frict ON mate.mate_id=frict.mate_id WHERE uid=? AND mate.deleted=0 AND (frict.deleted IS NULL OR frict.deleted=0) ORDER BY mate_id ASC;";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($mate_id, $mate_first_name, $mate_last_name, $mate_gender, $frict_id, $frict_from_date, $frict_to_date, $frict_base, $notes);
   echo "frictlist\n";
   while($sql->fetch())
   {
      echo $mate_id."\t".$mate_first_name."\t".$mate_last_name."\t".$mate_gender."\t".$frict_id."\t".$frict_from_date."\t".$frict_to_date."\t".$frict_base."\t".$notes."\n";
   }
   $sql->free_result();
}

/*
 * @brief Allows a user to sign up for an account
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param email a valid email address up to 35 characters
 * @param password a password between 6 and 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @param birthdate the birthdate of the user
 * @param db the database object
 * @param table the table name
 * @retval the primary key associated with the new account
 * @retval -4 if the email address is in use
 * @retval -7 if signing up fails
 */
function sign_up($firstname, $lastname, $email, $password, $gender, $birthdate, $db, $table)
{
   if($email == null)
   {
      return -10;
   }
   
   //check that the email doesn't exist in the db
   $query="select * from ".$table." where email=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $email);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->num_rows;
   $sql->free_result();
 
   //the email address is available if the query returns 0 matching rows
   if($numrows == 0)
   {
      //hash the password
      $hash=pw_hash($password);

      //the email address is available so proceed with creating account
      $query2="insert into ".$table."(email, password, first_name, last_name, birthdate, gender, creation_datetime) values(?, ?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."')";
      $sql2=$db->prepare($query2);
      $sql2->bind_param('sssssi', $email, $hash, $firstname, $lastname, $birthdate, $gender);
      $sql2->execute();
      $sql2->free_result();
      
      //check result is TRUE meaning the insert was successful
      if($sql2 == TRUE)
      {
         //sign in as normal to get the uid
         return sign_in($email, $password, $db, $table);
      }
      else
      {
         //something went wrong when signing up
         return -7;
      }
   }
   else
   {
      //the email address is taken so return error code
      return -4;
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
 * @param to the last occurrence of the frict
 * @param notes notes about the frict
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -80 insert was unsuccessful
 * @retval frict_id on success
 */
function add_frict($mate_id, $base, $from, $to, $notes, $db, $table_user, $table_mate, $table_frict)
{
   //validate ids
   $rc = validateId($table_mate, "mate_id", $mate_id, $db);
   if($rc != $SUCCESS)
   {
      return $rc;
   }
   
   //insert into frict table
   $query="insert into ".$table_frict."(mate_id, frict_from_date, frict_to_date, frict_base, notes, creation_datetime) values(?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."')";
   $sql=$db->prepare($query);
   $sql->bind_param('issis', $mate_id, $from, $to, $base, $notes);
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
 * @param to the last occurrence of the frict
 * @param notes notes about the frict
 * @param db the database object
 * @param table_mate the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -90 if the update was unsuccessful
 * @retval frict_id if the update of the hookup and frict table was successful
 */
function update_frict($frict_id, $mate_id, $base, $from, $to, $notes, $db, $table_mate, $table_frict)
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
   $query="update ".$table_frict." set frict_from_date=?, frict_to_date=?, frict_base=?, notes=?, last_update=? where frict_id='".$frict_id."'";
   $sql=$db->prepare($query);
   $sql->bind_param('ssiss', $from, $to, $base, $notes, $datetime);
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
 
?>