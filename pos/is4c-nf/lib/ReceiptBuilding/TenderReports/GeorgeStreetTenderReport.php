<?php
/*******************************************************************************

    Copyright 2016 George Street Co-op

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
    $tenders = CoreLocal::get('TRDesiredTenders');
    if (!is_array($tenders)) {
        $tenders = array(
                'CA' => 'Cash',
                'CK' => 'Check',
                'CC' => 'Credit',
                'DC' => 'PIN Debit',
                'EF' => 'EBT Foodstamps',
            );
    }

    $db_a = Database::mDataConnect();
    $emp_no = CoreLocal::get('CashierNo');
    $register_no = CoreLocal::get('laneno');

    $receipt = ReceiptLib::biggerFont("Lane {$register_no}, cashier {$emp_no} ".trim(CoreLocal::get('cashier')))."\n\n";
    $receipt .= ReceiptLib::biggerFont(date('D M j Y - g:ia'))."\n\n";

    $report_params = array(
        'department' => "
                    SELECT
                        'departments' Plural,
                        CONCAT_WS(' ', t.dept_no, t.dept_name) GroupLabel,
                        SUM(IF(department IN (102, 113) OR scale = 1, 1, d.quantity)) AS GroupQuantity,
                        'item' AS GroupQuantityLabel,
                        SUM(d.total) AS GroupValue
                    FROM dlog AS d
                        LEFT JOIN core_opdata.departments AS t ON d.department=t.dept_no
                    WHERE emp_no={$emp_no} AND register_no={$register_no}
                        AND d.department <> 0 AND d.trans_type <> 'T'
                    GROUP BY t.dept_no
                ",
        'tax' => "
                    SELECT
                        'taxes' Plural,
                        IF(total = 0, 'Non-taxed', 'Taxed') GroupLabel,
                        COUNT(*) GroupQuantity,
                        'transaction' AS GroupQuantityLabel,
                        SUM(total) GroupValue
                    FROM dlog
                    WHERE emp_no={$emp_no} AND register_no={$register_no}
                        AND upc = 'TAX'
                    GROUP BY (total = 0)
                ",
        'discount' => "
                    SELECT
                        'discounts' Plural,
                        CONCAT(percentDiscount, '%') GroupLabel,
                        COUNT(*) AS GroupQuantity,
                        'transaction' AS GroupQuantityLabel,
                        -SUM(total) AS GroupValue
                    FROM dlog
                    WHERE emp_no={$emp_no} AND register_no={$register_no}
                        AND trans_type = 'S'
                    GROUP BY percentDiscount
                ",
        'tender' => "
                    SELECT
                        'tenders' Plural,
                        CONCAT_WS(' ', ttg.tender_code, t.TenderName) GroupLabel,
                        COUNT(*) GroupQuantity,
                        'transaction' AS GroupQuantityLabel,
                        SUM(tender) GroupValue
                    FROM TenderTapeGeneric ttg
                        LEFT JOIN core_opdata.tenders t ON ttg.tender_code = t.TenderCode
                    WHERE emp_no={$emp_no} AND register_no={$register_no}
                        AND tender_code IN ('".join("', '", array_keys($tenders))."')
                    GROUP BY tender_code
                    ORDER BY FIELD(tender_code, 'CA', 'CK', 'CC', 'DC', 'EF', tender_code), tender_code
                ",
        );

    foreach ($report_params as $report => $query) {
        $receipt .= "\n";

        $receipt .= ReceiptLib::boldFont();
        $receipt .= ReceiptLib::centerString(ucwords($report).' Report')."\n";
        $receipt .= ReceiptLib::normalFont();

        $result = $db_a->query($query);
        $total_quantity = $total_value = 0;

        while ($row = $db_a->fetchRow($result)) {
            $plural = $row['Plural'];
            $group_label = $row['GroupLabel'];
            $group_quantity = $row['GroupQuantity'];
            $group_quantity_label = $row['GroupQuantityLabel'];
            $group_value = $row['GroupValue'];

            $total_quantity += $group_quantity;
            $total_value += $group_value;

            $group_quantity = rtrim(number_format($group_quantity, 3), '.0');
            $group_value = number_format($group_value, 2);

            $receipt .= ReceiptLib::boldFont();
            $receipt .= "{$group_label}: ";
            $receipt .= ReceiptLib::normalFont();
            $receipt .= "\${$group_value} from {$group_quantity} {$group_quantity_label}".($group_quantity==1?'':'s')."\n";
        }
        $total_values[$report] = $total_value;

        $total_quantity = rtrim(number_format($total_quantity, 3), '.0');
        $total_value = number_format($total_value, 2);

        $receipt .= ReceiptLib::boldFont();
        $receipt .= "All ".ucwords($plural).": \${$total_value} from {$total_quantity} {$group_quantity_label}".($total_quantity==1?'':'s')."\n";
        $receipt .= ReceiptLib::normalFont();
    }

    $receipt .= "\n";
    foreach ($total_values as $report => $total_value) {
        switch ($report) {
            case 'discount':
            case 'tender':
                $sign = -1;
                break;
            default:
                $sign = 1;
        }
        $checksum += ($sign * $total_value);
        $total_value = number_format($total_value, 2);

        $receipt .= "\n";
        $receipt .= str_repeat(' ', 8);
        $receipt .= ReceiptLib::boldFont();
        $receipt .= ucwords($report).' Total:';
        $receipt .= ReceiptLib::normalFont();
        $receipt .= str_repeat(' ', 32 - strlen("{$report}{$total_value}"));
        $receipt .= ($sign < 0? '-' : '+') . " \${$total_value}";
    }
    $checksum = number_format($checksum, 2);
    if ($checksum === '-0.00') $checksum = '0.00'; // remove possible floating point sign error

    $receipt .= "\n";
    $receipt .= str_repeat(' ', 38);
    $receipt .= str_repeat('_', 14);
    $receipt .= "\n";
    $receipt .= str_repeat(' ', 8);
    $receipt .= ReceiptLib::boldFont();
    $receipt .= 'Checksum (should be zero):';
    $receipt .= str_repeat(' ', 15 - strlen("{$checksum}"));
    $receipt .= "\${$checksum}";
    $receipt .= ReceiptLib::normalFont();

    $receipt .= "\n";
    $receipt .= "\n";
    $receipt .= ReceiptLib::centerString("------------------------------------------------------");
    $receipt .= "\n";
//     $receipt .= str_repeat("\n", 2);
//     $receipt .= chr(28).'p'.chr(1).'0'; // print Co-op logo from NVRAM slot 1

    $receipt .= str_repeat("\n", 4);
    $receipt .= chr(27).chr(105); // cut
    return $receipt;
}

}

