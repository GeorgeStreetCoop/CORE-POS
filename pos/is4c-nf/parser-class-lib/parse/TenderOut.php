<?php
/*******************************************************************************

    Copyright 2007 Whole Foods Co-op

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

class TenderOut extends Parser 
{
    function check($str)
    {
        if ($str == "TO") {
            return true;
        } else {
            return false;
        }
    }

    function parse($str)
    {
        if (CoreLocal::get("LastID") == 0){
            $ret = $this->default_json();
            $ret['output'] = DisplayLib::boxMsg(
                _("no transaction in progress"),
                '',
                false,
                DisplayLib::standardClearButton()
            );
            return $ret;
        }
        else {
            return $this->tender_out("");
        }
    }

    function tender_out($asTender)
    {
        $ret = $this->default_json();
        Database::getsubtotals();
        if (CoreLocal::get("amtdue") <= 0.005) {
            CoreLocal::set("change",-1 * CoreLocal::get("amtdue"));
            $cash_return = CoreLocal::get("change");
            if ($asTender != "FS") {
                TransRecord::addchange($cash_return,'CA');
            }
            CoreLocal::set("End",1);
            $ret['output'] = DisplayLib::printReceiptFooter();
            $ret['redraw_footer'] = true;
            $ret['receipt'] = 'full';
            TransRecord::finalizeTransaction();
        } else {
            CoreLocal::set("change",0);
            CoreLocal::set("fntlflag",0);
            $ttl_result = PrehLib::ttl();
            TransRecord::debugLog('Tender Out (PrehLib): ' . print_r($ttl_result, true));
            TransRecord::debugLog('Tender Out (amtdue): ' . print_r(CoreLocal::get('amtdue'), true));
            $ret['output'] = DisplayLib::lastpage();
        }
        return $ret;
    }

    function doc(){
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td>TO</td>
                <td>Tender out. Not a WFC function; just
                reproduced for compatibility</td>
            </tr>
            </table>";
    }
}

