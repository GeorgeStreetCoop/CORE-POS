<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
  @class WEFC_Toronto_Kicker
  Class for opening cash drawer.
  Same as base except not open on signin.

*/
class WEFC_Toronto_Kicker extends Kicker
{

    /**
      Determine whether to open the drawer
      @return boolean
    */
    public function doKick($trans_num)
    {
        global $CORE_LOCAL;
        if($CORE_LOCAL->get('training') == 1) {
            return false;
        }
        $db = Database::tDataConnect();

        $query = "SELECT trans_id   
                  FROM localtranstoday 
                  WHERE 
                    (trans_subtype = 'CA' and total <> 0)
                    AND " . $this->refToWhere($trans_num);

        $result = $db->query($query);
        $num_rows = $db->num_rows($result);

        return ($num_rows > 0) ? true : false;
    }

    /**
      Determine whether to open the drawer when
      a cashier signs in
      @return boolean
    */
    public function kickOnSignIn()
    {
        return false;
    }

    /**
      Determine whether to open the drawer when
      a cashier signs out
      @return boolean
    */
    public function kickOnSignOut()
    {
        global $CORE_LOCAL;
        if($CORE_LOCAL->get('training') == 1) {
            return false;
        }

        return true;
    }
}

