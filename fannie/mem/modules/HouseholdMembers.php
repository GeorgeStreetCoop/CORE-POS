<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op, Duluth, MN

    This file is part of CORE-POS.

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

class HouseholdMembers extends \COREPOS\Fannie\API\member\MemberModule {

    public function width()
    {
        return parent::META_WIDTH_HALF;
    }

    function showEditForm($memNum, $country="US")
    {
        $account = self::getAccount();

        $ret = "<div class=\"panel panel-default\">
            <div class=\"panel-heading\">Household Members</div>
            <div class=\"panel-body\">";
        
        $count = 0; 
        foreach ($account['customers'] as $infoW) {
            if ($infoW['accountHolder']) {
                continue;
            }
            $ret .= sprintf('
                <div class="form-inline form-group">
                <span class="label primaryBackground">Name</span>
                <input name="HouseholdMembers_fn[]" placeholder="First"
                    maxlength="30" value="%s" class="form-control" />
                <input name="HouseholdMembers_ln[]" placeholder="Last"
                    maxlength="30" value="%s" class="form-control" />
                <input name="HouseholdMembers_ID[]" type="hidden" value="%d" />
                </div>',
                $infoW['firstName'],$infoW['lastName'],$infoW['customerID']);
            $count++;
        }

        while ($count < 3) {
            $ret .= sprintf('
                <div class="form-inline form-group">
                <span class="label primaryBackground">Name</span>
                <input name="HouseholdMembers_fn[]" placeholder="First"
                    maxlength="30" value="" class="form-control" />
                <input name="HouseholdMembers_ln[]" placeholder="Last"
                    maxlength="30" value="" class="form-control" />
                <input name="HouseholdMembers_ID[]" type="hidden" value="0" />
                </div>');
            $count++;
        }

        $ret .= "</div>";
        $ret .= "</div>";

        return $ret;
    }

    function saveFormData($memNum)
    {
        $dbc = $this->db();

        /**
          Use primary member for default column values
        */
        $account = self::getAccount();
        if (!$account) {
            return "Error: Problem saving household members<br />"; 
        }

        $json = array(
            'cardNo' => $memNum,
            'customerTypeID' => $account['customerTypeID'],
            'memberStatus' => $account['memberStatus'],
            'activeStatus' => $account['activeStatus'],
            'customers' => array()
        );
        $primary = array('discount'=>0, 'staff'=>0, 'lowIncomeBenefits'=>0, 'chargeAllowed'=>0, 'checksAllowed'=>0);
        foreach ($account['customers'] as $c) {
            if ($c['accountHolder']) {
                $primary = $c;
                break;
            }
        }

        $fns = FormLib::get_form_value('HouseholdMembers_fn',array());
        $lns = FormLib::get_form_value('HouseholdMembers_ln',array());
        $ids = FormLib::get('HouseholdMembers_ID', array());
        for ($i=0; $i<count($lns); $i++) {
            $json['customers'][] = array(
                'customerID' => $ids[$i],
                'firstName' => $fns[$i],
                'lastName' => $lns[$i],
                'accountHolder' => 0,
                'discount' => $primary['discount'],
                'staff' => $primary['staff'],
                'lowIncomeBenefits' => $primary['lowIncomeBenefits'],
                'chargeAllowed' => $primary['chargeAllowed'],
                'checksAllowed' => $primary['checksAllowed'],
            );
        }

        $resp = \COREPOS\Fannie\API\member\MemberREST::post($memNum, $json);

        if ($resp['errors'] > 0) {
            return "Error: Problem saving household members<br />"; 
        }

        return '';
    }
}

