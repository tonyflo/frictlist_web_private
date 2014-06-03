<?php
/* @file android_app_list.php
 * @date 2014-06-03
 * @author Tony Florida
 * @brief Backend of a html form to allow Android users to be added to a list
 * to be notified when Frictlist is available for Android.
 */
 
$SUCCESS = 0;
 
/*************************************************************************
 * Public MySQL Functions
 ************************************************************************/
 
/*
 * @brief Allows a user to be added to a list for the Android app
 * @param email a valid, unique email address 
 * @retval $SUCCESS if the user was added to the list
 * @retval -1 if the email was null
 * @retval -2 if the email was already in the db
 * @retval -3 if the insert was unsuccessful
 */
function android_app_list($email)
{
   if($email == null)
   {
      return -1;
   }
   
   include 'credentials.php';
   
   //check that the email doesn't exist in the db
   $queryA="SElECT email FROM android_app_list WHERE email=?";
   $sqlA=$db->prepare($queryA);
   $sqlA->bind_param('s', $email);
   $sqlA->execute();
   $sqlA->store_result();
   $numrowsA=$sqlA->num_rows;
   $sqlA->free_result();
 
   //the email address is available if the query returns 0 matching rows
   if($numrowsA != 0)
   {
      //the email address is already in the list so return error code
      return -2;
   }

   //the email address hasn't been registered so proceed with the insert
   $query2="insert into android_app_list(email, add_datetime) values(?, '".date("Y-m-d H:i:s")."')";
   $sql2=$db->prepare($query2);
   $sql2->bind_param('s', $email);
   $sql2->execute();
   $sql2->free_result();
   
   //check result is TRUE meaning the insert was successful
   if($sql2 == TRUE)
   {
      //sign in as normal to get the uid
      return $SUCCESS;
   }
   else
   {
      //something went wrong when adding the user
      return -3;
   }
} //end android_app_list()