<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/

  class osC_Account {

    function &getEntry() {
      global $osC_Database, $osC_Customer;

      $Qaccount = $osC_Database->query('select customers_gender, customers_firstname, customers_lastname, unix_timestamp(customers_dob) as customers_dob, customers_email_address from :table_customers where customers_id = :customers_id');
      $Qaccount->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qaccount->bindInt(':customers_id', $osC_Customer->getID());
      $Qaccount->execute();

      return $Qaccount;
    }

    function getID($email_address) {
      global $osC_Database;

      $Quser = $osC_Database->query('select customers_id from :table_customers where customers_email_address = :customers_email_address limit 1');
      $Quser->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Quser->bindValue(':customers_email_address', $email_address);
      $Quser->execute();

      if ($Quser->numberOfRows() === 1) {
        return $Quser->valueInt('customers_id');
      }

      return false;
    }

    function createEntry($data) {
      global $osC_Database, $osC_Session, $osC_Customer, $osC_NavigationHistory;

      $osC_Database->startTransaction();

      $Qcustomer = $osC_Database->query('insert into :table_customers (customers_firstname, customers_lastname, customers_email_address, customers_newsletter, customers_status, customers_ip_address, customers_password, customers_gender, customers_dob) values (:customers_firstname, :customers_lastname, :customers_email_address, :customers_newsletter, :customers_status, :customers_ip_address, :customers_password, :customers_gender, :customers_dob)');
      $Qcustomer->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qcustomer->bindValue(':customers_firstname', $data['firstname']);
      $Qcustomer->bindValue(':customers_lastname', $data['lastname']);
      $Qcustomer->bindValue(':customers_email_address', $data['email_address']);
      $Qcustomer->bindValue(':customers_newsletter', (isset($data['newsletter']) && ($data['newsletter'] == '1') ? '1' : ''));
      $Qcustomer->bindValue(':customers_status', '1');
      $Qcustomer->bindValue(':customers_ip_address', tep_get_ip_address());
      $Qcustomer->bindValue(':customers_password', tep_encrypt_password($data['password']));
      $Qcustomer->bindValue(':customers_gender', (((ACCOUNT_GENDER > -1) && isset($data['gender']) && (($data['gender'] == 'm') || ($data['gender'] == 'f'))) ? $data['gender'] : ''));
      $Qcustomer->bindValue(':customers_dob', ((ACCOUNT_DATE_OF_BIRTH > -1) ? date('Ymd', $data['dob']) : ''));
      $Qcustomer->execute();

      if ($Qcustomer->affectedRows() === 1) {
        $customer_id = $osC_Database->nextID();

        $Qci = $osC_Database->query('insert into :table_customers_info (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values (:customers_info_id, :customers_info_number_of_logons, :customers_info_date_account_created)');
        $Qci->bindTable(':table_customers_info', TABLE_CUSTOMERS_INFO);
        $Qci->bindInt(':customers_info_id', $customer_id);
        $Qci->bindInt(':customers_info_number_of_logons', 0);
        $Qci->bindRaw(':customers_info_date_account_created', 'now()');
        $Qci->execute();

        if ($Qci->affectedRows() === 1) {
          $osC_Database->commitTransaction();

          if (SERVICE_SESSION_REGENERATE_ID == 'True') {
            $osC_Session->recreate();
          }

          $osC_Customer->setCustomerData($customer_id);

// restore cart contents
          $_SESSION['cart']->restore_contents();

          $osC_NavigationHistory->removeCurrentPage();

// build the message content
          if ((ACCOUNT_GENDER > -1) && isset($data['gender'])) {
             if ($data['gender'] == 'm') {
               $email_text = sprintf(EMAIL_GREET_MR, $osC_Customer->getLastName());
             } else {
               $email_text = sprintf(EMAIL_GREET_MS, $osC_Customer->getLastName());
             }
          } else {
            $email_text = sprintf(EMAIL_GREET_NONE, $osC_Customer->getName());
          }

          $email_text .= EMAIL_WELCOME . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_WARNING;
          tep_mail($osC_Customer->getName(), $osC_Customer->getEmailAddress(), EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

          return true;
        } else {
          $osC_Database->rollbackTransaction();
        }
      } else {
        $osC_Database->rollbackTransaction();
      }

      return false;
    }

    function saveEntry($data) {
      global $osC_Database, $osC_Customer;

      $Qcustomer = $osC_Database->query('update :table_customers set customers_gender = :customers_gender, customers_firstname = :customers_firstname, customers_lastname = :customers_lastname, customers_email_address = :customers_email_address, customers_dob = :customers_dob where customers_id = :customers_id');
      $Qcustomer->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qcustomer->bindValue(':customers_gender', ((ACCOUNT_GENDER > -1) && isset($data['gender']) && (($data['gender'] == 'm') || ($data['gender'] == 'f'))) ? $data['gender'] : '');
      $Qcustomer->bindValue(':customers_firstname', $data['firstname']);
      $Qcustomer->bindValue(':customers_lastname', $data['lastname']);
      $Qcustomer->bindValue(':customers_email_address', $data['email_address']);
      $Qcustomer->bindValue(':customers_dob', (ACCOUNT_DATE_OF_BIRTH > -1) ? date('Ymd', $data['dob']) : '');
      $Qcustomer->bindInt(':customers_id', $osC_Customer->getID());
      $Qcustomer->execute();

      if ($Qcustomer->affectedRows() === 1) {
        $Qupdate = $osC_Database->query('update :table_customers_info set customers_info_date_account_last_modified = now() where customers_info_id = :customers_info_id');
        $Qupdate->bindTable(':table_customers_info', TABLE_CUSTOMERS_INFO);
        $Qupdate->bindInt(':customers_info_id', $osC_Customer->getID());
        $Qupdate->execute();

        return true;
      }

      return false;
    }

    function savePassword($password, $customer_id = null) {
      global $osC_Database, $osC_Customer;

      if (is_numeric($customer_id) === false) {
        $customer_id = $osC_Customer->getID();
      }

      $Qcustomer = $osC_Database->query('update :table_customers set customers_password = :customers_password where customers_id = :customers_id');
      $Qcustomer->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qcustomer->bindValue(':customers_password', tep_encrypt_password($password));
      $Qcustomer->bindInt(':customers_id', $customer_id);
      $Qcustomer->execute();

      if ($Qcustomer->affectedRows() === 1) {
        $Qupdate = $osC_Database->query('update :table_customers_info set customers_info_date_account_last_modified = now() where customers_info_id = :customers_info_id');
        $Qupdate->bindTable(':table_customers_info', TABLE_CUSTOMERS_INFO);
        $Qupdate->bindInt(':customers_info_id', $customer_id);
        $Qupdate->execute();

        return true;
      }

      return false;
    }

    function checkEntry($email_address) {
      global $osC_Database;

      $Qcheck = $osC_Database->query('select customers_id from :table_customers where customers_email_address = :customers_email_address limit 1');
      $Qcheck->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qcheck->bindValue(':customers_email_address', $email_address);
      $Qcheck->execute();

      if ($Qcheck->numberOfRows() === 1) {
        return true;
      }

      return false;
    }

    function checkPassword($password, $email_address = null) {
      global $osC_Database, $osC_Customer;

      if ($email_address === null) {
        $Qcheck = $osC_Database->query('select customers_password from :table_customers where customers_id = :customers_id');
        $Qcheck->bindTable(':table_customers', TABLE_CUSTOMERS);
        $Qcheck->bindInt(':customers_id', $osC_Customer->getID());
        $Qcheck->execute();
      } else {
        $Qcheck = $osC_Database->query('select customers_password from :table_customers where customers_email_address = :customers_email_address limit 1');
        $Qcheck->bindTable(':table_customers', TABLE_CUSTOMERS);
        $Qcheck->bindValue(':customers_email_address', $email_address);
        $Qcheck->execute();
      }

      if ($Qcheck->numberOfRows() === 1) {
        if ( (strlen($password) > 0) && (strlen($Qcheck->value('customers_password')) > 0) ) {
          $stack = explode(':', $Qcheck->value('customers_password'));

          if (sizeof($stack) === 2) {
            if (md5($stack[1] . $password) == $stack[0]) {
              return true;
            }
          }
        }
      }

      return false;
    }

    function checkDuplicateEntry($email_address) {
      global $osC_Database, $osC_Customer;

      $Qcheck = $osC_Database->query('select customers_id from :table_customers where customers_email_address = :customers_email_address and customers_id != :customers_id limit 1');
      $Qcheck->bindTable(':table_customers', TABLE_CUSTOMERS);
      $Qcheck->bindValue(':customers_email_address', $email_address);
      $Qcheck->bindInt(':customers_id', $osC_Customer->getID());
      $Qcheck->execute();

      if ($Qcheck->numberOfRows() === 1) {
        return true;
      }

      return false;
    }
  }
?>