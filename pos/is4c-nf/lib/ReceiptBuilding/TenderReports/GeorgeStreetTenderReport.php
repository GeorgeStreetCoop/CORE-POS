<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op

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
  @class DefaultTenderReport
  Generate a tender report
*/
class GeorgeStreetTenderReport extends TenderReport {

/** 
 Print a tender report

 This tender report is based on a single tender tape view
 rather than multiple views (e.g. ckTenders, ckTenderTotal, etc).
 Which tenders to include is defined via checkboxes by the
 tenders on the install page's "extras" tab.
 
 The only differences between this report and DefaultTenderReport are formatting:
 1. Cash line items are omitted entirely; just the sum is shown
 2. All items are shown in a less paper-intensive (more compact) format.
 3. Cashier ID and timestamp are made bigger and book-end the report.
 */
static public function get()
{
    $DESIRED_TENDERS = CoreLocal::get("TRDesiredTenders");
    if (!is_array($DESIRED_TENDERS)) {
        $DESIRED_TENDERS = array();
    }

    $db_a = Database::mDataConnect();

    $blank = self::standardBlank();
    $fieldNames = rtrim(self::standardFieldNames(), " \n\r");
    $receipt = ReceiptLib::biggerFont('Tender Report: '.CoreLocal::get("CashierNo")." ".CoreLocal::get("cashier"))."\n\n";

    foreach ($DESIRED_TENDERS as $tender_code => $titleStr) { 
        $query = "select tdate,register_no,trans_no,tender
                   from TenderTapeGeneric where emp_no=".CoreLocal::get("CashierNo").
            " and tender_code = '".$tender_code."' order by tdate";
        $result = $db_a->query($query);
        $num_rows = $db_a->num_rows($result);
        if ($num_rows <= 0) continue;

        $sum = 0;
	    $breakdown = $fieldNames;
        while ($row = $db_a->fetchRow($result)) {
	        $breakdown .= self::standardLine($row['tdate'], $row['register_no'], $row['trans_no'], $row['tender']);
            $sum += $row['tender'];
        }
	    $receipt .= ReceiptLib::boldFont();
        $receipt .= "{$titleStr}: \$".number_format($sum,2)." in {$num_rows} transaction".($num_rows==1?'':'s')."\n";
	    $receipt .= ReceiptLib::normalFont();
		if ($tender_code != 'CA') {
	        $receipt .= $breakdown;
	    }
	    $receipt .= ReceiptLib::centerString("------------------------------------------------------");
        $receipt .= "\n";
    }
    $receipt .= ReceiptLib::biggerFont(ReceiptLib::build_time(time()));
    $receipt .= str_repeat("\n", 2);
    $receipt .= chr(28).'p'.chr(1).'0'; // print Co-op logo from NVRAM slot 1
    $receipt .= str_repeat("\n", 6);
    $receipt .= chr(27).chr(105); // cut
    return $receipt;
}

}

