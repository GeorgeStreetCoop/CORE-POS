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

class ContactInfo extends \COREPOS\Fannie\API\member\MemberModule {

    function showEditForm($memNum, $country="US")
    {
        $account = self::getAccount();
        $primary = array();
        foreach ($account['customers'] as $c) {
            if ($c['accountHolder']) {
                $primary = $c;
                break;
            }
        }

        $labels = array();
        switch ($country) {
            case "US":
                $labels['state'] = "State";
                $labels['zip'] = "Zip";
                break;
            case "CA":
                $labels['state'] = "Province";
                $labels['zip'] = "Postal Code";
                break;
        }

        $ret = "<div class=\"panel panel-default\">
            <div class=\"panel-heading\">Contact Info</div>
            <div class=\"panel-body\">";

        $ret .= '<div class="form-group form-inline">';
        $ret .= '<input type="hidden" name="ContactInfo_customerID" value="' . $primary['customerID'] . '" />';
        $ret .= '<span class="label primaryBackground">First Name</span>';
        $ret .= sprintf('<input name="ContactInfo_fn" maxlength="30"
                value="%s" class="form-control" />',$primary['firstName']);
        $ret .= ' <span class="label primaryBackground">Last Name</span>';
        $ret .= sprintf('<input name="ContactInfo_ln" maxlength="30"
                value="%s" class="form-control" />',$primary['lastName']);
        $ret .= sprintf(' <a href="MemPurchasesPage.php?id=%d">Receipts</a>',
                    $memNum);
        $ret .= sprintf(' |  <a href="../reports/Patronage/MemberPatronageReport.php?id=%d">Patronage</a>',
                    $memNum);
        $ret .= sprintf(' |  <a href="../ordering/NewSpecialOrdersPage.php?card_no=%d">Special Orders</a>',
                    $memNum);
        $ret .= '</div>';
        $ret .= '</div>';

        $ret .= '<div class="form-group form-inline">';
        $ret .= '<span class="label primaryBackground">Address</span>';
        $ret .= sprintf('<input name="ContactInfo_addr1" maxlength="125"
                value="%s" class="form-control" />',$account['addressFirstLine']);
        $ret .= ' <span class="label primaryBackground">Address (2)</span>';
        $ret .= sprintf('<input name="ContactInfo_addr2" maxlength="125"
                value="%s" class="form-control" />',$account['addressSecondLine']);
        $ret .= ' <label><span class="label primaryBackground">Gets Mail</span>';
        $ret .= sprintf('<input type="checkbox" name="ContactInfo_mail"
                %s class="checkbox-inline" /></label>',($account['contactAllowed']==1?'checked':''));
        $ret .= '</div>';
        
        $ret .= '<div class="form-group form-inline">';
        $ret .= '<span class="label primaryBackground">City</span>';
        $ret .= sprintf('<input name="ContactInfo_city" maxlength="20"
                value="%s" class="form-control" />',$account['city']);
        $ret .= ' <span class="label primaryBackground">' . $labels['state'] . '</span>';
        $ret .= sprintf('<input name="ContactInfo_state" maxlength="2"
                value="%s" class="form-control" />',$account['state']);
        $ret .= ' <span class="label primaryBackground">' . $labels['zip'] . '</span>';
        $ret .= sprintf('<input name="ContactInfo_zip" maxlength="10"
                value="%s" class="form-control" />',$account['zip']);
        $ret .= '</div>';

        $ret .= '<div class="form-group form-inline">';
        $ret .= '<span class="label primaryBackground">Phone</span>';
        $ret .= sprintf('<input name="ContactInfo_ph1" maxlength="30"
                value="%s" class="form-control" />',$primary['phone']);
        $ret .= ' <span class="label primaryBackground">Alt. Phone</span>';
        $ret .= sprintf('<input name="ContactInfo_ph2" maxlength="30"
                value="%s" class="form-control" />',$primary['altPhone']);
        $ret .= ' <span class="label primaryBackground">E-mail</span>';
        $ret .= sprintf('<input type="email" name="ContactInfo_email" maxlength="75"
                value="%s" class="form-control" />',$primary['email']);
        $ret .= "</div>";

        $ret .= "</div>";
        $ret .= "</div>";

        return $ret;
    }

    function saveFormData($memNum)
    {
        $json = array(
            'addressFirstLine' => FormLib::get_form_value('ContactInfo_addr1',''),
            'addressSecondLine' => FormLib::get_form_value('ContactInfo_addr2',''),
            'city' => FormLib::get_form_value('ContactInfo_city',''),
            'state' => FormLib::get_form_value('ContactInfo_state',''),
            'zip' => FormLib::get_form_value('ContactInfo_zip',''),
            'contactAllowed' => (FormLib::get_form_value('ContactInfo_mail')!=='' ? 1 : 0),
            'customers' => array(),
        );
        $account = self::getAccount();
        foreach ($account['customers'] as $c) {
            if ($c['accountHolder']) {
                $json['customers'][] = array(
                    'customerID' => $c['customerID'],
                    'accountHolder' => 1,
                    'lastName' => FormLib::get('ContactInfo_ln', ''),
                    'firstName' => FormLib::get('ContactInfo_fn', ''),
                    'phone' => FormLib::get_form_value('ContactInfo_ph1',''),
                    'altPhone' => FormLib::get_form_value('ContactInfo_ph2',''),
                    'email' => FormLib::get_form_value('ContactInfo_email',''),
                );
            }
        }

        /* Canadian Postal Code, and City and Province
         * Phone style: ###-###-####
        */
        if (preg_match("/^[A-Z]\d[A-Z]/i", $json['zip']) ) {
            $json['zip'] = strtoupper($json['zip']);
            if (strlen($json['zip']) == 6) {
                $json['zip'] = substr($json['zip'],0,3).' '. substr($json['zip'],3,3);
            }
            // Postal code M* supply City and Province
            if (preg_match("/^M/", $json['zip']) &&
                    $json['city'] == '' && $json['state'] == '') {
                $json['city'] = 'Toronto';
                $json['state'] = 'ON';
            }
            // Phone# style: ###-###-####
            if (preg_match("/^[MKLP]/", $json['zip']) ) {
                if (preg_match("/^[-() .0-9]+$/",$json['customers'][0]['phone']) ) {
                    $phone = preg_replace("/[^0-9]/", '' ,$json['customers'][0]['phone']);
                    if (preg_match("/^\d{10}$/",$phone)) {
                        $json['customers'][0]['phone'] = preg_replace("/(\d{3})(\d{3})(\d{4})/",'${1}-${2}-${3}',$phone);
                    }
                }
                if (preg_match("/^[-() .0-9]+$/",$json['customers'][0]['altPhone']) ) {
                    $phone = preg_replace("/[^0-9]/", '' ,$json['customers'][0]['altPhone']);
                    if (preg_match("/^\d{10}$/",$phone)) {
                        $json['customers'][0]['altPhone'] = preg_replace("/(\d{3})(\d{3})(\d{4})/",'${1}-${2}-${3}',$phone);
                    }
                }
            }
        }

        $resp = \COREPOS\Fannie\API\member\MemberREST::post($memNum, $json);

        if ($resp['errors'] > 0) {
            return "Error: problem saving Contact Information<br />";
        } else {
            return '';
        }
    }

    function hasSearch(){ return True; }

    function showSearchForm($country="US")
    {
        $labels = array();
        switch ($country) {
            case "US":
                $labels['state'] = "State";
                $labels['zip'] = "Zip";
                break;
            case "CA":
                $labels['state'] = "Province";
                $labels['zip'] = "Postal Code";
                break;
        }
        return '
            <div class="row form-group form-inline">
                <label>First Name</label>
                <input type="text" name="ContactInfo_fn"
                    id="s_fn" class="form-control" />
                <label>Last Name</label> 
                <input type="text" name="ContactInfo_ln" id="s_ln" 
                    class="form-control" />
            </div>
            <div class="row form-group form-inline">
                <label>Address</label> 
                <input type="text" name="ContactInfo_addr" id="s_addr" 
                    class="form-control" />
            </div>
            <div class="row form-group form-inline">
                <label>City</label> 
                <input type="text" name="ContactInfo_city" id="s_city" 
                    class="form-control" />
                <label>' . $labels['state'] . '</label>
                <input type="text" name="ContactInfo_state" class="form-control" />
                <label>' . $labels['zip'] . '</label>
                <input type="text" name="ContactInfo_zip" class="form-control" />
            </div>
            <div class="row form-group form-inline">
                <label>Phone</label>:
                <input type="text" name="ContactInfo_phone" id="s_phone" 
                    class="form-control" />
                <label>Email</label>:
                <input type="text" name="ContactInfo_email" id="s_email" 
                    class="form-control" />
            </div>';
    }

    public function getSearchLoadCommands()
    {
        global $FANNIE_URL;
        return array(
            "bindAutoComplete('#s_fn', '" . $FANNIE_URL . "ws/', 'mFirstName');\n",
            "bindAutoComplete('#s_ln', '" . $FANNIE_URL . "ws/', 'mLastName');\n",
            "bindAutoComplete('#s_addr', '" . $FANNIE_URL . "ws/', 'mAddress');\n",
            "bindAutoComplete('#s_city', '" . $FANNIE_URL . "ws/', 'mCity');\n",
            "bindAutoComplete('#s_email', '" . $FANNIE_URL . "ws/', 'mEmail');\n",
        );
    }

    function getSearchResults()
    {
        $fn = FormLib::get_form_value('ContactInfo_fn');
        $ln = FormLib::get_form_value('ContactInfo_ln');
        $addr = FormLib::get_form_value('ContactInfo_addr');
        $city = FormLib::get_form_value('ContactInfo_city');
        $state = FormLib::get_form_value('ContactInfo_state');
        $zip = FormLib::get_form_value('ContactInfo_zip');
        $email = FormLib::get_form_value('ContactInfo_email');
        $phone = FormLib::get_form_value('ContactInfo_phone');
        
        $json = array();
        $customer = array();

        if (!empty($fn)){
            $customer['firstName'] = $fn;
        }
        if (!empty($ln)){
            $customer['lastName'] = $ln;
        }
        if (!empty($addr)){
            $json['addressFirstLine'] = $addr;
        }
        if (!empty($city)){
            $json['city'] = $city;
        }
        if (!empty($state)){
            $json['state'] = $state;
        }
        if (!empty($zip)){
            $json['zip'] = $zip;
        }
        if (!empty($email)){
            $customer['email'] = $email;
        }
        if (!empty($phone)){
            $customer['phone'] = $phone;
        }
        $json['customers'] = array($customer);

        $accounts = \COREPOS\Fannie\API\member\MemberREST::search($json, 0);

        return $accounts;
    }
}

