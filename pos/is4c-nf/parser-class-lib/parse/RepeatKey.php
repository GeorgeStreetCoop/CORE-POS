<?php
/*******************************************************************************

    Copyright 2015 Whole Foods Co-op

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

class RepeatKey extends Parser 
{
    public function check($str)
    {
        if (preg_match('/^\*[0-9]+$/', $str)) {
            return true;
        } elseif ($str === '*') {
            return true;
        } else {
            return false;
        }
    }

    public function parse($str)
    {
        $multiplier = strlen($str) > 1 ? substr($str, 1) : 1;
        $peek = PrehLib::peekItem(true, CoreLocal::get('currentid'));
        $fp = fopen('/tmp/peek', 'a');
        if ($peek && $peek['trans_type'] == 'I' && $peek['trans_status'] == '') {
            $upcP = new UPC();
            CoreLocal::set('quantity', $multiplier);
            CoreLocal::set('multiple', 1);
            $ret = $upcP->parse($peek['upc']);
            CoreLocal::set('multiple', 0);
            return $ret;
        } else {
            $json = $this->default_json();
            $json['output'] = DisplayLib::boxMsg(
                    _('product cannot be repeated'),
                    _('Ineligible line'),
                    false,
                    DisplayLib::standardClearButton()
            );

            return $json;
        }
    }

    public function doc()
    {
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td>*<i>number</i></td>
                <td>
                Ring the currently selected item
                <i>number</i> more times
                </td>
            </tr>
            </table>";
    }
}

