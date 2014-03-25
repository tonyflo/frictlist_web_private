<?php
/* @file account.php
 * @date 2014-03-22
 * @author Tony Florida
 * @brief Functions that allow a user to interact with their data
 */
 
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
   $query="select frict.frict_id, frict.accepted, frict.frict_from_date, frict.frict_to_date, frict.frict_base, frict.notes, hookup.hu_first_name, hookup.hu_last_name, hookup.hu_gender from frict inner join hookup on frict.hu_id = hookup.hu_id where frict.uid=? AND frict.deleted=0;";
   $sql=$db->prepare($query);
   $sql->bind_param('i', $uid);
   $sql->execute();
   $sql->bind_result($frict_id, $accepted, $frict_from_date, $frict_to_date, $frict_base, $notes, $hu_first_name, $hu_last_name, $hu_gender);
   echo "frictlist\n";
   while($sql->fetch())
   {
      echo $frict_id."\t".$hu_first_name."\t".$hu_last_name."\t".$frict_base."\t".$accepted."\t".$frict_from_date."\t".$frict_to_date."\t".$notes."\t".$hu_gender."\n";
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
 * @param uid the user id of the user adding the frict
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @parma base the base of the frict
 * @param from the first occurrence of the frict
 * @param from the last occurrence of the frict
 * @param notes notes about the frict
 * @param db the database object
 * @param table_hu the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -20 if an null or invalid uid
 * @retval -21 if uid isn't found
 * @retval -22 if the insert into the hookup table was not successful
 * @retval -23 if the insert into the frict table was not successful
 * @retval frict_id if the frict and hookup tables were updated successfully
 */
function add_frict($uid, $firstname, $lastname, $gender, $base, $from, $to, $notes, $db, $table_user, $table_hu, $table_frict)
{
   if($uid == null || $uid < 0)
   {
      return -20;
   }

   //check that the uid exists in the db
   $query="select * from ".$table_user." where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $uid);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->num_rows;
   $sql->free_result();
 
   //the uid was found and is unique so we can proceed with the insert
   if($numrows == 1)
   {
      //insert into hookup table
      $query2="insert into ".$table_hu."(hu_first_name, hu_last_name, hu_gender) values(?, ?, ?)";
      $sql2=$db->prepare($query2);
      $sql2->bind_param('ssi', $firstname, $lastname, $gender);
      $sql2->execute();
      //get id generated from the auto increment by the previous query
      $hu_id = $sql2->insert_id;
      $sql2->free_result();

      //check result is TRUE meaning the insert was successful
      if($sql2 == TRUE && $hu_id > 0)
      {
         //insert into frict table
         $query3="insert into ".$table_frict."(uid, hu_id, frict_from_date, frict_to_date, frict_base, notes, creation_datetime) values(?, ?, ?, ?, ?, ?, '".date("Y-m-d H:i:s")."')";
         $sql3=$db->prepare($query3);
         $sql3->bind_param('iissis', $uid, $hu_id, $from, $to, $base, $notes);
         $sql3->execute();
         //get id generated from the auto increment by the previous query
         $frict_id = $sql3->insert_id;
         $sql3->free_result();
         
         //check result is TRUE meaning the insert was successful
         if($sql3 == TRUE && $frict_id > 0)
         {
            return $frict_id;
         }
         else
         {
             //something went wrong when adding to the frict table
            return -23;     
         }
      }
      else
      {
         //something went wrong when adding to the hookup table
         return -22;
      }
   }
   else
   {
      //the uid was not found so return
      return -21;
   }
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
 * @retval -60 if uid was null or invalid
 * @retval -61 if uid wasn't found
 * @retval -62 if insert into mate table was not successful
 * @retval the id of the mate if success
 */
function add_mate($uid, $firstname, $lastname, $gender, $db, $table_user, $table_mate)
{
   if($uid == null || $uid < 0)
   {
      return -60;
   }

   //check that the uid exists in the db
   $query="select * from ".$table_user." where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $uid);
   $sql->execute();
   $sql->store_result();
   $numrows=$sql->num_rows;
   $sql->free_result();
 
   //the uid was found and is unique so we can proceed with the insert
   if($numrows == 1)
   {
      //insert into hookup table
      $query2="insert into ".$table_mate."(hu_first_name, hu_last_name, hu_gender) values(?, ?, ?)";
      $sql2=$db->prepare($query2);
      $sql2->bind_param('ssi', $firstname, $lastname, $gender);
      $sql2->execute();
      //get id generated from the auto increment by the previous query
      $mate_id = $sql2->insert_id;
      $sql2->free_result();

      //check result is TRUE meaning the insert was successful
      if($sql2 == TRUE && $mate_id > 0)
      {
         return $mate_id;
      }
      else
      {
         //something went wrong when adding to the mate table
         return -62;
      }
   }
   else
   {
      //the uid was not found so return
      return -61;
   }
}

/*
 * @brief Allows a user to update a frict in their list
 * @param uid the user id of the user adding the frict
 * @param frict_id the id of the frict
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @parma base the base of the frict
 * @param from the first occurrence of the frict
 * @param from the last occurrence of the frict
 * @param notes notes about the frict
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_hu the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval -30 if the uid or frict_id is null or invalid
 * @retval -31 if the uid wasn't found
 * @retval -32 if the frict_id wasn't found
 * @retval -33 if the hu_id wasn't found
 * @retval -34 if the update of the hookup table was not successful
 * @retval -35 if the update of the frict table was not successful
 * @retval frict_id if the update of the hookup and frict table was successful
 */
function update_frict($uid, $frict_id, $firstname, $lastname, $gender, $base, $from, $to, $notes, $db, $table_user, $table_hu, $table_frict)
{
   if($uid == null || $uid < 0 || $frict_id == null || $frict_id < 0)
   {
      return -30;
   }

   //check that the uid exists in the db
   $query="select * from ".$table_user." where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $uid);
   $sql->execute();
   $sql->store_result();
   $numrows_user=$sql->num_rows;
   $sql->free_result();
   
   //check that the frict_id exists in the db
   $query2="select * from ".$table_frict." where frict_id=?";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('s', $frict_id);
   $sql2->execute();
   $sql2->store_result();
   $numrows_frict=$sql2->num_rows;
   $sql2->free_result();

   //the uid was found and is unique so we can proceed with the insert
   if($numrows_user != 1)
   {
      //the uid was not found so return
      return -31;
   }
   
   //the frict_id was found and is unique so we can proceed with the insert
   if($numrows_frict != 1)
   {
      //the frict_id was not found so return
      return -32;
   }

   //query for hu_id
   $query3="select hu_id from ".$table_frict." where frict_id=?";
   $sql3=$db->prepare($query3);
   $sql3->bind_param('i', $frict_id);
   $sql3->execute();
   $sql3->bind_result($hu_id);
   $sql3->fetch();
   $sql3->free_result();
   
   //check if hu id is valid
   if($hu_id <= 0)
   {
      //invalid hu_id
      return -33;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //update hookup table
   $query4="update ".$table_hu." set hu_first_name=?, hu_last_name=?, hu_gender=?, last_update=? where hu_id='".$hu_id."'";
   $sql4=$db->prepare($query4);
   $sql4->bind_param('ssis', $firstname, $lastname, $gender, $datetime);
   $sql4->execute();
   $sql4->store_result();
   $numrows4=$sql4->affected_rows;
   $sql4->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows4 != 1)
   {
      //something went wrong when updating hookup table
      return -34;
   } 

   //update frict table
   $query5="update ".$table_frict." set frict_from_date=?, frict_to_date=?, frict_base=?, notes=?, last_update=? where frict_id='".$frict_id."'";
   $sql5=$db->prepare($query5);
   $sql5->bind_param('ssiss', $from, $to, $base, $notes, $datetime);
   $sql5->execute();
   $sql5->store_result();
   $numrows5=$sql5->affected_rows;
   $sql5->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows5 != 1)
   {
      //something went wrong when updating frict table
      return -35;
   }
   
   return $frict_id;
}

/*
 * @brief Allows a user to update a mate
 * @param uid the user id of the user 
  * @param mid the mate id of the mate
 * @param firstname the first name of the user up to 255 characters
 * @param lastname the first name of the user up to 255 characters
 * @param gender the gender of account: 0 if male, 1 is female
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_mate the table name of the mate table
 * @retval -60 if uid was null or invalid
 * @retval -61 if uid wasn't found
 * @retval -62 if insert into mate table was not successful
 * @retval the id of the mate if success
 */
function update_mate($uid, $mid, $firstname, $lastname, $gender, $db, $table_user, $table_hu)
{
   if($uid == null || $uid < 0 || $mid == null || $mid < 0)
   {
      return -70;
   }

   //check that the uid exists in the db
   $query="select * from ".$table_user." where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $uid);
   $sql->execute();
   $sql->store_result();
   $numrows_user=$sql->num_rows;
   $sql->free_result();
   
   //check that the hu_id (mid) exists in the db
   $query2="select * from ".$table_hu." where hu_id=?";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('s', $mid);
   $sql2->execute();
   $sql2->store_result();
   $numrows_mate=$sql2->num_rows;
   $sql2->free_result();

   //the uid was found and is unique so we can proceed with the insert
   if($numrows_user != 1)
   {
      //the uid was not found so return
      return -71;
   }
   
   //the mid was found and is unique so we can proceed with the insert
   if($numrows_mate != 1)
   {
      //the mid was not found so return
      return -72;
   }

   $datetime = date("Y-m-d H:i:s");
   
   //update hookup table
   $query4="update ".$table_hu." set hu_first_name=?, hu_last_name=?, hu_gender=?, last_update=? where hu_id='".$mid."'";
   $sql4=$db->prepare($query4);
   $sql4->bind_param('ssis', $firstname, $lastname, $gender, $datetime);
   $sql4->execute();
   $sql4->store_result();
   $numrows4=$sql4->affected_rows;
   $sql4->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows4 != 1)
   {
      //something went wrong when updating hookup table
      return -73;
   } 
   
   return $mid;
}

/*
 * @brief Allows a user to remove a frict to their list
 * @param uid the user id of the user removing the frict
 * @param frict_id the id of the frict
 * @param db the database object
 * @param table_user the table name of the user table
 * @param table_frict the table name of the frict table
 * @retval -40 if uid or frict_id was null or invalid
 * @retval -41 if the uid wasn't found
 * @retval -42 if the frict_id wasn't found
 * @retval -43 if the update of the frict table wasn't successful
 * @retval frict_id if the update of the frict table was successful
 */
function remove_frict($uid, $frict_id, $db, $table_user, $table_frict)
{
   if($uid == null || $uid < 0 || $frict_id == null || $frict_id < 0)
   {
      return -40;
   }

   //check that the uid exists in the db
   $query="select * from ".$table_user." where uid=?";
   $sql=$db->prepare($query);
   $sql->bind_param('s', $uid);
   $sql->execute();
   $sql->store_result();
   $numrows_user=$sql->num_rows;
   $sql->free_result();
   
   //check that the frict_id exists in the db
   $query2="select * from ".$table_frict." where frict_id=?";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('s', $frict_id);
   $sql2->execute();
   $sql2->store_result();
   $numrows_frict=$sql2->num_rows;
   $sql2->free_result();

   //the uid was found and is unique so we can proceed with the insert
   if($numrows_user != 1)
   {
      //the uid was not found so return
      return -41;
   }
   
   //the uid was found and is unique so we can proceed with the insert
   if($numrows_frict != 1)
   {
      //the frict_id was not found so return
      return -42;
   }

   $datetime = date("Y-m-d H:i:s");

   //update frict table
   $query5="update ".$table_frict." set deleted=1, last_update=? where frict_id='".$frict_id."'";
   $sql5=$db->prepare($query5);
   $sql5->bind_param('s', $datetime);
   $sql5->execute();
   $sql5->store_result();
   $numrows5=$sql5->affected_rows;
   $sql5->free_result();
   
   //check result is TRUE meaning the update was successful
   if($numrows5 != 1)
   {
      //something went wrong when updating frict table
      return -43;
   }
   
   return $frict_id;
}
 
?>