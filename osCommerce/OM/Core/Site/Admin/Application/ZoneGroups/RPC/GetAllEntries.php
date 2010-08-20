<?php
/*
  osCommerce Online Merchant $osCommerce-SIG$
  Copyright (c) 2010 osCommerce (http://www.oscommerce.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v2 (1991)
  as published by the Free Software Foundation.
*/

  namespace osCommerce\OM\Core\Site\Admin\Application\ZoneGroups\RPC;

  use osCommerce\OM\Core\Site\Admin\Application\ZoneGroups\ZoneGroups;
  use osCommerce\OM\Core\Site\RPC\Controller as RPC;

  class GetAllEntries {
    public static function execute() {
      if ( !isset($_GET['search']) ) {
        $_GET['search'] = '';
      }

      if ( !empty($_GET['search']) ) {
        $result = ZoneGroups::findEntries($_GET['search'], $_GET['id']);
      } else {
        $result = ZoneGroups::getAllEntries($_GET['id']);
      }

      $result['rpcStatus'] = RPC::STATUS_SUCCESS;

      echo json_encode($result);
    }
  }
?>
