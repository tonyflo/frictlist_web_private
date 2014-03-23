<?php
/* @file credentials.php
 * @date 2014-03-22
 * @author Tony Florida
 * @brief Logic to connect to the frictlist database
 */
   $user="flooreeda_user";
   $password="dontyouworrychild";
   $database="frictlist";
   $host="mysql.flooreeda.com";
   
   $db = new mysqli($host, $user, $password, $database);
   if($db->connect_errno > 0)
   {
      die('Unable to connect to database [' . $db->connect_error . ']');
   }
   error_reporting(E_ERROR);
?>
