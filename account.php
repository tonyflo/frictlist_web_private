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
 * @retval TODO
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
 * @brief Allows a user to add a frict to their list
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
 * @param table_hu the table name of the hookup table
 * @param table_frict the table name of the frict table
 * @retval TODO
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
   
      //the uid was found and is unique so we can proceed with the insert
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

   //update hookup table
   $query4="update ".$table_hu." set hu_first_name=?, hu_last_name=?, hu_gender=? where hu_id=?";
   $sql4=$db->prepare($query4);
   $sql4->bind_param('ssii', $firstname, $lastname, $gender, $hu_id);
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
   $query5="update ".$table_frict." set frict_from_date=?, frict_to_date=?, frict_base=?, notes=? where frict_id=?";
   $sql5=$db->prepare($query5);
   $sql5->bind_param('ssisi', $from, $to, $base, $notes, $frict_id);
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
 
?>
